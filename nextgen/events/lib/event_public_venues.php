<?php
declare(strict_types=1);

/**
 * Nyilvános helyszín oldal — eseménylista (helyszin_megjelenit.php).
 */

/**
 * Egy helyszín összes közzétett eseménye.
 *
 * @return list<array<string, mixed>>
 */
function events_public_venue_published_events(PDO $db, int $venueId, string $publishedStatus, ?int $listLimit = null): array {
    if ($venueId <= 0) {
        return [];
    }
    $limitSql = $listLimit === null ? '' : ' LIMIT ' . (int) $listLimit;
    $st = $db->prepare('
        SELECT e.`id`, e.`event_slug`, e.`event_name`, e.`event_featured_image_url`, e.`event_start`, e.`event_end`, e.`event_allday`,
               v.`city` AS `venue_city`
        FROM `events_calendar_events` e
        LEFT JOIN `events_venues` v ON v.`id` = e.`venue_id`
        WHERE e.`venue_id` = ? AND e.`event_status` = ?
        ORDER BY e.`id` DESC' . $limitSql . '
    ');
    $st->execute([$venueId, $publishedStatus]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function events_public_venue_published_events_total_count(PDO $db, int $venueId, string $publishedStatus): int {
    if ($venueId <= 0) {
        return 0;
    }
    $st = $db->prepare('
        SELECT COUNT(*)
        FROM `events_calendar_events` e
        WHERE e.`venue_id` = ? AND e.`event_status` = ?
    ');
    $st->execute([$venueId, $publishedStatus]);

    return (int) $st->fetchColumn();
}
