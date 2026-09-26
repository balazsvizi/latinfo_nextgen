<?php
declare(strict_types=1);

require_once __DIR__ . '/event_view_tracking.php';
require_once __DIR__ . '/event_edit_stats.php';
require_once __DIR__ . '/public_traffic.php';
require_once __DIR__ . '/public_home_notice_stats.php';

const EVENTS_REALTIME_WINDOW_MINUTES = 30;
const EVENTS_REALTIME_TOP_EVENTS = 10;
const EVENTS_REALTIME_TOP_PAGES = 8;
const EVENTS_REALTIME_TOP_NAV = 8;
const EVENTS_REALTIME_RECENT_LIMIT = 40;

/**
 * Index a 30 perces ablak lekérdezéseihez (idempotens).
 */
function events_realtime_ensure_indexes(PDO $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $stmt = $db->query("SHOW INDEX FROM `events_calendar_event_views` WHERE Key_name = 'idx_views_created_metric'");
        if ($stmt && $stmt->fetch(PDO::FETCH_ASSOC)) {
            return;
        }
        $db->exec(
            'ALTER TABLE `events_calendar_event_views`
             ADD INDEX `idx_views_created_metric` (`létrehozva`, `metric_type`)'
        );
    } catch (Throwable $ex) {
        error_log('events_realtime_ensure_indexes: ' . $ex->getMessage());
    }
}

/**
 * @return array{start: string, end: string, minutes: list<string>}
 */
function events_realtime_window(): array
{
    $end = new DateTimeImmutable('now');
    // Align to current minute floor for stable buckets.
    $endMinute = $end->setTime((int) $end->format('H'), (int) $end->format('i'), 0);
    $start = $endMinute->modify('-' . (EVENTS_REALTIME_WINDOW_MINUTES - 1) . ' minutes');

    $minutes = [];
    $cur = $start;
    for ($i = 0; $i < EVENTS_REALTIME_WINDOW_MINUTES; $i++) {
        $minutes[] = $cur->format('Y-m-d H:i:00');
        $cur = $cur->modify('+1 minute');
    }

    return [
        'start' => $start->format('Y-m-d H:i:s'),
        'end' => $end->format('Y-m-d H:i:s'),
        'minutes' => $minutes,
    ];
}

function events_realtime_source_label(string $source): string
{
    return match ($source) {
        EVENTS_VIEW_SOURCE_DIRECT => 'Közvetlen',
        EVENTS_VIEW_SOURCE_CALENDAR => 'Naptár',
        EVENTS_VIEW_SOURCE_CAL_PREVIEW => 'Naptár előnézet',
        EVENTS_VIEW_SOURCE_LIST => 'Lista',
        default => $source !== '' ? $source : 'Ismeretlen',
    };
}

function events_realtime_metric_label(string $metric): string
{
    return match ($metric) {
        EVENTS_VIEW_METRIC_PAGE => 'Oldal',
        EVENTS_VIEW_METRIC_CALENDAR_PREVIEW => 'Előnézet',
        EVENTS_VIEW_METRIC_EXTERNAL_INFO => 'További info',
        'hub_page' => 'Statikus oldal',
        'nav_click' => 'Menü',
        'notice_click' => 'Értesítő',
        default => $metric !== '' ? $metric : '—',
    };
}

function events_realtime_kind_label(string $kind): string
{
    return match ($kind) {
        'party' => 'Buli',
        'preview' => 'Előnézet',
        'external' => 'További info',
        'hub' => 'Statikus oldal',
        'nav' => 'Menü',
        'notice' => 'Értesítő',
        default => $kind !== '' ? $kind : '—',
    };
}

/**
 * @return array{
 *   users_30m: int,
 *   page_hits_30m: int,
 *   party_hits_30m: int,
 *   hub_hits_30m: int,
 *   nav_hits_30m: int,
 *   preview_hits_30m: int,
 *   external_hits_30m: int,
 *   notice_hits_30m: int,
 *   bot_hits_30m: int,
 *   window_start: string,
 *   window_end: string,
 *   per_minute: list<array{t: string, label: string, users: int, party: int, hub: int, nav: int, preview: int, external: int}>,
 *   top_events: list<array{id: int, name: string, slug: string, unique: int, page: int, preview: int, external: int}>,
 *   top_pages: list<array{key: string, label: string, count: int}>,
 *   top_nav: list<array{key: string, label: string, count: int}>,
 *   by_source: list<array{source: string, label: string, count: int}>,
 *   recent: list<array{
 *     at: string,
 *     kind: string,
 *     kind_label: string,
 *     target: string,
 *     href: string,
 *     detail: string,
 *     event_id: int,
 *     name: string,
 *     event_date: string,
 *     metric: string,
 *     metric_label: string,
 *     source: string,
 *     source_label: string,
 *     is_bot: bool
 *   }>
 * }
 */
function events_realtime_snapshot(PDO $db): array
{
    events_view_tracking_ensure_bot_column($db);
    events_realtime_ensure_indexes($db);
    events_public_traffic_ensure_schema($db);
    events_public_home_notice_stats_ensure_schema($db);

    $window = events_realtime_window();
    $start = $window['start'];
    $botReady = events_view_tracking_bot_column_ready($db);
    $tableReady = events_edit_stats_table_ready($db);
    $trafficReady = events_public_traffic_tables_ready($db);
    $noticeReady = events_public_home_notice_stats_tables_ready($db);

    $emptyMinute = static function (string $minute): array {
        return [
            't' => $minute,
            'label' => substr($minute, 11, 5),
            'users' => 0,
            'party' => 0,
            'hub' => 0,
            'nav' => 0,
            'preview' => 0,
            'external' => 0,
        ];
    };

    $empty = [
        'users_30m' => 0,
        'page_hits_30m' => 0,
        'party_hits_30m' => 0,
        'hub_hits_30m' => 0,
        'nav_hits_30m' => 0,
        'preview_hits_30m' => 0,
        'external_hits_30m' => 0,
        'notice_hits_30m' => 0,
        'bot_hits_30m' => 0,
        'window_start' => $start,
        'window_end' => $window['end'],
        'per_minute' => [],
        'top_events' => [],
        'top_pages' => [],
        'top_nav' => [],
        'by_source' => [],
        'recent' => [],
    ];

    foreach ($window['minutes'] as $minute) {
        $empty['per_minute'][] = $emptyMinute($minute);
    }

    try {
        $partyHits = events_realtime_count_hits($db, $start, EVENTS_VIEW_METRIC_PAGE, false, $botReady, $tableReady);
        $previewHits = $tableReady
            ? events_realtime_count_hits($db, $start, EVENTS_VIEW_METRIC_CALENDAR_PREVIEW, false, $botReady, $tableReady)
            : 0;
        $externalHits = $tableReady
            ? events_realtime_count_hits($db, $start, EVENTS_VIEW_METRIC_EXTERNAL_INFO, false, $botReady, $tableReady)
            : 0;
        $hubHits = $trafficReady
            ? events_realtime_count_traffic($db, $start, EVENTS_PUBLIC_TRAFFIC_PAGE_VIEW, false)
            : 0;
        $navHits = $trafficReady
            ? events_realtime_count_traffic($db, $start, EVENTS_PUBLIC_TRAFFIC_NAV_CLICK, false)
            : 0;
        $noticeHits = $noticeReady
            ? events_realtime_count_notice_clicks($db, $start, false)
            : 0;

        $users30 = events_realtime_count_unique_users_all($db, $start, $botReady, $tableReady, $trafficReady);
        $botHits = events_realtime_count_bot_hits_all($db, $start, $botReady, $tableReady, $trafficReady, $noticeReady);

        $perMinuteMap = [];
        foreach ($empty['per_minute'] as $row) {
            $perMinuteMap[$row['t']] = $row;
        }
        events_realtime_fill_per_minute($db, $start, $botReady, $tableReady, $trafficReady, $perMinuteMap);

        $perMinute = [];
        foreach ($window['minutes'] as $minute) {
            $perMinute[] = $perMinuteMap[$minute] ?? $emptyMinute($minute);
        }

        return [
            'users_30m' => $users30,
            // Legacy alias: buli oldalmegtekintések (régi UI / kliens kompatibilitás).
            'page_hits_30m' => $partyHits,
            'party_hits_30m' => $partyHits,
            'hub_hits_30m' => $hubHits,
            'nav_hits_30m' => $navHits,
            'preview_hits_30m' => $previewHits,
            'external_hits_30m' => $externalHits,
            'notice_hits_30m' => $noticeHits,
            'bot_hits_30m' => $botHits,
            'window_start' => $start,
            'window_end' => $window['end'],
            'per_minute' => $perMinute,
            'top_events' => events_realtime_top_events($db, $start, $botReady, $tableReady),
            'top_pages' => $trafficReady ? events_realtime_top_pages($db, $start) : [],
            'top_nav' => $trafficReady ? events_realtime_top_nav($db, $start) : [],
            'by_source' => events_realtime_by_source($db, $start, $botReady, $tableReady),
            'recent' => events_realtime_recent_all(
                $db,
                $start,
                $botReady,
                $tableReady,
                $trafficReady,
                $noticeReady
            ),
        ];
    } catch (Throwable $ex) {
        error_log('events_realtime_snapshot: ' . $ex->getMessage());

        return $empty;
    }
}

function events_realtime_count_unique_users(PDO $db, string $start, bool $botReady, bool $tableReady): int
{
    $metricAnd = $tableReady ? ' AND `metric_type` = ?' : '';
    $botAnd = $botReady ? ' AND `is_bot` = 0' : '';
    $sql = "SELECT COUNT(DISTINCT `ip_hash`)
            FROM `events_calendar_event_views`
            WHERE `létrehozva` >= ?
              AND `ip_hash` IS NOT NULL AND `ip_hash` <> ''
              {$botAnd}{$metricAnd}";
    $params = [$start];
    if ($tableReady) {
        $params[] = EVENTS_VIEW_METRIC_PAGE;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

/**
 * Egyedi emberi látogatók a buli- és a hub-/menü-forgalomból együtt.
 */
function events_realtime_count_unique_users_all(
    PDO $db,
    string $start,
    bool $botReady,
    bool $tableReady,
    bool $trafficReady
): int
{
    $parts = [];
    $params = [];

    $metricAnd = $tableReady ? ' AND `metric_type` = ?' : '';
    $botAnd = $botReady ? ' AND `is_bot` = 0' : '';
    $parts[] = "SELECT `ip_hash`
                FROM `events_calendar_event_views`
                WHERE `létrehozva` >= ?
                  AND `ip_hash` IS NOT NULL AND `ip_hash` <> ''
                  {$botAnd}{$metricAnd}";
    $params[] = $start;
    if ($tableReady) {
        $params[] = EVENTS_VIEW_METRIC_PAGE;
    }

    if ($trafficReady) {
        $parts[] = "SELECT `ip_hash`
                    FROM `events_public_traffic`
                    WHERE `occurred_at` >= ?
                      AND `is_bot` = 0
                      AND `event_type` = ?
                      AND `ip_hash` IS NOT NULL AND `ip_hash` <> ''";
        $params[] = $start;
        $params[] = EVENTS_PUBLIC_TRAFFIC_PAGE_VIEW;
    }

    if ($parts === []) {
        return 0;
    }

    $sql = 'SELECT COUNT(DISTINCT `ip_hash`) FROM (' . implode(' UNION ALL ', $parts) . ') u';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function events_realtime_count_hits(
    PDO $db,
    string $start,
    string $metricType,
    bool $botsOnly,
    bool $botReady,
    bool $tableReady
): int {
    $metricAnd = $tableReady ? ' AND `metric_type` = ?' : '';
    $botAnd = '';
    if ($botReady) {
        $botAnd = $botsOnly ? ' AND `is_bot` = 1' : ' AND `is_bot` = 0';
    } elseif ($botsOnly) {
        return 0;
    }

    $sql = "SELECT COUNT(*) FROM `events_calendar_event_views`
            WHERE `létrehozva` >= ?{$botAnd}{$metricAnd}";
    $params = [$start];
    if ($tableReady) {
        $params[] = $metricType;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function events_realtime_count_traffic(PDO $db, string $start, string $eventType, bool $botsOnly): int
{
    $sql = 'SELECT COUNT(*) FROM `events_public_traffic`
            WHERE `occurred_at` >= ? AND `event_type` = ? AND `is_bot` = ?';
    $stmt = $db->prepare($sql);
    $stmt->execute([$start, $eventType, $botsOnly ? 1 : 0]);

    return (int) $stmt->fetchColumn();
}

function events_realtime_count_notice_clicks(PDO $db, string $start, bool $botsOnly): int
{
    $sql = 'SELECT COUNT(*) FROM `events_public_home_notice_clicks`
            WHERE `clicked_at` >= ? AND `is_bot` = ?';
    $stmt = $db->prepare($sql);
    $stmt->execute([$start, $botsOnly ? 1 : 0]);

    return (int) $stmt->fetchColumn();
}

function events_realtime_count_bot_hits(PDO $db, string $start, bool $tableReady): int
{
    $metricAnd = $tableReady ? ' AND `metric_type` IN (?, ?, ?)' : '';
    $sql = "SELECT COUNT(*) FROM `events_calendar_event_views`
            WHERE `létrehozva` >= ? AND `is_bot` = 1{$metricAnd}";
    $params = [$start];
    if ($tableReady) {
        $params[] = EVENTS_VIEW_METRIC_PAGE;
        $params[] = EVENTS_VIEW_METRIC_CALENDAR_PREVIEW;
        $params[] = EVENTS_VIEW_METRIC_EXTERNAL_INFO;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function events_realtime_count_bot_hits_all(
    PDO $db,
    string $start,
    bool $botReady,
    bool $tableReady,
    bool $trafficReady,
    bool $noticeReady
): int {
    $total = 0;
    if ($botReady) {
        $total += events_realtime_count_bot_hits($db, $start, $tableReady);
    }
    if ($trafficReady) {
        $total += events_realtime_count_traffic($db, $start, EVENTS_PUBLIC_TRAFFIC_PAGE_VIEW, true);
        $total += events_realtime_count_traffic($db, $start, EVENTS_PUBLIC_TRAFFIC_NAV_CLICK, true);
    }
    if ($noticeReady) {
        $total += events_realtime_count_notice_clicks($db, $start, true);
    }

    return $total;
}

/**
 * @param array<string, array{t: string, label: string, users: int, party: int, hub: int, nav: int, preview: int, external: int}> $perMinuteMap
 */
function events_realtime_fill_per_minute(
    PDO $db,
    string $start,
    bool $botReady,
    bool $tableReady,
    bool $trafficReady,
    array &$perMinuteMap
): void {
    $botAnd = $botReady ? ' AND `is_bot` = 0' : '';
    $metricSelect = $tableReady ? '`metric_type`' : "'" . EVENTS_VIEW_METRIC_PAGE . "' AS `metric_type`";

    $sqlHits = "SELECT DATE_FORMAT(`létrehozva`, '%Y-%m-%d %H:%i:00') AS bucket,
                       {$metricSelect},
                       COUNT(*) AS cnt
                FROM `events_calendar_event_views`
                WHERE `létrehozva` >= ?{$botAnd}
                GROUP BY bucket" . ($tableReady ? ', `metric_type`' : '');
    $stmt = $db->prepare($sqlHits);
    $stmt->execute([$start]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $bucket = (string) ($row['bucket'] ?? '');
        if (!isset($perMinuteMap[$bucket])) {
            continue;
        }
        $metric = (string) ($row['metric_type'] ?? EVENTS_VIEW_METRIC_PAGE);
        $cnt = (int) ($row['cnt'] ?? 0);
        if ($metric === EVENTS_VIEW_METRIC_CALENDAR_PREVIEW) {
            $perMinuteMap[$bucket]['preview'] += $cnt;
        } elseif ($metric === EVENTS_VIEW_METRIC_EXTERNAL_INFO) {
            $perMinuteMap[$bucket]['external'] += $cnt;
        } elseif ($metric === EVENTS_VIEW_METRIC_PAGE) {
            $perMinuteMap[$bucket]['party'] += $cnt;
        }
    }

    if ($trafficReady) {
        $sqlTraffic = "SELECT DATE_FORMAT(`occurred_at`, '%Y-%m-%d %H:%i:00') AS bucket,
                              `event_type`,
                              COUNT(*) AS cnt
                       FROM `events_public_traffic`
                       WHERE `occurred_at` >= ? AND `is_bot` = 0
                       GROUP BY bucket, `event_type`";
        $stmt = $db->prepare($sqlTraffic);
        $stmt->execute([$start]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $bucket = (string) ($row['bucket'] ?? '');
            if (!isset($perMinuteMap[$bucket])) {
                continue;
            }
            $type = (string) ($row['event_type'] ?? '');
            $cnt = (int) ($row['cnt'] ?? 0);
            if ($type === EVENTS_PUBLIC_TRAFFIC_PAGE_VIEW) {
                $perMinuteMap[$bucket]['hub'] += $cnt;
            } elseif ($type === EVENTS_PUBLIC_TRAFFIC_NAV_CLICK) {
                $perMinuteMap[$bucket]['nav'] += $cnt;
            }
        }
    }

    // Unique users per minute (buli + hub oldal).
    $parts = [];
    $params = [];
    $metricAnd = $tableReady ? ' AND `metric_type` = ?' : '';
    $parts[] = "SELECT DATE_FORMAT(`létrehozva`, '%Y-%m-%d %H:%i:00') AS bucket, `ip_hash`
                FROM `events_calendar_event_views`
                WHERE `létrehozva` >= ?
                  AND `ip_hash` IS NOT NULL AND `ip_hash` <> ''
                  {$botAnd}{$metricAnd}";
    $params[] = $start;
    if ($tableReady) {
        $params[] = EVENTS_VIEW_METRIC_PAGE;
    }
    if ($trafficReady) {
        $parts[] = "SELECT DATE_FORMAT(`occurred_at`, '%Y-%m-%d %H:%i:00') AS bucket, `ip_hash`
                    FROM `events_public_traffic`
                    WHERE `occurred_at` >= ?
                      AND `is_bot` = 0
                      AND `event_type` = ?
                      AND `ip_hash` IS NOT NULL AND `ip_hash` <> ''";
        $params[] = $start;
        $params[] = EVENTS_PUBLIC_TRAFFIC_PAGE_VIEW;
    }

    $sqlUsers = 'SELECT bucket, COUNT(DISTINCT `ip_hash`) AS cnt
                 FROM (' . implode(' UNION ALL ', $parts) . ') x
                 GROUP BY bucket';
    $stmt = $db->prepare($sqlUsers);
    $stmt->execute($params);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $bucket = (string) ($row['bucket'] ?? '');
        if (!isset($perMinuteMap[$bucket])) {
            continue;
        }
        $perMinuteMap[$bucket]['users'] = (int) ($row['cnt'] ?? 0);
    }
}

/**
 * @return list<array{id: int, name: string, slug: string, unique: int, page: int, preview: int, external: int}>
 */
function events_realtime_top_events(PDO $db, string $start, bool $botReady, bool $tableReady): array
{
    $botAnd = $botReady ? ' AND v.`is_bot` = 0' : '';
    $pageMetricAnd = $tableReady ? ' AND v.`metric_type` = ?' : '';
    $previewMetricAnd = $tableReady ? ' AND v.`metric_type` = ?' : ' AND 1 = 0';
    $externalMetricAnd = $tableReady ? ' AND v.`metric_type` = ?' : ' AND 1 = 0';

    $sql = "
        SELECT e.`id`, e.`event_name`, e.`event_slug`,
            COALESCE(u.unique_cnt, 0) AS unique_cnt,
            COALESCE(p.page_cnt, 0) AS page_cnt,
            COALESCE(pr.preview_cnt, 0) AS preview_cnt,
            COALESCE(ex.external_cnt, 0) AS external_cnt
        FROM `events_calendar_events` e
        INNER JOIN (
            SELECT v.`esemény_id` AS event_id, COUNT(*) AS page_cnt
            FROM `events_calendar_event_views` v
            WHERE v.`létrehozva` >= ?{$botAnd}{$pageMetricAnd}
            GROUP BY v.`esemény_id`
        ) p ON p.event_id = e.`id`
        LEFT JOIN (
            SELECT v.`esemény_id` AS event_id, COUNT(DISTINCT v.`ip_hash`) AS unique_cnt
            FROM `events_calendar_event_views` v
            WHERE v.`létrehozva` >= ?
              AND v.`ip_hash` IS NOT NULL AND v.`ip_hash` <> ''
              {$botAnd}{$pageMetricAnd}
            GROUP BY v.`esemény_id`
        ) u ON u.event_id = e.`id`
        LEFT JOIN (
            SELECT v.`esemény_id` AS event_id, COUNT(*) AS preview_cnt
            FROM `events_calendar_event_views` v
            WHERE v.`létrehozva` >= ?{$botAnd}{$previewMetricAnd}
            GROUP BY v.`esemény_id`
        ) pr ON pr.event_id = e.`id`
        LEFT JOIN (
            SELECT v.`esemény_id` AS event_id, COUNT(*) AS external_cnt
            FROM `events_calendar_event_views` v
            WHERE v.`létrehozva` >= ?{$botAnd}{$externalMetricAnd}
            GROUP BY v.`esemény_id`
        ) ex ON ex.event_id = e.`id`
        ORDER BY page_cnt DESC, unique_cnt DESC, e.`id` DESC
        LIMIT " . (int) EVENTS_REALTIME_TOP_EVENTS;

    $params = [$start];
    if ($tableReady) {
        $params[] = EVENTS_VIEW_METRIC_PAGE;
    }
    $params[] = $start;
    if ($tableReady) {
        $params[] = EVENTS_VIEW_METRIC_PAGE;
    }
    $params[] = $start;
    if ($tableReady) {
        $params[] = EVENTS_VIEW_METRIC_CALENDAR_PREVIEW;
    }
    $params[] = $start;
    if ($tableReady) {
        $params[] = EVENTS_VIEW_METRIC_EXTERNAL_INFO;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['event_name'] ?? ''),
            'slug' => (string) ($row['event_slug'] ?? ''),
            'unique' => (int) ($row['unique_cnt'] ?? 0),
            'page' => (int) ($row['page_cnt'] ?? 0),
            'preview' => (int) ($row['preview_cnt'] ?? 0),
            'external' => (int) ($row['external_cnt'] ?? 0),
        ];
    }

    return $out;
}

/**
 * @return list<array{key: string, label: string, count: int}>
 */
function events_realtime_top_pages(PDO $db, string $start): array
{
    $sql = "SELECT `page_key`, COUNT(*) AS cnt
            FROM `events_public_traffic`
            WHERE `occurred_at` >= ?
              AND `is_bot` = 0
              AND `event_type` = ?
              AND `page_key` <> ''
            GROUP BY `page_key`
            ORDER BY cnt DESC
            LIMIT " . (int) EVENTS_REALTIME_TOP_PAGES;
    $stmt = $db->prepare($sql);
    $stmt->execute([$start, EVENTS_PUBLIC_TRAFFIC_PAGE_VIEW]);

    $out = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $key = (string) ($row['page_key'] ?? '');
        $out[] = [
            'key' => $key,
            'label' => events_public_traffic_page_label($key),
            'count' => (int) ($row['cnt'] ?? 0),
        ];
    }

    return $out;
}

/**
 * @return list<array{key: string, label: string, count: int}>
 */
function events_realtime_top_nav(PDO $db, string $start): array
{
    $sql = "SELECT `nav_key`, COUNT(*) AS cnt
            FROM `events_public_traffic`
            WHERE `occurred_at` >= ?
              AND `is_bot` = 0
              AND `event_type` = ?
              AND `nav_key` <> ''
            GROUP BY `nav_key`
            ORDER BY cnt DESC
            LIMIT " . (int) EVENTS_REALTIME_TOP_NAV;
    $stmt = $db->prepare($sql);
    $stmt->execute([$start, EVENTS_PUBLIC_TRAFFIC_NAV_CLICK]);

    $out = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $key = (string) ($row['nav_key'] ?? '');
        $out[] = [
            'key' => $key,
            'label' => events_public_traffic_nav_label($key, $db),
            'count' => (int) ($row['cnt'] ?? 0),
        ];
    }

    return $out;
}

/**
 * @return list<array{source: string, label: string, count: int}>
 */
function events_realtime_by_source(PDO $db, string $start, bool $botReady, bool $tableReady): array
{
    $botAnd = $botReady ? ' AND `is_bot` = 0' : '';
    $metricAnd = $tableReady ? ' AND `metric_type` = ?' : '';
    $sql = "SELECT COALESCE(NULLIF(TRIM(`source`), ''), 'direct') AS src, COUNT(*) AS cnt
            FROM `events_calendar_event_views`
            WHERE `létrehozva` >= ?{$botAnd}{$metricAnd}
            GROUP BY src
            ORDER BY cnt DESC";
    $params = [$start];
    if ($tableReady) {
        $params[] = EVENTS_VIEW_METRIC_PAGE;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $out = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $source = (string) ($row['src'] ?? EVENTS_VIEW_SOURCE_DIRECT);
        $out[] = [
            'source' => $source,
            'label' => events_realtime_source_label($source),
            'count' => (int) ($row['cnt'] ?? 0),
        ];
    }

    return $out;
}

/**
 * @return list<array{
 *   at: string,
 *   kind: string,
 *   kind_label: string,
 *   target: string,
 *   href: string,
 *   detail: string,
 *   event_id: int,
 *   name: string,
 *   event_date: string,
 *   metric: string,
 *   metric_label: string,
 *   source: string,
 *   source_label: string,
 *   is_bot: bool
 * }>
 */
function events_realtime_recent_all(
    PDO $db,
    string $start,
    bool $botReady,
    bool $tableReady,
    bool $trafficReady,
    bool $noticeReady
): array {
    if (!function_exists('events_admin_format_datum_cell')) {
        require_once __DIR__ . '/admin_event_filters.php';
    }

    $fetchLimit = (int) EVENTS_REALTIME_RECENT_LIMIT;
    $items = [];

    $botSelect = $botReady ? 'v.`is_bot`' : '0 AS `is_bot`';
    $metricSelect = $tableReady ? 'v.`metric_type`' : "'" . EVENTS_VIEW_METRIC_PAGE . "' AS `metric_type`";
    $sqlEvents = "SELECT v.`létrehozva` AS at_ts, v.`esemény_id` AS event_id,
                         e.`event_name`, e.`event_start`, e.`event_end`, e.`event_allday`,
                         {$metricSelect}, v.`source`, {$botSelect}
                  FROM `events_calendar_event_views` v
                  LEFT JOIN `events_calendar_events` e ON e.`id` = v.`esemény_id`
                  WHERE v.`létrehozva` >= ?
                  ORDER BY v.`létrehozva` DESC
                  LIMIT {$fetchLimit}";
    $stmt = $db->prepare($sqlEvents);
    $stmt->execute([$start]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $metric = (string) ($row['metric_type'] ?? EVENTS_VIEW_METRIC_PAGE);
        $source = trim((string) ($row['source'] ?? ''));
        if ($source === '') {
            $source = EVENTS_VIEW_SOURCE_DIRECT;
        }
        $eventId = (int) ($row['event_id'] ?? 0);
        $name = (string) ($row['event_name'] ?? ($eventId > 0 ? '#' . $eventId : 'Ismeretlen buli'));
        $kind = match ($metric) {
            EVENTS_VIEW_METRIC_CALENDAR_PREVIEW => 'preview',
            EVENTS_VIEW_METRIC_EXTERNAL_INFO => 'external',
            default => 'party',
        };
        $items[] = [
            'at' => (string) ($row['at_ts'] ?? ''),
            'kind' => $kind,
            'kind_label' => events_realtime_kind_label($kind),
            'target' => $name,
            'href' => $eventId > 0 ? 'event:' . $eventId : '',
            'detail' => events_realtime_source_label($source),
            'event_id' => $eventId,
            'name' => $name,
            'event_date' => events_admin_format_datum_cell($row),
            'metric' => $metric,
            'metric_label' => events_realtime_metric_label($metric),
            'source' => $source,
            'source_label' => events_realtime_source_label($source),
            'is_bot' => (int) ($row['is_bot'] ?? 0) === 1,
        ];
    }

    if ($trafficReady) {
        $sqlTraffic = "SELECT `occurred_at` AS at_ts, `event_type`, `page_key`, `nav_key`,
                              `entity_label`, `lang`, `is_bot`
                       FROM `events_public_traffic`
                       WHERE `occurred_at` >= ?
                       ORDER BY `occurred_at` DESC
                       LIMIT {$fetchLimit}";
        $stmt = $db->prepare($sqlTraffic);
        $stmt->execute([$start]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $type = (string) ($row['event_type'] ?? '');
            $pageKey = (string) ($row['page_key'] ?? '');
            $navKey = (string) ($row['nav_key'] ?? '');
            $lang = strtoupper((string) ($row['lang'] ?? 'hu'));
            $entityLabel = trim((string) ($row['entity_label'] ?? ''));

            if ($type === EVENTS_PUBLIC_TRAFFIC_NAV_CLICK) {
                $target = events_public_traffic_nav_label($navKey, $db);
                $detailParts = ['Menükattintás', $lang];
                if ($pageKey !== '') {
                    $detailParts[] = 'ról: ' . events_public_traffic_page_label($pageKey);
                }
                $items[] = [
                    'at' => (string) ($row['at_ts'] ?? ''),
                    'kind' => 'nav',
                    'kind_label' => events_realtime_kind_label('nav'),
                    'target' => $target,
                    'href' => '',
                    'detail' => implode(' · ', $detailParts),
                    'event_id' => 0,
                    'name' => $target,
                    'event_date' => '–',
                    'metric' => 'nav_click',
                    'metric_label' => events_realtime_metric_label('nav_click'),
                    'source' => $navKey,
                    'source_label' => $target,
                    'is_bot' => (int) ($row['is_bot'] ?? 0) === 1,
                ];
            } else {
                $target = events_public_traffic_page_label($pageKey);
                if ($entityLabel !== '') {
                    $target .= ' · ' . $entityLabel;
                }
                $items[] = [
                    'at' => (string) ($row['at_ts'] ?? ''),
                    'kind' => 'hub',
                    'kind_label' => events_realtime_kind_label('hub'),
                    'target' => $target,
                    'href' => '',
                    'detail' => 'Oldalmegtekintés · ' . $lang,
                    'event_id' => 0,
                    'name' => $target,
                    'event_date' => '–',
                    'metric' => 'hub_page',
                    'metric_label' => events_realtime_metric_label('hub_page'),
                    'source' => $pageKey,
                    'source_label' => events_public_traffic_page_label($pageKey),
                    'is_bot' => (int) ($row['is_bot'] ?? 0) === 1,
                ];
            }
        }
    }

    if ($noticeReady) {
        $sqlNotice = "SELECT `clicked_at` AS at_ts, `notice_text`, `notice_url`, `lang`, `is_bot`
                      FROM `events_public_home_notice_clicks`
                      WHERE `clicked_at` >= ?
                      ORDER BY `clicked_at` DESC
                      LIMIT {$fetchLimit}";
        $stmt = $db->prepare($sqlNotice);
        $stmt->execute([$start]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $text = trim((string) ($row['notice_text'] ?? ''));
            if ($text === '') {
                $text = 'Értesítő';
            }
            $text = events_public_home_notice_truncate($text, 64);
            $lang = strtoupper((string) ($row['lang'] ?? 'hu'));
            $items[] = [
                'at' => (string) ($row['at_ts'] ?? ''),
                'kind' => 'notice',
                'kind_label' => events_realtime_kind_label('notice'),
                'target' => $text,
                'href' => '',
                'detail' => 'Értesítő kattintás · ' . $lang,
                'event_id' => 0,
                'name' => $text,
                'event_date' => '–',
                'metric' => 'notice_click',
                'metric_label' => events_realtime_metric_label('notice_click'),
                'source' => 'notice',
                'source_label' => 'Értesítő',
                'is_bot' => (int) ($row['is_bot'] ?? 0) === 1,
            ];
        }
    }

    usort(
        $items,
        static function (array $a, array $b): int {
            return strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? ''));
        }
    );

    return array_slice($items, 0, $fetchLimit);
}

/**
 * @return list<array{at: string, event_id: int, name: string, event_date: string, metric: string, metric_label: string, source: string, source_label: string, is_bot: bool}>
 * @deprecated Use events_realtime_recent_all()
 */
function events_realtime_recent(PDO $db, string $start, bool $botReady, bool $tableReady): array
{
    return events_realtime_recent_all(
        $db,
        $start,
        $botReady,
        $tableReady,
        events_public_traffic_tables_ready($db),
        events_public_home_notice_stats_tables_ready($db)
    );
}
