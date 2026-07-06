<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_event_filters.php';
require_once __DIR__ . '/event_status.php';

/**
 * Publikus főoldal nézet: naptár, lista vagy térkép.
 */
function events_public_resolve_home_view(string $raw): string
{
    return match ($raw) {
        'list' => 'list',
        'map' => 'map',
        default => 'cal',
    };
}

/**
 * Nyilvános esemény főoldal szűrők — admin mintájára, csak közzétett eseményekkel.
 *
 * @return array<string, mixed>
 */
function events_public_filters_from_request(PDO $db): array {
    $f_city = trim((string) ($_GET['f_city'] ?? ''));
    $view = events_public_resolve_home_view((string) ($_GET['view'] ?? 'cal'));

    $savedGet = $_GET;
    unset($_GET['status'], $_GET['f_id'], $_GET['f_views_min']);
    $filters = events_admin_filters_from_request($db);
    $_GET = $savedGet;

    $filters['where'][] = 'e.event_status = ?';
    $filters['params'][] = events_public_post_status();

    if ($f_city !== '') {
        $filters['where'][] = 'EXISTS (
            SELECT 1 FROM `events_venues` vcf
            WHERE vcf.`id` = e.`venue_id` AND vcf.`city` LIKE ?
        )';
        $filters['params'][] = '%' . $f_city . '%';
    }

    $filters['f_city'] = $f_city;
    $filters['status'] = '';
    $filters['f_id'] = '';
    $filters['f_views_min'] = '';
    $filters['view'] = $view;

    $listLimitParsed = events_admin_list_limit_from_get(EVENTS_ADMIN_EVENTS_LIST_DEFAULT_LIMIT);
    $filters['list_limit_value'] = $listLimitParsed['value'];
    $filters['list_limit'] = $view === 'list' ? $listLimitParsed['sql_limit'] : null;

    $getParams = $filters['get_params'];
    unset($getParams['status'], $getParams['f_id'], $getParams['f_views_min'], $getParams['list_limit']);
    if ($f_city !== '') {
        $getParams['f_city'] = $f_city;
    }
    if ($view === 'list') {
        $getParams['view'] = 'list';
        if ($listLimitParsed['value'] !== (string) EVENTS_ADMIN_EVENTS_LIST_DEFAULT_LIMIT) {
            $getParams['list_limit'] = $listLimitParsed['value'];
        }
    } elseif ($view === 'map') {
        $getParams['view'] = 'map';
    }
    $filters['get_params'] = $getParams;

    return $filters;
}

/**
 * Van-e aktív szűrő (a panel ilyenkor alapból nyitva marad).
 *
 * @param array<string, mixed> $filters
 */
function events_public_filters_are_active(array $filters): bool {
    if (trim((string) ($filters['f_organizer'] ?? '')) !== '') {
        return true;
    }
    if (trim((string) ($filters['f_name'] ?? '')) !== '') {
        return true;
    }
    if (trim((string) ($filters['f_venue'] ?? '')) !== '') {
        return true;
    }
    if (trim((string) ($filters['f_city'] ?? '')) !== '') {
        return true;
    }
    if ((int) ($filters['f_category_id'] ?? 0) > 0) {
        return true;
    }
    if ((int) ($filters['f_tag_id'] ?? 0) > 0) {
        return true;
    }
    if ((int) ($filters['f_dj_id'] ?? 0) > 0) {
        return true;
    }
    if ((int) ($filters['f_main_style_id'] ?? 0) > 0) {
        return true;
    }
    if ((int) ($filters['f_supplementary_style_id'] ?? 0) > 0) {
        return true;
    }
    if (trim((string) ($filters['f_start_from'] ?? '')) !== '') {
        return true;
    }
    if (trim((string) ($filters['f_start_to'] ?? '')) !== '') {
        return true;
    }

    return false;
}

/**
 * Szűrőmező címke osztályai – aktív értéknél ugyanaz a kiemelés, mint az „aktív” jelvény.
 *
 * @param array<string, mixed> $filters
 */
function events_public_filter_label_attr_classes(array $filters, string $key): string
{
    return events_filter_label_attr_classes($filters, $key);
}

/**
 * @param array<string, mixed> $filters
 * @return list<array<string, mixed>>
 */
function events_public_fetch_filtered_events(PDO $db, array $filters): array {
    $whereSql = $filters['where'] !== [] ? 'WHERE ' . implode(' AND ', $filters['where']) : '';
    $fromSql = '`events_calendar_events` e LEFT JOIN `events_venues` v ON v.`id` = e.`venue_id`';
    if (($filters['view'] ?? 'cal') === 'list') {
        $poolFrom = events_admin_list_pool_from_sql($filters['list_limit']);
        $fromSql = $poolFrom . ' LEFT JOIN `events_venues` v ON v.`id` = e.`venue_id`';
    }
    $sql = "
        SELECT e.*,
            v.`name` AS `venue_name`,
            v.`city` AS `venue_city`,
            v.`address` AS `venue_address`,
            v.`postal_code` AS `venue_postal_code`,
            v.`latitude` AS `venue_latitude`,
            v.`longitude` AS `venue_longitude`
        FROM {$fromSql}
        {$whereSql}
        ORDER BY e.event_start IS NULL, e.event_start ASC, e.event_name ASC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($filters['params']);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Közzétett események száma az adatbázisban. */
function events_public_published_events_total_count(PDO $db): int {
    $stmt = $db->prepare('SELECT COUNT(*) FROM `events_calendar_events` WHERE `event_status` = ?');
    $stmt->execute([events_public_post_status()]);

    return (int) $stmt->fetchColumn();
}

/** @return array<string, string> */
function events_public_catalog_get_params(string $listLimitValue): array {
    $params = [];
    if ($listLimitValue !== (string) EVENTS_ADMIN_LIST_DEFAULT_LIMIT) {
        $params['list_limit'] = $listLimitValue;
    }

    return $params;
}

/** @return array<string, string> */
function events_public_events_list_get_params(string $listLimitValue): array {
    $params = [];
    if ($listLimitValue !== (string) EVENTS_ADMIN_EVENTS_LIST_DEFAULT_LIMIT) {
        $params['list_limit'] = $listLimitValue;
    }

    return $params;
}

function events_public_list_count_label(string $lang, string $listLimitValue, int $totalInDb): string {
    $formatCount = static fn (int $n): string => events_admin_list_format_count_compact($n);
    $displayCount = events_admin_list_display_limit_count($listLimitValue, $totalInDb);
    $suffix = $lang === 'en' ? ' shown' : ' megjelenítve';

    return $formatCount($displayCount) . ' / ' . $formatCount($totalInDb) . $suffix;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<int, list<array{id: int, name: string, color: string}>>
 */
function events_public_load_categories_by_event_id(PDO $db, array $rows): array {
    $categoriesByEventId = [];
    if ($rows === []) {
        return $categoriesByEventId;
    }
    $eventIds = array_values(array_unique(array_map(static fn (array $r): int => (int) $r['id'], $rows)));
    $ph = implode(',', array_fill(0, count($eventIds), '?'));
    $catStmt = $db->prepare("
        SELECT ec.`event_id`, c.`id`, c.`name`, c.`color`
        FROM `events_calendar_event_categories` ec
        INNER JOIN `events_categories` c ON c.`id` = ec.`category_id`
        WHERE ec.`event_id` IN ({$ph})
        ORDER BY c.`sort_order` ASC, c.`name` ASC, c.`id` ASC
    ");
    $catStmt->execute($eventIds);
    foreach ($catStmt->fetchAll(PDO::FETCH_ASSOC) as $catRow) {
        $eid = (int) $catRow['event_id'];
        if (!isset($categoriesByEventId[$eid])) {
            $categoriesByEventId[$eid] = [];
        }
        $categoriesByEventId[$eid][] = [
            'id' => (int) $catRow['id'],
            'name' => (string) $catRow['name'],
            'color' => trim((string) ($catRow['color'] ?? '')) !== '' ? trim((string) $catRow['color']) : '#6d8f63',
        ];
    }

    return $categoriesByEventId;
}
