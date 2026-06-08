<?php
declare(strict_types=1);

require_once __DIR__ . '/tag_type.php';
require_once __DIR__ . '/event_public_organizers.php';

/**
 * Nyilvános: címkék (megjelenit.php) és címke eseménylistája (tag.php).
 */

/**
 * Egy címke összes közzétett eseménye (egy sor / esemény).
 *
 * @return list<array<string,mixed>>
 */
function events_public_tag_published_events(PDO $db, int $tagId, string $publishedStatus): array {
    if ($tagId <= 0 || !events_tags_tables_available($db)) {
        return [];
    }
    $st = $db->prepare('
        SELECT DISTINCT e.`id`, e.`event_slug`, e.`event_name`, e.`event_featured_image_url`, e.`event_start`, e.`event_end`, e.`event_allday`,
               v.`city` AS `venue_city`
        FROM `events_calendar_events` e
        INNER JOIN `events_calendar_event_tags` et ON et.`event_id` = e.`id`
        LEFT JOIN `events_venues` v ON v.`id` = e.`venue_id`
        WHERE et.`tag_id` = ? AND e.`event_status` = ?
        ORDER BY e.`id` DESC
    ');
    $st->execute([$tagId, $publishedStatus]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Nyilvános címke-oldal fejléc felirata a típusok alapján (pl. egyetlen DJ → „DJ”).
 *
 * @param list<string> $typeCodes
 */
function events_public_tag_eyebrow_label(array $typeCodes, string $lang): string {
    $typeCodes = events_tag_type_normalize_codes($typeCodes);
    if (count($typeCodes) === 1) {
        return events_tag_type_label($typeCodes[0]);
    }

    return $lang === 'en' ? 'Tag' : 'Címke';
}
