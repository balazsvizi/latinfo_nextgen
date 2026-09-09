<?php
declare(strict_types=1);

require_once __DIR__ . '/tag_type.php';
require_once __DIR__ . '/tag_profile.php';
require_once __DIR__ . '/slug.php';
require_once __DIR__ . '/event_public_djs.php';

/**
 * Admin DJ lista (DJ típusú címkék).
 */

/**
 * @return array{
 *   f_q: string,
 *   order: string,
 *   dir_param: string,
 *   get_params: array<string, string>
 * }
 */
function events_djs_admin_filters_from_request(): array {
    $f_q = trim((string) ($_GET['f_q'] ?? ''));
    $order = (string) ($_GET['order'] ?? 'name');
    $allowed = ['id', 'name', 'events', 'slug'];
    if (!in_array($order, $allowed, true)) {
        $order = 'name';
    }
    $dir_param = strtolower((string) ($_GET['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
    $get_params = [];
    if ($f_q !== '') {
        $get_params['f_q'] = $f_q;
    }
    $get_params['order'] = $order;
    $get_params['dir'] = $dir_param;

    return [
        'f_q' => $f_q,
        'order' => $order,
        'dir_param' => $dir_param,
        'get_params' => $get_params,
    ];
}

/**
 * @param array{f_q:string,order:string,dir_param:string} $filters
 * @return list<array{id:int,name:string,slug:string,photo_url:string,event_count:int}>
 */
function events_djs_admin_fetch(PDO $db, array $filters, ?int $listLimit = null): array {
    if (!events_tags_tables_available($db) || !events_tag_types_tables_available($db)) {
        return [];
    }
    events_tags_ensure_dj_slugs($db);
    events_tags_ensure_profile_columns($db);
    $djTypeId = events_tag_type_id_by_code($db, 'dj');
    if ($djTypeId === null || $djTypeId <= 0) {
        return [];
    }

    $where = ['l.`tag_type_id` = ?'];
    $params = [$djTypeId];
    $f_q = trim((string) ($filters['f_q'] ?? ''));
    if ($f_q !== '') {
        if (ctype_digit($f_q)) {
            $where[] = '(t.`id` = ? OR t.`name` LIKE ? OR t.`slug` LIKE ?)';
            $params[] = (int) $f_q;
            $like = '%' . $f_q . '%';
            $params[] = $like;
            $params[] = $like;
        } else {
            $where[] = '(t.`name` LIKE ? OR t.`slug` LIKE ?)';
            $like = '%' . $f_q . '%';
            $params[] = $like;
            $params[] = $like;
        }
    }
    $whereSql = implode(' AND ', $where);

    $order = (string) ($filters['order'] ?? 'name');
    $dir = strtoupper((string) ($filters['dir_param'] ?? 'asc')) === 'DESC' ? 'DESC' : 'ASC';
    $orderSql = match ($order) {
        'id' => 't.`id` ' . $dir,
        'slug' => 't.`slug` ' . $dir . ', t.`name` ASC',
        'events' => 'event_count ' . $dir . ', t.`name` ASC',
        default => 't.`name` ' . $dir . ', t.`id` ASC',
    };

    $slugSelect = events_tags_slug_column_available($db) ? 't.`slug`' : 'NULL AS `slug`';
    $photoSelect = events_tags_profile_columns_available($db) ? 't.`photo_url`' : 'NULL AS `photo_url`';
    $limitSql = $listLimit === null ? '' : ' LIMIT ' . (int) $listLimit;

    $st = $db->prepare("
        SELECT t.`id`, t.`name`, {$slugSelect}, {$photoSelect},
               (
                   SELECT COUNT(*)
                   FROM `events_calendar_event_tags` et
                   WHERE et.`tag_id` = t.`id`
               ) AS `event_count`
        FROM `events_tags` t
        INNER JOIN `events_tag_type_links` l ON l.`tag_id` = t.`id`
        WHERE {$whereSql}
        ORDER BY {$orderSql}
        {$limitSql}
    ");
    $st->execute($params);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[] = [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'slug' => trim((string) ($row['slug'] ?? '')),
            'photo_url' => trim((string) ($row['photo_url'] ?? '')),
            'event_count' => (int) ($row['event_count'] ?? 0),
        ];
    }

    return $out;
}

function events_djs_admin_total_count(PDO $db): int {
    return events_public_dj_total_count($db);
}
