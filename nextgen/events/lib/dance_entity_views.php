<?php
declare(strict_types=1);

require_once __DIR__ . '/dance_schools_schema.php';
require_once __DIR__ . '/event_view_tracking.php';

/**
 * Tánciskola / tánctanár megtekintések mérése.
 */

function dance_entity_view_record(PDO $db, string $entityType, int $entityId, string $metric = 'page_view'): bool
{
    if ($entityId <= 0 || !in_array($entityType, ['school', 'teacher'], true)) {
        return false;
    }
    if (!dance_schools_ensure_schema($db)) {
        return false;
    }
    $isBot = events_view_tracking_detect_bot() ? 1 : 0;
    try {
        $db->prepare('
            INSERT INTO `dance_entity_views`
            (`entity_type`, `entity_id`, `metric`, `ip_hash`, `user_id`, `is_bot`)
            VALUES (?, ?, ?, ?, ?, ?)
        ')->execute([
            $entityType,
            $entityId,
            $metric,
            events_view_tracking_ip_hash(),
            events_view_tracking_current_user_id() ?: null,
            $isBot,
        ]);

        return true;
    } catch (Throwable $ex) {
        error_log('dance_entity_view_record: ' . $ex->getMessage());

        return false;
    }
}

function dance_entity_view_count(PDO $db, string $entityType, int $entityId, string $metric = 'page_view'): int
{
    if ($entityId <= 0 || !dance_schools_tables_ready($db)) {
        return 0;
    }
    try {
        $st = $db->prepare('
            SELECT COUNT(*) FROM `dance_entity_views`
            WHERE `entity_type` = ? AND `entity_id` = ? AND `metric` = ? AND `is_bot` = 0
        ');
        $st->execute([$entityType, $entityId, $metric]);

        return (int) $st->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}
