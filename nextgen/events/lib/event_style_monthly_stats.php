<?php
declare(strict_types=1);

require_once __DIR__ . '/event_monthly_stats.php';
require_once __DIR__ . '/style_request.php';

/**
 * Havi főstílus-statisztika: események súlyozott bontása fő stílusokra.
 * Több fő stílus esetén az esemény 1/n súllyal számít minden stílusra.
 */

/**
 * @return array{year:int,bucket_by:string,status:string}
 */
function events_style_monthly_stats_params_from_request(array $query): array
{
    $base = events_monthly_stats_params_from_request($query);

    return [
        'year' => (int) $base['year'],
        'bucket_by' => (string) $base['bucket_by'],
        'status' => (string) $base['status'],
    ];
}

/**
 * @return list<string>
 */
function events_style_monthly_stats_palette(): array
{
    return [
        '#6d8f63',
        '#3B50FF',
        '#c45c26',
        '#8b5cf6',
        '#0f766e',
        '#db2777',
        '#ca8a04',
        '#2563eb',
        '#65a30d',
        '#ea580c',
        '#7c3aed',
        '#0891b2',
        '#b45309',
        '#4f46e5',
        '#be123c',
    ];
}

function events_style_monthly_stats_format_weight(float $value): string
{
    if (abs($value) < 0.0005) {
        return '0';
    }
    $rounded = round($value, 2);
    if (abs($rounded - round($rounded)) < 0.0005) {
        return (string) (int) round($rounded);
    }

    return rtrim(rtrim(number_format($rounded, 2, ',', ' '), '0'), ',');
}

/**
 * @return array{
 *   year:int,
 *   prev_year:int,
 *   available_years:list<int>,
 *   styles_ready:bool,
 *   styles:list<array{id:int,name:string,color:string,months:array<int,float>,total:float}>,
 *   month_totals:array<int,float>,
 *   year_total:float,
 *   events_count:int,
 *   events_with_styles:int,
 *   events_without_styles:int,
 *   chart:array{labels:list<string>,datasets:list<array<string,mixed>>}
 * }
 */
function events_style_monthly_stats(PDO $db, array $params): array
{
    $year = (int) ($params['year'] ?? (int) date('Y'));
    $bucketBy = (string) ($params['bucket_by'] ?? 'event_start');
    $status = (string) ($params['status'] ?? 'public');
    $availableYears = events_monthly_stats_available_years($db);

    $emptyMonths = [];
    for ($m = 1; $m <= 12; $m++) {
        $emptyMonths[$m] = 0.0;
    }

    $empty = [
        'year' => $year,
        'prev_year' => $year - 1,
        'available_years' => $availableYears,
        'styles_ready' => false,
        'styles' => [],
        'month_totals' => $emptyMonths,
        'year_total' => 0.0,
        'events_count' => 0,
        'events_with_styles' => 0,
        'events_without_styles' => 0,
        'chart' => [
            'labels' => array_map(
                static fn (int $m): string => events_monthly_stats_month_label($m),
                range(1, 12)
            ),
            'datasets' => [],
        ],
    ];

    if (!events_styles_tables_available($db)) {
        return $empty;
    }

    $statusClause = events_monthly_stats_status_clause($status, 'e');
    $bucketWhere = events_monthly_stats_bucket_where($bucketBy, $year, 'e');
    $bucketExpr = events_monthly_stats_bucket_expr($bucketBy, 'e');

    $stylesById = [];
    $monthTotals = $emptyMonths;
    $yearTotal = 0.0;
    $eventsCount = 0;
    $eventsWithStyles = 0;
    $eventsWithoutStyles = 0;

    try {
        // Eseményenkénti súlyozás PHP-ban: elkerüli a MySQL float összeg hibáit (1/n).
        $sqlEvents = '
            SELECT
                e.`id` AS event_id,
                ' . $bucketExpr . ' AS month_key,
                GROUP_CONCAT(ms.`style_id` ORDER BY ms.`style_id` SEPARATOR ",") AS style_ids
            FROM `events_calendar_events` e
            LEFT JOIN `events_calendar_event_main_styles` ms ON ms.`event_id` = e.`id`
            WHERE ' . $bucketWhere['sql'] . '
              AND ' . $statusClause['sql'] . '
            GROUP BY e.`id`, month_key
        ';
        $stmtEvents = $db->prepare($sqlEvents);
        $stmtEvents->execute(array_merge($bucketWhere['bind'], $statusClause['bind']));
        $styleNames = events_load_style_options($db);
        $unstyledMonths = $emptyMonths;
        $unstyledTotal = 0.0;

        foreach ($stmtEvents->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $monthKey = (string) ($row['month_key'] ?? '');
            if ($monthKey === '' || strlen($monthKey) < 7) {
                continue;
            }
            $month = (int) substr($monthKey, 5, 2);
            if ($month < 1 || $month > 12) {
                continue;
            }

            $eventsCount++;
            $monthTotals[$month] += 1.0;
            $yearTotal += 1.0;

            $rawIds = trim((string) ($row['style_ids'] ?? ''));
            if ($rawIds === '') {
                $unstyledMonths[$month] += 1.0;
                $unstyledTotal += 1.0;
                $eventsWithoutStyles++;
                continue;
            }

            $ids = [];
            foreach (explode(',', $rawIds) as $rawId) {
                $sid = (int) $rawId;
                if ($sid > 0 && isset($styleNames[$sid])) {
                    $ids[$sid] = $sid;
                }
            }
            if ($ids === []) {
                $unstyledMonths[$month] += 1.0;
                $unstyledTotal += 1.0;
                $eventsWithoutStyles++;
                continue;
            }

            $eventsWithStyles++;
            $share = 1.0 / count($ids);
            foreach ($ids as $styleId) {
                if (!isset($stylesById[$styleId])) {
                    $stylesById[$styleId] = [
                        'id' => $styleId,
                        'name' => (string) $styleNames[$styleId],
                        'months' => $emptyMonths,
                        'total' => 0.0,
                    ];
                }
                $stylesById[$styleId]['months'][$month] += $share;
                $stylesById[$styleId]['total'] += $share;
            }
        }

        if ($unstyledTotal > 0) {
            $stylesById[0] = [
                'id' => 0,
                'name' => 'Nincs fő stílus',
                'months' => $unstyledMonths,
                'total' => $unstyledTotal,
            ];
        }
    } catch (Throwable $e) {
        error_log('events_style_monthly_stats: ' . $e->getMessage());

        return $empty;
    }

    uasort(
        $stylesById,
        static function (array $a, array $b): int {
            if ($a['id'] === 0) {
                return 1;
            }
            if ($b['id'] === 0) {
                return -1;
            }
            $byTotal = $b['total'] <=> $a['total'];
            if ($byTotal !== 0) {
                return $byTotal;
            }

            return strcasecmp((string) $a['name'], (string) $b['name']);
        }
    );

    $palette = events_style_monthly_stats_palette();
    $styles = [];
    $datasets = [];
    $colorIndex = 0;
    foreach ($stylesById as $style) {
        $color = $style['id'] === 0
            ? '#94a3b8'
            : $palette[$colorIndex % count($palette)];
        if ($style['id'] !== 0) {
            $colorIndex++;
        }
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[$m] = round((float) ($style['months'][$m] ?? 0), 2);
        }
        $total = round((float) $style['total'], 2);
        $styles[] = [
            'id' => (int) $style['id'],
            'name' => (string) $style['name'],
            'color' => $color,
            'months' => $months,
            'total' => $total,
        ];
        $datasets[] = [
            'label' => (string) $style['name'],
            'data' => array_values($months),
            'backgroundColor' => $color,
            'borderWidth' => 0,
            'stack' => 'styles',
        ];
    }

    $roundedMonthTotals = [];
    for ($m = 1; $m <= 12; $m++) {
        $roundedMonthTotals[$m] = round((float) ($monthTotals[$m] ?? 0), 2);
    }

    return [
        'year' => $year,
        'prev_year' => $year - 1,
        'available_years' => $availableYears,
        'styles_ready' => true,
        'styles' => $styles,
        'month_totals' => $roundedMonthTotals,
        'year_total' => round($yearTotal, 2),
        'events_count' => $eventsCount,
        'events_with_styles' => $eventsWithStyles,
        'events_without_styles' => $eventsWithoutStyles,
        'chart' => [
            'labels' => array_map(
                static fn (int $m): string => events_monthly_stats_month_label($m),
                range(1, 12)
            ),
            'datasets' => $datasets,
        ],
    ];
}
