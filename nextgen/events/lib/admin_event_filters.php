<?php
declare(strict_types=1);

require_once __DIR__ . '/event_request.php';
require_once __DIR__ . '/event_status.php';

/**
 * Admin eseménylista / naptár — közös szűrők és WHERE építés.
 *
 * @return array{
 *   f_organizer: string,
 *   f_name: string,
 *   f_id: string,
 *   f_category: string,
 *   f_tag: string,
 *   f_dj: string,
 *   f_main_style: string,
 *   f_supplementary_style: string,
 *   f_start_from: string,
 *   f_start_to: string,
 *   f_views_min: string,
 *   f_category_id: int,
 *   f_tag_id: int,
 *   f_dj_id: int,
 *   f_main_style_id: int,
 *   f_supplementary_style_id: int,
 *   status: string,
 *   categoryOptions: array<int, string>,
 *   tagOptions: array<int, string>,
 *   tagsAvailable: bool,
 *   djOptions: array<int, string>,
 *   djsAvailable: bool,
 *   styleOptions: array<int, string>,
 *   stylesAvailable: bool,
 *   where: list<string>,
 *   params: list<mixed>,
 *   get_params: array<string, string>,
 *   axisMin: DateTimeImmutable,
 *   axisMax: DateTimeImmutable,
 *   axisMinStr: string,
 *   axisMaxStr: string,
 *   daysSpan: int,
 *   idxFrom: int,
 *   idxTo: int
 * }
 */
function events_admin_filters_from_request(PDO $db): array {
    $f_organizer = trim((string) ($_GET['f_organizer'] ?? ''));
    $f_name = trim((string) ($_GET['f_name'] ?? ''));
    $f_id = trim((string) ($_GET['f_id'] ?? ''));
    $f_category = trim((string) ($_GET['f_category'] ?? ''));
    $f_tag = trim((string) ($_GET['f_tag'] ?? ''));
    $f_dj = trim((string) ($_GET['f_dj'] ?? ''));
    $f_main_style = trim((string) ($_GET['f_main_style'] ?? ''));
    $f_supplementary_style = trim((string) ($_GET['f_supplementary_style'] ?? ''));
    $f_start_from = trim((string) ($_GET['f_start_from'] ?? ''));
    $f_start_to = trim((string) ($_GET['f_start_to'] ?? ''));
    $f_views_min = trim((string) ($_GET['f_views_min'] ?? ''));

    $categoryOptions = events_load_category_options($db);
    $f_category_id = 0;
    if ($f_category !== '' && ctype_digit($f_category) && isset($categoryOptions[(int) $f_category])) {
        $f_category_id = (int) $f_category;
    }

    $tagsAvailable = events_tags_tables_available($db);
    $tagOptions = $tagsAvailable ? events_load_tag_options($db) : [];
    $f_tag_id = 0;
    if ($tagsAvailable && $f_tag !== '' && ctype_digit($f_tag) && isset($tagOptions[(int) $f_tag])) {
        $f_tag_id = (int) $f_tag;
    }

    $djsAvailable = events_tags_tables_available($db) && events_tag_types_tables_available($db);
    $djOptions = $djsAvailable ? events_load_tag_options_by_types($db, ['dj']) : [];
    $f_dj_id = 0;
    if ($djsAvailable && $f_dj !== '' && ctype_digit($f_dj) && isset($djOptions[(int) $f_dj])) {
        $f_dj_id = (int) $f_dj;
    }

    $stylesAvailable = events_styles_tables_available($db);
    $styleOptions = $stylesAvailable ? events_load_style_options($db) : [];
    $f_main_style_id = 0;
    $f_supplementary_style_id = 0;
    if ($stylesAvailable && $f_main_style !== '' && ctype_digit($f_main_style) && isset($styleOptions[(int) $f_main_style])) {
        $f_main_style_id = (int) $f_main_style;
    }
    if ($stylesAvailable && $f_supplementary_style !== '' && ctype_digit($f_supplementary_style) && isset($styleOptions[(int) $f_supplementary_style])) {
        $f_supplementary_style_id = (int) $f_supplementary_style;
    }

    $allowedStatus = array_merge([''], events_allowed_post_statuses());
    $status = isset($_GET['status']) && in_array((string) $_GET['status'], $allowedStatus, true) ? (string) $_GET['status'] : '';

    $boundsRow = $db->query('
        SELECT MIN(e.event_start) AS dmin, MAX(e.event_start) AS dmax
        FROM `events_calendar_events` e
        WHERE e.event_start IS NOT NULL
    ')->fetch(PDO::FETCH_ASSOC);

    $now = new DateTimeImmutable('today');
    if (!empty($boundsRow['dmin'])) {
        $axisMin = (new DateTimeImmutable((string) $boundsRow['dmin']))->modify('first day of this month')->modify('-1 month');
    } else {
        $axisMin = $now->modify('-18 months');
    }
    if (!empty($boundsRow['dmax'])) {
        $axisMax = (new DateTimeImmutable((string) $boundsRow['dmax']))->modify('first day of this month')->modify('+2 months');
    } else {
        $axisMax = $now->modify('+24 months');
    }
    if ($axisMax <= $axisMin) {
        $axisMax = $axisMin->modify('+1 year');
    }
    $axisMinStr = $axisMin->format('Y-m-d');
    $daysSpan = (int) $axisMin->diff($axisMax)->format('%a');
    if ($daysSpan < 1) {
        $daysSpan = 365;
    }

    $clampIdx = static function (int $v, int $max): int {
        if ($v < 0) {
            return 0;
        }
        if ($v > $max) {
            return $max;
        }

        return $v;
    };

    $idxFrom = 0;
    $idxTo = $daysSpan;
    if ($f_start_from !== '') {
        try {
            $d = new DateTimeImmutable($f_start_from);
            $idxFrom = $clampIdx((int) $axisMin->diff($d->setTime(0, 0, 0))->format('%a'), $daysSpan);
        } catch (Throwable) {
            $idxFrom = 0;
        }
    }
    if ($f_start_to !== '') {
        try {
            $d = new DateTimeImmutable($f_start_to);
            $idxTo = $clampIdx((int) $axisMin->diff($d->setTime(0, 0, 0))->format('%a'), $daysSpan);
        } catch (Throwable) {
            $idxTo = $daysSpan;
        }
    }
    if ($idxFrom > $idxTo) {
        [$idxFrom, $idxTo] = [$idxTo, $idxFrom];
    }

    $where = [];
    $params = [];

    if ($f_organizer !== '') {
        $where[] = 'EXISTS (
            SELECT 1 FROM `events_calendar_event_organizers` eo2
            INNER JOIN `events_organizers` o2 ON o2.id = eo2.organizer_id
            WHERE eo2.event_id = e.id AND o2.name LIKE ?
        )';
        $params[] = '%' . $f_organizer . '%';
    }
    if ($f_category_id > 0) {
        $where[] = 'EXISTS (
            SELECT 1 FROM `events_calendar_event_categories` ec2
            WHERE ec2.event_id = e.id AND ec2.category_id = ?
        )';
        $params[] = $f_category_id;
    }
    if ($f_tag_id > 0) {
        $where[] = 'EXISTS (
            SELECT 1 FROM `events_calendar_event_tags` et2
            WHERE et2.event_id = e.id AND et2.tag_id = ?
        )';
        $params[] = $f_tag_id;
    }
    if ($f_dj_id > 0) {
        $where[] = 'EXISTS (
            SELECT 1 FROM `events_calendar_event_tags` etdj
            INNER JOIN `events_tag_type_links` ttdj ON ttdj.`tag_id` = etdj.`tag_id`
            INNER JOIN `events_tag_types` tydj ON tydj.`id` = ttdj.`tag_type_id` AND tydj.`code` = ?
            WHERE etdj.event_id = e.id AND etdj.tag_id = ?
        )';
        array_push($params, 'dj', $f_dj_id);
    }
    if ($f_main_style_id > 0) {
        $where[] = 'EXISTS (
            SELECT 1 FROM `events_calendar_event_main_styles` ms2
            WHERE ms2.event_id = e.id AND ms2.style_id = ?
        )';
        $params[] = $f_main_style_id;
    }
    if ($f_supplementary_style_id > 0) {
        $where[] = 'EXISTS (
            SELECT 1 FROM `events_calendar_event_supplementary_styles` ss2
            WHERE ss2.event_id = e.id AND ss2.style_id = ?
        )';
        $params[] = $f_supplementary_style_id;
    }
    if ($f_name !== '') {
        $where[] = 'e.event_name LIKE ?';
        $params[] = '%' . $f_name . '%';
    }
    if ($f_id !== '') {
        if (ctype_digit($f_id)) {
            $where[] = 'e.id = ?';
            $params[] = (int) $f_id;
        } else {
            $where[] = 'CAST(e.id AS CHAR) LIKE ?';
            $params[] = '%' . $f_id . '%';
        }
    }
    if ($f_start_from !== '') {
        $where[] = '(e.event_start IS NOT NULL AND e.event_start >= ?)';
        $params[] = $f_start_from . (strlen($f_start_from) <= 10 ? ' 00:00:00' : '');
    }
    if ($f_start_to !== '') {
        $where[] = '(e.event_start IS NOT NULL AND e.event_start <= ?)';
        $params[] = $f_start_to . (strlen($f_start_to) <= 10 ? ' 23:59:59' : '');
    }
    if ($status !== '') {
        $where[] = 'e.event_status = ?';
        $params[] = $status;
    }
    if ($f_views_min !== '' && ctype_digit($f_views_min)) {
        $where[] = '(SELECT COUNT(*) FROM `events_calendar_event_views` m WHERE m.`esemény_id` = e.id) >= ?';
        $params[] = (int) $f_views_min;
    }

    $get_params = array_filter([
        'f_organizer' => $f_organizer !== '' ? $f_organizer : null,
        'f_name' => $f_name !== '' ? $f_name : null,
        'f_id' => $f_id !== '' ? $f_id : null,
        'f_category' => $f_category_id > 0 ? (string) $f_category_id : null,
        'f_tag' => $f_tag_id > 0 ? (string) $f_tag_id : null,
        'f_dj' => $f_dj_id > 0 ? (string) $f_dj_id : null,
        'f_main_style' => $f_main_style_id > 0 ? (string) $f_main_style_id : null,
        'f_supplementary_style' => $f_supplementary_style_id > 0 ? (string) $f_supplementary_style_id : null,
        'f_start_from' => $f_start_from !== '' ? $f_start_from : null,
        'f_start_to' => $f_start_to !== '' ? $f_start_to : null,
        'f_views_min' => $f_views_min !== '' ? $f_views_min : null,
        'status' => $status !== '' ? $status : null,
    ], static fn ($v): bool => $v !== null && $v !== '');

    return [
        'f_organizer' => $f_organizer,
        'f_name' => $f_name,
        'f_id' => $f_id,
        'f_category' => $f_category,
        'f_tag' => $f_tag,
        'f_dj' => $f_dj,
        'f_main_style' => $f_main_style,
        'f_supplementary_style' => $f_supplementary_style,
        'f_start_from' => $f_start_from,
        'f_start_to' => $f_start_to,
        'f_views_min' => $f_views_min,
        'f_category_id' => $f_category_id,
        'f_tag_id' => $f_tag_id,
        'f_dj_id' => $f_dj_id,
        'f_main_style_id' => $f_main_style_id,
        'f_supplementary_style_id' => $f_supplementary_style_id,
        'status' => $status,
        'categoryOptions' => $categoryOptions,
        'tagOptions' => $tagOptions,
        'tagsAvailable' => $tagsAvailable,
        'djOptions' => $djOptions,
        'djsAvailable' => $djsAvailable,
        'styleOptions' => $styleOptions,
        'stylesAvailable' => $stylesAvailable,
        'where' => $where,
        'params' => $params,
        'get_params' => $get_params,
        'axisMin' => $axisMin,
        'axisMax' => $axisMax,
        'axisMinStr' => $axisMinStr,
        'axisMaxStr' => $axisMax->format('Y-m-d'),
        'daysSpan' => $daysSpan,
        'idxFrom' => $idxFrom,
        'idxTo' => $idxTo,
    ];
}

function events_admin_format_datum_cell(array $r): string {
    $allday = !empty($r['event_allday']);
    $startRaw = $r['event_start'] ?? null;
    $endRaw = $r['event_end'] ?? null;

    if ($startRaw === null || $startRaw === '') {
        if ($endRaw === null || $endRaw === '') {
            return '–';
        }
        $endTs = strtotime((string) $endRaw);
        if ($endTs === false) {
            return '–';
        }

        return '– → ' . date($allday ? 'Y.m.d.' : 'Y.m.d. H:i', $endTs);
    }

    $startTs = strtotime((string) $startRaw);
    if ($startTs === false) {
        return '–';
    }

    if ($endRaw === null || $endRaw === '') {
        return date($allday ? 'Y.m.d.' : 'Y.m.d. H:i', $startTs);
    }

    $endTs = strtotime((string) $endRaw);
    if ($endTs === false) {
        return date($allday ? 'Y.m.d.' : 'Y.m.d. H:i', $startTs);
    }

    $sameDay = date('Y-m-d', $startTs) === date('Y-m-d', $endTs);

    if ($allday) {
        if ($sameDay) {
            return date('Y.m.d.', $startTs);
        }

        return date('Y.m.d.', $startTs) . ' → ' . date('Y.m.d.', $endTs);
    }

    if ($sameDay) {
        $day = date('Y.m.d.', $startTs);
        $tStart = date('H:i', $startTs);
        $tEnd = date('H:i', $endTs);
        if ($tStart === $tEnd) {
            return $day . ' ' . $tStart;
        }

        return $day . ' ' . $tStart . ' → ' . $tEnd;
    }

    return date('Y.m.d. H:i', $startTs) . ' → ' . date('Y.m.d. H:i', $endTs);
}
