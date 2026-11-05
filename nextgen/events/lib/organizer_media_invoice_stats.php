<?php
declare(strict_types=1);

require_once __DIR__ . '/event_edit_stats.php';

$organizerUnifyLib = dirname(__DIR__, 2) . '/lib/organizer/unify.php';
if (is_file($organizerUnifyLib)) {
    require_once $organizerUnifyLib;
}

/**
 * Szervezőnkénti havi médiaérték és leszámlázott összeg.
 *
 * @return array{year: int, mode: string}
 */
function organizer_media_invoice_stats_params_from_request(array $query): array
{
    $year = filter_var($query['stat_year'] ?? null, FILTER_VALIDATE_INT);
    $currentYear = (int) (new DateTimeImmutable('today'))->format('Y');
    if ($year === false || $year < 2000 || $year > ($currentYear + 5)) {
        $year = $currentYear;
    }

    return [
        'year' => (int) $year,
        'mode' => 'smart',
    ];
}

function organizer_media_invoice_stats_month_label(int $month): string
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

function organizer_media_invoice_stats_month_label_full(int $month): string
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
 * Egy összeg egyenlő felosztása egész forintra. A maradék a legkorábbi szeletekre kerül.
 */
function organizer_media_invoice_stats_split_ft(int $amountFt, int $parts, int $index): int
{
    if ($parts < 1 || $index < 0 || $index >= $parts) {
        return 0;
    }
    $sign = $amountFt < 0 ? -1 : 1;
    $absolute = abs($amountFt);
    $base = intdiv($absolute, $parts);
    $remainder = $absolute % $parts;
    $share = $base + ($index < $remainder ? 1 : 0);

    return $share * $sign;
}

/**
 * @return list<int>
 */
function organizer_media_invoice_stats_available_years(PDO $db): array
{
    $years = [];
    $queries = [
        'SELECT DISTINCT YEAR(`létrehozva`) AS y FROM `events_calendar_event_views` WHERE `létrehozva` IS NOT NULL',
        "SELECT DISTINCT YEAR(`dátum`) AS y FROM `finance_invoices` WHERE COALESCE(`törölve`, 0) = 0 AND `státusz` <> 'sztornó'",
        'SELECT DISTINCT `év` AS y FROM `finance_billing_periods`',
    ];
    foreach ($queries as $sql) {
        try {
            $column = $db->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            error_log('organizer_media_invoice_stats_available_years: ' . $e->getMessage());
            continue;
        }
        foreach ($column as $raw) {
            $year = (int) $raw;
            if ($year >= 2000 && $year <= 2100) {
                $years[$year] = $year;
            }
        }
    }

    $current = (int) (new DateTimeImmutable('today'))->format('Y');
    $years[$current] = $current;
    rsort($years, SORT_NUMERIC);

    return array_values($years);
}

/**
 * @param array{year?: int, mode?: string} $params
 * @return array{
 *   year: int,
 *   mode: string,
 *   available_years: list<int>,
 *   rates: array{page_unit_ft: int, click_unit_ft: int, preview_unit_ft: int},
 *   rows: list<array{
 *     key: string,
 *     name: string,
 *     events_organizer_id: int,
 *     finance_organizer_id: int,
 *     media: array<int, int>,
 *     invoice: array<int, int>,
 *     media_year: int,
 *     invoice_year: int
 *   }>,
 *   totals: array{media: array<int, int>, invoice: array<int, int>, media_year: int, invoice_year: int}
 * }
 */
function organizer_media_invoice_stats(PDO $db, array $params): array
{
    $resolved = organizer_media_invoice_stats_params_from_request([
        'stat_year' => $params['year'] ?? null,
    ]);
    $year = (int) $resolved['year'];
    [$pageUnit, $clickUnit, $previewUnit] = events_edit_stats_resolve_media_units(['custom_rates' => false]);

    /** @var array<string, array<string, mixed>> $buckets */
    $buckets = [];
    organizer_media_invoice_stats_apply_media($db, $buckets, $year, $pageUnit, $clickUnit, $previewUnit);
    if (function_exists('organizer_unify_ensure_schema')) {
        organizer_unify_ensure_schema($db);
    }
    $partnerEventMap = organizer_media_invoice_stats_partner_event_map($db);
    organizer_media_invoice_stats_apply_invoices($db, $buckets, $year, $partnerEventMap);

    $rows = array_values($buckets);
    usort($rows, static function (array $a, array $b): int {
        $scoreA = (int) $a['media_year'] + (int) $a['invoice_year'];
        $scoreB = (int) $b['media_year'] + (int) $b['invoice_year'];
        if ($scoreA !== $scoreB) {
            return $scoreB <=> $scoreA;
        }

        return strcmp(
            mb_strtolower((string) $a['name'], 'UTF-8'),
            mb_strtolower((string) $b['name'], 'UTF-8')
        );
    });

    $totals = [
        'media' => array_fill(1, 12, 0),
        'invoice' => array_fill(1, 12, 0),
        'media_year' => 0,
        'invoice_year' => 0,
    ];
    foreach ($rows as $row) {
        for ($month = 1; $month <= 12; $month++) {
            $totals['media'][$month] += (int) ($row['media'][$month] ?? 0);
            $totals['invoice'][$month] += (int) ($row['invoice'][$month] ?? 0);
        }
        $totals['media_year'] += (int) ($row['media_year'] ?? 0);
        $totals['invoice_year'] += (int) ($row['invoice_year'] ?? 0);
    }

    $availableYears = organizer_media_invoice_stats_available_years($db);
    if (!in_array($year, $availableYears, true)) {
        $availableYears[] = $year;
        rsort($availableYears, SORT_NUMERIC);
    }

    /** @var list<array{key: string, name: string, events_organizer_id: int, finance_organizer_id: int, media: array<int, int>, invoice: array<int, int>, media_year: int, invoice_year: int}> $rows */
    return [
        'year' => $year,
        'mode' => 'smart',
        'available_years' => $availableYears,
        'rates' => [
            'page_unit_ft' => $pageUnit,
            'click_unit_ft' => $clickUnit,
            'preview_unit_ft' => $previewUnit,
        ],
        'rows' => $rows,
        'totals' => $totals,
    ];
}

/**
 * @param array<string, array<string, mixed>> $buckets
 */
function organizer_media_invoice_stats_apply_media(
    PDO $db,
    array &$buckets,
    int $year,
    int $pageUnit,
    int $clickUnit,
    int $previewUnit
): void {
    events_view_tracking_ensure_bot_column($db);
    $tableReady = events_edit_stats_table_ready($db);
    $botReady = events_view_tracking_bot_column_ready($db);
    $eventJoin = events_stats_views_non_trash_join_sql('v', 'e');
    $smartAnd = events_edit_stats_smart_cutoff_sql(['mode' => 'smart'], 'v', 'e');
    $humanAnd = $botReady ? ' AND v.`is_bot` = 0' : '';
    $pageCase = $tableReady ? "v.`metric_type` = '" . EVENTS_VIEW_METRIC_PAGE . "'" : '1 = 1';
    $previewCase = $tableReady ? "v.`metric_type` = '" . EVENTS_VIEW_METRIC_CALENDAR_PREVIEW . "'" : '1 = 0';
    $clickCase = $tableReady ? "v.`metric_type` = '" . EVENTS_VIEW_METRIC_EXTERNAL_INFO . "'" : '1 = 0';

    $start = sprintf('%04d-01-01 00:00:00', $year);
    $end = sprintf('%04d-01-01 00:00:00', $year + 1);

    try {
        $sql = "
            SELECT
                o.`id` AS organizer_id,
                o.`name` AS organizer_name,
                MONTH(v.`létrehozva`) AS stat_month,
                SUM(CASE WHEN {$pageCase}{$humanAnd} THEN 1 ELSE 0 END) AS page_hits,
                SUM(CASE WHEN {$previewCase}{$humanAnd} THEN 1 ELSE 0 END) AS preview_hits,
                SUM(CASE WHEN {$clickCase}{$humanAnd} THEN 1 ELSE 0 END) AS click_hits
            FROM `events_organizers` o
            INNER JOIN `events_calendar_event_organizers` eo ON eo.`organizer_id` = o.`id`
            INNER JOIN `events_calendar_event_views` v ON v.`esemény_id` = eo.`event_id`
            {$eventJoin}
            WHERE v.`létrehozva` >= ?
              AND v.`létrehozva` < ?
              {$smartAnd}
            GROUP BY o.`id`, o.`name`, MONTH(v.`létrehozva`)
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$start, $end]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $eventsId = (int) ($row['organizer_id'] ?? 0);
            $month = (int) ($row['stat_month'] ?? 0);
            if ($eventsId <= 0 || $month < 1 || $month > 12) {
                continue;
            }
            $value = events_edit_stats_media_value(
                (int) ($row['page_hits'] ?? 0),
                (int) ($row['click_hits'] ?? 0),
                $pageUnit,
                $clickUnit,
                (int) ($row['preview_hits'] ?? 0),
                $previewUnit
            );
            $amount = (int) ($value['total_ft'] ?? 0);
            if ($amount === 0) {
                continue;
            }
            $key = 'e:' . $eventsId;
            organizer_media_invoice_stats_touch(
                $buckets,
                $key,
                trim((string) ($row['organizer_name'] ?? '')),
                $eventsId,
                0,
                true
            );
            organizer_media_invoice_stats_add($buckets, $key, 'media', $month, $amount);
        }
    } catch (Throwable $e) {
        error_log('organizer_media_invoice_stats_apply_media: ' . $e->getMessage());
    }
}

/**
 * Finance szervező → egyetlen eseményszervező, ha a partnerkapcsolat egyértelmű
 * és a finance sor még nincs közvetlenül kötve.
 *
 * @return array<int, array{events_id: int, name: string}>
 */
function organizer_media_invoice_stats_partner_event_map(PDO $db): array
{
    try {
        $stmt = $db->query('
            SELECT
                f.`id` AS finance_id,
                po.`organizer_id` AS events_id,
                e.`name` AS events_name
            FROM `finance_organizers` f
            INNER JOIN `nextgen_partner_finance_organizers` pf ON pf.`finance_organizer_id` = f.`id`
            INNER JOIN `nextgen_partner_events_organizers` po ON po.`partner_id` = pf.`partner_id`
            INNER JOIN `events_organizers` e ON e.`id` = po.`organizer_id`
            WHERE COALESCE(f.`events_organizer_id`, 0) = 0
              AND po.`organizer_id` > 0
        ');
        $grouped = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $financeId = (int) ($row['finance_id'] ?? 0);
            $eventsId = (int) ($row['events_id'] ?? 0);
            if ($financeId <= 0 || $eventsId <= 0) {
                continue;
            }
            $grouped[$financeId][$eventsId] = trim((string) ($row['events_name'] ?? ''));
        }
    } catch (Throwable $e) {
        error_log('organizer_media_invoice_stats_partner_event_map: ' . $e->getMessage());

        return [];
    }

    $map = [];
    foreach ($grouped as $financeId => $events) {
        if (count($events) !== 1) {
            continue;
        }
        $eventsId = (int) array_key_first($events);
        $map[(int) $financeId] = [
            'events_id' => $eventsId,
            'name' => (string) $events[$eventsId],
        ];
    }

    return $map;
}

/**
 * @param array<string, array<string, mixed>> $buckets
 * @param array<int, array{events_id: int, name: string}> $partnerEventMap
 */
function organizer_media_invoice_stats_apply_invoices(
    PDO $db,
    array &$buckets,
    int $year,
    array $partnerEventMap = []
): void
{
    if (function_exists('organizer_unify_ensure_schema')) {
        organizer_unify_ensure_schema($db);
    }

    $start = sprintf('%04d-01-01', $year);
    $end = sprintf('%04d-01-01', $year + 1);

    try {
        $itemStmt = $db->prepare('
            SELECT
                bi.`id` AS item_id,
                bi.`összeg` AS amount,
                f.`id` AS finance_id,
                f.`név` AS finance_name,
                COALESCE(f.`events_organizer_id`, 0) AS events_id,
                COALESCE(e.`name`, \'\') AS events_name,
                bp.`év` AS period_year,
                bp.`hónap` AS period_month
            FROM `finance_billing_items` bi
            INNER JOIN `finance_invoices` inv ON inv.`id` = bi.`számla_id`
            INNER JOIN `finance_organizers` f ON f.`id` = bi.`szervező_id`
            LEFT JOIN `events_organizers` e ON e.`id` = f.`events_organizer_id`
            INNER JOIN `finance_billing_periods` bp ON bp.`számlázandó_id` = bi.`id`
            WHERE COALESCE(bi.`törölve`, 0) = 0
              AND COALESCE(inv.`törölve`, 0) = 0
              AND inv.`státusz` <> ?
              AND EXISTS (
                  SELECT 1
                  FROM `finance_billing_periods` bp_year
                  WHERE bp_year.`számlázandó_id` = bi.`id`
                    AND bp_year.`év` = ?
              )
        ');
        $itemStmt->execute(['sztornó', $year]);

        /** @var array<int, array{amount: int, events_id: int, finance_id: int, name: string, prefer_name: bool, periods: array<string, array{year: int, month: int}>}> $items */
        $items = [];
        foreach ($itemStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            $periodYear = (int) ($row['period_year'] ?? 0);
            $periodMonth = (int) ($row['period_month'] ?? 0);
            if ($itemId <= 0 || $periodYear < 2000 || $periodMonth < 1 || $periodMonth > 12) {
                continue;
            }
            if (!isset($items[$itemId])) {
                $eventsId = (int) ($row['events_id'] ?? 0);
                $eventsName = trim((string) ($row['events_name'] ?? ''));
                $financeId = (int) ($row['finance_id'] ?? 0);
                $financeName = trim((string) ($row['finance_name'] ?? ''));
                if ($eventsId <= 0 && isset($partnerEventMap[$financeId])) {
                    $eventsId = (int) $partnerEventMap[$financeId]['events_id'];
                    if ($eventsName === '') {
                        $eventsName = (string) $partnerEventMap[$financeId]['name'];
                    }
                }
                $items[$itemId] = [
                    'amount' => (int) round((float) ($row['amount'] ?? 0)),
                    'events_id' => $eventsId,
                    'finance_id' => $financeId,
                    'name' => $eventsName !== '' ? $eventsName : $financeName,
                    'prefer_name' => $eventsName !== '',
                    'periods' => [],
                ];
            }
            $periodKey = $periodYear . '-' . $periodMonth;
            $items[$itemId]['periods'][$periodKey] = [
                'year' => $periodYear,
                'month' => $periodMonth,
            ];
        }

        foreach ($items as $item) {
            $periods = array_values($item['periods']);
            usort($periods, static function (array $a, array $b): int {
                if ($a['year'] !== $b['year']) {
                    return $a['year'] <=> $b['year'];
                }

                return $a['month'] <=> $b['month'];
            });
            $partCount = count($periods);
            if ($partCount < 1) {
                continue;
            }
            $eventsId = (int) $item['events_id'];
            $financeId = (int) $item['finance_id'];
            $key = $eventsId > 0 ? 'e:' . $eventsId : 'f:' . $financeId;
            if ($key === 'f:0') {
                continue;
            }
            organizer_media_invoice_stats_touch(
                $buckets,
                $key,
                (string) $item['name'],
                $eventsId,
                $financeId,
                (bool) $item['prefer_name']
            );
            foreach ($periods as $index => $period) {
                if ((int) $period['year'] !== $year) {
                    continue;
                }
                $share = organizer_media_invoice_stats_split_ft((int) $item['amount'], $partCount, (int) $index);
                organizer_media_invoice_stats_add($buckets, $key, 'invoice', (int) $period['month'], $share);
            }
        }
    } catch (Throwable $e) {
        error_log('organizer_media_invoice_stats_apply_invoices items: ' . $e->getMessage());
    }

    try {
        $invoiceStmt = $db->prepare('
            SELECT
                inv.`id` AS invoice_id,
                inv.`összeg` AS amount,
                MONTH(inv.`dátum`) AS stat_month,
                f.`id` AS finance_id,
                f.`név` AS finance_name,
                COALESCE(f.`events_organizer_id`, 0) AS events_id,
                COALESCE(e.`name`, \'\') AS events_name
            FROM `finance_invoices` inv
            INNER JOIN `finance_organizers` f ON f.`id` = inv.`szervező_id`
            LEFT JOIN `events_organizers` e ON e.`id` = f.`events_organizer_id`
            WHERE inv.`dátum` >= ?
              AND inv.`dátum` < ?
              AND COALESCE(inv.`törölve`, 0) = 0
              AND inv.`státusz` <> ?
              AND NOT EXISTS (
                  SELECT 1
                  FROM `finance_billing_items` bi
                  INNER JOIN `finance_billing_periods` bp ON bp.`számlázandó_id` = bi.`id`
                  WHERE bi.`számla_id` = inv.`id`
                    AND COALESCE(bi.`törölve`, 0) = 0
              )
        ');
        $invoiceStmt->execute([$start, $end, 'sztornó']);
        foreach ($invoiceStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $month = (int) ($row['stat_month'] ?? 0);
            $eventsId = (int) ($row['events_id'] ?? 0);
            $financeId = (int) ($row['finance_id'] ?? 0);
            if ($month < 1 || $month > 12) {
                continue;
            }
            $eventsName = trim((string) ($row['events_name'] ?? ''));
            if ($eventsId <= 0 && isset($partnerEventMap[$financeId])) {
                $eventsId = (int) $partnerEventMap[$financeId]['events_id'];
                if ($eventsName === '') {
                    $eventsName = (string) $partnerEventMap[$financeId]['name'];
                }
            }
            $key = $eventsId > 0 ? 'e:' . $eventsId : 'f:' . $financeId;
            if ($key === 'f:0') {
                continue;
            }
            $financeName = trim((string) ($row['finance_name'] ?? ''));
            organizer_media_invoice_stats_touch(
                $buckets,
                $key,
                $eventsName !== '' ? $eventsName : $financeName,
                $eventsId,
                $financeId,
                $eventsName !== ''
            );
            organizer_media_invoice_stats_add(
                $buckets,
                $key,
                'invoice',
                $month,
                (int) round((float) ($row['amount'] ?? 0))
            );
        }
    } catch (Throwable $e) {
        error_log('organizer_media_invoice_stats_apply_invoices bare: ' . $e->getMessage());
    }
}

/**
 * @param array<string, array<string, mixed>> $buckets
 */
function organizer_media_invoice_stats_touch(
    array &$buckets,
    string $key,
    string $name,
    int $eventsId,
    int $financeId,
    bool $preferName
): void {
    if (!isset($buckets[$key])) {
        $buckets[$key] = [
            'key' => $key,
            'name' => $name !== '' ? $name : ('Szervező #' . ($eventsId > 0 ? $eventsId : $financeId)),
            'events_organizer_id' => $eventsId,
            'finance_organizer_id' => $financeId,
            'media' => array_fill(1, 12, 0),
            'invoice' => array_fill(1, 12, 0),
            'media_year' => 0,
            'invoice_year' => 0,
        ];

        return;
    }

    if ($preferName && $name !== '') {
        $buckets[$key]['name'] = $name;
    } elseif (
        $name !== ''
        && (
            (string) $buckets[$key]['name'] === ''
            || str_starts_with((string) $buckets[$key]['name'], 'Szervező #')
        )
    ) {
        $buckets[$key]['name'] = $name;
    }
    if ($eventsId > 0) {
        $buckets[$key]['events_organizer_id'] = $eventsId;
    }
    if ($financeId > 0 && (int) $buckets[$key]['finance_organizer_id'] === 0) {
        $buckets[$key]['finance_organizer_id'] = $financeId;
    }
}

/**
 * @param array<string, array<string, mixed>> $buckets
 */
function organizer_media_invoice_stats_add(
    array &$buckets,
    string $key,
    string $kind,
    int $month,
    int $amount
): void {
    if (!isset($buckets[$key]) || ($kind !== 'media' && $kind !== 'invoice')) {
        return;
    }
    if ($month < 1 || $month > 12 || $amount === 0) {
        return;
    }
    $buckets[$key][$kind][$month] = (int) $buckets[$key][$kind][$month] + $amount;
    $yearKey = $kind . '_year';
    $buckets[$key][$yearKey] = (int) $buckets[$key][$yearKey] + $amount;
}
