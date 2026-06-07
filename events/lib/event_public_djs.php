<?php
declare(strict_types=1);

/**
 * Nyilvános: DJ-k (megjelenit.php) és DJ eseménylistája (dj.php).
 */

/**
 * @return list<array{id:int,name:string}>
 */
function events_public_event_djs_for_display(PDO $db, int $eventId): array {
    if (!events_djs_tables_available($db)) {
        return [];
    }
    $st = $db->prepare('
        SELECT d.`id`, d.`name`
        FROM `events_djs` d
        INNER JOIN `events_calendar_event_djs` ed ON ed.`dj_id` = d.`id`
        WHERE ed.`event_id` = ?
        ORDER BY d.`name` ASC, d.`id` ASC
    ');
    $st->execute([$eventId]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[] = ['id' => (int) $r['id'], 'name' => (string) $r['name']];
    }

    return $out;
}

/**
 * Egy DJ összes közzétett eseménye.
 *
 * @return list<array<string,mixed>>
 */
function events_public_dj_published_events(PDO $db, int $djId, string $publishedStatus): array {
    if ($djId <= 0 || !events_djs_tables_available($db)) {
        return [];
    }
    $st = $db->prepare('
        SELECT DISTINCT e.`id`, e.`event_slug`, e.`event_name`, e.`event_featured_image_url`, e.`event_start`, e.`event_end`, e.`event_allday`,
               v.`city` AS `venue_city`
        FROM `events_calendar_events` e
        INNER JOIN `events_calendar_event_djs` ed ON ed.`event_id` = e.`id`
        LEFT JOIN `events_venues` v ON v.`id` = e.`venue_id`
        WHERE ed.`dj_id` = ? AND e.`event_status` = ?
        ORDER BY e.`id` DESC
    ');
    $st->execute([$djId, $publishedStatus]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}
