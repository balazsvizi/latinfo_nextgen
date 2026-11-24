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

function events_style_monthly_stats_xlsx_col(int $index): string
{
    $n = $index + 1;
    $letters = '';
    while ($n > 0) {
        $n--;
        $letters = chr(65 + ($n % 26)) . $letters;
        $n = intdiv($n, 26);
    }

    return $letters;
}

function events_style_monthly_stats_xlsx_text(string $value): string
{
    $value = str_replace(["\r\n", "\r"], "\n", $value);

    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
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

/**
 * Valódi .xlsx (Office Open XML), hogy az Excel ne jelezzen formátum–kiterjesztés eltérést.
 *
 * @param array<string, mixed> $matrix
 * @param array{bucket_by?:string,status?:string} $params
 */
function events_style_monthly_stats_send_excel(array $matrix, array $params): void
{
    if (!class_exists(ZipArchive::class)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Az Excel export nem érhető el: hiányzik a PHP zip bővítmény.';

        return;
    }

    $styles = is_array($matrix['styles'] ?? null) ? $matrix['styles'] : [];
    $months = is_array($matrix['months'] ?? null) ? $matrix['months'] : [];
    $monthTotals = is_array($matrix['month_totals'] ?? null) ? $matrix['month_totals'] : [];
    $grandTotal = (float) ($matrix['grand_total'] ?? 0);
    $bucketBy = (string) ($params['bucket_by'] ?? 'event_start');
    $status = (string) ($params['status'] ?? 'public');
    $bucketLabel = $bucketBy === 'published_at' ? 'Publikálás dátuma' : 'Esemény dátuma';
    $statusLabel = match ($status) {
        'publish' => 'Csak közzétéve',
        'all' => 'Összes (lomtár nélkül)',
        default => 'Közzétéve + előzetes',
    };

    $colCount = count($styles) + 2;
    $lastCol = events_style_monthly_stats_xlsx_col($colCount - 1);
    $note = 'Szűrő: ' . $bucketLabel . ' · ' . $statusLabel
        . '. Több fő stílusnál az esemény 1/n súllyal szerepel.';

    $rowsXml = '';
    $rowNum = 1;
    $headerCells = '<c r="A1" t="inlineStr" s="1"><is><t>Hónap</t></is></c>';
    $colIndex = 1;
    foreach ($styles as $style) {
        $ref = events_style_monthly_stats_xlsx_col($colIndex) . '1';
        $headerCells .= '<c r="' . $ref . '" t="inlineStr" s="1"><is><t>'
            . events_style_monthly_stats_xlsx_text((string) ($style['name'] ?? ''))
            . '</t></is></c>';
        $colIndex++;
    }
    $headerCells .= '<c r="' . $lastCol . '1" t="inlineStr" s="1"><is><t>Összesen</t></is></c>';
    $rowsXml .= '<row r="1">' . $headerCells . '</row>';

    foreach ($months as $monthKey) {
        $rowNum++;
        $yearNum = (int) substr((string) $monthKey, 0, 4);
        $monthNum = (int) substr((string) $monthKey, 5, 2);
        $label = $yearNum . ' ' . mb_strtolower(events_monthly_stats_month_label_full($monthNum), 'UTF-8');
        $cells = '<c r="A' . $rowNum . '" t="inlineStr" s="2"><is><t>'
            . events_style_monthly_stats_xlsx_text($label)
            . '</t></is></c>';
        $colIndex = 1;
        foreach ($styles as $style) {
            $byMonth = is_array($style['by_month'] ?? null) ? $style['by_month'] : [];
            $ref = events_style_monthly_stats_xlsx_col($colIndex) . $rowNum;
            $number = number_format(round((float) ($byMonth[$monthKey] ?? 0), 2), 2, '.', '');
            $cells .= '<c r="' . $ref . '" s="3"><v>' . $number . '</v></c>';
            $colIndex++;
        }
        $rowTotal = number_format(round((float) ($monthTotals[$monthKey] ?? 0), 2), 2, '.', '');
        $cells .= '<c r="' . $lastCol . $rowNum . '" s="4"><v>' . $rowTotal . '</v></c>';
        $rowsXml .= '<row r="' . $rowNum . '">' . $cells . '</row>';
    }

    $rowNum++;
    $totalCells = '<c r="A' . $rowNum . '" t="inlineStr" s="1"><is><t>Összesen</t></is></c>';
    $colIndex = 1;
    foreach ($styles as $style) {
        $ref = events_style_monthly_stats_xlsx_col($colIndex) . $rowNum;
        $number = number_format(round((float) ($style['total'] ?? 0), 2), 2, '.', '');
        $totalCells .= '<c r="' . $ref . '" s="4"><v>' . $number . '</v></c>';
        $colIndex++;
    }
    $totalCells .= '<c r="' . $lastCol . $rowNum . '" s="4"><v>'
        . number_format(round($grandTotal, 2), 2, '.', '')
        . '</v></c>';
    $rowsXml .= '<row r="' . $rowNum . '">' . $totalCells . '</row>';

    $rowNum += 2;
    $rowsXml .= '<row r="' . $rowNum . '"><c r="A' . $rowNum . '" t="inlineStr" s="5"><is><t>'
        . events_style_monthly_stats_xlsx_text($note)
        . '</t></is></c></row>';

    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<dimension ref="A1:' . $lastCol . $rowNum . '"/>'
        . '<sheetViews><sheetView workbookViewId="0">'
        . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
        . '<selection pane="bottomLeft" activeCell="A2" sqref="A2"/>'
        . '</sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="15"/>'
        . '<cols><col min="1" max="1" width="22" customWidth="1"/>'
        . '<col min="2" max="' . $colCount . '" width="18" customWidth="1"/></cols>'
        . '<sheetData>' . $rowsXml . '</sheetData>'
        . '</worksheet>';

    $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="1"><numFmt numFmtId="164" formatCode="0.##"/></numFmts>'
        . '<fonts count="3">'
        . '<font><sz val="11"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
        . '<font><i/><sz val="11"/><color rgb="FF64748B"/><name val="Calibri"/></font>'
        . '</fonts>'
        . '<fills count="3">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFE7EFE4"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="6">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
        . '<xf numFmtId="164" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyNumberFormat="1"/>'
        . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        . '</cellXfs>'
        . '</styleSheet>';

    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Főstílus" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';

    $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';

    $tmp = tempnam(sys_get_temp_dir(), 'fostilus');
    if ($tmp === false) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Az Excel fájl létrehozása nem sikerült.';

        return;
    }
    $xlsxPath = $tmp . '.xlsx';
    @unlink($tmp);

    $zip = new ZipArchive();
    if ($zip->open($xlsxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Az Excel fájl létrehozása nem sikerült.';

        return;
    }
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rootRels);
    $zip->addFromString('xl/workbook.xml', $workbook);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
    $zip->addFromString('xl/styles.xml', $stylesXml);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();

    $size = filesize($xlsxPath);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="fostilus-havi-osszes.xlsx"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    if ($size !== false) {
        header('Content-Length: ' . $size);
    }
    readfile($xlsxPath);
    @unlink($xlsxPath);
}
