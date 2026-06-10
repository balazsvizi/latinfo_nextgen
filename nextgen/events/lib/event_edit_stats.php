<?php
declare(strict_types=1);

require_once __DIR__ . '/event_view_tracking.php';
require_once __DIR__ . '/admin_event_calendar.php';

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
/**
 * @param array<string, int> $pageByDay
 * @param array<string, int> $previewByDay
 * @return array{
 *   table_ready: bool,
 *   totals: array{page_views: int, calendar_previews: int},
 *   chart: array{labels: list<string>, datasets: list<array{label: string, data: list<int>, color: string, total: int}>}
 * }
 */
function events_edit_stats_build_result(array $labels, array $pageByDay, array $previewByDay, bool $tableReady): array
{
    $totalPage = 0;
    $totalPreview = 0;
    $pageData = [];
    $previewData = [];
    foreach ($labels as $label) {
        $pageVal = (int) ($pageByDay[$label] ?? 0);
        $previewVal = (int) ($previewByDay[$label] ?? 0);
        $pageData[] = $pageVal;
        $previewData[] = $previewVal;
        $totalPage += $pageVal;
        $totalPreview += $previewVal;
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

/**
 * @return array{start_inclusive: string, end_exclusive: string}
 */
function events_edit_stats_view_window(array $params): array
{
    $dateFrom = (string) ($params['date_from'] ?? '');
    $dateTo = (string) ($params['date_to'] ?? '');

    return [
        'start_inclusive' => $dateFrom . ' 00:00:00',
        'end_exclusive' => (new DateTimeImmutable($dateTo))->modify('+1 day')->format('Y-m-d 00:00:00'),
    ];
}

/**
 * @param array<string, int> $pageByDay
 * @param array<string, int> $previewByDay
 */
function events_edit_stats_apply_bucket_rows(array $rows, array &$pageByDay, array &$previewByDay, bool $tableReady): void
{
    foreach ($rows as $row) {
        $bucket = (string) ($row['bucket'] ?? '');
        $cnt = (int) ($row['cnt'] ?? 0);
        if ($cnt <= 0 || !array_key_exists($bucket, $pageByDay)) {
            continue;
        }
        if ($tableReady) {
            $metric = (string) ($row['metric_type'] ?? EVENTS_VIEW_METRIC_PAGE);
            if ($metric === EVENTS_VIEW_METRIC_CALENDAR_PREVIEW) {
                $previewByDay[$bucket] += $cnt;
            } else {
                $pageByDay[$bucket] += $cnt;
            }
        } else {
            $pageByDay[$bucket] += $cnt;
        }
    }
}

function events_edit_stats_for_event(PDO $db, int $eventId, array $params): array
{
    $empty = [
        'table_ready' => false,
        'totals' => ['page_views' => 0, 'calendar_previews' => 0],
        'chart' => ['labels' => [], 'datasets' => []],
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
    $window = events_edit_stats_view_window($params);

    try {
        if ($tableReady) {
            $stmt = $db->prepare('
                SELECT DATE(`létrehozva`) AS bucket, `metric_type`, COUNT(*) AS cnt
                FROM `events_calendar_event_views`
                WHERE `esemény_id` = ?
                  AND `létrehozva` >= ?
                  AND `létrehozva` < ?
                GROUP BY bucket, `metric_type`
            ');
            $stmt->execute([$eventId, $window['start_inclusive'], $window['end_exclusive']]);
        } else {
            $stmt = $db->prepare('
                SELECT DATE(`létrehozva`) AS bucket, COUNT(*) AS cnt
                FROM `events_calendar_event_views`
                WHERE `esemény_id` = ?
                  AND `létrehozva` >= ?
                  AND `létrehozva` < ?
                GROUP BY bucket
            ');
            $stmt->execute([$eventId, $window['start_inclusive'], $window['end_exclusive']]);
        }
        events_edit_stats_apply_bucket_rows($stmt->fetchAll(PDO::FETCH_ASSOC), $pageByDay, $previewByDay, $tableReady);
    } catch (Throwable) {
        return $empty;
    }

    return events_edit_stats_build_result($labels, $pageByDay, $previewByDay, $tableReady);
}

/**
 * @return array{
 *   table_ready: bool,
 *   totals: array{page_views: int, calendar_previews: int},
 *   chart: array{labels: list<string>, datasets: list<array{label: string, data: list<int>, color: string, total: int}>}
 * }
 */
function events_edit_stats_for_organizer(PDO $db, int $organizerId, array $params): array
{
    $empty = [
        'table_ready' => false,
        'totals' => ['page_views' => 0, 'calendar_previews' => 0],
        'chart' => ['labels' => [], 'datasets' => []],
    ];

    if ($organizerId <= 0) {
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
    $window = events_edit_stats_view_window($params);

    try {
        if ($tableReady) {
            $stmt = $db->prepare('
                SELECT DATE(v.`létrehozva`) AS bucket, v.`metric_type`, COUNT(*) AS cnt
                FROM `events_calendar_event_views` v
                INNER JOIN `events_calendar_event_organizers` eo ON eo.`event_id` = v.`esemény_id`
                WHERE eo.`organizer_id` = ?
                  AND v.`létrehozva` >= ?
                  AND v.`létrehozva` < ?
                GROUP BY bucket, v.`metric_type`
            ');
            $stmt->execute([$organizerId, $window['start_inclusive'], $window['end_exclusive']]);
        } else {
            $stmt = $db->prepare('
                SELECT DATE(v.`létrehozva`) AS bucket, COUNT(*) AS cnt
                FROM `events_calendar_event_views` v
                INNER JOIN `events_calendar_event_organizers` eo ON eo.`event_id` = v.`esemény_id`
                WHERE eo.`organizer_id` = ?
                  AND v.`létrehozva` >= ?
                  AND v.`létrehozva` < ?
                GROUP BY bucket
            ');
            $stmt->execute([$organizerId, $window['start_inclusive'], $window['end_exclusive']]);
        }
        events_edit_stats_apply_bucket_rows($stmt->fetchAll(PDO::FETCH_ASSOC), $pageByDay, $previewByDay, $tableReady);
    } catch (Throwable) {
        return $empty;
    }

    $result = events_edit_stats_build_result($labels, $pageByDay, $previewByDay, $tableReady);
    $eventsList = events_edit_stats_organizer_events_list($db, $organizerId, $params, $tableReady);
    $result['totals']['events_total'] = $eventsList['events_total'];
    $result['totals']['events_with_views'] = $eventsList['events_with_views'];
    $result['event_rows'] = $eventsList['rows'];
    $result['draft_rows'] = $eventsList['draft_rows'];

    return $result;
}

/**
 * Szervező összes eseménye + megtekintésszámok a grafikon időszakában.
 *
 * @return array{rows: list<array<string, mixed>>, events_total: int, events_with_views: int}
 */
function events_edit_stats_is_draft_event(array $row): bool
{
    $status = (string) ($row['event_status'] ?? '');

    return in_array($status, ['draft', 'auto-draft'], true);
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array{non_draft: list<array<string, mixed>>, drafts: list<array<string, mixed>>}
 */
function events_edit_stats_partition_draft_events(array $rows): array
{
    $nonDraft = [];
    $drafts = [];
    foreach ($rows as $row) {
        if (events_edit_stats_is_draft_event($row)) {
            $drafts[] = $row;
        } else {
            $nonDraft[] = $row;
        }
    }

    return ['non_draft' => $nonDraft, 'drafts' => $drafts];
}

function events_edit_stats_organizer_events_list(PDO $db, int $organizerId, array $params, ?bool $tableReady = null): array
{
    $empty = ['rows' => [], 'draft_rows' => [], 'events_total' => 0, 'events_with_views' => 0];
    if ($organizerId <= 0) {
        return $empty;
    }

    $tableReady = $tableReady ?? events_edit_stats_table_ready($db);
    $window = events_edit_stats_view_window($params);

    $pageMetricSql = $tableReady
        ? "(SELECT COUNT(*) FROM `events_calendar_event_views` v WHERE v.`esemény_id` = e.`id` AND v.`metric_type` = 'page_view' AND v.`létrehozva` >= ? AND v.`létrehozva` < ?)"
        : "(SELECT COUNT(*) FROM `events_calendar_event_views` v WHERE v.`esemény_id` = e.`id` AND v.`létrehozva` >= ? AND v.`létrehozva` < ?)";
    $previewMetricSql = "(SELECT COUNT(*) FROM `events_calendar_event_views` v WHERE v.`esemény_id` = e.`id` AND v.`metric_type` = 'calendar_preview' AND v.`létrehozva` >= ? AND v.`létrehozva` < ?)";

    $sql = "
        SELECT e.*,
            {$pageMetricSql} AS megtekintesek"
        . ($tableReady ? ",\n            {$previewMetricSql} AS naptar_elonezetek" : '') . "
        FROM `events_calendar_events` e
        INNER JOIN `events_calendar_event_organizers` eo ON eo.`event_id` = e.`id` AND eo.`organizer_id` = ?
        ORDER BY e.`event_start` IS NULL, e.`event_start` DESC, e.`id` DESC
    ";

    $executeParams = [
        $window['start_inclusive'],
        $window['end_exclusive'],
    ];
    if ($tableReady) {
        $executeParams[] = $window['start_inclusive'];
        $executeParams[] = $window['end_exclusive'];
    }
    $executeParams[] = $organizerId;

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($executeParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return $empty;
    }

    $partitioned = events_edit_stats_partition_draft_events($rows);
    $nonDraftRows = $partitioned['non_draft'];
    $draftRows = $partitioned['drafts'];

    $eventsWithViews = 0;
    foreach ($nonDraftRows as $row) {
        $views = (int) ($row['megtekintesek'] ?? 0) + (int) ($row['naptar_elonezetek'] ?? 0);
        if ($views > 0) {
            $eventsWithViews++;
        }
    }

    return [
        'rows' => $nonDraftRows,
        'draft_rows' => $draftRows,
        'events_total' => count($nonDraftRows),
        'events_with_views' => $eventsWithViews,
    ];
}
