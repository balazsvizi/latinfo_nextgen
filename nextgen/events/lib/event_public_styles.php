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

/**
 * @param list<array<string, mixed>> $rows
 * @return array{main: array<int, list<array{id:int,name:string}>>, supplementary: array<int, list<array{id:int,name:string}>>}
 */
function events_public_load_styles_by_event_id(PDO $db, array $rows): array {
    $empty = ['main' => [], 'supplementary' => []];
    if ($rows === [] || !events_styles_tables_available($db)) {
        return $empty;
    }
    $eventIds = array_values(array_unique(array_map(static fn (array $r): int => (int) $r['id'], $rows)));
    if ($eventIds === []) {
        return $empty;
    }
    $ph = implode(',', array_fill(0, count($eventIds), '?'));

    return [
        'main' => events_public_load_styles_map_for_table($db, $ph, $eventIds, 'events_calendar_event_main_styles'),
        'supplementary' => events_public_load_styles_map_for_table($db, $ph, $eventIds, 'events_calendar_event_supplementary_styles'),
    ];
}

/**
 * @param list<int> $eventIds
 * @return array<int, list<array{id:int,name:string}>>
 */
function events_public_load_styles_map_for_table(PDO $db, string $placeholders, array $eventIds, string $junctionTable): array {
    if ($junctionTable !== 'events_calendar_event_main_styles' && $junctionTable !== 'events_calendar_event_supplementary_styles') {
        return [];
    }
    $stmt = $db->prepare("
        SELECT es.`event_id`, s.`id`, s.`name`
        FROM `{$junctionTable}` es
        INNER JOIN `events_styles` s ON s.`id` = es.`style_id`
        WHERE es.`event_id` IN ({$placeholders})
        ORDER BY s.`name` ASC, s.`id` ASC
    ");
    $stmt->execute($eventIds);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $eid = (int) $row['event_id'];
        if (!isset($out[$eid])) {
            $out[$eid] = [];
        }
        $out[$eid][] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
        ];
    }

    return $out;
}
