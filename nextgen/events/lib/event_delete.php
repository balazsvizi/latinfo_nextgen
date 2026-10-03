<?php
declare(strict_types=1);

require_once __DIR__ . '/eventpics.php';
require_once __DIR__ . '/style_request.php';
require_once __DIR__ . '/tag_type.php';

/**
 * Esemény lomtárba helyezése (státusz → trash).
 *
 * @return array{0: bool, 1: string} [success, eseménynév vagy hibaüzenet]
 */
function events_trash_event(PDO $db, int $eventId): array
{
    if ($eventId <= 0) {
        return [false, 'Érvénytelen esemény azonosító.'];
    }

    $st = $db->prepare('
        SELECT `id`, `event_name`, `event_status`
        FROM `events_calendar_events`
        WHERE `id` = ?
        LIMIT 1
    ');
    $st->execute([$eventId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return [false, 'Esemény nem található.'];
    }

    $eventName = trim((string) ($row['event_name'] ?? ''));
    if ((string) ($row['event_status'] ?? '') === 'trash') {
        return [false, 'Az esemény már a lomtárban van.'];
    }

    try {
        $upd = $db->prepare('UPDATE `events_calendar_events` SET `event_status` = ? WHERE `id` = ?');
        $upd->execute(['trash', $eventId]);
    } catch (Throwable $e) {
        error_log('events_trash_event: ' . $e->getMessage());

        return [false, 'A törlés nem sikerült. Kérlek próbáld újra.'];
    }

    return [true, $eventName !== '' ? $eventName : ('#' . $eventId)];
}

/**
 * Esemény végleges törlése (csak lomtár státusz). Kapcsolótáblák, megtekintések;
 * eventpics fájl csak akkor törlődik, ha máshol nem használják.
 *
 * @return array{0: bool, 1: string} [success, eseménynév vagy hibaüzenet]
 */
function events_permanent_delete_event(PDO $db, int $eventId): array {
    if ($eventId <= 0) {
        return [false, 'Érvénytelen esemény azonosító.'];
    }

    $st = $db->prepare('
        SELECT `id`, `event_name`, `event_status`, `event_featured_image_url`
        FROM `events_calendar_events`
        WHERE `id` = ?
        LIMIT 1
    ');
    $st->execute([$eventId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return [false, 'Esemény nem található.'];
    }

    if ((string) ($row['event_status'] ?? '') !== 'trash') {
        return [false, 'Csak lomtárban lévő esemény törölhető véglegesen.'];
    }

    $eventName = trim((string) ($row['event_name'] ?? ''));
    $eventpicFile = events_eventpics_extract_selected_from_featured((string) ($row['event_featured_image_url'] ?? ''));

    try {
        $db->beginTransaction();
        events_permanent_delete_event_junctions($db, $eventId);
        $db->prepare('DELETE FROM `events_calendar_events` WHERE `id` = ?')->execute([$eventId]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('events_permanent_delete_event: ' . $e->getMessage());

        return [false, 'A törlés nem sikerült. Kérlek próbáld újra.'];
    }

    if ($eventpicFile !== '') {
        events_eventpics_delete_file_if_unused($db, $eventpicFile);
    }

    return [true, $eventName !== '' ? $eventName : ('#' . $eventId)];
}

function events_permanent_delete_event_junctions(PDO $db, int $eventId): void {
    $db->prepare('DELETE FROM `events_calendar_event_organizers` WHERE `event_id` = ?')->execute([$eventId]);
    $db->prepare('DELETE FROM `events_calendar_event_categories` WHERE `event_id` = ?')->execute([$eventId]);

    if (events_tags_tables_available($db)) {
        $db->prepare('DELETE FROM `events_calendar_event_tags` WHERE `event_id` = ?')->execute([$eventId]);
    }
    if (events_styles_tables_available($db)) {
        $db->prepare('DELETE FROM `events_calendar_event_main_styles` WHERE `event_id` = ?')->execute([$eventId]);
        $db->prepare('DELETE FROM `events_calendar_event_supplementary_styles` WHERE `event_id` = ?')->execute([$eventId]);
    }

    $db->prepare('DELETE FROM `events_calendar_event_views` WHERE `esemény_id` = ?')->execute([$eventId]);

    if (function_exists('events_slug_redirects_delete_for_event')) {
        events_slug_redirects_delete_for_event($db, $eventId);
    }

    if (function_exists('db_table_exists') && db_table_exists($db, 'events_calendar_event_djs')) {
        $db->prepare('DELETE FROM `events_calendar_event_djs` WHERE `event_id` = ?')->execute([$eventId]);
    }
}
