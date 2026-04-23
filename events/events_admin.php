<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/nextgen/includes/auth.php';
requireLogin();

$db = getDb();

$f_organizer = trim($_GET['f_organizer'] ?? '');
$f_name = trim($_GET['f_name'] ?? '');
$f_id = trim($_GET['f_id'] ?? '');
$f_start_from = trim($_GET['f_start_from'] ?? '');
$f_start_to = trim($_GET['f_start_to'] ?? '');
$f_views_min = trim($_GET['f_views_min'] ?? '');

$allowedStatus = array_merge([''], events_allowed_post_statuses());
$status = isset($_GET['status']) && in_array((string) $_GET['status'], $allowedStatus, true) ? (string) $_GET['status'] : '';

$allowedOrder = ['id', 'organizer', 'name', 'start', 'end', 'status', 'views'];
if (isset($_GET['order']) && in_array((string) $_GET['order'], $allowedOrder, true)) {
    $order = (string) $_GET['order'];
    $dir_param = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'asc' : 'desc';
} else {
    $order = 'start';
    $dir_param = 'desc';
}

$boundsRow = $db->query('
    SELECT MIN(e.event_start) AS dmin, MAX(e.event_start) AS dmax
    FROM `events_calendar_events` e
    WHERE e.event_start IS NOT NULL
')->fetch(PDO::FETCH_ASSOC);

$now = new DateTimeImmutable('today');
if (!empty($boundsRow['dmin'])) {
    $axisMin = (new DateTimeImmutable($boundsRow['dmin']))->modify('first day of this month')->modify('-1 month');
} else {
    $axisMin = $now->modify('-18 months');
}
if (!empty($boundsRow['dmax'])) {
    $axisMax = (new DateTimeImmutable($boundsRow['dmax']))->modify('first day of this month')->modify('+2 months');
} else {
    $axisMax = $now->modify('+24 months');
}
if ($axisMax <= $axisMin) {
    $axisMax = $axisMin->modify('+1 year');
}
$axisMinStr = $axisMin->format('Y-m-d');
$axisMaxStr = $axisMax->format('Y-m-d');
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

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$dirSql = $dir_param === 'asc' ? 'ASC' : 'DESC';
$orderSql = match ($order) {
    'id' => "e.id $dirSql",
    'organizer' => "( (SELECT MIN(o.name) FROM `events_calendar_event_organizers` eo INNER JOIN `events_organizers` o ON o.id = eo.organizer_id WHERE eo.event_id = e.id) IS NULL) ASC, (SELECT MIN(o.name) FROM `events_calendar_event_organizers` eo INNER JOIN `events_organizers` o ON o.id = eo.organizer_id WHERE eo.event_id = e.id) $dirSql",
    'name' => "e.event_name $dirSql",
    'start' => "e.event_start IS NULL, e.event_start $dirSql",
    'end' => "e.event_end IS NULL, e.event_end $dirSql",
    'status' => "e.event_status $dirSql",
    'views' => "megtekintesek $dirSql",
    default => 'e.event_start IS NULL, e.event_start DESC',
};

$sql = "
    SELECT e.*,
        (SELECT GROUP_CONCAT(o.name ORDER BY eo.sort_order ASC, o.name ASC SEPARATOR ', ')
         FROM `events_calendar_event_organizers` eo
         INNER JOIN `events_organizers` o ON o.id = eo.organizer_id
         WHERE eo.event_id = e.id) AS organizer_name,
        (SELECT COUNT(*) FROM `events_calendar_event_views` m WHERE m.`esemény_id` = e.id) AS megtekintesek
    FROM `events_calendar_events` e
    $whereSql
    ORDER BY $orderSql
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$get_params = array_filter([
    'f_organizer' => $f_organizer !== '' ? $f_organizer : null,
    'f_name' => $f_name !== '' ? $f_name : null,
    'f_id' => $f_id !== '' ? $f_id : null,
    'f_start_from' => $f_start_from !== '' ? $f_start_from : null,
    'f_start_to' => $f_start_to !== '' ? $f_start_to : null,
    'f_views_min' => $f_views_min !== '' ? $f_views_min : null,
    'status' => $status !== '' ? $status : null,
]);

$editBase = events_url('szerkeszt.php?id=');

function events_admin_format_datum_cell(array $r): string {
    $allday = !empty($r['event_allday']);
    $fmt = $allday ? 'Y.m.d.' : 'Y.m.d. H:i';
    $start = !empty($r['event_start']) ? date($fmt, strtotime((string) $r['event_start'])) : '–';
    $endRaw = $r['event_end'] ?? null;
    if ($endRaw === null || $endRaw === '') {
        return $start;
    }
    $end = date($fmt, strtotime((string) $endRaw));
    if ($start === '–' && $end !== '') {
        return '– → ' . $end;
    }
    return $start . ' → ' . $end;
}

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Események';
require_once dirname(__DIR__) . '/nextgen/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="card events-admin-card">
    <form method="get" action="<?= h(events_url('events_admin.php')) ?>" class="events-admin-form" id="events-admin-filter-form">
        <input type="hidden" name="order" value="<?= h($order) ?>">
        <input type="hidden" name="dir" value="<?= h($dir_param) ?>">

        <div class="events-list-head">
            <h2 class="events-list-title">Események</h2>
            <div class="events-list-actions">
                <a href="<?= h(events_url('events_admin.php')) ?>" class="btn btn-secondary">Szűrők törlése</a>
                <a href="<?= h(events_url('letrehoz.php')) ?>" class="btn btn-primary">Új esemény</a>
                <a href="<?= h(events_url('import_csv.php')) ?>" class="btn btn-secondary">CSV import</a>
            </div>
        </div>

        <section class="events-filters-shell" aria-label="Szűrők"
            data-axis-min="<?= h($axisMinStr) ?>"
            data-axis-days="<?= (int) $daysSpan ?>"
            data-idx-from="<?= (int) $idxFrom ?>"
            data-idx-to="<?= (int) $idxTo ?>">
            <div class="events-filters-grid">
                <div class="events-filter-field">
                    <label class="events-filter-label" for="ev-f-organizer">Szervező</label>
                    <input class="events-filter-input" type="text" name="f_organizer" id="ev-f-organizer" value="<?= h($f_organizer) ?>" placeholder="Részlet a névből…" autocomplete="off">
                </div>
                <div class="events-filter-field">
                    <label class="events-filter-label" for="ev-f-name">Esemény neve</label>
                    <input class="events-filter-input" type="text" name="f_name" id="ev-f-name" value="<?= h($f_name) ?>" placeholder="Keresés a címben…" autocomplete="off">
                </div>
                <div class="events-filter-field">
                    <label class="events-filter-label" for="ev-f-id">ID</label>
                    <input class="events-filter-input" type="text" name="f_id" id="ev-f-id" value="<?= h($f_id) ?>" placeholder="Pl. 100001" inputmode="numeric" autocomplete="off">
                </div>
                <div class="events-filter-field">
                    <label class="events-filter-label" for="ev-f-views">Min. megtekintés</label>
                    <input class="events-filter-input" type="number" name="f_views_min" id="ev-f-views" value="<?= h($f_views_min) ?>" placeholder="0" min="0" step="1">
                </div>
                <div class="events-filter-field events-filter-field--status">
                    <label class="events-filter-label" for="ev-f-status">Státusz</label>
                    <div class="events-filter-select-wrap">
                        <select class="events-filter-select events-filter-status" name="status" id="ev-f-status" title="Státusz szűrő">
                            <option value="">Összes státusz</option>
                            <?php foreach (events_allowed_post_statuses() as $st): ?>
                                <option value="<?= h($st) ?>" <?= $status === $st ? 'selected' : '' ?>><?= h(events_post_status_label($st)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="events-filter-field events-filter-field--full">
                    <div class="events-date-slider-row">
                        <div class="events-date-range-visual">
                            <div class="events-date-range-track-bg" aria-hidden="true"></div>
                            <div class="events-date-range-fill" id="ev-date-range-fill" aria-hidden="true"></div>
                            <input type="range" class="events-range events-range-from" id="ev-range-from" min="0" max="<?= (int) $daysSpan ?>" value="<?= (int) $idxFrom ?>" step="1" aria-valuemin="0" aria-valuemax="<?= (int) $daysSpan ?>" aria-label="Kezdő nap a tengelyen">
                            <input type="range" class="events-range events-range-to" id="ev-range-to" min="0" max="<?= (int) $daysSpan ?>" value="<?= (int) $idxTo ?>" step="1" aria-label="Záró nap a tengelyen">
                        </div>
                        <button type="submit" class="btn btn-primary events-filter-submit-inline">Szűrés alkalmazása</button>
                    </div>
                    <div class="events-date-range-readouts">
                        <div class="events-date-readout">
                            <span class="events-date-readout-label" id="ev-lbl-from">Ettől</span>
                            <input class="events-filter-input events-filter-input--date" type="date" name="f_start_from" id="ev-f-start-from" value="<?= h($f_start_from) ?>">
                        </div>
                        <div class="events-date-readout">
                            <span class="events-date-readout-label" id="ev-lbl-to">Eddig</span>
                            <input class="events-filter-input events-filter-input--date" type="date" name="f_start_to" id="ev-f-start-to" value="<?= h($f_start_to) ?>">
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="table-wrap events-admin-table-wrap">
            <table class="sortable-table events-admin-table">
                <thead>
                    <tr>
                        <th class="events-th-actions" scope="col"><span class="visually-hidden">Műveletek</span></th>
                        <th><?= sort_th('Szervező', 'organizer', $order, $dir_param, $get_params) ?></th>
                        <th><?= sort_th('Név', 'name', $order, $dir_param, $get_params) ?></th>
                        <th><?= sort_th('Dátum', 'start', $order, $dir_param, $get_params) ?></th>
                        <th><?= sort_th('Státusz', 'status', $order, $dir_param, $get_params) ?></th>
                        <th class="th-center"><?= sort_th('Megtekintés', 'views', $order, $dir_param, $get_params) ?></th>
                        <th><?= sort_th('ID', 'id', $order, $dir_param, $get_params) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <?php
                        $eid = (int) $r['id'];
                        $edit = $editBase . $eid;
                        $st = (string) ($r['event_status'] ?? '');
                        $badgeClass = events_post_status_badge_class($st);
                        ?>
                        <tr>
                            <td class="events-td-actions">
                                <div class="events-action-icons">
                                    <a href="<?= h($edit) ?>" class="events-icon-action" title="Szerkesztés" aria-label="Szerkesztés">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" aria-hidden="true"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </a>
                                    <?php if (($r['event_status'] ?? '') === events_public_post_status()): ?>
                                        <a href="<?= h(events_megjelenit_url((string) $r['event_slug'])) ?>" class="events-icon-action" title="Nyilvános megtekintés (új lap)" aria-label="Nyilvános megtekintés új lapon" target="_blank" rel="noopener">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" aria-hidden="true"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><a class="events-cell-edit" href="<?= h($edit) ?>"><?= ($r['organizer_name'] ?? '') !== '' ? h((string) $r['organizer_name']) : '–' ?></a></td>
                            <td><a class="events-cell-edit" href="<?= h($edit) ?>"><?= h((string) $r['event_name']) ?></a></td>
                            <td><a class="events-cell-edit" href="<?= h($edit) ?>"><?= h(events_admin_format_datum_cell($r)) ?></a></td>
                            <td>
                                <a class="events-cell-edit events-cell-edit--badge" href="<?= h($edit) ?>">
                                    <span class="event-status-badge <?= h($badgeClass) ?>"><?= h(events_post_status_label($st)) ?></span>
                                </a>
                            </td>
                            <td class="text-center"><a class="events-cell-edit" href="<?= h($edit) ?>"><?= (int) $r['megtekintesek'] ?></a></td>
                            <td><a class="events-cell-edit" href="<?= h($edit) ?>"><?= $eid ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
    <?php if (count($rows) === 0): ?>
        <p class="events-admin-empty">Nincs találat.</p>
    <?php endif; ?>
</div>
<script>
(function () {
    var shell = document.querySelector('.events-filters-shell');
    if (!shell) return;
    var axisMin = shell.getAttribute('data-axis-min');
    var days = parseInt(shell.getAttribute('data-axis-days'), 10) || 0;
    if (!axisMin || days < 1) return;

    var axisStart = new Date(axisMin + 'T12:00:00');
    function idxToYmd(idx) {
        var d = new Date(axisStart);
        d.setDate(d.getDate() + idx);
        var y = d.getFullYear();
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + day;
    }
    function ymdToIdx(ymd) {
        if (!ymd) return null;
        var t = new Date(ymd + 'T12:00:00').getTime();
        if (isNaN(t)) return null;
        var diff = Math.round((t - axisStart.getTime()) / 86400000);
        if (diff < 0) return 0;
        if (diff > days) return days;
        return diff;
    }
    var rFrom = document.getElementById('ev-range-from');
    var rTo = document.getElementById('ev-range-to');
    var dFrom = document.getElementById('ev-f-start-from');
    var dTo = document.getElementById('ev-f-start-to');
    var fill = document.getElementById('ev-date-range-fill');
    if (!rFrom || !rTo || !dFrom || !dTo) return;

    function parseIdx(el) {
        var v = parseInt(el.value, 10);
        if (isNaN(v)) return 0;
        if (v < 0) return 0;
        if (v > days) return days;
        return v;
    }
    function updateFill() {
        var a = parseIdx(rFrom);
        var b = parseIdx(rTo);
        if (a > b) { b = a; rTo.value = String(b); }
        var p0 = (a / days) * 100;
        var p1 = (b / days) * 100;
        if (fill) {
            fill.style.left = p0 + '%';
            fill.style.width = Math.max(0, p1 - p0) + '%';
        }
    }
    function syncSlidersToDates() {
        var i0 = ymdToIdx(dFrom.value);
        var i1 = ymdToIdx(dTo.value);
        if (dFrom.value === '') rFrom.value = '0';
        else if (i0 !== null) rFrom.value = String(i0);
        if (dTo.value === '') rTo.value = String(days);
        else if (i1 !== null) rTo.value = String(i1);
        var a = parseIdx(rFrom);
        var b = parseIdx(rTo);
        if (a > b) { rFrom.value = String(b); a = b; }
        updateFill();
    }
    function syncDatesFromSliders() {
        var a = parseIdx(rFrom);
        var b = parseIdx(rTo);
        if (a > b) { rTo.value = String(a); b = a; }
        dFrom.value = a === 0 ? '' : idxToYmd(a);
        dTo.value = b >= days ? '' : idxToYmd(b);
        updateFill();
    }

    rFrom.addEventListener('input', syncDatesFromSliders);
    rTo.addEventListener('input', syncDatesFromSliders);
    dFrom.addEventListener('change', syncSlidersToDates);
    dTo.addEventListener('change', syncSlidersToDates);

    syncSlidersToDates();
})();
</script>
<?php require_once dirname(__DIR__) . '/nextgen/partials/footer.php'; ?>
