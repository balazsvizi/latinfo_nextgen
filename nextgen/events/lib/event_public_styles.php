<?php
declare(strict_types=1);

/**
 * Nyilvános eseményoldal: fő és kiegészítő stílusok megjelenítése.
 */

/**
 * @return list<array{id:int,name:string}>
 */
function events_public_event_main_styles_for_display(PDO $db, int $eventId): array {
    return events_public_event_styles_for_display($db, $eventId, 'events_calendar_event_main_styles');
}

/**
 * @return list<array{id:int,name:string}>
 */
function events_public_event_supplementary_styles_for_display(PDO $db, int $eventId): array {
    return events_public_event_styles_for_display($db, $eventId, 'events_calendar_event_supplementary_styles');
}

/**
 * @return list<array{id:int,name:string}>
 */
function events_public_event_styles_for_display(PDO $db, int $eventId, string $junctionTable): array {
    if (!events_styles_tables_available($db)) {
        return [];
    }
    if ($junctionTable !== 'events_calendar_event_main_styles' && $junctionTable !== 'events_calendar_event_supplementary_styles') {
        return [];
    }
    $st = $db->prepare("
        SELECT s.`id`, s.`name`
        FROM `events_styles` s
        INNER JOIN `{$junctionTable}` es ON es.`style_id` = s.`id`
        WHERE es.`event_id` = ?
        ORDER BY s.`name` ASC, s.`id` ASC
    ");
    $st->execute([$eventId]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[] = ['id' => (int) $r['id'], 'name' => (string) $r['name']];
    }

    return $out;
}
