<?php
declare(strict_types=1);

require_once __DIR__ . '/event_status.php';
require_once __DIR__ . '/event_view_tracking.php';
require_once __DIR__ . '/event_edit_stats.php';

/**
 * Éves / havi esemény-összehasonlító statisztika.
 */

/**
 * @return array{year:int,bucket_by:string,status:string,mode:string}
 */
function events_monthly_stats_params_from_request(array $query): array
{
    $year = filter_var($query['stat_year'] ?? null, FILTER_VALIDATE_INT);
    $currentYear = (int) (new DateTimeImmutable('today'))->format('Y');
    if ($year === false || $year < 2000 || $year > ($currentYear + 5)) {
        $year = $currentYear;
    }

    $bucketBy = strtolower(trim((string) ($query['stat_bucket'] ?? 'event_start')));
    if (!in_array($bucketBy, ['event_start', 'published_at'], true)) {
        $bucketBy = 'event_start';
    }

    $status = strtolower(trim((string) ($query['stat_status'] ?? 'public')));
    if (!in_array($status, ['public', 'publish', 'all'], true)) {
        $status = 'public';
    }

    return [
        'year' => (int) $year,
        'bucket_by' => $bucketBy,
        'status' => $status,
        'mode' => events_edit_stats_normalize_mode($query['stat_mode'] ?? 'smart'),
    ];
}

/**
 * Forgalmi aggregáció eseményenként: smart módban csak emberi + esemény záró napjáig.
 *
 * @return array{join:string,selects:string,bind:list<mixed>,smart:bool}
 */
function events_period_stats_views_join(PDO $db, string $mode): array
{
    $tableReady = events_edit_stats_table_ready($db);
    $emptySelects = '
        0 AS page_views_human,
        0 AS external_clicks_human,
        0 AS calendar_previews_human,
        0 AS engaged_events
    ';
    if (!$tableReady) {
        return [
            'join' => '',
            'selects' => $emptySelects,
            'bind' => [],
            'smart' => events_edit_stats_is_smart_mode(['mode' => $mode]),
        ];
    }

    $modeParams = ['mode' => events_edit_stats_normalize_mode($mode)];
    $isSmart = events_edit_stats_is_smart_mode($modeParams);
    $botReady = events_view_tracking_bot_column_ready($db);
    $humanFilter = ($isSmart && $botReady) ? 'AND COALESCE(v.`is_bot`, 0) = 0' : '';
    $smartJoin = events_edit_stats_smart_event_join_sql($modeParams, 'v', 'ev');
    $smartAnd = events_edit_stats_smart_cutoff_sql($modeParams, 'v', 'ev');

    $join = '
        LEFT JOIN (
            SELECT
                v.`esemény_id` AS event_id,
                SUM(CASE WHEN v.`metric_type` = ? ' . $humanFilter . ' THEN 1 ELSE 0 END) AS page_views_human,
                SUM(CASE WHEN v.`metric_type` = ? ' . $humanFilter . ' THEN 1 ELSE 0 END) AS external_clicks_human,
                SUM(CASE WHEN v.`metric_type` = ? ' . $humanFilter . ' THEN 1 ELSE 0 END) AS calendar_previews_human,
                SUM(
                    CASE
                        WHEN v.`metric_type` IN (?, ?, ?) ' . $humanFilter . '
                        THEN 1 ELSE 0
                    END
                ) AS total_hits_human
            FROM `events_calendar_event_views` v
            ' . $smartJoin . '
            WHERE 1 = 1
              ' . $smartAnd . '
            GROUP BY v.`esemény_id`
        ) views ON views.event_id = e.`id`
    ';

    return [
        'join' => $join,
        'selects' => '
            COALESCE(SUM(views.page_views_human), 0) AS page_views_human,
            COALESCE(SUM(views.external_clicks_human), 0) AS external_clicks_human,
            COALESCE(SUM(views.calendar_previews_human), 0) AS calendar_previews_human,
            SUM(CASE WHEN COALESCE(views.total_hits_human, 0) > 0 THEN 1 ELSE 0 END) AS engaged_events
        ',
        'bind' => [
            EVENTS_VIEW_METRIC_PAGE,
            EVENTS_VIEW_METRIC_EXTERNAL_INFO,
            EVENTS_VIEW_METRIC_CALENDAR_PREVIEW,
            EVENTS_VIEW_METRIC_PAGE,
            EVENTS_VIEW_METRIC_EXTERNAL_INFO,
            EVENTS_VIEW_METRIC_CALENDAR_PREVIEW,
        ],
        'smart' => $isSmart,
    ];
}

/**
 * @return list<int>
 */
function events_monthly_stats_available_years(PDO $db): array
{
    $years = [];
    try {
        $rows = $db->query('
            SELECT DISTINCT YEAR(`event_start`) AS y
            FROM `events_calendar_events`
            WHERE `event_start` IS NOT NULL
            UNION
            SELECT DISTINCT YEAR(`event_published_at`) AS y
            FROM `events_calendar_events`
            WHERE `event_published_at` IS NOT NULL
              AND `event_published_at` NOT IN (\'\', \'0000-00-00 00:00:00\')
            ORDER BY y DESC
        ')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rows as $raw) {
            $y = (int) $raw;
            if ($y >= 2000 && $y <= 2100) {
                $years[$y] = $y;
            }
        }
    } catch (Throwable $e) {
        error_log('events_monthly_stats_available_years: ' . $e->getMessage());
    }

    $current = (int) (new DateTimeImmutable('today'))->format('Y');
    if ($years === []) {
        return [$current];
    }
    if (!isset($years[$current])) {
        $years[$current] = $current;
    }
    rsort($years, SORT_NUMERIC);

    return array_values($years);
}

function events_monthly_stats_month_label(int $month): string
{
    return match ($month) {
        1 => 'jan.',
        2 => 'febr.',
        3 => 'márc.',
        4 => 'ápr.',
        5 => 'máj.',
        6 => 'jún.',
        7 => 'júl.',
        8 => 'aug.',
        9 => 'szept.',
        10 => 'okt.',
        11 => 'nov.',
        12 => 'dec.',
        default => (string) $month,
    };
}

function events_monthly_stats_month_label_full(int $month): string
{
    return match ($month) {
        1 => 'Január',
        2 => 'Február',
        3 => 'Március',
        4 => 'Április',
        5 => 'Május',
        6 => 'Június',
        7 => 'Július',
        8 => 'Augusztus',
        9 => 'Szeptember',
        10 => 'Október',
        11 => 'November',
        12 => 'December',
        default => (string) $month,
    };
}

/**
 * @return array{sql:string,bind:list<mixed>}
 */
function events_monthly_stats_status_clause(string $status, string $alias = 'e'): array
{
    if ($status === 'publish') {
        return [
            'sql' => $alias . '.`event_status` = ?',
            'bind' => [events_public_post_status()],
        ];
    }
    if ($status === 'all') {
        return [
            'sql' => $alias . '.`event_status` NOT IN (?, ?)',
            'bind' => ['trash', 'auto-draft'],
        ];
    }

    $public = events_publicly_visible_post_statuses();
    $placeholders = implode(', ', array_fill(0, count($public), '?'));

    return [
        'sql' => $alias . '.`event_status` IN (' . $placeholders . ')',
        'bind' => $public,
    ];
}

function events_monthly_stats_bucket_expr(string $bucketBy, string $alias = 'e'): string
{
    if ($bucketBy === 'published_at') {
        return 'DATE_FORMAT(' . $alias . '.`event_published_at`, \'%Y-%m-01\')';
    }

    return 'DATE_FORMAT(' . $alias . '.`event_start`, \'%Y-%m-01\')';
}

/**
 * @return array{sql:string,bind:list<mixed>}
 */
function events_monthly_stats_bucket_where(string $bucketBy, int $year, string $alias = 'e'): array
{
    if ($bucketBy === 'published_at') {
        return [
            'sql' => $alias . '.`event_published_at` IS NOT NULL'
                . ' AND ' . $alias . '.`event_published_at` NOT IN (\'\', \'0000-00-00 00:00:00\')'
                . ' AND YEAR(' . $alias . '.`event_published_at`) = ?',
            'bind' => [$year],
        ];
    }

    return [
        'sql' => $alias . '.`event_start` IS NOT NULL AND YEAR(' . $alias . '.`event_start`) = ?',
        'bind' => [$year],
    ];
}

/**
 * @return array<string, array<string, int|float|null>>
 */
function events_monthly_stats_fetch_year_rows(
    PDO $db,
    int $year,
    string $bucketBy,
    string $status,
    string $mode = 'smart'
): array {
    $statusClause = events_monthly_stats_status_clause($status);
    $bucketWhere = events_monthly_stats_bucket_where($bucketBy, $year);
    $bucketExpr = events_monthly_stats_bucket_expr($bucketBy);
    $views = events_period_stats_views_join($db, $mode);

    $sql = '
        SELECT
            ' . $bucketExpr . ' AS month_key,
            COUNT(*) AS events_count,
            SUM(CASE WHEN e.`event_status` = ? THEN 1 ELSE 0 END) AS published_count,
            SUM(CASE WHEN e.`event_status` = ? THEN 1 ELSE 0 END) AS preliminary_count,
            SUM(CASE WHEN e.`venue_id` IS NOT NULL AND e.`venue_id` > 0 THEN 1 ELSE 0 END) AS with_venue,
            SUM(CASE WHEN e.`event_url` IS NOT NULL AND TRIM(e.`event_url`) <> \'\' THEN 1 ELSE 0 END) AS with_url,
            SUM(
                CASE
                    WHEN e.`event_featured_image_url` IS NOT NULL
                         AND TRIM(e.`event_featured_image_url`) <> \'\'
                    THEN 1 ELSE 0
                END
            ) AS with_image,
            AVG(
                CASE
                    WHEN e.`event_published_at` IS NOT NULL
                         AND e.`event_published_at` NOT IN (\'\', \'0000-00-00 00:00:00\')
                         AND e.`event_start` IS NOT NULL
                         AND DATE(e.`event_published_at`) <= DATE(e.`event_start`)
                    THEN DATEDIFF(DATE(e.`event_start`), DATE(e.`event_published_at`))
                    ELSE NULL
                END
            ) AS avg_lead_days,
            SUM(
                CASE
                    WHEN e.`event_published_at` IS NOT NULL
                         AND e.`event_published_at` NOT IN (\'\', \'0000-00-00 00:00:00\')
                         AND e.`event_start` IS NOT NULL
                         AND DATE(e.`event_published_at`) <= DATE(e.`event_start`)
                    THEN DATEDIFF(DATE(e.`event_start`), DATE(e.`event_published_at`))
                    ELSE 0
                END
            ) AS lead_days_sum,
            SUM(
                CASE
                    WHEN e.`event_published_at` IS NOT NULL
                         AND e.`event_published_at` NOT IN (\'\', \'0000-00-00 00:00:00\')
                         AND e.`event_start` IS NOT NULL
                         AND DATE(e.`event_published_at`) <= DATE(e.`event_start`)
                    THEN 1
                    ELSE 0
                END
            ) AS lead_days_count,
            ' . $views['selects'] . '
        FROM `events_calendar_events` e
        ' . $views['join'] . '
        WHERE ' . $bucketWhere['sql'] . '
          AND ' . $statusClause['sql'] . '
        GROUP BY month_key
        ORDER BY month_key ASC
    ';

    // Positional `?` order follows appearance in the SQL string.
    $bind = [
        events_public_post_status(),
        events_preliminary_post_status(),
    ];
    foreach ($views['bind'] as $v) {
        $bind[] = $v;
    }
    foreach ($bucketWhere['bind'] as $v) {
        $bind[] = $v;
    }
    foreach ($statusClause['bind'] as $v) {
        $bind[] = $v;
    }

    $byMonth = [];
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($bind);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $key = substr((string) ($row['month_key'] ?? ''), 0, 10);
            if ($key === '' || !preg_match('/^\d{4}-\d{2}-01$/', $key)) {
                continue;
            }
            $byMonth[$key] = [
                'events_count' => (int) ($row['events_count'] ?? 0),
                'published_count' => (int) ($row['published_count'] ?? 0),
                'preliminary_count' => (int) ($row['preliminary_count'] ?? 0),
                'with_venue' => (int) ($row['with_venue'] ?? 0),
                'with_url' => (int) ($row['with_url'] ?? 0),
                'with_image' => (int) ($row['with_image'] ?? 0),
                'avg_lead_days' => $row['avg_lead_days'] !== null ? round((float) $row['avg_lead_days'], 1) : null,
                'lead_days_sum' => (float) ($row['lead_days_sum'] ?? 0),
                'lead_days_count' => (int) ($row['lead_days_count'] ?? 0),
                'page_views_human' => (int) ($row['page_views_human'] ?? 0),
                'external_clicks_human' => (int) ($row['external_clicks_human'] ?? 0),
                'calendar_previews_human' => (int) ($row['calendar_previews_human'] ?? 0),
                'engaged_events' => (int) ($row['engaged_events'] ?? 0),
                'unique_organizers' => 0,
            ];
        }
    } catch (Throwable $e) {
        error_log('events_monthly_stats_fetch_year_rows: ' . $e->getMessage());
    }

    try {
        $orgSql = '
            SELECT
                ' . $bucketExpr . ' AS month_key,
                COUNT(DISTINCT eo.`organizer_id`) AS unique_organizers
            FROM `events_calendar_events` e
            INNER JOIN `events_calendar_event_organizers` eo ON eo.`event_id` = e.`id`
            WHERE ' . $bucketWhere['sql'] . '
              AND ' . $statusClause['sql'] . '
            GROUP BY month_key
        ';
        $orgBind = array_merge($bucketWhere['bind'], $statusClause['bind']);
        $stmt = $db->prepare($orgSql);
        $stmt->execute($orgBind);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $key = substr((string) ($row['month_key'] ?? ''), 0, 10);
            if (isset($byMonth[$key])) {
                $byMonth[$key]['unique_organizers'] = (int) ($row['unique_organizers'] ?? 0);
            }
        }
    } catch (Throwable $e) {
        error_log('events_monthly_stats organizers: ' . $e->getMessage());
    }

    return $byMonth;
}

/**
 * @param array<string, int|float|null> $row
 * @return array<string, int|float|null>
 */
function events_monthly_stats_enrich_row(array $row): array
{
    $events = (int) ($row['events_count'] ?? 0);
    $pageViews = (int) ($row['page_views_human'] ?? 0);
    $external = (int) ($row['external_clicks_human'] ?? 0);
    $previews = (int) ($row['calendar_previews_human'] ?? 0);
    $clicks = $pageViews + $external;
    $engaged = (int) ($row['engaged_events'] ?? 0);

    $row['total_clicks_human'] = $clicks;
    $row['clicks_per_event'] = $events > 0 ? round($clicks / $events, 1) : null;
    $row['page_views_per_event'] = $events > 0 ? round($pageViews / $events, 1) : null;
    $row['external_per_event'] = $events > 0 ? round($external / $events, 2) : null;
    $row['external_ctr_pct'] = $pageViews > 0 ? round(100 * $external / $pageViews, 1) : null;
    $row['engaged_pct'] = $events > 0 ? round(100 * $engaged / $events, 1) : null;
    $row['venue_pct'] = $events > 0 ? round(100 * (int) ($row['with_venue'] ?? 0) / $events, 1) : null;
    $row['url_pct'] = $events > 0 ? round(100 * (int) ($row['with_url'] ?? 0) / $events, 1) : null;
    $row['image_pct'] = $events > 0 ? round(100 * (int) ($row['with_image'] ?? 0) / $events, 1) : null;
    $row['calendar_previews_human'] = $previews;

    return $row;
}

function events_monthly_stats_pct_change(?float $current, ?float $previous): ?float
{
    if ($previous === null || $current === null) {
        return null;
    }
    if ((float) $previous === 0.0 && (float) $current === 0.0) {
        return null;
    }
    if ((float) $previous === 0.0) {
        return $current > 0 ? 100.0 : -100.0;
    }

    return round(100 * (($current - $previous) / $previous), 1);
}

/**
 * @param array{year:int,bucket_by:string,status:string,mode?:string} $params
 * @return array<string, mixed>
 */
function events_monthly_stats(PDO $db, array $params): array
{
    events_view_tracking_ensure_bot_column($db);

    $year = (int) $params['year'];
    $bucketBy = (string) $params['bucket_by'];
    $status = (string) $params['status'];
    $mode = events_edit_stats_normalize_mode($params['mode'] ?? 'smart');
    $prevYear = $year - 1;

    $currentRaw = events_monthly_stats_fetch_year_rows($db, $year, $bucketBy, $status, $mode);
    $prevRaw = events_monthly_stats_fetch_year_rows($db, $prevYear, $bucketBy, $status, $mode);

    $months = [];
    $totals = [
        'events_count' => 0,
        'published_count' => 0,
        'preliminary_count' => 0,
        'page_views_human' => 0,
        'external_clicks_human' => 0,
        'calendar_previews_human' => 0,
        'total_clicks_human' => 0,
        'engaged_events' => 0,
        'with_venue' => 0,
        'with_url' => 0,
        'with_image' => 0,
        'lead_days_sum' => 0.0,
        'lead_days_count' => 0,
    ];
    $prevTotals = [
        'events_count' => 0,
        'page_views_human' => 0,
        'external_clicks_human' => 0,
        'total_clicks_human' => 0,
    ];

    for ($m = 1; $m <= 12; $m++) {
        $key = sprintf('%04d-%02d-01', $year, $m);
        $prevKey = sprintf('%04d-%02d-01', $prevYear, $m);
        $empty = [
            'events_count' => 0,
            'published_count' => 0,
            'preliminary_count' => 0,
            'with_venue' => 0,
            'with_url' => 0,
            'with_image' => 0,
            'avg_lead_days' => null,
            'lead_days_sum' => 0.0,
            'lead_days_count' => 0,
            'page_views_human' => 0,
            'external_clicks_human' => 0,
            'calendar_previews_human' => 0,
            'engaged_events' => 0,
            'unique_organizers' => 0,
        ];
        $row = events_monthly_stats_enrich_row($currentRaw[$key] ?? $empty);
        $prevRow = events_monthly_stats_enrich_row($prevRaw[$prevKey] ?? $empty);

        $row['month'] = $m;
        $row['month_key'] = $key;
        $row['label'] = events_monthly_stats_month_label($m);
        $row['label_full'] = events_monthly_stats_month_label_full($m);
        $row['prev_year'] = [
            'events_count' => (int) ($prevRow['events_count'] ?? 0),
            'total_clicks_human' => (int) ($prevRow['total_clicks_human'] ?? 0),
            'page_views_human' => (int) ($prevRow['page_views_human'] ?? 0),
            'external_clicks_human' => (int) ($prevRow['external_clicks_human'] ?? 0),
            'clicks_per_event' => $prevRow['clicks_per_event'] ?? null,
            'avg_lead_days' => $prevRow['avg_lead_days'] ?? null,
        ];
        $row['yoy_events_pct'] = events_monthly_stats_pct_change(
            (float) ($row['events_count'] ?? 0),
            (float) ($prevRow['events_count'] ?? 0)
        );
        $row['yoy_clicks_pct'] = events_monthly_stats_pct_change(
            (float) ($row['total_clicks_human'] ?? 0),
            (float) ($prevRow['total_clicks_human'] ?? 0)
        );
        $row['yoy_clicks_per_event_pct'] = events_monthly_stats_pct_change(
            isset($row['clicks_per_event']) ? (float) $row['clicks_per_event'] : null,
            isset($prevRow['clicks_per_event']) ? (float) $prevRow['clicks_per_event'] : null
        );

        $months[] = $row;

        $totals['events_count'] += (int) $row['events_count'];
        $totals['published_count'] += (int) $row['published_count'];
        $totals['preliminary_count'] += (int) $row['preliminary_count'];
        $totals['page_views_human'] += (int) $row['page_views_human'];
        $totals['external_clicks_human'] += (int) $row['external_clicks_human'];
        $totals['calendar_previews_human'] += (int) $row['calendar_previews_human'];
        $totals['total_clicks_human'] += (int) $row['total_clicks_human'];
        $totals['engaged_events'] += (int) $row['engaged_events'];
        $totals['with_venue'] += (int) $row['with_venue'];
        $totals['with_url'] += (int) $row['with_url'];
        $totals['with_image'] += (int) $row['with_image'];
        $totals['lead_days_sum'] += (float) ($row['lead_days_sum'] ?? 0);
        $totals['lead_days_count'] += (int) ($row['lead_days_count'] ?? 0);

        $prevTotals['events_count'] += (int) ($prevRow['events_count'] ?? 0);
        $prevTotals['page_views_human'] += (int) ($prevRow['page_views_human'] ?? 0);
        $prevTotals['external_clicks_human'] += (int) ($prevRow['external_clicks_human'] ?? 0);
        $prevTotals['total_clicks_human'] += (int) ($prevRow['total_clicks_human'] ?? 0);
    }

    // MoM: januárnál az előző év decemberéhez viszonyítunk
    $decPrevKey = sprintf('%04d-12-01', $prevYear);
    $decPrev = events_monthly_stats_enrich_row($prevRaw[$decPrevKey] ?? [
        'events_count' => 0,
        'published_count' => 0,
        'preliminary_count' => 0,
        'with_venue' => 0,
        'with_url' => 0,
        'with_image' => 0,
        'avg_lead_days' => null,
        'lead_days_sum' => 0.0,
        'lead_days_count' => 0,
        'page_views_human' => 0,
        'external_clicks_human' => 0,
        'calendar_previews_human' => 0,
        'engaged_events' => 0,
        'unique_organizers' => 0,
    ]);

    for ($i = 0; $i < 12; $i++) {
        $prev = $i > 0 ? $months[$i - 1] : $decPrev;
        $months[$i]['mom_events_pct'] = events_monthly_stats_pct_change(
            (float) $months[$i]['events_count'],
            (float) ($prev['events_count'] ?? 0)
        );
        $months[$i]['mom_clicks_pct'] = events_monthly_stats_pct_change(
            (float) $months[$i]['total_clicks_human'],
            (float) ($prev['total_clicks_human'] ?? 0)
        );
        $months[$i]['mom_clicks_per_event_pct'] = events_monthly_stats_pct_change(
            isset($months[$i]['clicks_per_event']) ? (float) $months[$i]['clicks_per_event'] : null,
            isset($prev['clicks_per_event']) ? (float) $prev['clicks_per_event'] : null
        );
        $months[$i]['mom_lead_days_pct'] = events_monthly_stats_pct_change(
            isset($months[$i]['avg_lead_days']) ? (float) $months[$i]['avg_lead_days'] : null,
            isset($prev['avg_lead_days']) ? (float) $prev['avg_lead_days'] : null
        );
    }

    $events = (int) $totals['events_count'];
    $clicks = (int) $totals['total_clicks_human'];
    $pageViews = (int) $totals['page_views_human'];
    $external = (int) $totals['external_clicks_human'];
    $summary = [
        'events_count' => $events,
        'published_count' => (int) $totals['published_count'],
        'preliminary_count' => (int) $totals['preliminary_count'],
        'page_views_human' => $pageViews,
        'external_clicks_human' => $external,
        'calendar_previews_human' => (int) $totals['calendar_previews_human'],
        'total_clicks_human' => $clicks,
        'clicks_per_event' => $events > 0 ? round($clicks / $events, 1) : null,
        'page_views_per_event' => $events > 0 ? round($pageViews / $events, 1) : null,
        'external_per_event' => $events > 0 ? round($external / $events, 2) : null,
        'external_ctr_pct' => $pageViews > 0 ? round(100 * $external / $pageViews, 1) : null,
        'engaged_pct' => $events > 0
            ? round(100 * (int) $totals['engaged_events'] / $events, 1)
            : null,
        'avg_lead_days' => $totals['lead_days_count'] > 0
            ? round($totals['lead_days_sum'] / $totals['lead_days_count'], 1)
            : null,
        'venue_pct' => $events > 0 ? round(100 * (int) $totals['with_venue'] / $events, 1) : null,
        'url_pct' => $events > 0 ? round(100 * (int) $totals['with_url'] / $events, 1) : null,
        'image_pct' => $events > 0 ? round(100 * (int) $totals['with_image'] / $events, 1) : null,
        'yoy_events_pct' => events_monthly_stats_pct_change(
            (float) $events,
            (float) $prevTotals['events_count']
        ),
        'yoy_clicks_pct' => events_monthly_stats_pct_change(
            (float) $clicks,
            (float) $prevTotals['total_clicks_human']
        ),
        'prev_year_events' => (int) $prevTotals['events_count'],
        'prev_year_clicks' => (int) $prevTotals['total_clicks_human'],
    ];

    $labels = array_map(static fn (array $r): string => (string) $r['label'], $months);
    $chart = [
        'labels' => $labels,
        'events' => array_map(static fn (array $r): int => (int) $r['events_count'], $months),
        'prev_events' => array_map(static fn (array $r): int => (int) $r['prev_year']['events_count'], $months),
        'page_views' => array_map(static fn (array $r): int => (int) $r['page_views_human'], $months),
        'external_clicks' => array_map(static fn (array $r): int => (int) $r['external_clicks_human'], $months),
        'clicks_per_event' => array_map(
            static fn (array $r): ?float => isset($r['clicks_per_event']) ? (float) $r['clicks_per_event'] : null,
            $months
        ),
        'avg_lead_days' => array_map(
            static fn (array $r): ?float => isset($r['avg_lead_days']) ? (float) $r['avg_lead_days'] : null,
            $months
        ),
        'external_ctr_pct' => array_map(
            static fn (array $r): ?float => isset($r['external_ctr_pct']) ? (float) $r['external_ctr_pct'] : null,
            $months
        ),
    ];

    // Peak months for quick insights
    $peakEvents = null;
    $peakCpe = null;
    $peakLead = null;
    foreach ($months as $row) {
        if ((int) $row['events_count'] <= 0) {
            continue;
        }
        if ($peakEvents === null || (int) $row['events_count'] > (int) $peakEvents['events_count']) {
            $peakEvents = $row;
        }
        if (
            $row['clicks_per_event'] !== null
            && ($peakCpe === null || (float) $row['clicks_per_event'] > (float) $peakCpe['clicks_per_event'])
        ) {
            $peakCpe = $row;
        }
        if (
            $row['avg_lead_days'] !== null
            && ($peakLead === null || (float) $row['avg_lead_days'] > (float) $peakLead['avg_lead_days'])
        ) {
            $peakLead = $row;
        }
    }

    return [
        'year' => $year,
        'prev_year' => $prevYear,
        'bucket_by' => $bucketBy,
        'status' => $status,
        'mode' => $mode,
        'table_ready' => events_edit_stats_table_ready($db),
        'bot_ready' => events_view_tracking_bot_column_ready($db),
        'months' => $months,
        'summary' => $summary,
        'chart' => $chart,
        'insights' => [
            'peak_events' => $peakEvents,
            'peak_clicks_per_event' => $peakCpe,
            'peak_lead_days' => $peakLead,
        ],
        'available_years' => events_monthly_stats_available_years($db),
    ];
}
