<?php
declare(strict_types=1);

/**
 * Nyilvános eseményoldal: szervezők és kapcsolódó események (megjelenit.php).
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
 * Más közzétett események, amelyek legalább egy közös szervezőt osztanak a jelenlegi eseménnyel.
 * Kulcs: szervező ID, érték: eseménysorok (id, slug, név, kép, dátum mezők).
 *
 * @return array<int, list<array<string,mixed>>>
 */
function events_public_related_events_by_organizer(PDO $db, int $currentEventId, string $publishedStatus): array {
    $st = $db->prepare('
        SELECT e.`id`, e.`event_slug`, e.`event_name`, e.`event_featured_image_url`, e.`event_start`, e.`event_end`, e.`event_allday`,
               eco2.`organizer_id`
        FROM `events_calendar_events` e
        INNER JOIN `events_calendar_event_organizers` eco2 ON eco2.`event_id` = e.`id`
        WHERE e.`event_status` = ?
          AND e.`id` <> ?
          AND eco2.`organizer_id` IN (
            SELECT `organizer_id` FROM `events_calendar_event_organizers` WHERE `event_id` = ?
          )
        ORDER BY (e.`event_start` IS NULL) ASC, e.`event_start` DESC, e.`id` DESC
    ');
    $st->execute([$publishedStatus, $currentEventId, $currentEventId]);
    $byOrg = [];
    $seen = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $oid = (int) $r['organizer_id'];
        $eid = (int) $r['id'];
        $k = $oid . ':' . $eid;
        if (isset($seen[$k])) {
            continue;
        }
        $seen[$k] = true;
        if (!isset($byOrg[$oid])) {
            $byOrg[$oid] = [];
        }
        $byOrg[$oid][] = $r;
    }

    return $byOrg;
}
