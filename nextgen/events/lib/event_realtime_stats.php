<?php
declare(strict_types=1);

require_once __DIR__ . '/event_view_tracking.php';
require_once __DIR__ . '/event_edit_stats.php';

const EVENTS_REALTIME_WINDOW_MINUTES = 30;
const EVENTS_REALTIME_TOP_EVENTS = 10;
const EVENTS_REALTIME_RECENT_LIMIT = 20;

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
        default => $metric !== '' ? $metric : '—',
    };
}

/**
 * @return array{
 *   users_30m: int,
 *   page_hits_30m: int,
 *   preview_hits_30m: int,
 *   bot_hits_30m: int,
 *   window_start: string,
 *   window_end: string,
 *   per_minute: list<array{t: string, label: string, users: int, page: int, preview: int}>,
 *   top_events: list<array{id: int, name: string, slug: string, unique: int, page: int, preview: int}>,
 *   by_source: list<array{source: string, label: string, count: int}>,
 *   recent: list<array{at: string, event_id: int, name: string, event_date: string, metric: string, metric_label: string, source: string, source_label: string, is_bot: bool}>
 * }
 */
function events_realtime_snapshot(PDO $db): array
{
    events_view_tracking_ensure_bot_column($db);
    events_realtime_ensure_indexes($db);

    $window = events_realtime_window();
    $start = $window['start'];
    $botReady = events_view_tracking_bot_column_ready($db);
    $tableReady = events_edit_stats_table_ready($db);

    $empty = [
        'users_30m' => 0,
        'page_hits_30m' => 0,
        'preview_hits_30m' => 0,
        'bot_hits_30m' => 0,
        'window_start' => $start,
        'window_end' => $window['end'],
        'per_minute' => [],
        'top_events' => [],
        'by_source' => [],
        'recent' => [],
    ];

    foreach ($window['minutes'] as $minute) {
        $empty['per_minute'][] = [
            't' => $minute,
            'label' => substr($minute, 11, 5),
            'users' => 0,
            'page' => 0,
            'preview' => 0,
        ];
    }

    try {
        $users30 = events_realtime_count_unique_users($db, $start, $botReady, $tableReady);
        $pageHits = events_realtime_count_hits($db, $start, EVENTS_VIEW_METRIC_PAGE, false, $botReady, $tableReady);
        $previewHits = $tableReady
            ? events_realtime_count_hits($db, $start, EVENTS_VIEW_METRIC_CALENDAR_PREVIEW, false, $botReady, $tableReady)
            : 0;
        $botHits = $botReady ? events_realtime_count_bot_hits($db, $start, $tableReady) : 0;

        $perMinuteMap = [];
        foreach ($empty['per_minute'] as $row) {
            $perMinuteMap[$row['t']] = $row;
        }
        events_realtime_fill_per_minute($db, $start, $botReady, $tableReady, $perMinuteMap);

        $perMinute = [];
        foreach ($window['minutes'] as $minute) {
            $perMinute[] = $perMinuteMap[$minute] ?? [
                't' => $minute,
                'label' => substr($minute, 11, 5),
                'users' => 0,
                'page' => 0,
                'preview' => 0,
            ];
        }

        return [
            'users_30m' => $users30,
            'page_hits_30m' => $pageHits,
            'preview_hits_30m' => $previewHits,
            'bot_hits_30m' => $botHits,
            'window_start' => $start,
            'window_end' => $window['end'],
            'per_minute' => $perMinute,
            'top_events' => events_realtime_top_events($db, $start, $botReady, $tableReady),
            'by_source' => events_realtime_by_source($db, $start, $botReady, $tableReady),
            'recent' => events_realtime_recent($db, $start, $botReady, $tableReady),
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

function events_realtime_count_bot_hits(PDO $db, string $start, bool $tableReady): int
{
    $metricAnd = $tableReady ? ' AND `metric_type` IN (?, ?)' : '';
    $sql = "SELECT COUNT(*) FROM `events_calendar_event_views`
            WHERE `létrehozva` >= ? AND `is_bot` = 1{$metricAnd}";
    $params = [$start];
    if ($tableReady) {
        $params[] = EVENTS_VIEW_METRIC_PAGE;
        $params[] = EVENTS_VIEW_METRIC_CALENDAR_PREVIEW;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

/**
 * @param array<string, array{t: string, label: string, users: int, page: int, preview: int}> $perMinuteMap
 */
function events_realtime_fill_per_minute(
    PDO $db,
    string $start,
    bool $botReady,
    bool $tableReady,
    array &$perMinuteMap
): void {
    $botAnd = $botReady ? ' AND `is_bot` = 0' : '';
    $metricSelect = $tableReady ? '`metric_type`' : "'" . EVENTS_VIEW_METRIC_PAGE . "' AS `metric_type`";

    // Hits per minute (page / preview).
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
        } else {
            $perMinuteMap[$bucket]['page'] += $cnt;
        }
    }

    // Unique users per minute (page views only).
    $metricAnd = $tableReady ? ' AND `metric_type` = ?' : '';
    $sqlUsers = "SELECT DATE_FORMAT(`létrehozva`, '%Y-%m-%d %H:%i:00') AS bucket,
                        COUNT(DISTINCT `ip_hash`) AS cnt
                 FROM `events_calendar_event_views`
                 WHERE `létrehozva` >= ?
                   AND `ip_hash` IS NOT NULL AND `ip_hash` <> ''
                   {$botAnd}{$metricAnd}
                 GROUP BY bucket";
    $params = [$start];
    if ($tableReady) {
        $params[] = EVENTS_VIEW_METRIC_PAGE;
    }
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
 * @return list<array{id: int, name: string, slug: string, unique: int, page: int, preview: int}>
 */
function events_realtime_top_events(PDO $db, string $start, bool $botReady, bool $tableReady): array
{
    $botAnd = $botReady ? ' AND v.`is_bot` = 0' : '';
    $pageMetricAnd = $tableReady ? ' AND v.`metric_type` = ?' : '';
    $previewMetricAnd = $tableReady ? ' AND v.`metric_type` = ?' : ' AND 1 = 0';

    $sql = "
        SELECT e.`id`, e.`event_name`, e.`event_slug`,
            COALESCE(u.unique_cnt, 0) AS unique_cnt,
            COALESCE(p.page_cnt, 0) AS page_cnt,
            COALESCE(pr.preview_cnt, 0) AS preview_cnt
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
 * @return list<array{at: string, event_id: int, name: string, event_date: string, metric: string, metric_label: string, source: string, source_label: string, is_bot: bool}>
 */
function events_realtime_recent(PDO $db, string $start, bool $botReady, bool $tableReady): array
{
    if (!function_exists('events_admin_format_datum_cell')) {
        require_once __DIR__ . '/admin_event_filters.php';
    }

    $botSelect = $botReady ? 'v.`is_bot`' : '0 AS `is_bot`';
    $metricSelect = $tableReady ? 'v.`metric_type`' : "'" . EVENTS_VIEW_METRIC_PAGE . "' AS `metric_type`";

    $sql = "SELECT v.`létrehozva` AS at_ts, v.`esemény_id` AS event_id,
                   e.`event_name`, e.`event_start`, e.`event_end`, e.`event_allday`,
                   {$metricSelect}, v.`source`, {$botSelect}
            FROM `events_calendar_event_views` v
            LEFT JOIN `events_calendar_events` e ON e.`id` = v.`esemény_id`
            WHERE v.`létrehozva` >= ?
            ORDER BY v.`létrehozva` DESC
            LIMIT " . (int) EVENTS_REALTIME_RECENT_LIMIT;
    $stmt = $db->prepare($sql);
    $stmt->execute([$start]);

    $out = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $metric = (string) ($row['metric_type'] ?? EVENTS_VIEW_METRIC_PAGE);
        $source = trim((string) ($row['source'] ?? ''));
        if ($source === '') {
            $source = EVENTS_VIEW_SOURCE_DIRECT;
        }
        $out[] = [
            'at' => (string) ($row['at_ts'] ?? ''),
            'event_id' => (int) ($row['event_id'] ?? 0),
            'name' => (string) ($row['event_name'] ?? ('#' . (int) ($row['event_id'] ?? 0))),
            'event_date' => events_admin_format_datum_cell($row),
            'metric' => $metric,
            'metric_label' => events_realtime_metric_label($metric),
            'source' => $source,
            'source_label' => events_realtime_source_label($source),
            'is_bot' => (int) ($row['is_bot'] ?? 0) === 1,
        ];
    }

    return $out;
}
