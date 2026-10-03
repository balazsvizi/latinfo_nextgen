<?php
declare(strict_types=1);

require_once __DIR__ . '/event_monthly_stats.php';

/**
 * Év/év esemény-összehasonlító statisztika.
 */

/**
 * @return array{year_from:int,year_to:int,bucket_by:string,status:string,mode:string}
 */
function events_yearly_stats_params_from_request(array $query, array $availableYears): array
{
    $currentYear = (int) (new DateTimeImmutable('today'))->format('Y');
    $years = $availableYears !== [] ? $availableYears : [$currentYear];
    $minY = min($years);
    $maxY = max($years);

    $yearFrom = filter_var($query['stat_year_from'] ?? null, FILTER_VALIDATE_INT);
    $yearTo = filter_var($query['stat_year_to'] ?? null, FILTER_VALIDATE_INT);
    if ($yearFrom === false) {
        $yearFrom = max($minY, $currentYear - 4);
    }
    if ($yearTo === false) {
        $yearTo = $maxY;
    }
    $yearFrom = max(2000, min((int) $yearFrom, $currentYear + 5));
    $yearTo = max(2000, min((int) $yearTo, $currentYear + 5));
    if ($yearFrom > $yearTo) {
        [$yearFrom, $yearTo] = [$yearTo, $yearFrom];
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
        'year_from' => $yearFrom,
        'year_to' => $yearTo,
        'bucket_by' => $bucketBy,
        'status' => $status,
        'mode' => events_edit_stats_normalize_mode($query['stat_mode'] ?? 'smart'),
    ];
}

function events_yearly_stats_year_expr(string $bucketBy, string $alias = 'e'): string
{
    if ($bucketBy === 'published_at') {
        return 'YEAR(' . $alias . '.`event_published_at`)';
    }

    return 'YEAR(' . $alias . '.`event_start`)';
}

/**
 * @return array{sql:string,bind:list<mixed>}
 */
function events_yearly_stats_range_where(string $bucketBy, int $yearFrom, int $yearTo, string $alias = 'e'): array
{
    if ($bucketBy === 'published_at') {
        return [
            'sql' => $alias . '.`event_published_at` IS NOT NULL'
                . ' AND ' . $alias . '.`event_published_at` NOT IN (\'\', \'0000-00-00 00:00:00\')'
                . ' AND YEAR(' . $alias . '.`event_published_at`) BETWEEN ? AND ?',
            'bind' => [$yearFrom, $yearTo],
        ];
    }

    return [
        'sql' => $alias . '.`event_start` IS NOT NULL'
            . ' AND YEAR(' . $alias . '.`event_start`) BETWEEN ? AND ?',
        'bind' => [$yearFrom, $yearTo],
    ];
}

/**
 * @return array<int, array<string, int|float|null>>
 */
function events_yearly_stats_fetch_rows(
    PDO $db,
    int $yearFrom,
    int $yearTo,
    string $bucketBy,
    string $status,
    string $mode = 'smart'
): array {
    $statusClause = events_monthly_stats_status_clause($status);
    $rangeWhere = events_yearly_stats_range_where($bucketBy, $yearFrom, $yearTo);
    $yearExpr = events_yearly_stats_year_expr($bucketBy);
    $views = events_period_stats_views_join($db, $mode);

    $sql = '
        SELECT
            ' . $yearExpr . ' AS year_key,
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
        WHERE ' . $rangeWhere['sql'] . '
          AND ' . $statusClause['sql'] . '
        GROUP BY year_key
        ORDER BY year_key ASC
    ';

    $bind = [
        events_public_post_status(),
        events_preliminary_post_status(),
    ];
    foreach ($views['bind'] as $v) {
        $bind[] = $v;
    }
    foreach ($rangeWhere['bind'] as $v) {
        $bind[] = $v;
    }
    foreach ($statusClause['bind'] as $v) {
        $bind[] = $v;
    }

    $byYear = [];
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($bind);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $y = (int) ($row['year_key'] ?? 0);
            if ($y < 2000 || $y > 2100) {
                continue;
            }
            $byYear[$y] = [
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
        error_log('events_yearly_stats_fetch_rows: ' . $e->getMessage());
    }

    try {
        $orgSql = '
            SELECT
                ' . $yearExpr . ' AS year_key,
                COUNT(DISTINCT eo.`organizer_id`) AS unique_organizers
            FROM `events_calendar_events` e
            INNER JOIN `events_calendar_event_organizers` eo ON eo.`event_id` = e.`id`
            WHERE ' . $rangeWhere['sql'] . '
              AND ' . $statusClause['sql'] . '
            GROUP BY year_key
        ';
        $orgBind = array_merge($rangeWhere['bind'], $statusClause['bind']);
        $stmt = $db->prepare($orgSql);
        $stmt->execute($orgBind);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $y = (int) ($row['year_key'] ?? 0);
            if (isset($byYear[$y])) {
                $byYear[$y]['unique_organizers'] = (int) ($row['unique_organizers'] ?? 0);
            }
        }
    } catch (Throwable $e) {
        error_log('events_yearly_stats organizers: ' . $e->getMessage());
    }

    return $byYear;
}

/**
 * @param array{year_from:int,year_to:int,bucket_by:string,status:string,mode?:string} $params
 * @return array<string, mixed>
 */
function events_yearly_stats(PDO $db, array $params): array
{
    events_view_tracking_ensure_bot_column($db);

    $yearFrom = (int) $params['year_from'];
    $yearTo = (int) $params['year_to'];
    $bucketBy = (string) $params['bucket_by'];
    $status = (string) $params['status'];
    $mode = events_edit_stats_normalize_mode($params['mode'] ?? 'smart');

    // Egy évvel bővebb tartomány a legelső év YoY számításához
    $fetchFrom = max(2000, $yearFrom - 1);
    $raw = events_yearly_stats_fetch_rows($db, $fetchFrom, $yearTo, $bucketBy, $status, $mode);

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

    $years = [];
    for ($y = $yearFrom; $y <= $yearTo; $y++) {
        $row = events_monthly_stats_enrich_row($raw[$y] ?? $empty);
        $prev = events_monthly_stats_enrich_row($raw[$y - 1] ?? $empty);

        $row['year'] = $y;
        $row['label'] = (string) $y;
        $row['yoy_events_pct'] = events_monthly_stats_pct_change(
            (float) ($row['events_count'] ?? 0),
            (float) ($prev['events_count'] ?? 0)
        );
        $row['yoy_clicks_pct'] = events_monthly_stats_pct_change(
            (float) ($row['total_clicks_human'] ?? 0),
            (float) ($prev['total_clicks_human'] ?? 0)
        );
        $row['yoy_clicks_per_event_pct'] = events_monthly_stats_pct_change(
            isset($row['clicks_per_event']) ? (float) $row['clicks_per_event'] : null,
            isset($prev['clicks_per_event']) ? (float) $prev['clicks_per_event'] : null
        );
        $row['yoy_lead_days_pct'] = events_monthly_stats_pct_change(
            isset($row['avg_lead_days']) ? (float) $row['avg_lead_days'] : null,
            isset($prev['avg_lead_days']) ? (float) $prev['avg_lead_days'] : null
        );
        $row['prev_year'] = [
            'events_count' => (int) ($prev['events_count'] ?? 0),
            'total_clicks_human' => (int) ($prev['total_clicks_human'] ?? 0),
            'clicks_per_event' => $prev['clicks_per_event'] ?? null,
            'avg_lead_days' => $prev['avg_lead_days'] ?? null,
        ];
        $years[] = $row;
    }

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
    foreach ($years as $row) {
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
    }

    $events = (int) $totals['events_count'];
    $clicks = (int) $totals['total_clicks_human'];
    $pageViews = (int) $totals['page_views_human'];
    $external = (int) $totals['external_clicks_human'];

    $latest = $years !== [] ? $years[count($years) - 1] : null;
    $first = $years !== [] ? $years[0] : null;

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
        'years_count' => count($years),
        'latest_yoy_events_pct' => is_array($latest) ? ($latest['yoy_events_pct'] ?? null) : null,
        'range_events_change_pct' => ($first !== null && $latest !== null)
            ? events_monthly_stats_pct_change(
                (float) ($latest['events_count'] ?? 0),
                (float) ($first['events_count'] ?? 0)
            )
            : null,
    ];

    $labels = array_map(static fn (array $r): string => (string) $r['label'], $years);
    $chart = [
        'labels' => $labels,
        'events' => array_map(static fn (array $r): int => (int) $r['events_count'], $years),
        'page_views' => array_map(static fn (array $r): int => (int) $r['page_views_human'], $years),
        'external_clicks' => array_map(static fn (array $r): int => (int) $r['external_clicks_human'], $years),
        'clicks_per_event' => array_map(
            static fn (array $r): ?float => isset($r['clicks_per_event']) ? (float) $r['clicks_per_event'] : null,
            $years
        ),
        'avg_lead_days' => array_map(
            static fn (array $r): ?float => isset($r['avg_lead_days']) ? (float) $r['avg_lead_days'] : null,
            $years
        ),
        'external_ctr_pct' => array_map(
            static fn (array $r): ?float => isset($r['external_ctr_pct']) ? (float) $r['external_ctr_pct'] : null,
            $years
        ),
        'yoy_events_pct' => array_map(
            static fn (array $r): ?float => isset($r['yoy_events_pct']) ? (float) $r['yoy_events_pct'] : null,
            $years
        ),
    ];

    $peakEvents = null;
    $peakCpe = null;
    $peakLead = null;
    foreach ($years as $row) {
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
        'year_from' => $yearFrom,
        'year_to' => $yearTo,
        'bucket_by' => $bucketBy,
        'status' => $status,
        'mode' => $mode,
        'table_ready' => events_edit_stats_table_ready($db),
        'bot_ready' => events_view_tracking_bot_column_ready($db),
        'years' => $years,
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
