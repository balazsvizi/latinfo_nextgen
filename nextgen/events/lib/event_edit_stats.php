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
 * Gyors időszak presetek (sorrend = UI sorrend).
 *
 * @return list<array{id: string, label: string}>
 */
function events_edit_stats_presets(): array
{
    return [
        ['id' => 'today', 'label' => 'Ma'],
        ['id' => '7', 'label' => '7 nap'],
        ['id' => '30', 'label' => '30 nap'],
        ['id' => 'month', 'label' => 'Ez a hónap'],
        ['id' => 'last_month', 'label' => 'Előző hónap'],
        ['id' => '90', 'label' => '90 nap'],
        ['id' => 'ytd', 'label' => 'Idei év'],
        ['id' => 'year', 'label' => '1 év'],
        ['id' => 'all', 'label' => 'Összes'],
    ];
}

/**
 * Gyors időszak-preset tartomány.
 *
 * @return array{date_from: string, date_to: string}
 */
function events_edit_stats_range_for_preset(string $preset, ?string $allFromYmd = null): array
{
    $today = new DateTimeImmutable('today');
    $to = $today->format('Y-m-d');

    return match ($preset) {
        'today' => [
            'date_from' => $to,
            'date_to' => $to,
        ],
        '7' => [
            'date_from' => $today->modify('-6 days')->format('Y-m-d'),
            'date_to' => $to,
        ],
        'month' => [
            'date_from' => $today->modify('first day of this month')->format('Y-m-d'),
            'date_to' => $to,
        ],
        'last_month' => [
            'date_from' => $today->modify('first day of last month')->format('Y-m-d'),
            'date_to' => $today->modify('last day of last month')->format('Y-m-d'),
        ],
        '90' => [
            'date_from' => $today->modify('-89 days')->format('Y-m-d'),
            'date_to' => $to,
        ],
        'ytd' => [
            'date_from' => $today->format('Y-01-01'),
            'date_to' => $to,
        ],
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
    foreach (events_edit_stats_presets() as $preset) {
        $id = (string) $preset['id'];
        $range = events_edit_stats_range_for_preset($id, $allFromYmd);
        if ($range['date_from'] === $from && $range['date_to'] === $to) {
            return $id;
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
 *     calendar_previews_bot: int,
 *     external_info_clicks: int,
 *     external_info_clicks_human: int,
 *     external_info_clicks_bot: int,
 *     unique_visitors?: int,
 *     unique_visitors_human?: int,
 *     unique_visitors_bot?: int
 *   },
 *   chart: array{
 *     labels: list<string>,
 *     default_mode: string,
 *     modes: array<string, array{datasets: list<array{label: string, data: list<int>, color: string, total: int}>}>,
 *     datasets: list<array{label: string, data: list<int>, color: string, total: int}>
 *   }
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
    $totalPreviewHuman = 0;
    $totalPreviewBot = 0;
    $totalExternalHuman = 0;
    $totalExternalBot = 0;
    $pageHumanData = [];
    $pageBotData = [];
    $pageTotalData = [];
    $previewHumanData = [];
    $previewBotData = [];
    $previewTotalData = [];
    $externalHumanData = [];
    $externalBotData = [];
    $externalTotalData = [];

    foreach ($labels as $label) {
        $ph = (int) ($pageHumanByDay[$label] ?? 0);
        $pb = (int) ($pageBotByDay[$label] ?? 0);
        $vh = (int) ($previewHumanByDay[$label] ?? 0);
        $vb = (int) ($previewBotByDay[$label] ?? 0);
        $eh = (int) ($externalHumanByDay[$label] ?? 0);
        $eb = (int) ($externalBotByDay[$label] ?? 0);
        $pageHumanData[] = $ph;
        $pageBotData[] = $pb;
        $pageTotalData[] = $ph + $pb;
        $previewHumanData[] = $vh;
        $previewBotData[] = $vb;
        $previewTotalData[] = $vh + $vb;
        $externalHumanData[] = $eh;
        $externalBotData[] = $eb;
        $externalTotalData[] = $eh + $eb;
        $totalPageHuman += $ph;
        $totalPageBot += $pb;
        $totalPreviewHuman += $vh;
        $totalPreviewBot += $vb;
        $totalExternalHuman += $eh;
        $totalExternalBot += $eb;
    }

    $totalPage = $totalPageHuman + $totalPageBot;
    $totalPreview = $totalPreviewHuman + $totalPreviewBot;
    $totalExternal = $totalExternalHuman + $totalExternalBot;

    $datasetsHuman = [
        [
            'label' => 'Oldal',
            'data' => $pageHumanData,
            'color' => '#3d6b4f',
            'total' => $totalPageHuman,
        ],
    ];
    $datasetsTotal = [
        [
            'label' => 'Oldal',
            'data' => $pageTotalData,
            'color' => '#3d6b4f',
            'total' => $totalPage,
        ],
    ];
    if ($tableReady) {
        $datasetsHuman[] = [
            'label' => 'Előnézet',
            'data' => $previewHumanData,
            'color' => '#6b7fa8',
            'total' => $totalPreviewHuman,
        ];
        $datasetsHuman[] = [
            'label' => 'További info',
            'data' => $externalHumanData,
            'color' => '#a8784a',
            'total' => $totalExternalHuman,
        ];
        $datasetsTotal[] = [
            'label' => 'Előnézet',
            'data' => $previewTotalData,
            'color' => '#6b7fa8',
            'total' => $totalPreview,
        ];
        $datasetsTotal[] = [
            'label' => 'További info',
            'data' => $externalTotalData,
            'color' => '#a8784a',
            'total' => $totalExternal,
        ];
    }

    // Régi admin nézet kompatibilitás.
    $datasetsLegacy = [
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
        $datasetsLegacy[] = [
            'label' => 'Előnézet',
            'data' => $previewTotalData,
            'color' => '#6b7fa8',
            'total' => $totalPreview,
        ];
        $datasetsLegacy[] = [
            'label' => 'További info',
            'data' => $externalTotalData,
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
            'calendar_previews_human' => $totalPreviewHuman,
            'calendar_previews_bot' => $totalPreviewBot,
            'external_info_clicks' => $totalExternal,
            'external_info_clicks_human' => $totalExternalHuman,
            'external_info_clicks_bot' => $totalExternalBot,
        ],
        'chart' => [
            'labels' => events_edit_stats_chart_labels($labels),
            'default_mode' => 'human',
            'modes' => [
                'human' => ['datasets' => $datasetsHuman],
                'total' => ['datasets' => $datasetsTotal],
            ],
            'datasets' => $datasetsLegacy,
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
            'calendar_previews_bot' => 0,
            'external_info_clicks' => 0,
            'external_info_clicks_human' => 0,
            'external_info_clicks_bot' => 0,
            'unique_visitors' => 0,
            'unique_visitors_human' => 0,
            'unique_visitors_bot' => 0,
            'events_in_period' => 0,
            'events_opened' => 0,
            'events_total' => 0,
            'events_with_views' => 0,
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
    $uniqueCounts = events_edit_stats_unique_visitor_counts_for_event(
        $db,
        $eventId,
        $params,
        $tableReady,
        $botReady
    );
    $result['totals']['unique_visitors'] = $uniqueCounts['human'];
    $result['totals']['unique_visitors_human'] = $uniqueCounts['human'];
    $result['totals']['unique_visitors_bot'] = $uniqueCounts['bot'];

    return $result;
}

/**
 * Egyedi oldal-látogatók (DISTINCT ip_hash) egy eseményre, emberi / bot bontásban.
 *
 * @param array{date_from: string, date_to: string} $params
 * @return array{human: int, bot: int}
 */
function events_edit_stats_unique_visitor_counts_for_event(
    PDO $db,
    int $eventId,
    array $params,
    ?bool $tableReady = null,
    ?bool $botReady = null
): array {
    $empty = ['human' => 0, 'bot' => 0];
    if ($eventId <= 0) {
        return $empty;
    }

    $tableReady = $tableReady ?? events_edit_stats_table_ready($db);
    $botReady = $botReady ?? events_view_tracking_bot_column_ready($db);
    $window = events_edit_stats_view_window($params);
    $metricAnd = $tableReady ? " AND `metric_type` = 'page_view'" : '';

    $countFor = static function (string $botAnd) use ($db, $eventId, $window, $metricAnd): int {
        try {
            $stmt = $db->prepare("
                SELECT COUNT(DISTINCT `ip_hash`)
                FROM `events_calendar_event_views`
                WHERE `esemény_id` = ?
                  AND `létrehozva` >= ?
                  AND `létrehozva` < ?
                  AND `ip_hash` IS NOT NULL
                  AND `ip_hash` <> ''
                  {$metricAnd}
                  {$botAnd}
            ");
            $stmt->execute([$eventId, $window['start_inclusive'], $window['end_exclusive']]);

            return (int) $stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    };

    if ($botReady) {
        return [
            'human' => $countFor(' AND `is_bot` = 0'),
            'bot' => $countFor(' AND `is_bot` = 1'),
        ];
    }

    return [
        'human' => $countFor(''),
        'bot' => 0,
    ];
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
    $result['totals']['events_in_period'] = $eventsList['events_in_period'];
    $result['totals']['events_opened'] = $eventsList['events_with_views'];
    $uniqueCounts = events_edit_stats_unique_visitor_counts_for_organizers(
        $db,
        $organizerIds,
        $params,
        $tableReady,
        $botReady
    );
    $result['totals']['unique_visitors'] = $uniqueCounts['human'];
    $result['totals']['unique_visitors_human'] = $uniqueCounts['human'];
    $result['totals']['unique_visitors_bot'] = $uniqueCounts['bot'];
    $result['event_rows'] = $eventsList['rows'];
    $result['draft_rows'] = $eventsList['draft_rows'];

    return $result;
}

/**
 * Egyedi oldal-látogatók (DISTINCT ip_hash) emberi / bot bontásban.
 *
 * @param list<int> $organizerIds
 * @param array{date_from: string, date_to: string} $params
 * @return array{human: int, bot: int}
 */
function events_edit_stats_unique_visitor_counts_for_organizers(
    PDO $db,
    array $organizerIds,
    array $params,
    ?bool $tableReady = null,
    ?bool $botReady = null
): array {
    $empty = ['human' => 0, 'bot' => 0];
    $organizerIds = array_values(array_unique(array_filter(array_map('intval', $organizerIds), static fn (int $id): bool => $id > 0)));
    if ($organizerIds === []) {
        return $empty;
    }

    $tableReady = $tableReady ?? events_edit_stats_table_ready($db);
    $botReady = $botReady ?? events_view_tracking_bot_column_ready($db);
    $window = events_edit_stats_view_window($params);
    $orgPh = implode(',', array_fill(0, count($organizerIds), '?'));
    $metricAnd = $tableReady ? " AND v.`metric_type` = 'page_view'" : '';

    $countFor = static function (string $botAnd) use ($db, $organizerIds, $orgPh, $window, $metricAnd): int {
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
    };

    if ($botReady) {
        return [
            'human' => $countFor(' AND v.`is_bot` = 0'),
            'bot' => $countFor(' AND v.`is_bot` = 1'),
        ];
    }

    return [
        'human' => $countFor(''),
        'bot' => 0,
    ];
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
    $counts = events_edit_stats_unique_visitor_counts_for_organizers($db, $organizerIds, $params, $tableReady, $botReady);

    return (int) $counts['human'];
}

/**
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

/**
 * Esemény naptár-dátuma esik-e a választott időszakba.
 *
 * @param array<string, mixed> $row
 */
function events_edit_stats_event_overlaps_period(array $row, string $dateFrom, string $dateTo): bool
{
    $rawStart = trim((string) ($row['event_start'] ?? ''));
    $rawEnd = trim((string) ($row['event_end'] ?? ''));
    if ($rawStart === '' && $rawEnd === '') {
        return false;
    }

    try {
        $start = $rawStart !== ''
            ? (new DateTimeImmutable($rawStart))->format('Y-m-d')
            : (new DateTimeImmutable($rawEnd))->format('Y-m-d');
        $end = $rawEnd !== ''
            ? (new DateTimeImmutable($rawEnd))->format('Y-m-d')
            : $start;
    } catch (Throwable) {
        return false;
    }

    if ($end < $dateFrom || $start > $dateTo) {
        return false;
    }

    return true;
}

/**
 * Hány napig volt „kint” az oldal a választott időszakban (közzétett státusz + létrehozás napjától).
 *
 * @param array<string, mixed> $row
 */
function events_edit_stats_live_days_in_period(array $row, string $dateFrom, string $dateTo): int
{
    $publishedStatus = events_public_post_status();
    $status = (string) ($row['event_status'] ?? '');
    if ($status !== $publishedStatus) {
        return 0;
    }

    $createdRaw = trim((string) ($row['created'] ?? ''));
    if ($createdRaw === '') {
        return 0;
    }

    try {
        $goLive = (new DateTimeImmutable($createdRaw))->setTime(0, 0, 0);
        $from = new DateTimeImmutable($dateFrom);
        $to = new DateTimeImmutable($dateTo);
    } catch (Throwable) {
        return 0;
    }

    if ($goLive > $to) {
        return 0;
    }

    $liveFrom = $goLive > $from ? $goLive : $from;
    if ($liveFrom > $to) {
        return 0;
    }

    return $liveFrom->diff($to)->days + 1;
}

function events_edit_stats_organizer_events_list(PDO $db, int $organizerId, array $params, ?bool $tableReady = null): array
{
    return events_edit_stats_organizers_events_list($db, $organizerId > 0 ? [$organizerId] : [], $params, $tableReady);
}

/**
 * @param list<int> $organizerIds
 * @param array{date_from: string, date_to: string} $params
 * @return array{
 *   rows: list<array<string, mixed>>,
 *   draft_rows: list<array<string, mixed>>,
 *   events_total: int,
 *   events_with_views: int,
 *   events_in_period: int
 * }
 */
function events_edit_stats_organizers_events_list(PDO $db, array $organizerIds, array $params, ?bool $tableReady = null): array
{
    $empty = [
        'rows' => [],
        'draft_rows' => [],
        'events_total' => 0,
        'events_with_views' => 0,
        'events_in_period' => 0,
    ];
    $organizerIds = array_values(array_unique(array_filter(array_map('intval', $organizerIds), static fn (int $id): bool => $id > 0)));
    if ($organizerIds === []) {
        return $empty;
    }

    events_view_tracking_ensure_bot_column($db);
    $tableReady = $tableReady ?? events_edit_stats_table_ready($db);
    $botReady = events_view_tracking_bot_column_ready($db);
    $window = events_edit_stats_view_window($params);
    $dateFrom = (string) ($params['date_from'] ?? '');
    $dateTo = (string) ($params['date_to'] ?? '');
    $orgPh = implode(',', array_fill(0, count($organizerIds), '?'));

    $timeAnd = ' AND v.`létrehozva` >= ? AND v.`létrehozva` < ?';
    $pageBase = "FROM `events_calendar_event_views` v WHERE v.`esemény_id` = e.`id`{$timeAnd}";
    $previewBase = "FROM `events_calendar_event_views` v WHERE v.`esemény_id` = e.`id` AND v.`metric_type` = 'calendar_preview'{$timeAnd}";
    $externalBase = "FROM `events_calendar_event_views` v WHERE v.`esemény_id` = e.`id` AND v.`metric_type` = 'external_info_click'{$timeAnd}";
    $pageTypeAnd = $tableReady ? " AND v.`metric_type` = 'page_view'" : '';
    $ipOk = " AND v.`ip_hash` IS NOT NULL AND v.`ip_hash` <> ''";

    if ($botReady) {
        $pageHumanSql = "(SELECT COUNT(*) {$pageBase}{$pageTypeAnd} AND v.`is_bot` = 0)";
        $pageBotSql = "(SELECT COUNT(*) {$pageBase}{$pageTypeAnd} AND v.`is_bot` = 1)";
        $pageTotalSql = "(SELECT COUNT(*) {$pageBase}{$pageTypeAnd})";
        $pageUniqueHumanSql = "(SELECT COUNT(DISTINCT v.`ip_hash`) {$pageBase}{$pageTypeAnd} AND v.`is_bot` = 0{$ipOk})";
        $pageUniqueBotSql = "(SELECT COUNT(DISTINCT v.`ip_hash`) {$pageBase}{$pageTypeAnd} AND v.`is_bot` = 1{$ipOk})";
        $previewHumanSql = "(SELECT COUNT(*) {$previewBase} AND v.`is_bot` = 0)";
        $previewBotSql = "(SELECT COUNT(*) {$previewBase} AND v.`is_bot` = 1)";
        $previewTotalSql = "(SELECT COUNT(*) {$previewBase})";
        $externalHumanSql = "(SELECT COUNT(*) {$externalBase} AND v.`is_bot` = 0)";
        $externalBotSql = "(SELECT COUNT(*) {$externalBase} AND v.`is_bot` = 1)";
        $externalTotalSql = "(SELECT COUNT(*) {$externalBase})";
    } else {
        $pageTotalSql = "(SELECT COUNT(*) {$pageBase}{$pageTypeAnd})";
        $pageHumanSql = $pageTotalSql;
        $pageBotSql = '0';
        $pageUniqueHumanSql = "(SELECT COUNT(DISTINCT v.`ip_hash`) {$pageBase}{$pageTypeAnd}{$ipOk})";
        $pageUniqueBotSql = '0';
        $previewTotalSql = "(SELECT COUNT(*) {$previewBase})";
        $previewHumanSql = $previewTotalSql;
        $previewBotSql = '0';
        $externalTotalSql = "(SELECT COUNT(*) {$externalBase})";
        $externalHumanSql = $externalTotalSql;
        $externalBotSql = '0';
    }

    $sql = "
        SELECT e.*,
            {$pageHumanSql} AS megtekintesek_human,
            {$pageBotSql} AS megtekintesek_bot,
            {$pageTotalSql} AS megtekintesek,
            {$pageUniqueHumanSql} AS egyedi_latogatok_human,
            {$pageUniqueBotSql} AS egyedi_latogatok_bot"
        . ($tableReady ? ",
            {$previewHumanSql} AS naptar_elonezetek_human,
            {$previewBotSql} AS naptar_elonezetek_bot,
            {$previewTotalSql} AS naptar_elonezetek,
            {$externalHumanSql} AS tovabbi_info_kattintasok_human,
            {$externalBotSql} AS tovabbi_info_kattintasok_bot,
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
    $pushWindow = static function () use (&$executeParams, $window): void {
        $executeParams[] = $window['start_inclusive'];
        $executeParams[] = $window['end_exclusive'];
    };
    $pushSqlParams = static function (string $sqlFragment) use ($pushWindow): void {
        if (str_contains($sqlFragment, '?')) {
            $pushWindow();
        }
    };

    $pushSqlParams($pageHumanSql);
    $pushSqlParams($pageBotSql);
    $pushSqlParams($pageTotalSql);
    $pushSqlParams($pageUniqueHumanSql);
    $pushSqlParams($pageUniqueBotSql);
    if ($tableReady) {
        $pushSqlParams($previewHumanSql);
        $pushSqlParams($previewBotSql);
        $pushSqlParams($previewTotalSql);
        $pushSqlParams($externalHumanSql);
        $pushSqlParams($externalBotSql);
        $pushSqlParams($externalTotalSql);
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
    $eventsInPeriod = 0;
    foreach ($nonDraftRows as $idx => $row) {
        $page = (int) ($row['megtekintesek'] ?? 0);
        $preview = (int) ($row['naptar_elonezetek'] ?? 0);
        $external = (int) ($row['tovabbi_info_kattintasok'] ?? 0);
        if (($page + $preview + $external) > 0) {
            $eventsWithViews++;
        }
        if (events_edit_stats_event_overlaps_period($row, $dateFrom, $dateTo)) {
            $eventsInPeriod++;
        }
        $nonDraftRows[$idx]['live_days'] = events_edit_stats_live_days_in_period($row, $dateFrom, $dateTo);
        $nonDraftRows[$idx]['egyedi_latogatok'] = (int) ($row['egyedi_latogatok_human'] ?? $row['egyedi_latogatok'] ?? 0);
        $nonDraftRows[$idx]['egyedi_latogatok_bot'] = (int) ($row['egyedi_latogatok_bot'] ?? 0);
        if (!$tableReady) {
            $nonDraftRows[$idx]['naptar_elonezetek'] = 0;
            $nonDraftRows[$idx]['naptar_elonezetek_human'] = 0;
            $nonDraftRows[$idx]['naptar_elonezetek_bot'] = 0;
            $nonDraftRows[$idx]['tovabbi_info_kattintasok'] = 0;
            $nonDraftRows[$idx]['tovabbi_info_kattintasok_human'] = 0;
            $nonDraftRows[$idx]['tovabbi_info_kattintasok_bot'] = 0;
        }
    }

    return [
        'rows' => $nonDraftRows,
        'draft_rows' => $draftRows,
        'events_total' => count($nonDraftRows),
        'events_with_views' => $eventsWithViews,
        'events_in_period' => $eventsInPeriod,
    ];
}

/**
 * Legkorábbi megtekintés napja az összes eseményen, vagy null.
 */
function events_edit_stats_earliest_view_date_all(PDO $db): ?string
{
    try {
        $day = $db->query('SELECT DATE(MIN(`létrehozva`)) AS first_day FROM `events_calendar_event_views`')->fetchColumn();
        if (!is_string($day) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) !== 1) {
            return null;
        }

        return $day;
    } catch (Throwable $e) {
        error_log('events_edit_stats_earliest_view_date_all: ' . $e->getMessage());

        return null;
    }
}

/**
 * Oldalmegtekintések (emberi / bot) az összes eseményen, adott időszakban.
 *
 * @param array{date_from: string, date_to: string} $params
 * @return array{human: int, bot: int, total: int}
 */
function events_edit_stats_page_views_all(PDO $db, array $params): array
{
    $empty = ['human' => 0, 'bot' => 0, 'total' => 0];
    $window = events_edit_stats_view_window($params);
    $tableReady = events_edit_stats_table_ready($db);
    $botReady = events_view_tracking_bot_column_ready($db);
    $metricAnd = $tableReady ? " AND `metric_type` = 'page_view'" : '';

    $countFor = static function (string $botAnd) use ($db, $window, $metricAnd): int {
        try {
            $stmt = $db->prepare("
                SELECT COUNT(*)
                FROM `events_calendar_event_views`
                WHERE `létrehozva` >= ?
                  AND `létrehozva` < ?
                  {$metricAnd}
                  {$botAnd}
            ");
            $stmt->execute([$window['start_inclusive'], $window['end_exclusive']]);

            return (int) $stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    };

    if ($botReady) {
        $human = $countFor(' AND `is_bot` = 0');
        $bot = $countFor(' AND `is_bot` = 1');

        return [
            'human' => $human,
            'bot' => $bot,
            'total' => $human + $bot,
        ];
    }

    $total = $countFor('');

    return [
        'human' => $total,
        'bot' => 0,
        'total' => $total,
    ];
}

/**
 * @param array{date_from: string, date_to: string} $params
 * @return array{human: int, bot: int}
 */
function events_edit_stats_unique_visitor_counts_all(
    PDO $db,
    array $params,
    ?bool $tableReady = null,
    ?bool $botReady = null
): array {
    $empty = ['human' => 0, 'bot' => 0];
    $tableReady = $tableReady ?? events_edit_stats_table_ready($db);
    $botReady = $botReady ?? events_view_tracking_bot_column_ready($db);
    $window = events_edit_stats_view_window($params);
    $metricAnd = $tableReady ? " AND `metric_type` = 'page_view'" : '';

    $countFor = static function (string $botAnd) use ($db, $window, $metricAnd): int {
        try {
            $stmt = $db->prepare("
                SELECT COUNT(DISTINCT `ip_hash`)
                FROM `events_calendar_event_views`
                WHERE `létrehozva` >= ?
                  AND `létrehozva` < ?
                  AND `ip_hash` IS NOT NULL
                  AND `ip_hash` <> ''
                  {$metricAnd}
                  {$botAnd}
            ");
            $stmt->execute([$window['start_inclusive'], $window['end_exclusive']]);

            return (int) $stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    };

    if ($botReady) {
        return [
            'human' => $countFor(' AND `is_bot` = 0'),
            'bot' => $countFor(' AND `is_bot` = 1'),
        ];
    }

    return [
        'human' => $countFor(''),
        'bot' => 0,
    ];
}

/**
 * Statisztika az összes eseményre (admin Stat modul).
 *
 * @param array{date_from: string, date_to: string} $params
 * @return array<string, mixed>
 */
function events_edit_stats_for_all_events(PDO $db, array $params, int $eventListLimit = 80): array
{
    $empty = events_edit_stats_empty_result();
    $empty['event_rows'] = [];
    $empty['draft_rows'] = [];

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
                WHERE `létrehozva` >= ?
                  AND `létrehozva` < ?
                GROUP BY bucket, `metric_type`, `is_bot`
            ');
            $stmt->execute([$window['start_inclusive'], $window['end_exclusive']]);
        } elseif ($tableReady) {
            $stmt = $db->prepare('
                SELECT DATE(`létrehozva`) AS bucket, `metric_type`, 0 AS is_bot, COUNT(*) AS cnt
                FROM `events_calendar_event_views`
                WHERE `létrehozva` >= ?
                  AND `létrehozva` < ?
                GROUP BY bucket, `metric_type`
            ');
            $stmt->execute([$window['start_inclusive'], $window['end_exclusive']]);
        } else {
            $stmt = $db->prepare('
                SELECT DATE(`létrehozva`) AS bucket, 0 AS is_bot, COUNT(*) AS cnt
                FROM `events_calendar_event_views`
                WHERE `létrehozva` >= ?
                  AND `létrehozva` < ?
                GROUP BY bucket
            ');
            $stmt->execute([$window['start_inclusive'], $window['end_exclusive']]);
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
    } catch (Throwable $e) {
        error_log('events_edit_stats_for_all_events: ' . $e->getMessage());

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

    $eventsList = events_edit_stats_all_events_list($db, $params, $tableReady, $eventListLimit);
    $result['totals']['events_total'] = $eventsList['events_total'];
    $result['totals']['events_with_views'] = $eventsList['events_with_views'];
    $result['totals']['events_in_period'] = $eventsList['events_in_period'];
    $result['totals']['events_opened'] = $eventsList['events_with_views'];
    $uniqueCounts = events_edit_stats_unique_visitor_counts_all($db, $params, $tableReady, $botReady);
    $result['totals']['unique_visitors'] = $uniqueCounts['human'];
    $result['totals']['unique_visitors_human'] = $uniqueCounts['human'];
    $result['totals']['unique_visitors_bot'] = $uniqueCounts['bot'];
    $result['event_rows'] = $eventsList['rows'];
    $result['draft_rows'] = $eventsList['draft_rows'];

    return $result;
}

/**
 * Top események megtekintés szerint + összesített eseményszámok (draft nélkül).
 *
 * @param array{date_from: string, date_to: string} $params
 * @return array{
 *   rows: list<array<string, mixed>>,
 *   draft_rows: list<array<string, mixed>>,
 *   events_total: int,
 *   events_with_views: int,
 *   events_in_period: int
 * }
 */
function events_edit_stats_all_events_list(
    PDO $db,
    array $params,
    ?bool $tableReady = null,
    int $limit = 80
): array {
    $empty = [
        'rows' => [],
        'draft_rows' => [],
        'events_total' => 0,
        'events_with_views' => 0,
        'events_in_period' => 0,
    ];

    $limit = max(1, min(200, $limit));
    events_view_tracking_ensure_bot_column($db);
    $tableReady = $tableReady ?? events_edit_stats_table_ready($db);
    $botReady = events_view_tracking_bot_column_ready($db);
    $window = events_edit_stats_view_window($params);
    $dateFrom = (string) ($params['date_from'] ?? '');
    $dateTo = (string) ($params['date_to'] ?? '');

    $pageTypeAnd = $tableReady ? " AND v.`metric_type` = 'page_view'" : '';
    $previewTypeAnd = $tableReady ? " AND v.`metric_type` = 'calendar_preview'" : ' AND 1=0';
    $externalTypeAnd = $tableReady ? " AND v.`metric_type` = 'external_info_click'" : ' AND 1=0';
    $ipOk = "v.`ip_hash` IS NOT NULL AND v.`ip_hash` <> ''";

    if ($botReady) {
        $pageHumanSql = "SUM(CASE WHEN 1=1{$pageTypeAnd} AND v.`is_bot` = 0 THEN 1 ELSE 0 END)";
        $pageBotSql = "SUM(CASE WHEN 1=1{$pageTypeAnd} AND v.`is_bot` = 1 THEN 1 ELSE 0 END)";
        $pageTotalSql = "SUM(CASE WHEN 1=1{$pageTypeAnd} THEN 1 ELSE 0 END)";
        $pageUniqueHumanSql = "COUNT(DISTINCT CASE WHEN 1=1{$pageTypeAnd} AND v.`is_bot` = 0 AND {$ipOk} THEN v.`ip_hash` END)";
        $pageUniqueBotSql = "COUNT(DISTINCT CASE WHEN 1=1{$pageTypeAnd} AND v.`is_bot` = 1 AND {$ipOk} THEN v.`ip_hash` END)";
        $previewHumanSql = "SUM(CASE WHEN 1=1{$previewTypeAnd} AND v.`is_bot` = 0 THEN 1 ELSE 0 END)";
        $previewBotSql = "SUM(CASE WHEN 1=1{$previewTypeAnd} AND v.`is_bot` = 1 THEN 1 ELSE 0 END)";
        $previewTotalSql = "SUM(CASE WHEN 1=1{$previewTypeAnd} THEN 1 ELSE 0 END)";
        $externalHumanSql = "SUM(CASE WHEN 1=1{$externalTypeAnd} AND v.`is_bot` = 0 THEN 1 ELSE 0 END)";
        $externalBotSql = "SUM(CASE WHEN 1=1{$externalTypeAnd} AND v.`is_bot` = 1 THEN 1 ELSE 0 END)";
        $externalTotalSql = "SUM(CASE WHEN 1=1{$externalTypeAnd} THEN 1 ELSE 0 END)";
    } else {
        $pageTotalSql = "SUM(CASE WHEN 1=1{$pageTypeAnd} THEN 1 ELSE 0 END)";
        $pageHumanSql = $pageTotalSql;
        $pageBotSql = '0';
        $pageUniqueHumanSql = "COUNT(DISTINCT CASE WHEN 1=1{$pageTypeAnd} AND {$ipOk} THEN v.`ip_hash` END)";
        $pageUniqueBotSql = '0';
        $previewTotalSql = "SUM(CASE WHEN 1=1{$previewTypeAnd} THEN 1 ELSE 0 END)";
        $previewHumanSql = $previewTotalSql;
        $previewBotSql = '0';
        $externalTotalSql = "SUM(CASE WHEN 1=1{$externalTypeAnd} THEN 1 ELSE 0 END)";
        $externalHumanSql = $externalTotalSql;
        $externalBotSql = '0';
    }

    $sql = "
        SELECT e.`id`, e.`event_name`, e.`event_slug`, e.`event_status`, e.`event_start`, e.`event_end`, e.`created`,
            {$pageHumanSql} AS megtekintesek_human,
            {$pageBotSql} AS megtekintesek_bot,
            {$pageTotalSql} AS megtekintesek,
            {$pageUniqueHumanSql} AS egyedi_latogatok_human,
            {$pageUniqueBotSql} AS egyedi_latogatok_bot,
            {$previewHumanSql} AS naptar_elonezetek_human,
            {$previewBotSql} AS naptar_elonezetek_bot,
            {$previewTotalSql} AS naptar_elonezetek,
            {$externalHumanSql} AS tovabbi_info_kattintasok_human,
            {$externalBotSql} AS tovabbi_info_kattintasok_bot,
            {$externalTotalSql} AS tovabbi_info_kattintasok
        FROM `events_calendar_events` e
        INNER JOIN `events_calendar_event_views` v
            ON v.`esemény_id` = e.`id`
           AND v.`létrehozva` >= ?
           AND v.`létrehozva` < ?
        WHERE e.`event_status` NOT IN ('draft', 'auto-draft')
        GROUP BY e.`id`
        ORDER BY megtekintesek DESC, e.`event_start` IS NULL, e.`event_start` DESC, e.`id` DESC
        LIMIT {$limit}
    ";

    $eventsTotal = 0;
    $eventsWithViews = 0;
    $eventsInPeriod = 0;
    try {
        $totalStmt = $db->query("
            SELECT COUNT(*) FROM `events_calendar_events`
            WHERE `event_status` NOT IN ('draft', 'auto-draft')
        ");
        $eventsTotal = (int) $totalStmt->fetchColumn();

        $withViewsStmt = $db->prepare('
            SELECT COUNT(DISTINCT `esemény_id`)
            FROM `events_calendar_event_views`
            WHERE `létrehozva` >= ?
              AND `létrehozva` < ?
        ');
        $withViewsStmt->execute([$window['start_inclusive'], $window['end_exclusive']]);
        $eventsWithViews = (int) $withViewsStmt->fetchColumn();

        $inPeriodStmt = $db->prepare("
            SELECT COUNT(*)
            FROM `events_calendar_events`
            WHERE `event_status` NOT IN ('draft', 'auto-draft')
              AND `event_start` IS NOT NULL
              AND DATE(`event_start`) <= ?
              AND DATE(COALESCE(`event_end`, `event_start`)) >= ?
        ");
        $inPeriodStmt->execute([$dateTo, $dateFrom]);
        $eventsInPeriod = (int) $inPeriodStmt->fetchColumn();

        $stmt = $db->prepare($sql);
        $stmt->execute([$window['start_inclusive'], $window['end_exclusive']]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('events_edit_stats_all_events_list: ' . $e->getMessage());

        return $empty;
    }

    foreach ($rows as $idx => $row) {
        $rows[$idx]['live_days'] = events_edit_stats_live_days_in_period($row, $dateFrom, $dateTo);
        $rows[$idx]['egyedi_latogatok'] = (int) ($row['egyedi_latogatok_human'] ?? 0);
        $rows[$idx]['egyedi_latogatok_bot'] = (int) ($row['egyedi_latogatok_bot'] ?? 0);
        if (!$tableReady) {
            $rows[$idx]['naptar_elonezetek'] = 0;
            $rows[$idx]['naptar_elonezetek_human'] = 0;
            $rows[$idx]['naptar_elonezetek_bot'] = 0;
            $rows[$idx]['tovabbi_info_kattintasok'] = 0;
            $rows[$idx]['tovabbi_info_kattintasok_human'] = 0;
            $rows[$idx]['tovabbi_info_kattintasok_bot'] = 0;
        }
    }

    return [
        'rows' => $rows,
        'draft_rows' => [],
        'events_total' => $eventsTotal,
        'events_with_views' => $eventsWithViews,
        'events_in_period' => $eventsInPeriod,
    ];
}

/**
 * Egy esemény egy napjának órás bontása + konkrét megtekintési tételek.
 *
 * @return array{
 *   day: string,
 *   table_ready: bool,
 *   bot_ready: bool,
 *   hourly: array<string, mixed>,
 *   items: list<array<string, mixed>>,
 *   items_truncated: bool,
 *   totals: array<string, int>
 * }
 */
function events_edit_stats_day_detail_for_event(PDO $db, int $eventId, string $dayYmd, int $itemsLimit = 500): array
{
    $emptyHourly = events_edit_stats_empty_hourly_chart();
    $empty = [
        'day' => $dayYmd,
        'table_ready' => false,
        'bot_ready' => false,
        'hourly' => $emptyHourly,
        'items' => [],
        'items_truncated' => false,
        'totals' => [
            'page_views' => 0,
            'page_views_human' => 0,
            'page_views_bot' => 0,
            'calendar_previews' => 0,
            'calendar_previews_human' => 0,
            'calendar_previews_bot' => 0,
            'external_info_clicks' => 0,
            'external_info_clicks_human' => 0,
            'external_info_clicks_bot' => 0,
            'rows' => 0,
        ],
    ];

    if ($eventId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dayYmd)) {
        return $empty;
    }

    try {
        $dayStart = new DateTimeImmutable($dayYmd . ' 00:00:00');
    } catch (Throwable) {
        return $empty;
    }
    $dayEnd = $dayStart->modify('+1 day');
    $startInclusive = $dayStart->format('Y-m-d H:i:s');
    $endExclusive = $dayEnd->format('Y-m-d H:i:s');

    events_view_tracking_ensure_bot_column($db);
    $tableReady = events_edit_stats_table_ready($db);
    $botReady = events_view_tracking_bot_column_ready($db);
    $empty['table_ready'] = $tableReady;
    $empty['bot_ready'] = $botReady;

    $hourKeys = [];
    for ($h = 0; $h < 24; $h++) {
        $hourKeys[] = sprintf('%02d', $h);
    }
    $pageHumanByHour = array_fill_keys($hourKeys, 0);
    $pageBotByHour = array_fill_keys($hourKeys, 0);
    $previewHumanByHour = array_fill_keys($hourKeys, 0);
    $previewBotByHour = array_fill_keys($hourKeys, 0);
    $externalHumanByHour = array_fill_keys($hourKeys, 0);
    $externalBotByHour = array_fill_keys($hourKeys, 0);

    try {
        if ($tableReady && $botReady) {
            $stmt = $db->prepare('
                SELECT HOUR(`létrehozva`) AS bucket_hour, `metric_type`, `is_bot`, COUNT(*) AS cnt
                FROM `events_calendar_event_views`
                WHERE `esemény_id` = ?
                  AND `létrehozva` >= ?
                  AND `létrehozva` < ?
                GROUP BY bucket_hour, `metric_type`, `is_bot`
            ');
            $stmt->execute([$eventId, $startInclusive, $endExclusive]);
        } elseif ($tableReady) {
            $stmt = $db->prepare('
                SELECT HOUR(`létrehozva`) AS bucket_hour, `metric_type`, 0 AS is_bot, COUNT(*) AS cnt
                FROM `events_calendar_event_views`
                WHERE `esemény_id` = ?
                  AND `létrehozva` >= ?
                  AND `létrehozva` < ?
                GROUP BY bucket_hour, `metric_type`
            ');
            $stmt->execute([$eventId, $startInclusive, $endExclusive]);
        } else {
            $stmt = $db->prepare('
                SELECT HOUR(`létrehozva`) AS bucket_hour, 0 AS is_bot, COUNT(*) AS cnt
                FROM `events_calendar_event_views`
                WHERE `esemény_id` = ?
                  AND `létrehozva` >= ?
                  AND `létrehozva` < ?
                GROUP BY bucket_hour
            ');
            $stmt->execute([$eventId, $startInclusive, $endExclusive]);
        }

        $bucketRows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $hour = sprintf('%02d', (int) ($row['bucket_hour'] ?? 0));
            $bucketRows[] = [
                'bucket' => $hour,
                'metric_type' => $row['metric_type'] ?? EVENTS_VIEW_METRIC_PAGE,
                'is_bot' => (int) ($row['is_bot'] ?? 0),
                'cnt' => (int) ($row['cnt'] ?? 0),
            ];
        }
        events_edit_stats_apply_bucket_rows(
            $bucketRows,
            $pageHumanByHour,
            $pageBotByHour,
            $previewHumanByHour,
            $previewBotByHour,
            $externalHumanByHour,
            $externalBotByHour,
            $tableReady,
            $botReady
        );
    } catch (Throwable $e) {
        error_log('events_edit_stats_day_detail_for_event hourly: ' . $e->getMessage());

        return $empty;
    }

    $built = events_edit_stats_build_result(
        $hourKeys,
        $pageHumanByHour,
        $pageBotByHour,
        $previewHumanByHour,
        $previewBotByHour,
        $externalHumanByHour,
        $externalBotByHour,
        $tableReady,
        $botReady
    );
    // Órás tengely: 00–23 (ne m.d. formátum).
    $built['chart']['labels'] = $hourKeys;

    $itemsLimit = max(1, min(2000, $itemsLimit));
    $items = [];
    $itemsTruncated = false;
    try {
        $botSelect = $botReady ? '`is_bot`' : '0 AS `is_bot`';
        $metricSelect = $tableReady ? '`metric_type`' : "'" . EVENTS_VIEW_METRIC_PAGE . "' AS `metric_type`";
        $limitPlus = $itemsLimit + 1;
        $stmt = $db->prepare("
            SELECT `id`, `létrehozva`, {$metricSelect}, `source`, {$botSelect}, `ip_hash`
            FROM `events_calendar_event_views`
            WHERE `esemény_id` = ?
              AND `létrehozva` >= ?
              AND `létrehozva` < ?
            ORDER BY `létrehozva` DESC, `id` DESC
            LIMIT {$limitPlus}
        ");
        $stmt->execute([$eventId, $startInclusive, $endExclusive]);
        $rawItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rawItems) > $itemsLimit) {
            $itemsTruncated = true;
            $rawItems = array_slice($rawItems, 0, $itemsLimit);
        }
        foreach ($rawItems as $row) {
            $ipHash = (string) ($row['ip_hash'] ?? '');
            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'at' => (string) ($row['létrehozva'] ?? ''),
                'metric' => (string) ($row['metric_type'] ?? EVENTS_VIEW_METRIC_PAGE),
                'source' => (string) ($row['source'] ?? ''),
                'is_bot' => ((int) ($row['is_bot'] ?? 0)) === 1,
                'ip_short' => $ipHash !== '' ? substr($ipHash, 0, 8) : '',
            ];
        }
    } catch (Throwable $e) {
        error_log('events_edit_stats_day_detail_for_event items: ' . $e->getMessage());
    }

    $totals = $built['totals'];
    $totals['rows'] = count($items) + ($itemsTruncated ? 1 : 0);

    return [
        'day' => $dayYmd,
        'table_ready' => $tableReady,
        'bot_ready' => $botReady,
        'hourly' => $built['chart'],
        'items' => $items,
        'items_truncated' => $itemsTruncated,
        'totals' => $totals,
    ];
}

/**
 * @return array{labels: list<string>, default_mode: string, modes: array<string, array{datasets: list<array<string, mixed>>}>, datasets: list<array<string, mixed>>}
 */
function events_edit_stats_empty_hourly_chart(): array
{
    $labels = [];
    for ($h = 0; $h < 24; $h++) {
        $labels[] = sprintf('%02d', $h);
    }

    return [
        'labels' => $labels,
        'default_mode' => 'human',
        'modes' => [
            'human' => ['datasets' => []],
            'total' => ['datasets' => []],
        ],
        'datasets' => [],
    ];
}

/**
 * Szervezőnként aggregált metrikák a választott időszakra.
 * Üres $organizerIds = minden szervező, akinek van forgalma a periódusban.
 *
 * @param list<int> $organizerIds
 * @param array{date_from: string, date_to: string} $params
 * @return list<array{
 *   id: int,
 *   name: string,
 *   events_with_views: int,
 *   megtekintesek_human: int,
 *   megtekintesek_bot: int,
 *   megtekintesek: int,
 *   egyedi_latogatok_human: int,
 *   egyedi_latogatok_bot: int,
 *   naptar_elonezetek_human: int,
 *   naptar_elonezetek_bot: int,
 *   naptar_elonezetek: int,
 *   tovabbi_info_kattintasok_human: int,
 *   tovabbi_info_kattintasok_bot: int,
 *   tovabbi_info_kattintasok: int
 * }>
 */
function events_edit_stats_organizers_period_rows(
    PDO $db,
    array $organizerIds,
    array $params,
    int $limit = 300
): array {
    $organizerIds = array_values(array_unique(array_filter(
        array_map(static fn (mixed $id): int => (int) $id, $organizerIds),
        static fn (int $id): bool => $id > 0
    )));
    $limit = max(1, min(1000, $limit));

    events_view_tracking_ensure_bot_column($db);
    $tableReady = events_edit_stats_table_ready($db);
    $botReady = events_view_tracking_bot_column_ready($db);
    $window = events_edit_stats_view_window($params);

    $orgFilterSql = '';
    $paramsExec = [$window['start_inclusive'], $window['end_exclusive']];
    if ($organizerIds !== []) {
        $orgPh = implode(',', array_fill(0, count($organizerIds), '?'));
        $orgFilterSql = " AND o.`id` IN ({$orgPh})";
        $paramsExec = array_merge($paramsExec, $organizerIds);
    }

    $pageMetric = $tableReady ? " AND v.`metric_type` = 'page_view'" : '';
    $previewMetric = $tableReady ? " AND v.`metric_type` = 'calendar_preview'" : ' AND 1=0';
    $externalMetric = $tableReady ? " AND v.`metric_type` = 'external_info_click'" : ' AND 1=0';
    $ipOk = " AND v.`ip_hash` IS NOT NULL AND v.`ip_hash` <> ''";

    if ($botReady) {
        $pageHuman = "SUM(CASE WHEN 1=1{$pageMetric} AND v.`is_bot` = 0 THEN 1 ELSE 0 END)";
        $pageBot = "SUM(CASE WHEN 1=1{$pageMetric} AND v.`is_bot` = 1 THEN 1 ELSE 0 END)";
        $pageTotal = "SUM(CASE WHEN 1=1{$pageMetric} THEN 1 ELSE 0 END)";
        $uniqueHuman = "COUNT(DISTINCT CASE WHEN 1=1{$pageMetric} AND v.`is_bot` = 0{$ipOk} THEN v.`ip_hash` END)";
        $uniqueBot = "COUNT(DISTINCT CASE WHEN 1=1{$pageMetric} AND v.`is_bot` = 1{$ipOk} THEN v.`ip_hash` END)";
        $previewHuman = "SUM(CASE WHEN 1=1{$previewMetric} AND v.`is_bot` = 0 THEN 1 ELSE 0 END)";
        $previewBot = "SUM(CASE WHEN 1=1{$previewMetric} AND v.`is_bot` = 1 THEN 1 ELSE 0 END)";
        $previewTotal = "SUM(CASE WHEN 1=1{$previewMetric} THEN 1 ELSE 0 END)";
        $externalHuman = "SUM(CASE WHEN 1=1{$externalMetric} AND v.`is_bot` = 0 THEN 1 ELSE 0 END)";
        $externalBot = "SUM(CASE WHEN 1=1{$externalMetric} AND v.`is_bot` = 1 THEN 1 ELSE 0 END)";
        $externalTotal = "SUM(CASE WHEN 1=1{$externalMetric} THEN 1 ELSE 0 END)";
    } else {
        $pageHuman = "SUM(CASE WHEN 1=1{$pageMetric} THEN 1 ELSE 0 END)";
        $pageBot = '0';
        $pageTotal = $pageHuman;
        $uniqueHuman = "COUNT(DISTINCT CASE WHEN 1=1{$pageMetric}{$ipOk} THEN v.`ip_hash` END)";
        $uniqueBot = '0';
        $previewHuman = "SUM(CASE WHEN 1=1{$previewMetric} THEN 1 ELSE 0 END)";
        $previewBot = '0';
        $previewTotal = $previewHuman;
        $externalHuman = "SUM(CASE WHEN 1=1{$externalMetric} THEN 1 ELSE 0 END)";
        $externalBot = '0';
        $externalTotal = $externalHuman;
    }

    $havingSql = $organizerIds === []
        ? 'HAVING (
            megtekintesek > 0
            OR naptar_elonezetek > 0
            OR tovabbi_info_kattintasok > 0
          )'
        : '';

    try {
        $sql = "
            SELECT
                o.`id`,
                o.`name`,
                COUNT(DISTINCT v.`esemény_id`) AS events_with_views,
                {$pageHuman} AS megtekintesek_human,
                {$pageBot} AS megtekintesek_bot,
                {$pageTotal} AS megtekintesek,
                {$uniqueHuman} AS egyedi_latogatok_human,
                {$uniqueBot} AS egyedi_latogatok_bot,
                {$previewHuman} AS naptar_elonezetek_human,
                {$previewBot} AS naptar_elonezetek_bot,
                {$previewTotal} AS naptar_elonezetek,
                {$externalHuman} AS tovabbi_info_kattintasok_human,
                {$externalBot} AS tovabbi_info_kattintasok_bot,
                {$externalTotal} AS tovabbi_info_kattintasok
            FROM `events_organizers` o
            INNER JOIN `events_calendar_event_organizers` eo ON eo.`organizer_id` = o.`id`
            INNER JOIN `events_calendar_event_views` v ON v.`esemény_id` = eo.`event_id`
            WHERE v.`létrehozva` >= ?
              AND v.`létrehozva` < ?
              {$orgFilterSql}
            GROUP BY o.`id`, o.`name`
            {$havingSql}
            ORDER BY megtekintesek DESC, o.`name` ASC, o.`id` ASC
            LIMIT {$limit}
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($paramsExec);
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'events_with_views' => (int) ($row['events_with_views'] ?? 0),
                'megtekintesek_human' => (int) ($row['megtekintesek_human'] ?? 0),
                'megtekintesek_bot' => (int) ($row['megtekintesek_bot'] ?? 0),
                'megtekintesek' => (int) ($row['megtekintesek'] ?? 0),
                'egyedi_latogatok_human' => (int) ($row['egyedi_latogatok_human'] ?? 0),
                'egyedi_latogatok_bot' => (int) ($row['egyedi_latogatok_bot'] ?? 0),
                'naptar_elonezetek_human' => (int) ($row['naptar_elonezetek_human'] ?? 0),
                'naptar_elonezetek_bot' => (int) ($row['naptar_elonezetek_bot'] ?? 0),
                'naptar_elonezetek' => (int) ($row['naptar_elonezetek'] ?? 0),
                'tovabbi_info_kattintasok_human' => (int) ($row['tovabbi_info_kattintasok_human'] ?? 0),
                'tovabbi_info_kattintasok_bot' => (int) ($row['tovabbi_info_kattintasok_bot'] ?? 0),
                'tovabbi_info_kattintasok' => (int) ($row['tovabbi_info_kattintasok'] ?? 0),
            ];
        }

        // Kiválasztott szervezők 0 forgalommal is megjelenjenek.
        if ($organizerIds !== []) {
            $found = [];
            foreach ($rows as $row) {
                $found[(int) $row['id']] = true;
            }
            $missing = array_values(array_filter(
                $organizerIds,
                static fn (int $id): bool => !isset($found[$id])
            ));
            if ($missing !== []) {
                $missPh = implode(',', array_fill(0, count($missing), '?'));
                $nameStmt = $db->prepare("SELECT `id`, `name` FROM `events_organizers` WHERE `id` IN ({$missPh})");
                $nameStmt->execute($missing);
                foreach ($nameStmt->fetchAll(PDO::FETCH_ASSOC) as $org) {
                    $rows[] = [
                        'id' => (int) ($org['id'] ?? 0),
                        'name' => (string) ($org['name'] ?? ''),
                        'events_with_views' => 0,
                        'megtekintesek_human' => 0,
                        'megtekintesek_bot' => 0,
                        'megtekintesek' => 0,
                        'egyedi_latogatok_human' => 0,
                        'egyedi_latogatok_bot' => 0,
                        'naptar_elonezetek_human' => 0,
                        'naptar_elonezetek_bot' => 0,
                        'naptar_elonezetek' => 0,
                        'tovabbi_info_kattintasok_human' => 0,
                        'tovabbi_info_kattintasok_bot' => 0,
                        'tovabbi_info_kattintasok' => 0,
                    ];
                }
            }
        }

        return $rows;
    } catch (Throwable $e) {
        error_log('events_edit_stats_organizers_period_rows: ' . $e->getMessage());

        return [];
    }
}
