<?php
declare(strict_types=1);

require_once __DIR__ . '/tag_type.php';
require_once __DIR__ . '/tag_profile.php';
require_once __DIR__ . '/slug.php';
require_once __DIR__ . '/event_status.php';
require_once __DIR__ . '/event_public_djs.php';

/**
 * Admin DJ lista (DJ típusú címkék).
 */

/**
 * DJ-hez kötött események összesítői (szervező lista mintájára).
 */
function events_djs_admin_stats_subquery_sql(): string
{
    $publishedSql = "'" . str_replace("'", "''", events_public_post_status()) . "'";

    return "
        SELECT
            et.`tag_id`,
            COUNT(DISTINCT e.`id`) AS `event_count`,
            COUNT(DISTINCT CASE WHEN e.`event_status` = {$publishedSql} THEN e.`id` END) AS `published_count`,
            COUNT(DISTINCT CASE
                WHEN e.`event_status` = {$publishedSql}
                    AND COALESCE(e.`event_end`, e.`event_start`) >= NOW()
                THEN e.`id`
            END) AS `upcoming_count`,
            MAX(COALESCE(e.`event_end`, e.`event_start`)) AS `last_event_at`,
            MIN(CASE
                WHEN e.`event_status` = {$publishedSql}
                    AND e.`event_start` IS NOT NULL
                    AND e.`event_start` >= CURDATE()
                THEN e.`event_start`
            END) AS `next_event_at`
        FROM `events_calendar_event_tags` et
        INNER JOIN `events_calendar_events` e ON e.`id` = et.`event_id`
        WHERE e.`event_status` NOT IN ('trash')
        GROUP BY et.`tag_id`
    ";
}

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
    $allowed = ['id', 'name', 'events', 'published', 'upcoming', 'last_event', 'next_event', 'slug', 'photo'];
    if (isset($_GET['order']) && in_array((string) $_GET['order'], $allowed, true)) {
        $order = (string) $_GET['order'];
        $dir_param = isset($_GET['dir']) && strtolower((string) $_GET['dir']) === 'asc' ? 'asc' : 'desc';
    } else {
        $order = 'name';
        $dir_param = 'asc';
    }
    // order/dir szándékosan nincs a get_params-ban: a sort_th teszi rá (mint a szervezőlistán).
    $get_params = [];
    if ($f_q !== '') {
        $get_params['f_q'] = $f_q;
    }

    return [
        'f_q' => $f_q,
        'order' => $order,
        'dir_param' => $dir_param,
        'get_params' => $get_params,
    ];
}

/**
 * @param array{f_q:string,order:string,dir_param:string} $filters
 * @return list<array{
 *   id:int,
 *   name:string,
 *   slug:string,
 *   photo_url:string,
 *   event_count:int,
 *   published_count:int,
 *   upcoming_count:int,
 *   last_event_at:?string,
 *   next_event_at:?string
 * }>
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
    $hasPhotoCol = events_tags_profile_columns_available($db);
    $orderSql = match ($order) {
        'id' => 't.`id` ' . $dir,
        'slug' => 't.`slug` ' . $dir . ', t.`name` ASC',
        'photo' => $hasPhotoCol
            ? '(CASE WHEN COALESCE(t.`photo_url`, \'\') <> \'\' OR COALESCE(t.`logo_url`, \'\') <> \'\' THEN 1 ELSE 0 END) ' . $dir . ', t.`name` ASC'
            : 't.`name` ' . $dir . ', t.`id` ASC',
        'events' => 'COALESCE(st.`event_count`, 0) ' . $dir . ', t.`name` ASC',
        'published' => 'COALESCE(st.`published_count`, 0) ' . $dir . ', t.`name` ASC',
        'upcoming' => 'COALESCE(st.`upcoming_count`, 0) ' . $dir . ', t.`name` ASC',
        'last_event' => 'st.`last_event_at` IS NULL, st.`last_event_at` ' . $dir . ', t.`name` ASC',
        'next_event' => 'st.`next_event_at` IS NULL, st.`next_event_at` ' . $dir . ', t.`name` ASC',
        default => 't.`name` ' . $dir . ', t.`id` ASC',
    };

    $slugSelect = events_tags_slug_column_available($db) ? 't.`slug`' : 'NULL AS `slug`';
    $photoSelect = events_tags_profile_columns_available($db)
        ? 't.`photo_url`, t.`logo_url`'
        : 'NULL AS `photo_url`, NULL AS `logo_url`';
    $limitSql = $listLimit === null ? '' : ' LIMIT ' . (int) $listLimit;
    $statsSql = events_djs_admin_stats_subquery_sql();

    $st = $db->prepare("
        SELECT t.`id`, t.`name`, {$slugSelect}, {$photoSelect},
               COALESCE(st.`event_count`, 0) AS `event_count`,
               COALESCE(st.`published_count`, 0) AS `published_count`,
               COALESCE(st.`upcoming_count`, 0) AS `upcoming_count`,
               st.`last_event_at`,
               st.`next_event_at`
        FROM `events_tags` t
        INNER JOIN `events_tag_type_links` l ON l.`tag_id` = t.`id`
        LEFT JOIN ({$statsSql}) st ON st.`tag_id` = t.`id`
        WHERE {$whereSql}
        ORDER BY {$orderSql}
        {$limitSql}
    ");
    $st->execute($params);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $last = $row['last_event_at'] ?? null;
        $next = $row['next_event_at'] ?? null;
        $out[] = [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'slug' => trim((string) ($row['slug'] ?? '')),
            'photo_url' => trim((string) ($row['photo_url'] ?? '')),
            'logo_url' => trim((string) ($row['logo_url'] ?? '')),
            'event_count' => (int) ($row['event_count'] ?? 0),
            'published_count' => (int) ($row['published_count'] ?? 0),
            'upcoming_count' => (int) ($row['upcoming_count'] ?? 0),
            'last_event_at' => is_string($last) && $last !== '' ? $last : null,
            'next_event_at' => is_string($next) && $next !== '' ? $next : null,
        ];
    }

    return $out;
}

function events_djs_admin_total_count(PDO $db): int {
    return events_public_dj_total_count($db);
}

function events_djs_admin_format_datetime(?string $value): string
{
    if ($value === null || trim($value) === '' || str_starts_with($value, '0000-00-00')) {
        return '–';
    }
    try {
        $dt = new DateTimeImmutable($value);

        return $dt->format('Y.m.d H:i');
    } catch (Throwable) {
        return '–';
    }
}

/**
 * Eseményadmin szűrés erre a DJ-re.
 */
function events_djs_admin_events_filter_url(int $djId): string
{
    if ($djId <= 0) {
        return events_url('events_admin.php');
    }

    return events_url('events_admin.php?' . http_build_query(['f_dj' => (string) $djId], '', '&', PHP_QUERY_RFC3986));
}
