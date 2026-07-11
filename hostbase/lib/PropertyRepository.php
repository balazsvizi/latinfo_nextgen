<?php
declare(strict_types=1);

final class HbPropertyRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function listForSubscriber(PDO $db, int $subscriberId): array
    {
        if ($subscriberId <= 0) {
            return [];
        }

        $stmt = $db->prepare('
            SELECT p.*,
                u.id AS unit_id,
                u.name AS unit_name,
                u.max_guests
            FROM hb_properties p
            INNER JOIN hb_units u ON u.property_id = p.id AND u.active = 1
            WHERE p.subscriber_id = ? AND p.active = 1
            ORDER BY p.sort_order ASC, p.name ASC
        ');
        $stmt->execute([$subscriberId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findForSubscriber(PDO $db, int $propertyId, int $subscriberId): ?array
    {
        if ($propertyId <= 0 || $subscriberId <= 0) {
            return null;
        }

        $stmt = $db->prepare('
            SELECT p.*,
                u.id AS unit_id,
                u.name AS unit_name,
                u.max_guests
            FROM hb_properties p
            INNER JOIN hb_units u ON u.property_id = p.id AND u.active = 1
            WHERE p.id = ? AND p.subscriber_id = ? AND p.active = 1
            LIMIT 1
        ');
        $stmt->execute([$propertyId, $subscriberId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public static function ensureCurrentProperty(PDO $db, int $subscriberId): ?array
    {
        $properties = self::listForSubscriber($db, $subscriberId);
        if ($properties === []) {
            hb_set_current_property_id(0);

            return null;
        }

        $currentId = hb_current_property_id();
        foreach ($properties as $property) {
            if ((int) $property['id'] === $currentId) {
                return $property;
            }
        }

        $first = $properties[0];
        hb_set_current_property_id((int) $first['id']);

        return $first;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function update(PDO $db, int $propertyId, int $subscriberId, array $data, int $userId): bool
    {
        $property = self::findForSubscriber($db, $propertyId, $subscriberId);
        if ($property === null) {
            return false;
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return false;
        }

        $city = trim((string) ($data['city'] ?? ''));
        $address = trim((string) ($data['address'] ?? ''));
        $checkIn = self::normalizeTime((string) ($data['check_in_time'] ?? '16:00'));
        $checkOut = self::normalizeTime((string) ($data['check_out_time'] ?? '10:00'));
        $maxGuests = max(1, (int) ($data['max_guests'] ?? 1));

        $db->beginTransaction();
        try {
            $stmt = $db->prepare('
                UPDATE hb_properties
                SET name = ?, city = ?, address = ?, check_in_time = ?, check_out_time = ?
                WHERE id = ? AND subscriber_id = ?
            ');
            $stmt->execute([
                $name,
                $city !== '' ? $city : null,
                $address !== '' ? $address : null,
                $checkIn,
                $checkOut,
                $propertyId,
                $subscriberId,
            ]);

            $stmt = $db->prepare('UPDATE hb_units SET max_guests = ? WHERE id = ? AND property_id = ?');
            $stmt->execute([$maxGuests, (int) $property['unit_id'], $propertyId]);

            $db->commit();

            HbActivityLog::log(
                $db,
                $subscriberId,
                $userId,
                'property_update',
                'property',
                $propertyId,
                $name
            );

            return true;
        } catch (Throwable $ex) {
            $db->rollBack();
            error_log('HbPropertyRepository::update: ' . $ex->getMessage());

            return false;
        }
    }

    private static function normalizeTime(string $time): string
    {
        if (preg_match('/^\d{1,2}:\d{2}$/', $time) === 1) {
            [$h, $m] = explode(':', $time);

            return sprintf('%02d:%02d:00', (int) $h, (int) $m);
        }

        return '16:00:00';
    }
}
