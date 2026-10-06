<?php
declare(strict_types=1);

require_once __DIR__ . '/event_status.php';
require_once __DIR__ . '/event_view_tracking.php';
require_once __DIR__ . '/event_edit_stats.php';
require_once __DIR__ . '/event_monthly_stats.php';
require_once __DIR__ . '/event_realtime_stats.php';

/**
 * Esemény előtti 30 nap oldalbetöltés-statisztika.
 * Ablak: [max(event_start − 30 nap, publikálás), event_start nap) — a publikálás előtti forgalom nem számít.
 */

const EVENTS_PRE_EVENT_STATS_WINDOW_DAYS = 30;

/**
 * Érvényes publikálási dátum SQL kifejezés (NULL, ha nincs / invalid).
 */
function events_pre_event_stats_published_at_expr(string $eventAlias = 'e'): string
{
    $alias = trim($eventAlias);
    if ($alias !== '' && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias) !== 1) {
        return 'NULL';
    }
    $col = $alias === '' ? '`event_published_at`' : '`' . $alias . '`.`event_published_at`';

    return 'CASE
        WHEN ' . $col . ' IS NULL
             OR ' . $col . ' IN (\'\', \'0000-00-00 00:00:00\')
        THEN NULL
        ELSE ' . $col . '
    END';
}

/**
 * @return array{year:int,status:string,visitor:string,event_id:int|null}
 */
function events_pre_event_stats_params_from_request(array $query): array
{
    $year = filter_var($query['stat_year'] ?? null, FILTER_VALIDATE_INT);
    $currentYear = (int) (new DateTimeImmutable('today'))->format('Y');
    if ($year === false || $year < 2000 || $year > ($currentYear + 5)) {
        $year = $currentYear;
    }

    $status = strtolower(trim((string) ($query['stat_status'] ?? 'public')));
    if (!in_array($status, ['public', 'publish', 'all'], true)) {
        $status = 'public';
    }

    $visitor = events_realtime_normalize_visitor($query['visitor'] ?? 'human');

    $eventIdRaw = filter_var($query['event_id'] ?? null, FILTER_VALIDATE_INT);
    $eventId = ($eventIdRaw !== false && $eventIdRaw > 0) ? (int) $eventIdRaw : null;

    return [
        'year' => (int) $year,
        'status' => $status,
        'visitor' => $visitor,
        'event_id' => $eventId,
    ];
}

/**
 * Nézet ablak: event_start előtti 30 nap, de nem korábban, mint a publikálás.
 *
 * @return array{sql:string,bind:list<mixed>}
 */
function events_pre_event_stats_window_clause(string $eventAlias = 'e', string $viewAlias = 'v'): array
{
    $days = EVENTS_PRE_EVENT_STATS_WINDOW_DAYS;
    $published = events_pre_event_stats_published_at_expr($eventAlias);

    return [
        'sql' => $viewAlias . '.`létrehozva` >= DATE_SUB(DATE(' . $eventAlias . '.`event_start`), INTERVAL ' . $days . ' DAY)'
            . ' AND ' . $viewAlias . '.`létrehozva` < DATE(' . $eventAlias . '.`event_start`)'
            . ' AND (' . $published . ' IS NULL OR ' . $viewAlias . '.`létrehozva` >= ' . $published . ')',
        'bind' => [],
    ];
}

/**
 * Relatív nap az esemény előtt: 1 = előző nap, 30 = 30 nappal korábban.
 */
function events_pre_event_stats_days_before_expr(string $eventAlias = 'e', string $viewAlias = 'v'): string
{
    return 'DATEDIFF(DATE(' . $eventAlias . '.`event_start`), DATE(' . $viewAlias . '.`létrehozva`))';
}

/**
 * @return list<int> napok 30 … 1 (eseményhez közeledve)
 */
function events_pre_event_stats_day_offsets(): array
{
    $days = [];
    for ($d = EVENTS_PRE_EVENT_STATS_WINDOW_DAYS; $d >= 1; $d--) {
        $days[] = $d;
    }

    return $days;
}

/**
 * @return array{
 *   table_ready: bool,
 *   bot_ready: bool,
 *   year: int,
 *   status: string,
 *   visitor: string,
 *   event_id: int|null,
 *   available_years: list<int>,
 *   summary: array<string, int|float|null>,
 *   distribution: list<array{days_before:int,label:string,count:int,pct:float|null}>,
 *   chart: array{labels:list<string>,counts:list<int>,pcts:list<float|null>},
 *   events: list<array<string, mixed>>,
 *   selected_event: array<string, mixed>|null
 * }
 */
function events_pre_event_stats(PDO $db, array $params): array
{
    $year = (int) ($params['year'] ?? (int) date('Y'));
    $status = (string) ($params['status'] ?? 'public');
    $visitor = events_realtime_normalize_visitor($params['visitor'] ?? 'human');
    $eventId = isset($params['event_id']) && $params['event_id'] !== null
        ? (int) $params['event_id']
        : null;
    if ($eventId !== null && $eventId <= 0) {
        $eventId = null;
    }

    $availableYears = events_monthly_stats_available_years($db);
    $tableReady = events_edit_stats_table_ready($db);
    $botReady = events_view_tracking_bot_column_ready($db);

    $emptyDistribution = [];
    foreach (events_pre_event_stats_day_offsets() as $daysBefore) {
        $emptyDistribution[] = [
            'days_before' => $daysBefore,
            'label' => '-' . $daysBefore,
            'count' => 0,
            'pct' => null,
        ];
    }

    $empty = [
        'table_ready' => $tableReady,
        'bot_ready' => $botReady,
        'year' => $year,
        'status' => $status,
        'visitor' => $visitor,
        'event_id' => $eventId,
        'available_years' => $availableYears,
        'summary' => [
            'events_count' => 0,
            'events_with_views' => 0,
            'page_views' => 0,
            'avg_views_per_event' => null,
            'median_views' => null,
        ],
        'distribution' => $emptyDistribution,
        'chart' => [
            'labels' => array_column($emptyDistribution, 'label'),
            'counts' => array_fill(0, count($emptyDistribution), 0),
            'pcts' => array_fill(0, count($emptyDistribution), null),
        ],
        'events' => [],
        'selected_event' => null,
    ];

    if (!$tableReady) {
        return $empty;
    }

    try {
        $eventRows = events_pre_event_stats_fetch_events($db, $year, $status, $visitor, $botReady);
        $countsByDay = events_pre_event_stats_fetch_day_counts($db, $year, $status, $visitor, $botReady, null);

        $distribution = [];
        $totalViews = 0;
        foreach (events_pre_event_stats_day_offsets() as $daysBefore) {
            $cnt = (int) ($countsByDay[$daysBefore] ?? 0);
            $totalViews += $cnt;
            $distribution[] = [
                'days_before' => $daysBefore,
                'label' => '-' . $daysBefore,
                'count' => $cnt,
                'pct' => null,
            ];
        }
        foreach ($distribution as &$row) {
            $row['pct'] = $totalViews > 0
                ? round(((int) $row['count'] / $totalViews) * 100, 2)
                : null;
        }
        unset($row);

        $viewTotals = [];
        foreach ($eventRows as $er) {
            $viewTotals[] = (int) ($er['page_views'] ?? 0);
        }
        $eventsWithViews = 0;
        foreach ($viewTotals as $vt) {
            if ($vt > 0) {
                $eventsWithViews++;
            }
        }
        $eventsCount = count($eventRows);
        $avg = $eventsCount > 0 ? round($totalViews / $eventsCount, 1) : null;
        $median = events_pre_event_stats_median($viewTotals);

        $selectedEvent = null;
        if ($eventId !== null) {
            $selectedEvent = events_pre_event_stats_build_selected_event(
                $db,
                $eventId,
                $visitor,
                $botReady,
                $eventRows
            );
        }

        return [
            'table_ready' => true,
            'bot_ready' => $botReady,
            'year' => $year,
            'status' => $status,
            'visitor' => $visitor,
            'event_id' => $eventId,
            'available_years' => $availableYears,
            'summary' => [
                'events_count' => $eventsCount,
                'events_with_views' => $eventsWithViews,
                'page_views' => $totalViews,
                'avg_views_per_event' => $avg,
                'median_views' => $median,
            ],
            'distribution' => $distribution,
            'chart' => [
                'labels' => array_column($distribution, 'label'),
                'counts' => array_map(static fn (array $r): int => (int) $r['count'], $distribution),
                'pcts' => array_map(static fn (array $r): ?float => $r['pct'], $distribution),
            ],
            'events' => $eventRows,
            'selected_event' => $selectedEvent,
        ];
    } catch (Throwable $e) {
        error_log('events_pre_event_stats: ' . $e->getMessage());

        return $empty;
    }
}

/**
 * @param list<int> $values
 */
function events_pre_event_stats_median(array $values): ?float
{
    if ($values === []) {
        return null;
    }
    sort($values, SORT_NUMERIC);
    $n = count($values);
    $mid = intdiv($n, 2);
    if ($n % 2 === 1) {
        return (float) $values[$mid];
    }

    return round(($values[$mid - 1] + $values[$mid]) / 2, 1);
}

/**
 * @return list<array{
 *   id:int,event_name:string,event_slug:string,event_status:string,
 *   event_start:string,event_published_at:string|null,publish_days_before:int|null,
 *   page_views:int,share_pct:float|null
 * }>
 */
function events_pre_event_stats_fetch_events(
    PDO $db,
    int $year,
    string $status,
    string $visitor,
    bool $botReady
): array {
    $statusClause = events_monthly_stats_status_clause($status, 'e');
    $window = events_pre_event_stats_window_clause('e', 'v');
    $botAnd = events_realtime_bot_and($visitor, $botReady, 'v.`is_bot`');
    $publishedExpr = events_pre_event_stats_published_at_expr('e');

    $sql = '
        SELECT
            e.`id`,
            e.`event_name`,
            e.`event_slug`,
            e.`event_status`,
            e.`event_start`,
            ' . $publishedExpr . ' AS event_published_at,
            CASE
                WHEN ' . $publishedExpr . ' IS NULL OR DATE(' . $publishedExpr . ') > DATE(e.`event_start`)
                THEN NULL
                ELSE DATEDIFF(DATE(e.`event_start`), DATE(' . $publishedExpr . '))
            END AS publish_days_before,
            COUNT(v.`esemény_id`) AS page_views
        FROM `events_calendar_events` e
        LEFT JOIN `events_calendar_event_views` v
            ON v.`esemény_id` = e.`id`
           AND v.`metric_type` = ?
           AND ' . $window['sql'] . '
           ' . $botAnd . '
        WHERE e.`event_start` IS NOT NULL
          AND YEAR(e.`event_start`) = ?
          AND ' . $statusClause['sql'] . '
          AND ' . events_stats_exclude_trash_sql('e') . '
        GROUP BY e.`id`, e.`event_name`, e.`event_slug`, e.`event_status`, e.`event_start`, e.`event_published_at`
        ORDER BY page_views DESC, e.`event_start` DESC, e.`id` DESC
    ';

    $bind = [EVENTS_VIEW_METRIC_PAGE, $year, ...$statusClause['bind']];
    $stmt = $db->prepare($sql);
    $stmt->execute($bind);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $total = 0;
    foreach ($rows as $row) {
        $total += (int) ($row['page_views'] ?? 0);
    }

    $out = [];
    foreach ($rows as $row) {
        $views = (int) ($row['page_views'] ?? 0);
        $publishedRaw = $row['event_published_at'] ?? null;
        $publishedAt = is_string($publishedRaw) && $publishedRaw !== '' ? $publishedRaw : null;
        $publishDays = isset($row['publish_days_before']) && $row['publish_days_before'] !== null
            ? (int) $row['publish_days_before']
            : null;

        $out[] = [
            'id' => (int) ($row['id'] ?? 0),
            'event_name' => (string) ($row['event_name'] ?? ''),
            'event_slug' => (string) ($row['event_slug'] ?? ''),
            'event_status' => (string) ($row['event_status'] ?? ''),
            'event_start' => (string) ($row['event_start'] ?? ''),
            'event_published_at' => $publishedAt,
            'publish_days_before' => $publishDays,
            'page_views' => $views,
            'share_pct' => $total > 0 ? round(($views / $total) * 100, 2) : null,
        ];
    }

    return $out;
}

/**
 * @return array<int, int> days_before => count
 */
function events_pre_event_stats_fetch_day_counts(
    PDO $db,
    int $year,
    string $status,
    string $visitor,
    bool $botReady,
    ?int $eventId
): array {
    $statusClause = events_monthly_stats_status_clause($status, 'e');
    $window = events_pre_event_stats_window_clause('e', 'v');
    $botAnd = events_realtime_bot_and($visitor, $botReady, 'v.`is_bot`');
    $daysExpr = events_pre_event_stats_days_before_expr('e', 'v');

    $eventFilter = '';
    $bind = [$year, ...$statusClause['bind'], EVENTS_VIEW_METRIC_PAGE];
    if ($eventId !== null && $eventId > 0) {
        $eventFilter = ' AND e.`id` = ?';
        $bind[] = $eventId;
    }

    $sql = '
        SELECT
            ' . $daysExpr . ' AS days_before,
            COUNT(*) AS cnt
        FROM `events_calendar_event_views` v
        INNER JOIN `events_calendar_events` e
            ON e.`id` = v.`esemény_id`
        WHERE e.`event_start` IS NOT NULL
          AND YEAR(e.`event_start`) = ?
          AND ' . $statusClause['sql'] . '
          AND ' . events_stats_exclude_trash_sql('e') . '
          AND v.`metric_type` = ?
          ' . $botAnd . '
          AND ' . $window['sql'] . '
          ' . $eventFilter . '
          AND ' . $daysExpr . ' BETWEEN 1 AND ' . EVENTS_PRE_EVENT_STATS_WINDOW_DAYS . '
        GROUP BY days_before
    ';

    $stmt = $db->prepare($sql);
    $stmt->execute($bind);
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $d = (int) ($row['days_before'] ?? 0);
        if ($d >= 1 && $d <= EVENTS_PRE_EVENT_STATS_WINDOW_DAYS) {
            $map[$d] = (int) ($row['cnt'] ?? 0);
        }
    }

    return $map;
}

/**
 * @param list<array<string, mixed>> $eventRows
 * @return array<string, mixed>|null
 */
function events_pre_event_stats_build_selected_event(
    PDO $db,
    int $eventId,
    string $visitor,
    bool $botReady,
    array $eventRows
): ?array {
    $meta = null;
    foreach ($eventRows as $row) {
        if ((int) ($row['id'] ?? 0) === $eventId) {
            $meta = $row;
            break;
        }
    }

    if ($meta === null) {
        try {
            $publishedExpr = events_pre_event_stats_published_at_expr('');
            $stmt = $db->prepare('
                SELECT
                    `id`, `event_name`, `event_slug`, `event_status`, `event_start`,
                    ' . $publishedExpr . ' AS event_published_at,
                    CASE
                        WHEN ' . $publishedExpr . ' IS NULL OR DATE(' . $publishedExpr . ') > DATE(`event_start`)
                        THEN NULL
                        ELSE DATEDIFF(DATE(`event_start`), DATE(' . $publishedExpr . '))
                    END AS publish_days_before
                FROM `events_calendar_events`
                WHERE `id` = ?
                  AND `event_start` IS NOT NULL
                  AND ' . events_stats_exclude_trash_sql('') . '
                LIMIT 1
            ');
            $stmt->execute([$eventId]);
            $found = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$found) {
                return null;
            }
            $publishedRaw = $found['event_published_at'] ?? null;
            $meta = [
                'id' => (int) $found['id'],
                'event_name' => (string) ($found['event_name'] ?? ''),
                'event_slug' => (string) ($found['event_slug'] ?? ''),
                'event_status' => (string) ($found['event_status'] ?? ''),
                'event_start' => (string) ($found['event_start'] ?? ''),
                'event_published_at' => is_string($publishedRaw) && $publishedRaw !== '' ? $publishedRaw : null,
                'publish_days_before' => isset($found['publish_days_before']) && $found['publish_days_before'] !== null
                    ? (int) $found['publish_days_before']
                    : null,
                'page_views' => 0,
                'share_pct' => null,
            ];
        } catch (Throwable $e) {
            error_log('events_pre_event_stats_build_selected_event: ' . $e->getMessage());

            return null;
        }
    }

    $window = events_pre_event_stats_window_clause('e', 'v');
    $botAnd = events_realtime_bot_and($visitor, $botReady, 'v.`is_bot`');
    $daysExpr = events_pre_event_stats_days_before_expr('e', 'v');
    $publishDaysBefore = isset($meta['publish_days_before']) && $meta['publish_days_before'] !== null
        ? (int) $meta['publish_days_before']
        : null;

    $sql = '
        SELECT
            ' . $daysExpr . ' AS days_before,
            COUNT(*) AS cnt
        FROM `events_calendar_event_views` v
        INNER JOIN `events_calendar_events` e ON e.`id` = v.`esemény_id`
        WHERE e.`id` = ?
          AND e.`event_start` IS NOT NULL
          AND v.`metric_type` = ?
          ' . $botAnd . '
          AND ' . $window['sql'] . '
          AND ' . $daysExpr . ' BETWEEN 1 AND ' . EVENTS_PRE_EVENT_STATS_WINDOW_DAYS . '
        GROUP BY days_before
    ';

    $counts = [];
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([$eventId, EVENTS_VIEW_METRIC_PAGE]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $d = (int) ($row['days_before'] ?? 0);
            if ($d >= 1 && $d <= EVENTS_PRE_EVENT_STATS_WINDOW_DAYS) {
                $counts[$d] = (int) ($row['cnt'] ?? 0);
            }
        }
    } catch (Throwable $e) {
        error_log('events_pre_event_stats_build_selected_event days: ' . $e->getMessage());
    }

    $distribution = [];
    $total = 0;
    foreach (events_pre_event_stats_day_offsets() as $daysBefore) {
        $cnt = (int) ($counts[$daysBefore] ?? 0);
        $beforePublish = $publishDaysBefore !== null && $daysBefore > $publishDaysBefore;
        if (!$beforePublish) {
            $total += $cnt;
        }
        $distribution[] = [
            'days_before' => $daysBefore,
            'label' => '-' . $daysBefore,
            'count' => $beforePublish ? 0 : $cnt,
            'pct' => null,
            'before_publish' => $beforePublish,
        ];
    }
    foreach ($distribution as &$row) {
        if (!empty($row['before_publish'])) {
            $row['pct'] = null;
            continue;
        }
        $row['pct'] = $total > 0
            ? round(((int) $row['count'] / $total) * 100, 2)
            : null;
    }
    unset($row);

    $meta['page_views'] = $total;
    $meta['distribution'] = $distribution;
    $meta['chart'] = [
        'labels' => array_column($distribution, 'label'),
        'counts' => array_map(
            static fn (array $r): ?int => !empty($r['before_publish']) ? null : (int) $r['count'],
            $distribution
        ),
        'pcts' => array_map(static fn (array $r): ?float => $r['pct'], $distribution),
    ];

    return $meta;
}
