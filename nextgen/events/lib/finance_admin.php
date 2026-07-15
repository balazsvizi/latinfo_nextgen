<?php
declare(strict_types=1);

require_once __DIR__ . '/organizer_finance.php';
require_once __DIR__ . '/admin_event_filters.php';
require_once __DIR__ . '/event_status.php';

/**
 * Event Admin – Finance lista szűrők / rendezés.
 *
 * @return array{
 *   f_q: string,
 *   f_id: string,
 *   f_organizer: string,
 *   f_payer: string,
 *   f_note: string,
 *   f_cost_from: string,
 *   f_cost_to: string,
 *   f_fee: string,
 *   f_has_fee: string,
 *   f_status: string,
 *   f_start_from: string,
 *   f_start_to: string,
 *   order: string,
 *   dir_param: string,
 *   where: list<string>,
 *   params: list<mixed>,
 *   get_params: array<string, string>
 * }
 */
function events_finance_admin_filters_from_request(): array
{
    $f_q = trim((string) ($_GET['f_q'] ?? ''));
    $f_id = trim((string) ($_GET['f_id'] ?? ''));
    $f_organizer = trim((string) ($_GET['f_organizer'] ?? ''));
    $f_payer = trim((string) ($_GET['f_payer'] ?? ''));
    $f_note = trim((string) ($_GET['f_note'] ?? ''));
    $f_cost_from = trim((string) ($_GET['f_cost_from'] ?? ''));
    $f_cost_to = trim((string) ($_GET['f_cost_to'] ?? ''));
    $f_fee = trim((string) ($_GET['f_fee'] ?? ''));
    $f_has_fee = trim((string) ($_GET['f_has_fee'] ?? ''));
    if ($f_has_fee !== 'yes' && $f_has_fee !== 'no') {
        $f_has_fee = '';
    }
    $f_status = trim((string) ($_GET['f_status'] ?? ''));
    if ($f_status !== '' && !events_is_allowed_post_status($f_status)) {
        $f_status = '';
    }
    $f_start_from = trim((string) ($_GET['f_start_from'] ?? ''));
    $f_start_to = trim((string) ($_GET['f_start_to'] ?? ''));
    if ($f_start_from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_start_from)) {
        $f_start_from = '';
    }
    if ($f_start_to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_start_to)) {
        $f_start_to = '';
    }

    $allowedOrder = [
        'id', 'start', 'name', 'organizer', 'status',
        'cost_from', 'cost_to', 'fee',
        'cal_previews', 'fee_per_preview', 'views', 'fee_per_view',
        'payer', 'note',
    ];
    if (isset($_GET['order']) && in_array((string) $_GET['order'], $allowedOrder, true)) {
        $order = (string) $_GET['order'];
        $dir_param = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'asc' : 'desc';
    } else {
        $order = 'start';
        $dir_param = 'desc';
    }

    $where = [];
    $params = [];

    if ($f_q !== '') {
        $where[] = '(e.event_name LIKE ? OR CAST(e.id AS CHAR) LIKE ? OR e.finance_note LIKE ?)';
        $like = '%' . $f_q . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
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
    if ($f_organizer !== '') {
        $where[] = 'EXISTS (
            SELECT 1 FROM `events_calendar_event_organizers` eo
            INNER JOIN `events_organizers` o ON o.id = eo.organizer_id
            WHERE eo.event_id = e.id AND o.name LIKE ?
        )';
        $params[] = '%' . $f_organizer . '%';
    }
    if ($f_payer !== '') {
        $where[] = 'EXISTS (
            SELECT 1 FROM `events_organizers` po
            WHERE po.id = e.finance_payer_organizer_id AND po.name LIKE ?
        )';
        $params[] = '%' . $f_payer . '%';
    }
    if ($f_note !== '') {
        $where[] = 'e.finance_note LIKE ?';
        $params[] = '%' . $f_note . '%';
    }
    if ($f_cost_from !== '') {
        $norm = str_replace([' ', ','], ['', '.'], $f_cost_from);
        if (is_numeric($norm)) {
            $where[] = 'e.event_cost_from = ?';
            $params[] = (float) $norm;
        }
    }
    if ($f_cost_to !== '') {
        $norm = str_replace([' ', ','], ['', '.'], $f_cost_to);
        if (is_numeric($norm)) {
            $where[] = 'e.event_cost_to = ?';
            $params[] = (float) $norm;
        }
    }
    if ($f_fee !== '') {
        $norm = str_replace([' ', ','], ['', '.'], $f_fee);
        if (is_numeric($norm)) {
            $where[] = 'e.finance_organizer_fee = ?';
            $params[] = (float) $norm;
        }
    }
    if ($f_has_fee === 'yes') {
        $where[] = 'e.finance_organizer_fee IS NOT NULL AND e.finance_organizer_fee > 0';
    } elseif ($f_has_fee === 'no') {
        $where[] = '(e.finance_organizer_fee IS NULL OR e.finance_organizer_fee = 0)';
    }
    if ($f_status !== '') {
        $where[] = 'e.event_status = ?';
        $params[] = $f_status;
    }
    if ($f_start_from !== '') {
        $where[] = 'DATE(e.event_start) >= ?';
        $params[] = $f_start_from;
    }
    if ($f_start_to !== '') {
        $where[] = 'DATE(e.event_start) <= ?';
        $params[] = $f_start_to;
    }

    $get_params = [];
    foreach ([
        'f_q' => $f_q,
        'f_id' => $f_id,
        'f_organizer' => $f_organizer,
        'f_payer' => $f_payer,
        'f_note' => $f_note,
        'f_cost_from' => $f_cost_from,
        'f_cost_to' => $f_cost_to,
        'f_fee' => $f_fee,
        'f_has_fee' => $f_has_fee,
        'f_status' => $f_status,
        'f_start_from' => $f_start_from,
        'f_start_to' => $f_start_to,
        'order' => $order,
        'dir' => $dir_param,
    ] as $k => $v) {
        if ($v !== '' && $v !== null) {
            $get_params[$k] = (string) $v;
        }
    }

    return [
        'f_q' => $f_q,
        'f_id' => $f_id,
        'f_organizer' => $f_organizer,
        'f_payer' => $f_payer,
        'f_note' => $f_note,
        'f_cost_from' => $f_cost_from,
        'f_cost_to' => $f_cost_to,
        'f_fee' => $f_fee,
        'f_has_fee' => $f_has_fee,
        'f_status' => $f_status,
        'f_start_from' => $f_start_from,
        'f_start_to' => $f_start_to,
        'order' => $order,
        'dir_param' => $dir_param,
        'where' => $where,
        'params' => $params,
        'get_params' => $get_params,
    ];
}

/**
 * @param array<string, mixed> $filters
 * @return list<array<string, mixed>>
 */
function events_finance_admin_fetch(PDO $db, array $filters, ?int $listLimit): array
{
    events_organizer_finance_ensure_schema($db);

    $whereSql = $filters['where'] !== [] ? 'WHERE ' . implode(' AND ', $filters['where']) : '';
    $params = $filters['params'];
    $poolFromSql = events_admin_list_pool_from_sql($listLimit);
    $dirSql = ($filters['dir_param'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    $order = (string) ($filters['order'] ?? 'start');

    $orderSql = match ($order) {
        'id' => "e.id {$dirSql}",
        'name' => "e.event_name {$dirSql}",
        'start' => "e.event_start IS NULL, e.event_start {$dirSql}",
        'status' => "e.event_status {$dirSql}",
        'cost_from' => "e.event_cost_from IS NULL, e.event_cost_from {$dirSql}",
        'cost_to' => "e.event_cost_to IS NULL, e.event_cost_to {$dirSql}",
        'fee' => "e.finance_organizer_fee IS NULL, e.finance_organizer_fee {$dirSql}",
        'cal_previews' => "naptar_elonezetek {$dirSql}",
        'fee_per_preview' => "fee_per_preview IS NULL, fee_per_preview {$dirSql}",
        'views' => "megtekintesek {$dirSql}",
        'fee_per_view' => "fee_per_view IS NULL, fee_per_view {$dirSql}",
        'note' => "(e.finance_note IS NULL OR e.finance_note = ''), e.finance_note {$dirSql}",
        'payer' => "(payer_name IS NULL OR payer_name = ''), payer_name {$dirSql}",
        'organizer' => "(organizer_name IS NULL OR organizer_name = ''), organizer_name {$dirSql}",
        default => 'e.event_start IS NULL, e.event_start DESC',
    };

    $sql = "
        SELECT e.id, e.event_name, e.event_slug, e.event_status, e.event_start,
               e.event_cost_from, e.event_cost_to, e.finance_organizer_fee,
               e.finance_payer_organizer_id, e.finance_note,
               (SELECT GROUP_CONCAT(o.name ORDER BY eo.sort_order ASC, o.name ASC SEPARATOR ', ')
                FROM `events_calendar_event_organizers` eo
                INNER JOIN `events_organizers` o ON o.id = eo.organizer_id
                WHERE eo.event_id = e.id) AS organizer_name,
               (SELECT po.name FROM `events_organizers` po WHERE po.id = e.finance_payer_organizer_id LIMIT 1) AS payer_name,
               (SELECT COUNT(*) FROM `events_calendar_event_views` m
                WHERE m.`esemény_id` = e.id AND m.`metric_type` = 'calendar_preview') AS naptar_elonezetek,
               (SELECT COUNT(*) FROM `events_calendar_event_views` m
                WHERE m.`esemény_id` = e.id AND m.`metric_type` = 'page_view') AS megtekintesek,
               CASE
                   WHEN e.finance_organizer_fee IS NOT NULL AND e.finance_organizer_fee > 0
                        AND (SELECT COUNT(*) FROM `events_calendar_event_views` m
                             WHERE m.`esemény_id` = e.id AND m.`metric_type` = 'calendar_preview') > 0
                   THEN e.finance_organizer_fee / (
                       SELECT COUNT(*) FROM `events_calendar_event_views` m
                       WHERE m.`esemény_id` = e.id AND m.`metric_type` = 'calendar_preview'
                   )
                   ELSE NULL
               END AS fee_per_preview,
               CASE
                   WHEN e.finance_organizer_fee IS NOT NULL AND e.finance_organizer_fee > 0
                        AND (SELECT COUNT(*) FROM `events_calendar_event_views` m
                             WHERE m.`esemény_id` = e.id AND m.`metric_type` = 'page_view') > 0
                   THEN e.finance_organizer_fee / (
                       SELECT COUNT(*) FROM `events_calendar_event_views` m
                       WHERE m.`esemény_id` = e.id AND m.`metric_type` = 'page_view'
                   )
                   ELSE NULL
               END AS fee_per_view
        FROM {$poolFromSql}
        {$whereSql}
        ORDER BY {$orderSql}
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * @return array{
 *   events_total: int,
 *   with_fee: int,
 *   without_fee: int,
 *   fee_sum: float,
 *   with_payer: int,
 *   with_cost: int
 * }
 */
function events_finance_dashboard_stats(PDO $db): array
{
    events_organizer_finance_ensure_schema($db);

    $eventsTotal = (int) $db->query('SELECT COUNT(*) FROM `events_calendar_events`')->fetchColumn();
    $withFee = (int) $db->query('
        SELECT COUNT(*) FROM `events_calendar_events`
        WHERE `finance_organizer_fee` IS NOT NULL AND `finance_organizer_fee` > 0
    ')->fetchColumn();
    $feeSum = (float) $db->query('
        SELECT COALESCE(SUM(`finance_organizer_fee`), 0) FROM `events_calendar_events`
        WHERE `finance_organizer_fee` IS NOT NULL
    ')->fetchColumn();
    $withPayer = (int) $db->query('
        SELECT COUNT(*) FROM `events_calendar_events`
        WHERE `finance_payer_organizer_id` IS NOT NULL AND `finance_payer_organizer_id` > 0
    ')->fetchColumn();
    $withCost = (int) $db->query('
        SELECT COUNT(*) FROM `events_calendar_events`
        WHERE (`event_cost_from` IS NOT NULL AND `event_cost_from` > 0)
           OR (`event_cost_to` IS NOT NULL AND `event_cost_to` > 0)
    ')->fetchColumn();

    return [
        'events_total' => $eventsTotal,
        'with_fee' => $withFee,
        'without_fee' => max(0, $eventsTotal - $withFee),
        'fee_sum' => $feeSum,
        'with_payer' => $withPayer,
        'with_cost' => $withCost,
    ];
}

function events_finance_format_money(null|int|float|string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $n = (float) $value;

    return number_format($n, $n == floor($n) ? 0 : 2, ',', ' ') . ' Ft';
}

function events_finance_format_start_date(?string $datetime): string
{
    if ($datetime === null || trim($datetime) === '') {
        return '—';
    }
    $dt = date_create($datetime);
    if ($dt === false) {
        return '—';
    }

    return $dt->format('Y.m.d.');
}

function events_finance_format_fee_per_click(null|int|float|string $feePerClick): string
{
    if ($feePerClick === null || $feePerClick === '') {
        return '—';
    }

    return events_finance_format_money((float) $feePerClick);
}
