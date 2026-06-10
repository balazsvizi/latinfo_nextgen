<?php
declare(strict_types=1);

require_once __DIR__ . '/event_view_tracking.php';

/**
 * @return array{date_from: string, date_to: string}
 */
function events_edit_stats_params_from_request(array $query): array
{
    $today = new DateTimeImmutable('today');
    $defaultFrom = $today->modify('-29 days');

    $dateFrom = trim((string) ($query['stat_date_from'] ?? ''));
    $dateTo = trim((string) ($query['stat_date_to'] ?? ''));

    if ($dateFrom === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $dateFrom = $defaultFrom->format('Y-m-d');
    }
    if ($dateTo === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $dateTo = $today->format('Y-m-d');
    }

    try {
        $fromDt = new DateTimeImmutable($dateFrom);
        $toDt = new DateTimeImmutable($dateTo);
    } catch (Throwable) {
        return [
            'date_from' => $defaultFrom->format('Y-m-d'),
            'date_to' => $today->format('Y-m-d'),
        ];
    }

    if ($fromDt > $toDt) {
        [$fromDt, $toDt] = [$toDt, $fromDt];
    }

    return [
        'date_from' => $fromDt->format('Y-m-d'),
        'date_to' => $toDt->format('Y-m-d'),
    ];
}

function events_edit_stats_table_ready(PDO $db): bool
{
    try {
        $stmt = $db->query("SHOW COLUMNS FROM `events_calendar_event_views` LIKE 'metric_type'");

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return false;
    }
}

/**
 * @return list<string>
 */
function events_edit_stats_date_labels(string $dateFrom, string $dateTo): array
{
    try {
        $start = new DateTimeImmutable($dateFrom);
        $end = new DateTimeImmutable($dateTo);
    } catch (Throwable) {
        return [];
    }

    if ($start > $end) {
        [$start, $end] = [$end, $start];
    }

    $labels = [];
    $cur = $start;
    while ($cur <= $end) {
        $labels[] = $cur->format('Y-m-d');
        $cur = $cur->modify('+1 day');
    }

    return $labels;
}

/**
 * @param list<string> $labels
 * @return list<string>
 */
function events_edit_stats_chart_labels(array $labels): array
{
    return array_map(static function (string $ymd): string {
        try {
            return (new DateTimeImmutable($ymd))->format('m.d.');
        } catch (Throwable) {
            return $ymd;
        }
    }, $labels);
}

/**
 * @return array{
 *   table_ready: bool,
 *   totals: array{page_views: int, calendar_previews: int},
 *   chart: array{labels: list<string>, datasets: list<array{label: string, data: list<int>, color: string, total: int}>}
 * }
 */
function events_edit_stats_for_event(PDO $db, int $eventId, array $params): array
{
    $emptyChart = [
        'labels' => [],
        'datasets' => [],
    ];
    $empty = [
        'table_ready' => false,
        'totals' => ['page_views' => 0, 'calendar_previews' => 0],
        'chart' => $emptyChart,
    ];

    if ($eventId <= 0) {
        return $empty;
    }

    $dateFrom = (string) ($params['date_from'] ?? '');
    $dateTo = (string) ($params['date_to'] ?? '');
    $labels = events_edit_stats_date_labels($dateFrom, $dateTo);
    if ($labels === []) {
        return $empty;
    }

    $tableReady = events_edit_stats_table_ready($db);
    $pageByDay = array_fill_keys($labels, 0);
    $previewByDay = array_fill_keys($labels, 0);
    $totalPage = 0;
    $totalPreview = 0;

    try {
        $endExclusive = (new DateTimeImmutable($dateTo))->modify('+1 day')->format('Y-m-d 00:00:00');
        $startInclusive = $dateFrom . ' 00:00:00';

        if ($tableReady) {
            $stmt = $db->prepare('
                SELECT DATE(`létrehozva`) AS bucket, `metric_type`, COUNT(*) AS cnt
                FROM `events_calendar_event_views`
                WHERE `esemény_id` = ?
                  AND `létrehozva` >= ?
                  AND `létrehozva` < ?
                GROUP BY bucket, `metric_type`
            ');
            $stmt->execute([$eventId, $startInclusive, $endExclusive]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $bucket = (string) ($row['bucket'] ?? '');
                $cnt = (int) ($row['cnt'] ?? 0);
                if ($cnt <= 0 || !array_key_exists($bucket, $pageByDay)) {
                    continue;
                }
                $metric = (string) ($row['metric_type'] ?? EVENTS_VIEW_METRIC_PAGE);
                if ($metric === EVENTS_VIEW_METRIC_CALENDAR_PREVIEW) {
                    $previewByDay[$bucket] = ($previewByDay[$bucket] ?? 0) + $cnt;
                    $totalPreview += $cnt;
                } else {
                    $pageByDay[$bucket] = ($pageByDay[$bucket] ?? 0) + $cnt;
                    $totalPage += $cnt;
                }
            }
        } else {
            $stmt = $db->prepare('
                SELECT DATE(`létrehozva`) AS bucket, COUNT(*) AS cnt
                FROM `events_calendar_event_views`
                WHERE `esemény_id` = ?
                  AND `létrehozva` >= ?
                  AND `létrehozva` < ?
                GROUP BY bucket
            ');
            $stmt->execute([$eventId, $startInclusive, $endExclusive]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $bucket = (string) ($row['bucket'] ?? '');
                $cnt = (int) ($row['cnt'] ?? 0);
                if ($cnt <= 0 || !isset($pageByDay[$bucket])) {
                    continue;
                }
                $pageByDay[$bucket] += $cnt;
                $totalPage += $cnt;
            }
        }
    } catch (Throwable) {
        return $empty;
    }

    $pageData = [];
    $previewData = [];
    foreach ($labels as $label) {
        $pageData[] = (int) ($pageByDay[$label] ?? 0);
        $previewData[] = (int) ($previewByDay[$label] ?? 0);
    }

    $datasets = [
        [
            'label' => 'Oldal megtekintés',
            'data' => $pageData,
            'color' => '#3d6b4f',
            'total' => $totalPage,
        ],
    ];
    if ($tableReady) {
        $datasets[] = [
            'label' => 'Naptár előnézet',
            'data' => $previewData,
            'color' => '#6b7fa8',
            'total' => $totalPreview,
        ];
    }

    return [
        'table_ready' => $tableReady,
        'totals' => [
            'page_views' => $totalPage,
            'calendar_previews' => $totalPreview,
        ],
        'chart' => [
            'labels' => events_edit_stats_chart_labels($labels),
            'datasets' => $datasets,
        ],
    ];
}
