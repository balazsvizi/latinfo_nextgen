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

/**
 * Gyors időszak-preset: 30 nap, 1 év, összes.
 *
 * @return array{date_from: string, date_to: string}
 */
function events_edit_stats_range_for_preset(string $preset, ?string $allFromYmd = null): array
{
    $today = new DateTimeImmutable('today');
    $to = $today->format('Y-m-d');

    return match ($preset) {
        'year' => [
            'date_from' => $today->modify('-1 year')->format('Y-m-d'),
            'date_to' => $to,
        ],
        'all' => [
            'date_from' => ($allFromYmd !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $allFromYmd) === 1)
                ? $allFromYmd
                : $today->modify('-5 years')->format('Y-m-d'),
            'date_to' => $to,
        ],
        default => [
            'date_from' => $today->modify('-29 days')->format('Y-m-d'),
            'date_to' => $to,
        ],
    };
}

/**
 * @param array{date_from?: string, date_to?: string} $params
 */
function events_edit_stats_detect_preset(array $params, ?string $allFromYmd = null): string
{
    $from = (string) ($params['date_from'] ?? '');
    $to = (string) ($params['date_to'] ?? '');
    foreach (['30', 'year', 'all'] as $preset) {
        $range = events_edit_stats_range_for_preset($preset, $allFromYmd);
        if ($range['date_from'] === $from && $range['date_to'] === $to) {
            return $preset;
        }
    }

    return 'custom';
}

/**
 * @param array{date_from: string, date_to: string} $range
 * @param array<string, scalar|null> $extraQuery
 */
function events_edit_stats_filter_url(string $baseUrl, array $range, array $extraQuery = []): string
{
    $query = array_merge($extraQuery, [
        'stat_date_from' => $range['date_from'],
        'stat_date_to' => $range['date_to'],
    ]);
    $qs = http_build_query($query);
    if ($qs === '') {
        return $baseUrl;
    }
    $sep = str_contains($baseUrl, '?') ? '&' : '?';

    return $baseUrl . $sep . $qs;
}

/**
 * Legkorábbi megtekintés napja a szervező(k) eseményein, vagy null.
 *
 * @param list<int> $organizerIds
 */
function events_edit_stats_earliest_view_date_for_organizers(PDO $db, array $organizerIds): ?string
{
    $organizerIds = array_values(array_unique(array_filter(
        array_map(static fn (mixed $id): int => (int) $id, $organizerIds),
        static fn (int $id): bool => $id > 0
    )));
    if ($organizerIds === []) {
        return null;
    }

    $orgPh = implode(',', array_fill(0, count($organizerIds), '?'));
    try {
        $stmt = $db->prepare("
            SELECT DATE(MIN(v.`létrehozva`)) AS first_day
            FROM `events_calendar_event_views` v
            INNER JOIN `events_calendar_event_organizers` eo ON eo.`event_id` = v.`esemény_id`
            WHERE eo.`organizer_id` IN ({$orgPh})
        ");
        $stmt->execute($organizerIds);
        $day = $stmt->fetchColumn();
        if (!is_string($day) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            return null;
        }

        return $day;
    } catch (Throwable $e) {
        error_log('events_edit_stats_earliest_view_date_for_organizers: ' . $e->getMessage());

        return null;
    }
}

/**
 * Legkorábbi megtekintés napja egy eseményen, vagy null.
 */
function events_edit_stats_earliest_view_date_for_event(PDO $db, int $eventId): ?string
{
    if ($eventId <= 0) {
        return null;
    }

    try {
        $stmt = $db->prepare('SELECT DATE(MIN(`létrehozva`)) AS first_day FROM `events_calendar_event_views` WHERE `esemény_id` = ?');
        $stmt->execute([$eventId]);
        $day = $stmt->fetchColumn();
        if (!is_string($day) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            return null;
        }

        return $day;
    } catch (Throwable $e) {
        error_log('events_edit_stats_earliest_view_date_for_event: ' . $e->getMessage());

        return null;
    }
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
 * @param array<string, int> $pageHumanByDay
 * @param array<string, int> $pageBotByDay
 * @param array<string, int> $previewHumanByDay
 * @param array<string, int> $previewBotByDay
 * @param array<string, int> $externalHumanByDay
 * @param array<string, int> $externalBotByDay
 * @return array{
 *   table_ready: bool,
 *   bot_ready: bool,
 *   totals: array{
 *     page_views: int,
 *     page_views_human: int,
 *     page_views_bot: int,
 *     calendar_previews: int,
 *     calendar_previews_human: int,
 *     external_info_clicks: int,
 *     external_info_clicks_human: int,
 *     unique_visitors?: int
 *   },
 *   chart: array{labels: list<string>, datasets: list<array{label: string, data: list<int>, color: string, total: int}>}
 * }
 */
function events_edit_stats_build_result(
    array $labels,
    array $pageHumanByDay,
    array $pageBotByDay,
    array $previewHumanByDay,
    array $previewBotByDay,
    array $externalHumanByDay,
    array $externalBotByDay,
    bool $tableReady,
    bool $botReady
): array {
    $totalPageHuman = 0;
    $totalPageBot = 0;
    $totalPreview = 0;
    $totalExternal = 0;
    $pageHumanData = [];
    $pageBotData = [];
    $previewData = [];
    $externalData = [];

    foreach ($labels as $label) {
        $ph = (int) ($pageHumanByDay[$label] ?? 0);
        $pb = (int) ($pageBotByDay[$label] ?? 0);
        $vh = (int) ($previewHumanByDay[$label] ?? 0);
        $vb = (int) ($previewBotByDay[$label] ?? 0);
        $eh = (int) ($externalHumanByDay[$label] ?? 0);
        $eb = (int) ($externalBotByDay[$label] ?? 0);
        $pageHumanData[] = $ph;
        $pageBotData[] = $pb;
        $previewData[] = $vh + $vb;
        $externalData[] = $eh + $eb;
        $totalPageHuman += $ph;
        $totalPageBot += $pb;
        $totalPreview += $vh + $vb;
        $totalExternal += $eh + $eb;
    }

    $totalPage = $totalPageHuman + $totalPageBot;

    $datasets = [
        [
            'label' => 'Oldal — emberi',
            'data' => $pageHumanData,
            'color' => '#3d6b4f',
            'total' => $totalPageHuman,
        ],
        [
            'label' => 'Oldal — bot',
            'data' => $pageBotData,
            'color' => '#9aab9f',
            'total' => $totalPageBot,
        ],
    ];
    if ($tableReady) {
        $datasets[] = [
            'label' => 'Előnézet',
            'data' => $previewData,
            'color' => '#6b7fa8',
            'total' => $totalPreview,
        ];
        $datasets[] = [
            'label' => 'További info',
            'data' => $externalData,
            'color' => '#a8784a',
            'total' => $totalExternal,
        ];
    }

    return [
        'table_ready' => $tableReady,
        'bot_ready' => $botReady,
        'totals' => [
            'page_views' => $totalPage,
            'page_views_human' => $totalPageHuman,
            'page_views_bot' => $totalPageBot,
            'calendar_previews' => $totalPreview,
            'calendar_previews_human' => $totalPreview,
            'external_info_clicks' => $totalExternal,
            'external_info_clicks_human' => $totalExternal,
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
 * @param array<string, int> $pageHumanByDay
 * @param array<string, int> $pageBotByDay
 * @param array<string, int> $previewHumanByDay
 * @param array<string, int> $previewBotByDay
 * @param array<string, int> $externalHumanByDay
 * @param array<string, int> $externalBotByDay
 */
function events_edit_stats_apply_bucket_rows(
    array $rows,
    array &$pageHumanByDay,
    array &$pageBotByDay,
    array &$previewHumanByDay,
    array &$previewBotByDay,
    array &$externalHumanByDay,
    array &$externalBotByDay,
    bool $tableReady,
    bool $botReady
): void {
    foreach ($rows as $row) {
        $bucket = (string) ($row['bucket'] ?? '');
        $cnt = (int) ($row['cnt'] ?? 0);
        if ($cnt <= 0 || !array_key_exists($bucket, $pageHumanByDay)) {
            continue;
        }
        $isBot = $botReady && (int) ($row['is_bot'] ?? 0) === 1;
        if ($tableReady) {
            $metric = (string) ($row['metric_type'] ?? EVENTS_VIEW_METRIC_PAGE);
            if ($metric === EVENTS_VIEW_METRIC_CALENDAR_PREVIEW) {
                if ($isBot) {
                    $previewBotByDay[$bucket] += $cnt;
                } else {
                    $previewHumanByDay[$bucket] += $cnt;
                }
            } elseif ($metric === EVENTS_VIEW_METRIC_EXTERNAL_INFO) {
                if ($isBot) {
                    $externalBotByDay[$bucket] += $cnt;
                } else {
                    $externalHumanByDay[$bucket] += $cnt;
                }
            } elseif ($metric === EVENTS_VIEW_METRIC_PAGE) {
                if ($isBot) {
                    $pageBotByDay[$bucket] += $cnt;
                } else {
                    $pageHumanByDay[$bucket] += $cnt;
                }
            }
        } elseif ($isBot) {
            $pageBotByDay[$bucket] += $cnt;
        } else {
            $pageHumanByDay[$bucket] += $cnt;
        }
    }
}

/**
 * @return array{
 *   table_ready: bool,
 *   bot_ready: bool,
 *   totals: array<string, int>,
 *   chart: array{labels: list<string>, datasets: list<array{label: string, data: list<int>, color: string, total: int}>}
 * }
 */
function events_edit_stats_empty_result(): array
{
    return [
        'table_ready' => false,
        'bot_ready' => false,
        'totals' => [
            'page_views' => 0,
            'page_views_human' => 0,
            'page_views_bot' => 0,
            'calendar_previews' => 0,
            'calendar_previews_human' => 0,
            'external_info_clicks' => 0,
            'external_info_clicks_human' => 0,
            'unique_visitors' => 0,
        ],
        'chart' => ['labels' => [], 'datasets' => []],
    ];
}

function events_edit_stats_for_event(PDO $db, int $eventId, array $params): array
{
    $empty = events_edit_stats_empty_result();

    if ($eventId <= 0) {
        return $empty;
    }

    $dateFrom = (string) ($params['date_from'] ?? '');
    $dateTo = (string) ($params['date_to'] ?? '');
    $labels = events_edit_stats_date_labels($dateFrom, $dateTo);
    if ($labels === []) {
        return $empty;
    }

    events_view_tracking_ensure_bot_column($db);
    $tableReady = events_edit_stats_table_ready($db);
    $botReady = events_view_tracking_bot_column_ready($db);
    $pageHumanByDay = array_fill_keys($labels, 0);
    $pageBotByDay = array_fill_keys($labels, 0);
    $previewHumanByDay = array_fill_keys($labels, 0);
    $previewBotByDay = array_fill_keys($labels, 0);
    $externalHumanByDay = array_fill_keys($labels, 0);
    $externalBotByDay = array_fill_keys($labels, 0);
    $window = events_edit_stats_view_window($params);

    try {
        if ($tableReady && $botReady) {
            $stmt = $db->prepare('
                SELECT DATE(`létrehozva`) AS bucket, `metric_type`, `is_bot`, COUNT(*) AS cnt
                FROM `events_calendar_event_views`
                WHERE `esemény_id` = ?
                  AND `létrehozva` >= ?
                  AND `létrehozva` < ?
                GROUP BY bucket, `metric_type`, `is_bot`
            ');
            $stmt->execute([$eventId, $window['start_inclusive'], $window['end_exclusive']]);
        } elseif ($tableReady) {
            $stmt = $db->prepare('
                SELECT DATE(`létrehozva`) AS bucket, `metric_type`, 0 AS is_bot, COUNT(*) AS cnt
                FROM `events_calendar_event_views`
                WHERE `esemény_id` = ?
                  AND `létrehozva` >= ?
                  AND `létrehozva` < ?
                GROUP BY bucket, `metric_type`
            ');
            $stmt->execute([$eventId, $window['start_inclusive'], $window['end_exclusive']]);
        } else {
            $stmt = $db->prepare('
                SELECT DATE(`létrehozva`) AS bucket, 0 AS is_bot, COUNT(*) AS cnt
                FROM `events_calendar_event_views`
                WHERE `esemény_id` = ?
                  AND `létrehozva` >= ?
                  AND `létrehozva` < ?
                GROUP BY bucket
            ');
            $stmt->execute([$eventId, $window['start_inclusive'], $window['end_exclusive']]);
        }
        events_edit_stats_apply_bucket_rows(
            $stmt->fetchAll(PDO::FETCH_ASSOC),
            $pageHumanByDay,
            $pageBotByDay,
            $previewHumanByDay,
            $previewBotByDay,
            $externalHumanByDay,
            $externalBotByDay,
            $tableReady,
            $botReady
        );
    } catch (Throwable) {
        return $empty;
    }

    return events_edit_stats_build_result(
        $labels,
        $pageHumanByDay,
        $pageBotByDay,
        $previewHumanByDay,
        $previewBotByDay,
        $externalHumanByDay,
        $externalBotByDay,
        $tableReady,
        $botReady
    );
}

/**
 * @return array{
 *   table_ready: bool,
 *   bot_ready: bool,
 *   totals: array<string, int>,
 *   chart: array{labels: list<string>, datasets: list<array{label: string, data: list<int>, color: string, total: int}>},
 *   event_rows?: list<array<string, mixed>>,
 *   draft_rows?: list<array<string, mixed>>
 * }
 */
function events_edit_stats_for_organizer(PDO $db, int $organizerId, array $params): array
{
    return events_edit_stats_for_organizers($db, $organizerId > 0 ? [$organizerId] : [], $params);
}

/**
 * @param list<int> $organizerIds
 * @param array{date_from: string, date_to: string} $params
 * @return array<string, mixed>
 */
function events_edit_stats_for_organizers(PDO $db, array $organizerIds, array $params): array
{
    $empty = events_edit_stats_empty_result();
    $organizerIds = array_values(array_unique(array_filter(array_map('intval', $organizerIds), static fn (int $id): bool => $id > 0)));
    if ($organizerIds === []) {
        return $empty;
    }

    $dateFrom = (string) ($params['date_from'] ?? '');
    $dateTo = (string) ($params['date_to'] ?? '');
    $labels = events_edit_stats_date_labels($dateFrom, $dateTo);
    if ($labels === []) {
        return $empty;
    }

    events_view_tracking_ensure_bot_column($db);
    $tableReady = events_edit_stats_table_ready($db);
    $botReady = events_view_tracking_bot_column_ready($db);
    $pageHumanByDay = array_fill_keys($labels, 0);
    $pageBotByDay = array_fill_keys($labels, 0);
    $previewHumanByDay = array_fill_keys($labels, 0);
    $previewBotByDay = array_fill_keys($labels, 0);
    $externalHumanByDay = array_fill_keys($labels, 0);
    $externalBotByDay = array_fill_keys($labels, 0);
    $window = events_edit_stats_view_window($params);
    $orgPh = implode(',', array_fill(0, count($organizerIds), '?'));

    try {
        if ($tableReady && $botReady) {
            $stmt = $db->prepare("
                SELECT DATE(v.`létrehozva`) AS bucket, v.`metric_type`, v.`is_bot`, COUNT(*) AS cnt
                FROM `events_calendar_event_views` v
                INNER JOIN `events_calendar_event_organizers` eo ON eo.`event_id` = v.`esemény_id`
                WHERE eo.`organizer_id` IN ({$orgPh})
                  AND v.`létrehozva` >= ?
                  AND v.`létrehozva` < ?
                GROUP BY bucket, v.`metric_type`, v.`is_bot`
            ");
            $stmt->execute([...$organizerIds, $window['start_inclusive'], $window['end_exclusive']]);
        } elseif ($tableReady) {
            $stmt = $db->prepare("
                SELECT DATE(v.`létrehozva`) AS bucket, v.`metric_type`, 0 AS is_bot, COUNT(*) AS cnt
                FROM `events_calendar_event_views` v
                INNER JOIN `events_calendar_event_organizers` eo ON eo.`event_id` = v.`esemény_id`
                WHERE eo.`organizer_id` IN ({$orgPh})
                  AND v.`létrehozva` >= ?
                  AND v.`létrehozva` < ?
                GROUP BY bucket, v.`metric_type`
            ");
            $stmt->execute([...$organizerIds, $window['start_inclusive'], $window['end_exclusive']]);
        } else {
            $stmt = $db->prepare("
                SELECT DATE(v.`létrehozva`) AS bucket, 0 AS is_bot, COUNT(*) AS cnt
                FROM `events_calendar_event_views` v
                INNER JOIN `events_calendar_event_organizers` eo ON eo.`event_id` = v.`esemény_id`
                WHERE eo.`organizer_id` IN ({$orgPh})
                  AND v.`létrehozva` >= ?
                  AND v.`létrehozva` < ?
                GROUP BY bucket
            ");
            $stmt->execute([...$organizerIds, $window['start_inclusive'], $window['end_exclusive']]);
        }
        events_edit_stats_apply_bucket_rows(
            $stmt->fetchAll(PDO::FETCH_ASSOC),
            $pageHumanByDay,
            $pageBotByDay,
            $previewHumanByDay,
            $previewBotByDay,
            $externalHumanByDay,
            $externalBotByDay,
            $tableReady,
            $botReady
        );
    } catch (Throwable) {
        return $empty;
    }

    $result = events_edit_stats_build_result(
        $labels,
        $pageHumanByDay,
        $pageBotByDay,
        $previewHumanByDay,
        $previewBotByDay,
        $externalHumanByDay,
        $externalBotByDay,
        $tableReady,
        $botReady
    );
    $eventsList = events_edit_stats_organizers_events_list($db, $organizerIds, $params, $tableReady);
    $result['totals']['events_total'] = $eventsList['events_total'];
    $result['totals']['events_with_views'] = $eventsList['events_with_views'];
    $result['totals']['unique_visitors'] = events_edit_stats_unique_visitors_for_organizers(
        $db,
        $organizerIds,
        $params,
        $tableReady,
        $botReady
    );
    $result['event_rows'] = $eventsList['rows'];
    $result['draft_rows'] = $eventsList['draft_rows'];

    return $result;
}

/**
 * Egyedi emberi oldal-látogatók (DISTINCT ip_hash) a szervező(k) eseményein.
 *
 * @param list<int> $organizerIds
 * @param array{date_from: string, date_to: string} $params
 */
function events_edit_stats_unique_visitors_for_organizers(
    PDO $db,
    array $organizerIds,
    array $params,
    ?bool $tableReady = null,
    ?bool $botReady = null
): int {
    $organizerIds = array_values(array_unique(array_filter(array_map('intval', $organizerIds), static fn (int $id): bool => $id > 0)));
    if ($organizerIds === []) {
        return 0;
    }

    $tableReady = $tableReady ?? events_edit_stats_table_ready($db);
    $botReady = $botReady ?? events_view_tracking_bot_column_ready($db);
    $window = events_edit_stats_view_window($params);
    $orgPh = implode(',', array_fill(0, count($organizerIds), '?'));

    $metricAnd = $tableReady ? " AND v.`metric_type` = 'page_view'" : '';
    $botAnd = $botReady ? ' AND v.`is_bot` = 0' : '';

    try {
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT v.`ip_hash`)
            FROM `events_calendar_event_views` v
            INNER JOIN `events_calendar_event_organizers` eo ON eo.`event_id` = v.`esemény_id`
            WHERE eo.`organizer_id` IN ({$orgPh})
              AND v.`létrehozva` >= ?
              AND v.`létrehozva` < ?
              AND v.`ip_hash` IS NOT NULL
              AND v.`ip_hash` <> ''
              {$metricAnd}
              {$botAnd}
        ");
        $stmt->execute([...$organizerIds, $window['start_inclusive'], $window['end_exclusive']]);

        return (int) $stmt->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
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
    return events_edit_stats_organizers_events_list($db, $organizerId > 0 ? [$organizerId] : [], $params, $tableReady);
}

/**
 * @param list<int> $organizerIds
 * @param array{date_from: string, date_to: string} $params
 * @return array{rows: list<array<string, mixed>>, draft_rows: list<array<string, mixed>>, events_total: int, events_with_views: int}
 */
function events_edit_stats_organizers_events_list(PDO $db, array $organizerIds, array $params, ?bool $tableReady = null): array
{
    $empty = ['rows' => [], 'draft_rows' => [], 'events_total' => 0, 'events_with_views' => 0];
    $organizerIds = array_values(array_unique(array_filter(array_map('intval', $organizerIds), static fn (int $id): bool => $id > 0)));
    if ($organizerIds === []) {
        return $empty;
    }

    events_view_tracking_ensure_bot_column($db);
    $tableReady = $tableReady ?? events_edit_stats_table_ready($db);
    $botReady = events_view_tracking_bot_column_ready($db);
    $window = events_edit_stats_view_window($params);
    $orgPh = implode(',', array_fill(0, count($organizerIds), '?'));

    // Időablakos COUNT-ok.
    $timeAnd = ' AND v.`létrehozva` >= ? AND v.`létrehozva` < ?';
    $pageBase = "FROM `events_calendar_event_views` v WHERE v.`esemény_id` = e.`id`{$timeAnd}";
    $previewBase = "FROM `events_calendar_event_views` v WHERE v.`esemény_id` = e.`id` AND v.`metric_type` = 'calendar_preview'{$timeAnd}";
    $externalBase = "FROM `events_calendar_event_views` v WHERE v.`esemény_id` = e.`id` AND v.`metric_type` = 'external_info_click'{$timeAnd}";
    $pageTypeAnd = $tableReady ? " AND v.`metric_type` = 'page_view'" : '';

    if ($botReady) {
        $pageHumanSql = "(SELECT COUNT(*) {$pageBase}{$pageTypeAnd} AND v.`is_bot` = 0)";
        $pageBotSql = "(SELECT COUNT(*) {$pageBase}{$pageTypeAnd} AND v.`is_bot` = 1)";
        $pageTotalSql = "(SELECT COUNT(*) {$pageBase}{$pageTypeAnd})";
        $pageUniqueSql = "(SELECT COUNT(DISTINCT v.`ip_hash`) {$pageBase}{$pageTypeAnd} AND v.`is_bot` = 0 AND v.`ip_hash` IS NOT NULL AND v.`ip_hash` <> '')";
        $previewTotalSql = "(SELECT COUNT(*) {$previewBase})";
        $externalTotalSql = "(SELECT COUNT(*) {$externalBase})";
    } else {
        $pageTotalSql = "(SELECT COUNT(*) {$pageBase}{$pageTypeAnd})";
        $pageHumanSql = $pageTotalSql;
        $pageBotSql = '0';
        $pageUniqueSql = "(SELECT COUNT(DISTINCT v.`ip_hash`) {$pageBase}{$pageTypeAnd} AND v.`ip_hash` IS NOT NULL AND v.`ip_hash` <> '')";
        $previewTotalSql = "(SELECT COUNT(*) {$previewBase})";
        $externalTotalSql = "(SELECT COUNT(*) {$externalBase})";
    }

    $sql = "
        SELECT e.*,
            {$pageHumanSql} AS megtekintesek_human,
            {$pageBotSql} AS megtekintesek_bot,
            {$pageTotalSql} AS megtekintesek,
            {$pageUniqueSql} AS egyedi_latogatok"
        . ($tableReady ? ",
            {$previewTotalSql} AS naptar_elonezetek,
            {$externalTotalSql} AS tovabbi_info_kattintasok" : '') . "
        FROM `events_calendar_events` e
        WHERE e.`id` IN (
            SELECT eo.`event_id`
            FROM `events_calendar_event_organizers` eo
            WHERE eo.`organizer_id` IN ({$orgPh})
        )
        ORDER BY e.`event_start` IS NULL, e.`event_start` DESC, e.`id` DESC
    ";

    $executeParams = [];
    // page human
    $executeParams[] = $window['start_inclusive'];
    $executeParams[] = $window['end_exclusive'];
    if ($botReady) {
        // page bot
        $executeParams[] = $window['start_inclusive'];
        $executeParams[] = $window['end_exclusive'];
    }
    // page total
    $executeParams[] = $window['start_inclusive'];
    $executeParams[] = $window['end_exclusive'];
    // page unique
    $executeParams[] = $window['start_inclusive'];
    $executeParams[] = $window['end_exclusive'];
    if ($tableReady) {
        // preview total
        $executeParams[] = $window['start_inclusive'];
        $executeParams[] = $window['end_exclusive'];
        // external info total
        $executeParams[] = $window['start_inclusive'];
        $executeParams[] = $window['end_exclusive'];
    }
    foreach ($organizerIds as $oid) {
        $executeParams[] = $oid;
    }

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
        $views = (int) ($row['megtekintesek'] ?? 0)
            + (int) ($row['naptar_elonezetek'] ?? 0)
            + (int) ($row['tovabbi_info_kattintasok'] ?? 0);
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
