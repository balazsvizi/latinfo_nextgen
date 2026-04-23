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

$where = [];
$params = [];

if ($f_organizer !== '') {
    $where[] = 'o.name LIKE ?';
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
    'organizer' => "(o.name IS NULL) ASC, o.name $dirSql",
    'name' => "e.event_name $dirSql",
    'start' => "e.event_start IS NULL, e.event_start $dirSql",
    'end' => "e.event_end IS NULL, e.event_end $dirSql",
    'status' => "e.event_status $dirSql",
    'views' => "megtekintesek $dirSql",
    default => 'e.event_start IS NULL, e.event_start DESC',
};

$sql = "
    SELECT e.*, o.name AS organizer_name,
        (SELECT COUNT(*) FROM `events_calendar_event_views` m WHERE m.`esemény_id` = e.id) AS megtekintesek
    FROM `events_calendar_events` e
    LEFT JOIN events_organizers o ON o.id = e.organizer_id
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

$pageTitle = 'Események';
require_once dirname(__DIR__) . '/nextgen/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="card events-admin-card">
    <form method="get" action="<?= h(events_url('events_admin.php')) ?>" class="events-admin-form">
        <input type="hidden" name="order" value="<?= h($order) ?>">
        <input type="hidden" name="dir" value="<?= h($dir_param) ?>">
        <div class="events-list-head">
            <h2 class="events-list-title">Események</h2>
            <div class="events-list-actions">
                <button type="submit" class="btn btn-primary">Szűrés</button>
                <a href="<?= h(events_url('events_admin.php')) ?>" class="btn btn-secondary">Szűrők törlése</a>
                <a href="<?= h(events_url('letrehoz.php')) ?>" class="btn btn-primary">Új esemény</a>
                <a href="<?= h(events_url('import_csv.php')) ?>" class="btn btn-secondary">CSV import</a>
            </div>
        </div>

        <div class="table-wrap">
            <table class="sortable-table events-admin-table">
                <thead>
                    <tr>
                        <th><?= sort_th('Szervező', 'organizer', $order, $dir_param, $get_params) ?></th>
                        <th><?= sort_th('Név', 'name', $order, $dir_param, $get_params) ?></th>
                        <th><?= sort_th('Dátum', 'start', $order, $dir_param, $get_params) ?></th>
                        <th><?= sort_th('Státusz', 'status', $order, $dir_param, $get_params) ?></th>
                        <th class="th-center"><?= sort_th('Megtekintés', 'views', $order, $dir_param, $get_params) ?></th>
                        <th>Műveletek</th>
                        <th><?= sort_th('ID', 'id', $order, $dir_param, $get_params) ?></th>
                    </tr>
                    <tr class="events-filter-row">
                        <th><input type="text" name="f_organizer" value="<?= h($f_organizer) ?>" placeholder="Szűrés…" autocomplete="off"></th>
                        <th><input type="text" name="f_name" value="<?= h($f_name) ?>" placeholder="Szűrés…" autocomplete="off"></th>
                        <th class="events-th-dates">
                            <input type="date" name="f_start_from" value="<?= h($f_start_from) ?>" title="Kezdés tól">
                            <input type="date" name="f_start_to" value="<?= h($f_start_to) ?>" title="Kezdés ig">
                        </th>
                        <th>
                            <select name="status" title="Státusz">
                                <option value="">Mind</option>
                                <?php foreach (events_allowed_post_statuses() as $st): ?>
                                    <option value="<?= h($st) ?>" <?= $status === $st ? 'selected' : '' ?>><?= h(events_post_status_label($st)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </th>
                        <th><input type="number" name="f_views_min" value="<?= h($f_views_min) ?>" placeholder="Min." min="0" step="1" title="Minimum megtekintés"></th>
                        <th></th>
                        <th><input type="text" name="f_id" value="<?= h($f_id) ?>" placeholder="ID" inputmode="numeric" autocomplete="off"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <?php $eid = (int) $r['id']; $edit = $editBase . $eid; ?>
                        <tr>
                            <td><a class="events-cell-edit" href="<?= h($edit) ?>"><?= ($r['organizer_name'] ?? '') !== '' ? h((string) $r['organizer_name']) : '–' ?></a></td>
                            <td><a class="events-cell-edit" href="<?= h($edit) ?>"><?= h((string) $r['event_name']) ?></a></td>
                            <td><a class="events-cell-edit" href="<?= h($edit) ?>"><?= h(events_admin_format_datum_cell($r)) ?></a></td>
                            <td><a class="events-cell-edit" href="<?= h($edit) ?>"><?= h(events_post_status_label((string) $r['event_status'])) ?></a></td>
                            <td class="text-center"><a class="events-cell-edit" href="<?= h($edit) ?>"><?= (int) $r['megtekintesek'] ?></a></td>
                            <td class="actions">
                                <?php if (($r['event_status'] ?? '') === events_public_post_status()): ?>
                                    <a href="<?= h(events_megjelenit_url((string) $r['event_slug'])) ?>" class="btn btn-sm btn-secondary" target="_blank" rel="noopener">Nyilvános</a>
                                <?php endif; ?>
                                <a href="<?= h($edit) ?>" class="btn btn-sm btn-secondary">Szerkeszt</a>
                            </td>
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
<?php require_once dirname(__DIR__) . '/nextgen/partials/footer.php'; ?>
