<?php
declare(strict_types=1);

require_once __DIR__ . '/tag_type.php';
require_once __DIR__ . '/event_public_organizers.php';

/**
 * Nyilvános DJ-katalógus (DJ típusú címkék).
 */

function events_public_tag_has_type_code(PDO $db, int $tagId, string $typeCode): bool {
    $codes = events_load_tag_type_codes($db, $tagId);

    return in_array($typeCode, $codes, true);
}

/**
 * @return list<array{
 *   id: int,
 *   name: string,
 *   event_total: int,
 *   event_upcoming: int,
 *   next_event_start: ?string
 * }>
 */
function events_public_dj_catalog(PDO $db, string $publishedStatus, ?int $listLimit = null): array {
    if (!events_tags_tables_available($db) || !events_tag_types_tables_available($db)) {
        return [];
    }
    $djTypeId = events_tag_type_id_by_code($db, 'dj');
    if ($djTypeId === null || $djTypeId <= 0) {
        return [];
    }

    require_once __DIR__ . '/admin_event_filters.php';
    $poolFrom = events_admin_table_pool_from_sql('events_tags', 't', $listLimit);

    $st = $db->prepare('
        SELECT t.`id`, t.`name`
        FROM ' . $poolFrom . '
        INNER JOIN `events_tag_type_links` l ON l.`tag_id` = t.`id` AND l.`tag_type_id` = ?
        ORDER BY t.`name` ASC, t.`id` ASC
    ');
    $st->execute([$djTypeId]);
    $tags = $st->fetchAll(PDO::FETCH_ASSOC);
    if ($tags === []) {
        return [];
    }

    $byId = [];
    foreach ($tags as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $byId[$id] = [
            'id' => $id,
            'name' => (string) ($row['name'] ?? ''),
            'event_total' => 0,
            'event_upcoming' => 0,
            'next_event_start' => null,
        ];
    }
    if ($byId === []) {
        return [];
    }

    $tagIds = array_keys($byId);
    $ph = implode(',', array_fill(0, count($tagIds), '?'));
    $evSt = $db->prepare("
        SELECT et.`tag_id`, e.`id`, e.`event_start`, e.`event_end`, e.`event_allday`
        FROM `events_calendar_event_tags` et
        INNER JOIN `events_calendar_events` e ON e.`id` = et.`event_id`
        WHERE et.`tag_id` IN ({$ph}) AND e.`event_status` = ?
    ");
    $evSt->execute(array_merge($tagIds, [$publishedStatus]));
    $nowTs = time();

    foreach ($evSt->fetchAll(PDO::FETCH_ASSOC) as $evRow) {
        $tid = (int) ($evRow['tag_id'] ?? 0);
        if (!isset($byId[$tid])) {
            continue;
        }
        $byId[$tid]['event_total']++;
        if (events_public_event_row_is_past($evRow, $nowTs)) {
            continue;
        }
        $byId[$tid]['event_upcoming']++;
        $start = (string) ($evRow['event_start'] ?? '');
        if ($start !== '') {
            $cur = $byId[$tid]['next_event_start'];
            if ($cur === null || $start < $cur) {
                $byId[$tid]['next_event_start'] = $start;
            }
        }
    }

    return array_values($byId);
}

function events_public_dj_total_count(PDO $db): int {
    if (!events_tags_tables_available($db) || !events_tag_types_tables_available($db)) {
        return 0;
    }
    $djTypeId = events_tag_type_id_by_code($db, 'dj');
    if ($djTypeId === null || $djTypeId <= 0) {
        return 0;
    }

    $st = $db->prepare('
        SELECT COUNT(*)
        FROM `events_tags` t
        INNER JOIN `events_tag_type_links` l ON l.`tag_id` = t.`id` AND l.`tag_type_id` = ?
    ');
    $st->execute([$djTypeId]);

    return (int) $st->fetchColumn();
}
