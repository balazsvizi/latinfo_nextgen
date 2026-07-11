<?php
declare(strict_types=1);

final class HbActivityLog
{
    public static function tableReady(PDO $db): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $db->query('SELECT 1 FROM hb_activity_log LIMIT 1');
            $cached = true;
        } catch (Throwable) {
            $cached = false;
        }

        return $cached;
    }

    public static function log(
        PDO $db,
        int $subscriberId,
        ?int $userId,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $details = null
    ): void {
        if ($subscriberId <= 0 || trim($action) === '' || !self::tableReady($db)) {
            return;
        }

        try {
            $stmt = $db->prepare('
                INSERT INTO hb_activity_log (subscriber_id, user_id, action, entity_type, entity_id, details, ip_address)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $subscriberId,
                $userId,
                trim($action),
                $entityType,
                $entityId,
                $details !== null && trim($details) !== '' ? trim($details) : null,
                hb_client_ip(),
            ]);
        } catch (Throwable $ex) {
            error_log('HbActivityLog::log: ' . $ex->getMessage());
        }
    }

    public static function logLoginFailed(PDO $db, string $email, ?int $subscriberId = null): void
    {
        if (!self::tableReady($db)) {
            return;
        }

        if ($subscriberId !== null && $subscriberId > 0) {
            self::log($db, $subscriberId, null, 'login_failed', null, null, 'email=' . $email);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listForSubscriber(PDO $db, int $subscriberId, ?int $userId = null, int $limit = 100): array
    {
        if ($subscriberId <= 0 || !self::tableReady($db)) {
            return [];
        }

        $limit = max(1, min(500, $limit));
        $params = [$subscriberId];
        $userFilter = '';
        if ($userId !== null && $userId > 0) {
            $userFilter = ' AND l.user_id = ?';
            $params[] = $userId;
        }

        try {
            $stmt = $db->prepare("
                SELECT l.*, u.name AS user_name, u.email AS user_email
                FROM hb_activity_log l
                LEFT JOIN hb_users u ON u.id = l.user_id
                WHERE l.subscriber_id = ?{$userFilter}
                ORDER BY l.created_at DESC, l.id DESC
                LIMIT {$limit}
            ");
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
    }

    public static function actionLabel(string $action): string
    {
        $key = 'logs.action.' . $action;
        $translated = hb_t($key);

        return $translated !== $key ? $translated : $action;
    }

    public static function actorLabel(array $row): string
    {
        $name = trim((string) ($row['user_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        return hb_t('logs.filter_all_users');
    }
}
