<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/nextgen/includes/auth.php';
requireLogin();

$db = getDb();

$kereso = trim($_GET['kereso'] ?? '');
$allowedStatus = array_merge([''], events_allowed_post_statuses());
$status = isset($_GET['status']) && in_array((string) $_GET['status'], $allowedStatus, true) ? (string) $_GET['status'] : '';

$where = [];
$params = [];
if ($kereso !== '') {
    $like = '%' . $kereso . '%';
    if (ctype_digit($kereso)) {
        $where[] = '(e.event_name LIKE ? OR e.event_slug LIKE ? OR e.id = ?)';
        $params[] = $like;
        $params[] = $like;
        $params[] = (int) $kereso;
    } else {
        $where[] = '(e.event_name LIKE ? OR e.event_slug LIKE ?)';
        $params[] = $like;
        $params[] = $like;
    }
}
if ($status !== '') {
    $where[] = 'e.event_status = ?';
    $params[] = $status;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT e.*, o.name AS organizer_name,
        (SELECT COUNT(*) FROM `events_calendar_event_views` m WHERE m.`esemény_id` = e.id) AS megtekintesek
    FROM `events_calendar_events` e
    LEFT JOIN events_organizers o ON o.id = e.organizer_id
    $whereSql
    ORDER BY e.event_start IS NULL, e.event_start DESC, e.id DESC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$get_params = array_filter(['kereso' => $kereso, 'status' => $status ?: null]);

$pageTitle = 'Események';
require_once dirname(__DIR__) . '/nextgen/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>
<p class="toolbar" style="margin-bottom:1rem;">
    <a href="<?= h(nextgen_url('apps.php')) ?>" class="btn btn-secondary">← Alkalmazások</a>
    <a href="<?= h(nextgen_url('index.php')) ?>" class="btn btn-secondary">Finance →</a>
</p>
<div class="card">
    <h2>Események</h2>
    <p class="card-lead">Naptár admin – lista, keresés, szerkesztés.</p>

    <form method="get" class="toolbar">
        <input type="search" name="kereso" placeholder="Név, slug vagy ID…" value="<?= h($kereso) ?>">
        <select name="status" title="Státusz">
            <option value="">Minden státusz</option>
            <?php foreach (events_allowed_post_statuses() as $st): ?>
            <option value="<?= h($st) ?>" <?= $status === $st ? 'selected' : '' ?>><?= h(events_post_status_label($st)) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Szűrés</button>
        <a href="<?= h(events_url('letrehoz.php')) ?>" class="btn btn-primary">Új esemény</a>
        <a href="<?= h(events_url('import_csv.php')) ?>" class="btn btn-secondary">CSV import</a>
    </form>

    <div class="table-wrap">
        <table class="sortable-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Név</th>
                    <th>Slug</th>
                    <th>Kezdés</th>
                    <th>Státusz</th>
                    <th>Szervező</th>
                    <th class="th-center">Megtekintés</th>
                    <th>Műveletek</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= (int) $r['id'] ?></td>
                    <td><?= h($r['event_name']) ?></td>
                    <td><code><?= h($r['event_slug']) ?></code></td>
                    <td><?= !empty($r['event_start']) ? h(date('Y-m-d H:i', strtotime((string) $r['event_start']))) : '–' ?></td>
                    <td><?= h(events_post_status_label((string) $r['event_status'])) ?></td>
                    <td><?= h($r['organizer_name'] ?? '') ?: '–' ?></td>
                    <td class="text-center"><?= (int) $r['megtekintesek'] ?></td>
                    <td class="actions">
                        <?php if (($r['event_status'] ?? '') === events_public_post_status()): ?>
                        <a href="<?= h(events_megjelenit_url((string) $r['event_slug'])) ?>" class="btn btn-sm btn-secondary" target="_blank" rel="noopener">Nyilvános</a>
                        <?php endif; ?>
                        <a href="<?= h(events_url('szerkeszt.php?id=')) ?><?= (int) $r['id'] ?>" class="btn btn-sm btn-secondary">Szerkeszt</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (count($rows) === 0): ?>
        <p>Nincs találat.</p>
    <?php endif; ?>
</div>
<?php require_once dirname(__DIR__) . '/nextgen/partials/footer.php'; ?>
