<?php
$pageTitle = 'Rendszer log';
require_once __DIR__ . '/../partials/header.php';

$db = getDb();

$oldal = max(1, (int)($_GET['oldal'] ?? 1));
$per_page = 50;
$offset = ($oldal - 1) * $per_page;

$where = [];
$params = [];
if (!empty($_GET['entitas'])) {
    $where[] = 'entitás = ?';
    $params[] = $_GET['entitas'];
}
if (!empty($_GET['muvelet'])) {
    $where[] = 'művelet LIKE ?';
    $params[] = '%' . $_GET['muvelet'] . '%';
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$order = isset($_GET['order']) && in_array($_GET['order'], ['létrehozva', 'entitás', 'művelet'], true) ? $_GET['order'] : 'létrehozva';
$dir_param = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'asc' : 'desc';
$dir = $dir_param === 'desc' ? 'DESC' : 'ASC';

$total = $db->prepare("SELECT COUNT(*) FROM rendszer_log $where_sql");
$total->execute($params);
$total = (int) $total->fetchColumn();

$stmt = $db->prepare("
    SELECT r.*, a.név AS admin_név
    FROM rendszer_log r
    LEFT JOIN adminok a ON a.id = r.admin_id
    $where_sql
    ORDER BY r.$order $dir
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$logok = $stmt->fetchAll();

$get_params = array_filter(['entitas' => $_GET['entitas'] ?? '', 'muvelet' => $_GET['muvelet'] ?? '']);

$entitasok = $db->query("SELECT DISTINCT entitás FROM rendszer_log ORDER BY entitás")->fetchAll(PDO::FETCH_COLUMN);
?>
<div class="card">
    <h2>Rendszer log</h2>
    <p>Összes fenti adatbázisba tétel felvétel, státusz módosítás.</p>

    <form method="get" class="toolbar" action="<?= h(nextgen_url('admin/log.php')) ?>">
        <select name="entitas">
            <option value="">Minden entitás</option>
            <?php foreach ($entitasok as $e): ?>
                <option value="<?= h($e) ?>" <?= ($_GET['entitas'] ?? '') === $e ? 'selected' : '' ?>><?= h($e) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="muvelet" placeholder="Művelet keresése" value="<?= h($_GET['muvelet'] ?? '') ?>">
        <button type="submit" class="btn btn-primary">Szűrés</button>
    </form>

    <div class="table-wrap">
        <table class="sortable-table">
            <thead>
                <tr>
                    <th><?= sort_th('Időpont', 'létrehozva', $order, $dir_param, $get_params) ?></th>
                    <th><?= sort_th('Típus', 'entitás', $order, $dir_param, $get_params) ?></th>
                    <th>ID</th>
                    <th><?= sort_th('Művelet', 'művelet', $order, $dir_param, $get_params) ?></th>
                    <th>Részletek</th>
                    <th>Admin</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logok as $r):
                    $eid = (isset($r['entitás_id']) && $r['entitás_id'] !== '' && $r['entitás_id'] !== null) ? (int) $r['entitás_id'] : null;
                    $entity_url = log_entity_url($r['entitás'], $eid);
                ?>
                <tr>
                    <td><?= h($r['létrehozva']) ?></td>
                    <td><?= h($r['entitás']) ?></td>
                    <td>
                        <?php if ($entity_url !== null): ?>
                            <a href="<?= h(BASE_URL . $entity_url) ?>"><?= h($r['entitás_id']) ?></a>
                        <?php else: ?>
                            <?= h($r['entitás_id'] ?? '–') ?>
                        <?php endif; ?>
                    </td>
                    <td><?= h($r['művelet']) ?></td>
                    <td><?= h($r['részletek']) ?></td>
                    <td><?= h($r['admin_név'] ?? '–') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php
    $total_pages = (int) ceil($total / $per_page);
    if ($total_pages > 1):
        $q = http_build_query(array_filter([
            'entitas' => $_GET['entitas'] ?? '',
            'muvelet' => $_GET['muvelet'] ?? '',
            'order' => $order,
            'dir' => $dir_param,
        ]));
        $base = nextgen_url('admin/log.php?') . $q . ($q ? '&' : '');
    ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <?php if ($i === $oldal): ?>
                <span class="current"><?= $i ?></span>
            <?php else: ?>
                <a href="<?= h($base . 'oldal=' . $i) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
