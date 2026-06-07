<?php
declare(strict_types=1);

/**
 * Nyilvános eseményoldal: címkék megjelenítése (megjelenit.php).
 */

/**
 * @return list<array{id:int,name:string}>
 */
function events_public_event_tags_for_display(PDO $db, int $eventId): array {
    if (!events_tags_tables_available($db)) {
        return [];
    }
    $st = $db->prepare('
        SELECT t.`id`, t.`name`
        FROM `events_tags` t
        INNER JOIN `events_calendar_event_tags` et ON et.`tag_id` = t.`id`
        WHERE et.`event_id` = ?
        ORDER BY t.`name` ASC, t.`id` ASC
    ');
    $st->execute([$eventId]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[] = ['id' => (int) $r['id'], 'name' => (string) $r['name']];
    }

    return $out;
}
