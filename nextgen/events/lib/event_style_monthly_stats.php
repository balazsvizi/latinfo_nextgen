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
        'unstyled_events' => [],
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
        'unstyled_events' => events_style_monthly_stats_unstyled_events($db, $params),
        'chart' => [
            'labels' => array_map(
                static fn (int $m): string => events_monthly_stats_month_label($m),
                range(1, 12)
            ),
            'datasets' => $datasets,
        ],
    ];
}

/**
 * A szűrőnek megfelelő események, amelyekhez nincs érvényes fő stílus.
 *
 * @param array{year?:int,bucket_by?:string,status?:string} $params
 * @return list<array{id:int,name:string,start:?string,end:?string,allday:bool,status:string,slug:string,organizers:string}>
 */
function events_style_monthly_stats_unstyled_events(PDO $db, array $params): array
{
    if (!events_styles_tables_available($db)) {
        return [];
    }

    $year = (int) ($params['year'] ?? (int) date('Y'));
    $bucketBy = (string) ($params['bucket_by'] ?? 'event_start');
    $status = (string) ($params['status'] ?? 'public');
    $statusClause = events_monthly_stats_status_clause($status, 'e');
    $bucketWhere = events_monthly_stats_bucket_where($bucketBy, $year, 'e');
    $orderExpr = $bucketBy === 'published_at' ? 'e.`event_published_at`' : 'e.`event_start`';

    try {
        $sql = '
            SELECT
                e.`id`,
                e.`event_name`,
                e.`event_start`,
                e.`event_end`,
                e.`event_allday`,
                e.`event_status`,
                e.`event_slug`,
                (
                    SELECT GROUP_CONCAT(o.`name` ORDER BY eo.`sort_order` ASC, o.`name` ASC SEPARATOR \', \')
                    FROM `events_calendar_event_organizers` eo
                    INNER JOIN `events_organizers` o ON o.`id` = eo.`organizer_id`
                    WHERE eo.`event_id` = e.`id`
                ) AS organizer_names
            FROM `events_calendar_events` e
            WHERE ' . $bucketWhere['sql'] . '
              AND ' . $statusClause['sql'] . '
              AND NOT EXISTS (
                    SELECT 1
                    FROM `events_calendar_event_main_styles` ms
                    INNER JOIN `events_styles` s ON s.`id` = ms.`style_id`
                    WHERE ms.`event_id` = e.`id`
              )
            ORDER BY ' . $orderExpr . ' ASC, e.`event_name` ASC, e.`id` ASC
        ';
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge($bucketWhere['bind'], $statusClause['bind']));
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $start = trim((string) ($row['event_start'] ?? ''));
            $end = trim((string) ($row['event_end'] ?? ''));
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => trim((string) ($row['event_name'] ?? '')),
                'start' => $start !== '' ? $start : null,
                'end' => $end !== '' ? $end : null,
                'allday' => !empty($row['event_allday']),
                'status' => (string) ($row['event_status'] ?? ''),
                'slug' => trim((string) ($row['event_slug'] ?? '')),
                'organizers' => trim((string) ($row['organizer_names'] ?? '')),
            ];
        }

        return $out;
    } catch (Throwable $e) {
        error_log('events_style_monthly_stats_unstyled_events: ' . $e->getMessage());

        return [];
    }
}

function events_style_monthly_stats_excel_cell(string $value, string $styleId = ''): string
{
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $style = $styleId !== '' ? ' ss:StyleID="' . $styleId . '"' : '';

    return '<Cell' . $style . '><Data ss:Type="String">'
        . htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8')
        . '</Data></Cell>';
}

/**
 * Dátumszűrő év nélkül: minden hónap, amelyhez van eseménydátum.
 *
 * @return array{sql:string,bind:list<mixed>}
 */
function events_style_monthly_stats_bucket_where_all(string $bucketBy, string $alias = 'e'): array
{
    if ($bucketBy === 'published_at') {
        return [
            'sql' => $alias . '.`event_published_at` IS NOT NULL'
                . ' AND ' . $alias . '.`event_published_at` NOT IN (\'\', \'0000-00-00 00:00:00\')',
            'bind' => [],
        ];
    }

    return [
        'sql' => $alias . '.`event_start` IS NOT NULL',
        'bind' => [],
    ];
}

/**
 * Teljes idősor: hónapok (Y-m) sorban, stílusok oszlopban. Üres hónapok is benne vannak.
 *
 * @param array{bucket_by?:string,status?:string} $params
 * @return array{
 *   styles_ready:bool,
 *   months:list<string>,
 *   styles:list<array{id:int,name:string,by_month:array<string,float>,total:float}>,
 *   month_totals:array<string,float>,
 *   grand_total:float
 * }
 */
function events_style_monthly_stats_all_months(PDO $db, array $params): array
{
    $bucketBy = (string) ($params['bucket_by'] ?? 'event_start');
    $status = (string) ($params['status'] ?? 'public');
    $empty = [
        'styles_ready' => false,
        'months' => [],
        'styles' => [],
        'month_totals' => [],
        'grand_total' => 0.0,
    ];
    if (!events_styles_tables_available($db)) {
        return $empty;
    }

    $statusClause = events_monthly_stats_status_clause($status, 'e');
    $bucketWhere = events_style_monthly_stats_bucket_where_all($bucketBy, 'e');
    $bucketExpr = events_monthly_stats_bucket_expr($bucketBy, 'e');

    /** @var array<int, array{id:int,name:string,by_month:array<string,float>,total:float}> $stylesById */
    $stylesById = [];
    /** @var array<string, float> $monthTotals */
    $monthTotals = [];

    try {
        $sql = '
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
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge($bucketWhere['bind'], $statusClause['bind']));
        $styleNames = events_load_style_options($db);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $monthKey = (string) ($row['month_key'] ?? '');
            if (!preg_match('/^(\d{4})-(\d{2})-01$/', $monthKey, $match)) {
                continue;
            }
            $yearNum = (int) $match[1];
            $monthNum = (int) $match[2];
            if ($yearNum < 2000 || $yearNum > 2100 || $monthNum < 1 || $monthNum > 12) {
                continue;
            }
            $key = sprintf('%04d-%02d', $yearNum, $monthNum);
            $monthTotals[$key] = ($monthTotals[$key] ?? 0.0) + 1.0;

            $rawIds = trim((string) ($row['style_ids'] ?? ''));
            $ids = [];
            if ($rawIds !== '') {
                foreach (explode(',', $rawIds) as $rawId) {
                    $sid = (int) $rawId;
                    if ($sid > 0 && isset($styleNames[$sid])) {
                        $ids[$sid] = $sid;
                    }
                }
            }
            if ($ids === []) {
                if (!isset($stylesById[0])) {
                    $stylesById[0] = [
                        'id' => 0,
                        'name' => 'Nincs fő stílus',
                        'by_month' => [],
                        'total' => 0.0,
                    ];
                }
                $stylesById[0]['by_month'][$key] = ($stylesById[0]['by_month'][$key] ?? 0.0) + 1.0;
                $stylesById[0]['total'] += 1.0;
                continue;
            }

            $share = 1.0 / count($ids);
            foreach ($ids as $styleId) {
                if (!isset($stylesById[$styleId])) {
                    $stylesById[$styleId] = [
                        'id' => $styleId,
                        'name' => (string) $styleNames[$styleId],
                        'by_month' => [],
                        'total' => 0.0,
                    ];
                }
                $stylesById[$styleId]['by_month'][$key] = ($stylesById[$styleId]['by_month'][$key] ?? 0.0) + $share;
                $stylesById[$styleId]['total'] += $share;
            }
        }
    } catch (Throwable $e) {
        error_log('events_style_monthly_stats_all_months: ' . $e->getMessage());

        return $empty;
    }

    if ($monthTotals === []) {
        $empty['styles_ready'] = true;

        return $empty;
    }

    $keys = array_keys($monthTotals);
    sort($keys, SORT_STRING);
    $cursor = DateTimeImmutable::createFromFormat('!Y-m', $keys[0]);
    $end = DateTimeImmutable::createFromFormat('!Y-m', $keys[count($keys) - 1]);
    $months = [];
    if ($cursor instanceof DateTimeImmutable && $end instanceof DateTimeImmutable) {
        while ($cursor <= $end) {
            $months[] = $cursor->format('Y-m');
            $cursor = $cursor->modify('+1 month');
        }
    } else {
        $months = $keys;
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

    $styles = [];
    $grand = 0.0;
    $roundedTotals = [];
    foreach ($months as $key) {
        $roundedTotals[$key] = round((float) ($monthTotals[$key] ?? 0), 2);
        $grand += $roundedTotals[$key];
    }
    foreach ($stylesById as $style) {
        $byMonth = [];
        foreach ($months as $key) {
            $byMonth[$key] = round((float) ($style['by_month'][$key] ?? 0), 2);
        }
        $styles[] = [
            'id' => (int) $style['id'],
            'name' => (string) $style['name'],
            'by_month' => $byMonth,
            'total' => round((float) $style['total'], 2),
        ];
    }

    return [
        'styles_ready' => true,
        'months' => $months,
        'styles' => $styles,
        'month_totals' => $roundedTotals,
        'grand_total' => round($grand, 2),
    ];
}

function events_style_monthly_stats_excel_number(float $value, string $styleId): string
{
    $number = number_format(round($value, 2), 2, '.', '');

    return '<Cell ss:StyleID="' . $styleId . '"><Data ss:Type="Number">' . $number . '</Data></Cell>';
}

/**
 * Excel (SpreadsheetML): minden hónap sorban, stílusok oszlopban.
 *
 * @param array<string, mixed> $matrix
 * @param array{bucket_by?:string,status?:string} $params
 */
function events_style_monthly_stats_send_excel(array $matrix, array $params): void
{
    $styles = is_array($matrix['styles'] ?? null) ? $matrix['styles'] : [];
    $months = is_array($matrix['months'] ?? null) ? $matrix['months'] : [];
    $monthTotals = is_array($matrix['month_totals'] ?? null) ? $matrix['month_totals'] : [];
    $grandTotal = (float) ($matrix['grand_total'] ?? 0);
    $bucketBy = (string) ($params['bucket_by'] ?? 'event_start');
    $status = (string) ($params['status'] ?? 'public');

    $filename = 'fostilus-havi-osszes.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');

    $bucketLabel = $bucketBy === 'published_at' ? 'Publikálás dátuma' : 'Esemény dátuma';
    $statusLabel = match ($status) {
        'publish' => 'Csak közzétéve',
        'all' => 'Összes (lomtár nélkül)',
        default => 'Közzétéve + előzetes',
    };

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
        . ' xmlns:o="urn:schemas-microsoft-com:office:office"'
        . ' xmlns:x="urn:schemas-microsoft-com:office:excel"'
        . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"'
        . ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
    echo '<Styles>'
        . '<Style ss:ID="hdr"><Font ss:Bold="1"/><Interior ss:Color="#E7EFE4" ss:Pattern="Solid"/></Style>'
        . '<Style ss:ID="month"><Font ss:Bold="1"/></Style>'
        . '<Style ss:ID="num"><NumberFormat ss:Format="0.##"/></Style>'
        . '<Style ss:ID="total"><Font ss:Bold="1"/><NumberFormat ss:Format="0.##"/></Style>'
        . '<Style ss:ID="note"><Font ss:Italic="1" ss:Color="#64748B"/></Style>'
        . '</Styles>' . "\n";

    echo '<Worksheet ss:Name="Főstílus"><Table>' . "\n";
    echo '<Column ss:Width="130"/>';
    for ($col = 0, $colCount = count($styles) + 1; $col < $colCount; $col++) {
        echo '<Column ss:Width="96"/>';
    }
    echo "\n";

    echo '<Row>';
    echo events_style_monthly_stats_excel_cell('Hónap', 'hdr');
    foreach ($styles as $style) {
        echo events_style_monthly_stats_excel_cell((string) ($style['name'] ?? ''), 'hdr');
    }
    echo events_style_monthly_stats_excel_cell('Összesen', 'hdr');
    echo "</Row>\n";

    foreach ($months as $monthKey) {
        $yearNum = (int) substr((string) $monthKey, 0, 4);
        $monthNum = (int) substr((string) $monthKey, 5, 2);
        $label = $yearNum . ' ' . mb_strtolower(events_monthly_stats_month_label_full($monthNum), 'UTF-8');
        echo '<Row>';
        echo events_style_monthly_stats_excel_cell($label, 'month');
        foreach ($styles as $style) {
            $byMonth = is_array($style['by_month'] ?? null) ? $style['by_month'] : [];
            echo events_style_monthly_stats_excel_number((float) ($byMonth[$monthKey] ?? 0), 'num');
        }
        echo events_style_monthly_stats_excel_number((float) ($monthTotals[$monthKey] ?? 0), 'total');
        echo "</Row>\n";
    }

    echo '<Row>';
    echo events_style_monthly_stats_excel_cell('Összesen', 'hdr');
    foreach ($styles as $style) {
        echo events_style_monthly_stats_excel_number((float) ($style['total'] ?? 0), 'total');
    }
    echo events_style_monthly_stats_excel_number($grandTotal, 'total');
    echo "</Row>\n";

    echo '<Row></Row>';
    echo '<Row>';
    echo events_style_monthly_stats_excel_cell(
        'Szűrő: ' . $bucketLabel . ' · ' . $statusLabel . '. Több fő stílusnál az esemény 1/n súllyal szerepel.',
        'note'
    );
    echo "</Row>\n";

    echo '</Table>';
    echo '<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">';
    echo '<FreezePanes/><FrozenNoSplit/>';
    echo '<SplitHorizontal>1</SplitHorizontal><TopRowBottomPane>1</TopRowBottomPane><ActivePane>2</ActivePane>';
    echo '</WorksheetOptions>';
    echo '</Worksheet></Workbook>';
}
