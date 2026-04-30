<?php
declare(strict_types=1);

/**
 * Nyilvános: szervezők (megjelenit.php) és szervező eseménylistája (organizer.php).
 */

/**
 * @return list<array{id:int,name:string}>
 */
function events_public_event_organizers_for_display(PDO $db, int $eventId): array {
    $st = $db->prepare('
        SELECT o.`id`, o.`name`
        FROM `events_organizers` o
        INNER JOIN `events_calendar_event_organizers` eco ON eco.`organizer_id` = o.`id`
        WHERE eco.`event_id` = ?
        ORDER BY eco.`sort_order` ASC, o.`id` ASC
    ');
    $st->execute([$eventId]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[] = ['id' => (int) $r['id'], 'name' => (string) $r['name']];
    }

    return $out;
}

/**
 * Egy szervező összes közzétett eseménye (egy sor / esemény).
 *
 * @return list<array<string,mixed>>
 */
function events_public_organizer_published_events(PDO $db, int $organizerId, string $publishedStatus): array {
    if ($organizerId <= 0) {
        return [];
    }
    $st = $db->prepare('
        SELECT DISTINCT e.`id`, e.`event_slug`, e.`event_name`, e.`event_featured_image_url`, e.`event_start`, e.`event_end`, e.`event_allday`
        FROM `events_calendar_events` e
        INNER JOIN `events_calendar_event_organizers` eco ON eco.`event_id` = e.`id`
        WHERE eco.`organizer_id` = ? AND e.`event_status` = ?
        ORDER BY (e.`event_start` IS NULL) ASC, e.`event_start` DESC, e.`id` DESC
    ');
    $st->execute([$organizerId, $publishedStatus]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}
