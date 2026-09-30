<?php
declare(strict_types=1);

require_once __DIR__ . '/tag_type.php';
require_once __DIR__ . '/tag_profile.php';
require_once __DIR__ . '/event_public_organizers.php';
require_once __DIR__ . '/event_public_lang.php';
require_once __DIR__ . '/event_public_djs.php';

/**
 * Nyilvános zenekar-katalógus (zenekar típusú címkék).
 * Média / avatar segédek: ugyanazok a profiloszlopok, mint a DJ-nél.
 */

function events_public_zenekar_initials(string $name): string
{
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    if ($name === '') {
        return 'ZK';
    }
    $parts = preg_split('/\s+/u', $name) ?: [];
    $letters = '';
    foreach ($parts as $part) {
        $part = trim((string) $part);
        if ($part === '') {
            continue;
        }
        $ch = mb_substr($part, 0, 1, 'UTF-8');
        if ($ch !== '') {
            $letters .= mb_strtoupper($ch, 'UTF-8');
        }
        if (mb_strlen($letters, 'UTF-8') >= 2) {
            break;
        }
    }

    return $letters !== '' ? mb_substr($letters, 0, 2, 'UTF-8') : 'ZK';
}

/**
 * @return list<array{
 *   id: int,
 *   name: string,
 *   slug: string,
 *   photo_url: string,
 *   photo_fit: string,
 *   photo_focus_x: string,
 *   photo_focus_y: string,
 *   photo_zoom: string,
 *   logo_url: string,
 *   logo_fit: string,
 *   logo_focus_x: string,
 *   logo_focus_y: string,
 *   logo_zoom: string,
 *   event_total: int,
 *   event_upcoming: int,
 *   next_event_start: ?string
 * }>
 */
function events_public_zenekar_catalog(PDO $db, string $publishedStatus, ?int $listLimit = null): array
{
    if (!events_tags_tables_available($db) || !events_tag_types_tables_available($db)) {
        return [];
    }
    events_tags_ensure_zenekar_slugs($db);
    events_tags_ensure_profile_columns($db);
    $typeId = events_tag_type_id_by_code($db, 'zenekar');
    if ($typeId === null || $typeId <= 0) {
        return [];
    }

    require_once __DIR__ . '/admin_event_filters.php';
    $poolFrom = events_admin_table_pool_from_sql('events_tags', 't', $listLimit);
    $slugSelect = events_tags_slug_column_available($db) ? 't.`slug`' : 'NULL AS `slug`';
    $photoSelect = events_public_dj_media_select_sql($db);

    $st = $db->prepare('
        SELECT t.`id`, t.`name`, ' . $slugSelect . ', ' . $photoSelect . '
        FROM ' . $poolFrom . '
        INNER JOIN `events_tag_type_links` l ON l.`tag_id` = t.`id` AND l.`tag_type_id` = ?
        ORDER BY t.`name` ASC, t.`id` ASC
    ');
    $st->execute([$typeId]);
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
        $byId[$id] = array_merge(events_public_dj_media_fields_from_row($row), [
            'id' => $id,
            'name' => (string) ($row['name'] ?? ''),
            'slug' => trim((string) ($row['slug'] ?? '')),
            'event_total' => 0,
            'event_upcoming' => 0,
            'next_event_start' => null,
        ]);
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

/**
 * @param list<array<string, mixed>> $catalog
 * @return list<array<string, mixed>>
 */
function events_public_zenekar_spotlight_pool(array $catalog, int $poolLimit = 12): array
{
    return events_public_dj_spotlight_pool($catalog, $poolLimit);
}

/**
 * @param list<array<string, mixed>> $pool
 * @param array<string, string> $strings
 * @return list<array{name:string,href:string,photo:string,photoStyle:string,isLogo:bool,logo:string,logoStyle:string,initials:string,meta:string,aria:string}>
 */
function events_public_zenekar_spotlight_cards(array $pool, string $lang, array $strings): array
{
    $cards = [];

    foreach ($pool as $row) {
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $slug = trim((string) ($row['slug'] ?? ''));
        $photo = trim((string) ($row['photo_url'] ?? ''));
        $logo = trim((string) ($row['logo_url'] ?? ''));
        $media = $photo !== '' ? $photo : $logo;
        $brandLogo = events_public_dj_brand_logo_url($row);
        $nextStart = trim((string) ($row['next_event_start'] ?? ''));
        $nextTs = $nextStart !== '' ? strtotime($nextStart) : false;

        $meta = '';
        if ($nextTs !== false) {
            $meta = (string) ($strings['next_event'] ?? '') . ': ' . events_public_format_event_day($nextTs, $lang);
        }

        $cards[] = [
            'id' => (int) ($row['id'] ?? 0),
            'name' => $name,
            'href' => $slug !== ''
                ? events_public_zenekar_page_url($slug, $lang)
                : events_public_tag_page_url((int) $row['id'], $lang),
            'photo' => $media !== '' ? events_absolute_url($media) : '',
            'photoStyle' => $media !== '' ? events_public_dj_media_img_style($row) : '',
            'isLogo' => events_public_dj_media_is_logo($row),
            'logo' => $brandLogo,
            'logoStyle' => $brandLogo !== '' ? events_public_dj_brand_logo_style($row) : '',
            'initials' => events_public_zenekar_initials($name),
            'meta' => $meta,
            'aria' => (string) ($strings['card_aria'] ?? '') . ': ' . $name,
        ];
    }

    return $cards;
}

function events_public_zenekar_total_count(PDO $db): int
{
    if (!events_tags_tables_available($db) || !events_tag_types_tables_available($db)) {
        return 0;
    }
    $typeId = events_tag_type_id_by_code($db, 'zenekar');
    if ($typeId === null || $typeId <= 0) {
        return 0;
    }

    $st = $db->prepare('
        SELECT COUNT(*)
        FROM `events_tags` t
        INNER JOIN `events_tag_type_links` l ON l.`tag_id` = t.`id` AND l.`tag_type_id` = ?
    ');
    $st->execute([$typeId]);

    return (int) $st->fetchColumn();
}

/**
 * @return array{total:int,upcoming:int}
 */
function events_public_zenekar_unique_events_counts(PDO $db, string $publishedStatus): array
{
    if (!events_tags_tables_available($db) || !events_tag_types_tables_available($db)) {
        return ['total' => 0, 'upcoming' => 0];
    }
    $typeId = events_tag_type_id_by_code($db, 'zenekar');
    if ($typeId === null || $typeId <= 0) {
        return ['total' => 0, 'upcoming' => 0];
    }

    $st = $db->prepare('
        SELECT DISTINCT e.`id`, e.`event_start`, e.`event_end`, e.`event_allday`
        FROM `events_calendar_events` e
        INNER JOIN `events_calendar_event_tags` et ON et.`event_id` = e.`id`
        INNER JOIN `events_tag_type_links` l ON l.`tag_id` = et.`tag_id` AND l.`tag_type_id` = ?
        WHERE e.`event_status` = ?
    ');
    $st->execute([$typeId, $publishedStatus]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $nowTs = time();
    $upcoming = 0;
    foreach ($rows as $row) {
        if (!events_public_event_row_is_past($row, $nowTs)) {
            $upcoming++;
        }
    }

    return [
        'total' => count($rows),
        'upcoming' => $upcoming,
    ];
}

/**
 * @param list<array{id:int,name:string,slug:string,event_total:int,event_upcoming:int,next_event_start:?string}> $catalog
 * @return array{
 *   zenekar_total: int,
 *   zenekar_with_events: int,
 *   zenekar_with_upcoming: int,
 *   events_total: int,
 *   events_upcoming: int,
 *   top_by_events: list<array{id:int,name:string,slug:string,count:int}>,
 *   top_by_upcoming: list<array{id:int,name:string,slug:string,count:int}>,
 *   next_up: list<array{id:int,name:string,slug:string,next_event_start:string}>
 * }
 */
function events_public_zenekar_hub_stats(PDO $db, string $publishedStatus, array $catalog, int $topLimit = 8): array
{
    $topLimit = max(1, min(20, $topLimit));
    $withEvents = 0;
    $withUpcoming = 0;
    $byEvents = [];
    $byUpcoming = [];
    $nextUp = [];

    foreach ($catalog as $row) {
        $id = (int) ($row['id'] ?? 0);
        $name = (string) ($row['name'] ?? '');
        $slug = trim((string) ($row['slug'] ?? ''));
        $total = (int) ($row['event_total'] ?? 0);
        $upcoming = (int) ($row['event_upcoming'] ?? 0);
        $nextStart = trim((string) ($row['next_event_start'] ?? ''));
        if ($id <= 0) {
            continue;
        }
        if ($total > 0) {
            $withEvents++;
            $byEvents[] = ['id' => $id, 'name' => $name, 'slug' => $slug, 'count' => $total];
        }
        if ($upcoming > 0) {
            $withUpcoming++;
            $byUpcoming[] = ['id' => $id, 'name' => $name, 'slug' => $slug, 'count' => $upcoming];
        }
        if ($nextStart !== '') {
            $nextUp[] = [
                'id' => $id,
                'name' => $name,
                'slug' => $slug,
                'next_event_start' => $nextStart,
            ];
        }
    }

    usort($byEvents, static function (array $a, array $b): int {
        return ($b['count'] <=> $a['count']) ?: strcmp($a['name'], $b['name']);
    });
    usort($byUpcoming, static function (array $a, array $b): int {
        return ($b['count'] <=> $a['count']) ?: strcmp($a['name'], $b['name']);
    });
    usort($nextUp, static function (array $a, array $b): int {
        return strcmp($a['next_event_start'], $b['next_event_start']) ?: strcmp($a['name'], $b['name']);
    });

    $eventCounts = events_public_zenekar_unique_events_counts($db, $publishedStatus);

    return [
        'zenekar_total' => count($catalog),
        'zenekar_with_events' => $withEvents,
        'zenekar_with_upcoming' => $withUpcoming,
        'events_total' => $eventCounts['total'],
        'events_upcoming' => $eventCounts['upcoming'],
        'top_by_events' => array_slice($byEvents, 0, $topLimit),
        'top_by_upcoming' => array_slice($byUpcoming, 0, $topLimit),
        'next_up' => array_slice($nextUp, 0, $topLimit),
    ];
}
