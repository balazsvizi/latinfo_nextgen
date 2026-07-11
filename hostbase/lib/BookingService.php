<?php
declare(strict_types=1);

final class HbBookingService
{
    /**
     * @return array{ok: bool, error?: string, warning?: string, id?: int}
     */
    public static function create(PDO $db, int $subscriberId, int $userId, array $input): array
    {
        $validation = self::validateInput($db, $subscriberId, $input);
        if (!$validation['ok']) {
            return $validation;
        }

        /** @var array<string, mixed> $data */
        $data = $validation['data'];

        try {
            $stmt = $db->prepare('
                INSERT INTO hb_bookings
                    (unit_id, subscriber_id, guest_name, adults, children, check_in, check_out, notes, created_by, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $data['unit_id'],
                $subscriberId,
                $data['guest_name'],
                $data['adults'],
                $data['children'],
                $data['check_in'],
                $data['check_out'],
                $data['notes'],
                $userId,
                $userId,
            ]);
            $id = (int) $db->lastInsertId();

            HbActivityLog::log(
                $db,
                $subscriberId,
                $userId,
                'booking_create',
                'booking',
                $id,
                self::summaryLine($data)
            );

            $result = ['ok' => true, 'id' => $id];
            if (!empty($validation['warning'])) {
                $result['warning'] = $validation['warning'];
            }

            return $result;
        } catch (Throwable $ex) {
            error_log('HbBookingService::create: ' . $ex->getMessage());

            return ['ok' => false, 'error' => hb_t('error.generic')];
        }
    }

    /**
     * @return array{ok: bool, error?: string, warning?: string}
     */
    public static function update(PDO $db, int $bookingId, int $subscriberId, int $userId, array $input): array
    {
        $existing = self::findForSubscriber($db, $bookingId, $subscriberId);
        if ($existing === null) {
            return ['ok' => false, 'error' => hb_t('error.not_found')];
        }

        $input['unit_id'] = (int) $existing['unit_id'];
        $validation = self::validateInput($db, $subscriberId, $input, $bookingId);
        if (!$validation['ok']) {
            return $validation;
        }

        /** @var array<string, mixed> $data */
        $data = $validation['data'];

        try {
            $stmt = $db->prepare('
                UPDATE hb_bookings
                SET guest_name = ?, adults = ?, children = ?, check_in = ?, check_out = ?, notes = ?, updated_by = ?
                WHERE id = ? AND subscriber_id = ?
            ');
            $stmt->execute([
                $data['guest_name'],
                $data['adults'],
                $data['children'],
                $data['check_in'],
                $data['check_out'],
                $data['notes'],
                $userId,
                $bookingId,
                $subscriberId,
            ]);

            HbActivityLog::log(
                $db,
                $subscriberId,
                $userId,
                'booking_update',
                'booking',
                $bookingId,
                self::summaryLine($data)
            );

            $result = ['ok' => true];
            if (!empty($validation['warning'])) {
                $result['warning'] = $validation['warning'];
            }

            return $result;
        } catch (Throwable $ex) {
            error_log('HbBookingService::update: ' . $ex->getMessage());

            return ['ok' => false, 'error' => hb_t('error.generic')];
        }
    }

    public static function delete(PDO $db, int $bookingId, int $subscriberId, int $userId): bool
    {
        $existing = self::findForSubscriber($db, $bookingId, $subscriberId);
        if ($existing === null) {
            return false;
        }

        try {
            $stmt = $db->prepare('DELETE FROM hb_bookings WHERE id = ? AND subscriber_id = ?');
            $stmt->execute([$bookingId, $subscriberId]);

            HbActivityLog::log(
                $db,
                $subscriberId,
                $userId,
                'booking_delete',
                'booking',
                $bookingId,
                self::summaryLine($existing)
            );

            return true;
        } catch (Throwable $ex) {
            error_log('HbBookingService::delete: ' . $ex->getMessage());

            return false;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findForSubscriber(PDO $db, int $bookingId, int $subscriberId): ?array
    {
        $stmt = $db->prepare('
            SELECT b.*, p.name AS property_name, p.id AS property_id, u.max_guests, u.name AS unit_name
            FROM hb_bookings b
            INNER JOIN hb_units u ON u.id = b.unit_id
            INNER JOIN hb_properties p ON p.id = u.property_id
            WHERE b.id = ? AND b.subscriber_id = ?
            LIMIT 1
        ');
        $stmt->execute([$bookingId, $subscriberId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listForSubscriber(
        PDO $db,
        int $subscriberId,
        ?int $propertyId = null,
        ?string $from = null,
        ?string $to = null,
        int $limit = 200
    ): array {
        $limit = max(1, min(500, $limit));
        $params = [$subscriberId];
        $where = ['b.subscriber_id = ?'];

        if ($propertyId !== null && $propertyId > 0) {
            $where[] = 'p.id = ?';
            $params[] = $propertyId;
        }
        if ($from !== null && hb_validate_date($from)) {
            $where[] = 'b.check_in >= ?';
            $params[] = $from;
        }
        if ($to !== null && hb_validate_date($to)) {
            $where[] = 'b.check_in <= ?';
            $params[] = $to;
        }

        $sql = '
            SELECT b.*, p.name AS property_name, p.id AS property_id, u.max_guests, u.name AS unit_name
            FROM hb_bookings b
            INNER JOIN hb_units u ON u.id = b.unit_id
            INNER JOIN hb_properties p ON p.id = u.property_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY b.check_in DESC, b.id DESC
            LIMIT ' . $limit;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function upcomingForSubscriber(PDO $db, int $subscriberId, int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');

        $stmt = $db->prepare('
            SELECT b.*, p.name AS property_name, p.id AS property_id, u.max_guests, u.name AS unit_name
            FROM hb_bookings b
            INNER JOIN hb_units u ON u.id = b.unit_id
            INNER JOIN hb_properties p ON p.id = u.property_id
            WHERE b.subscriber_id = ? AND b.check_out > ?
            ORDER BY b.check_in ASC, b.id ASC
            LIMIT ' . $limit
        );
        $stmt->execute([$subscriberId, $today]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forPropertyInRange(PDO $db, int $propertyId, int $subscriberId, string $rangeStart, string $rangeEnd): array
    {
        $stmt = $db->prepare('
            SELECT b.*, u.max_guests
            FROM hb_bookings b
            INNER JOIN hb_units u ON u.id = b.unit_id
            INNER JOIN hb_properties p ON p.id = u.property_id
            WHERE p.id = ? AND b.subscriber_id = ?
              AND b.check_in < ? AND b.check_out > ?
            ORDER BY b.check_in ASC
        ');
        $stmt->execute([$propertyId, $subscriberId, $rangeEnd, $rangeStart]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function isNightOccupied(string $day, string $checkIn, string $checkOut): bool
    {
        return $day >= $checkIn && $day < $checkOut;
    }

    public static function hasOverlap(PDO $db, int $unitId, string $checkIn, string $checkOut, ?int $excludeId = null): bool
    {
        $params = [$unitId, $checkOut, $checkIn];
        $excludeSql = '';
        if ($excludeId !== null && $excludeId > 0) {
            $excludeSql = ' AND id <> ?';
            $params[] = $excludeId;
        }

        $stmt = $db->prepare('
            SELECT id FROM hb_bookings
            WHERE unit_id = ? AND check_in < ? AND check_out > ?' . $excludeSql . '
            LIMIT 1
        ');
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @return array{ok: bool, error?: string, warning?: string, data?: array<string, mixed>}
     */
    private static function validateInput(PDO $db, int $subscriberId, array $input, ?int $excludeId = null): array
    {
        $guestName = trim((string) ($input['guest_name'] ?? ''));
        if ($guestName === '') {
            return ['ok' => false, 'error' => hb_t('error.required_guest')];
        }

        $adults = max(0, (int) ($input['adults'] ?? 0));
        $children = max(0, (int) ($input['children'] ?? 0));
        if ($adults + $children < 1) {
            $adults = 1;
        }

        $checkIn = (string) ($input['check_in'] ?? '');
        $checkOut = (string) ($input['check_out'] ?? '');
        if (!hb_validate_date($checkIn) || !hb_validate_date($checkOut)) {
            return ['ok' => false, 'error' => hb_t('error.invalid_dates')];
        }
        if ($checkOut <= $checkIn) {
            return ['ok' => false, 'error' => hb_t('error.checkout_before_checkin')];
        }

        $propertyId = (int) ($input['property_id'] ?? 0);
        $unitId = (int) ($input['unit_id'] ?? 0);

        if ($unitId <= 0 && $propertyId > 0) {
            $property = HbPropertyRepository::findForSubscriber($db, $propertyId, $subscriberId);
            if ($property === null) {
                return ['ok' => false, 'error' => hb_t('error.not_found')];
            }
            $unitId = (int) $property['unit_id'];
        }

        if ($unitId <= 0) {
            return ['ok' => false, 'error' => hb_t('error.not_found')];
        }

        $stmt = $db->prepare('
            SELECT u.id, u.max_guests, p.subscriber_id
            FROM hb_units u
            INNER JOIN hb_properties p ON p.id = u.property_id
            WHERE u.id = ? AND p.subscriber_id = ?
            LIMIT 1
        ');
        $stmt->execute([$unitId, $subscriberId]);
        $unit = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($unit === false) {
            return ['ok' => false, 'error' => hb_t('error.not_found')];
        }

        if (self::hasOverlap($db, $unitId, $checkIn, $checkOut, $excludeId)) {
            return ['ok' => false, 'error' => hb_t('error.overlap')];
        }

        $notes = trim((string) ($input['notes'] ?? ''));
        $totalGuests = $adults + $children;
        $maxGuests = (int) $unit['max_guests'];

        $result = [
            'ok' => true,
            'data' => [
                'unit_id' => $unitId,
                'guest_name' => $guestName,
                'adults' => $adults,
                'children' => $children,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'notes' => $notes !== '' ? $notes : null,
            ],
        ];

        if ($totalGuests > $maxGuests) {
            $result['warning'] = hb_t('bookings.capacity_warning', [
                'count' => (string) $totalGuests,
                'max' => (string) $maxGuests,
            ]);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function summaryLine(array $data): string
    {
        return sprintf(
            '%s | %s – %s | %d+%d',
            (string) ($data['guest_name'] ?? ''),
            (string) ($data['check_in'] ?? ''),
            (string) ($data['check_out'] ?? ''),
            (int) ($data['adults'] ?? 0),
            (int) ($data['children'] ?? 0)
        );
    }
}
