<?php
$pageTitle = 'Adminok';
require_once __DIR__ . '/../../../nextgen/core/database.php';
require_once __DIR__ . '/../../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../../nextgen/includes/functions.php';
requireSuperadmin();

require_once __DIR__ . '/../../partials/header.php';

$db = getDb();
try {
    $col = $db->query("SHOW COLUMNS FROM nextgen_admins LIKE 'email'")->fetch();
    if (!$col) {
        $db->exec("ALTER TABLE nextgen_admins ADD COLUMN email VARCHAR(255) NULL AFTER felhasználónév");
    }
} catch (Throwable $e) {
    // nincs ALTER jog: migrációval felvehető
}
$kereso = trim($_GET['kereso'] ?? '');
$order = isset($_GET['order']) && in_array($_GET['order'], ['név', 'felhasználónév', 'email', 'szint', 'létrehozva'], true) ? $_GET['order'] : 'név';
$dir_param = isset($_GET['dir']) && $_GET['dir'] === 'desc' ? 'desc' : 'asc';
$dir = $dir_param === 'desc' ? 'DESC' : 'ASC';

$where = '';
$params = [];
if ($kereso !== '') {
    $where = 'WHERE név LIKE ? OR felhasználónév LIKE ? OR email LIKE ?';
    $p = '%' . $kereso . '%';
    $params = [$p, $p, $p];
}
$order_sql = $order === 'név' ? 'név' : ($order === 'felhasználónév' ? 'felhasználónév' : ($order === 'email' ? 'email' : ($order === 'szint' ? 'szint' : 'létrehozva')));
$stmt = $db->prepare("SELECT id, név, felhasználónév, email, szint, aktív, létrehozva FROM nextgen_admins $where ORDER BY $order_sql $dir");
$stmt->execute($params);
$adminok = $stmt->fetchAll();

$get_params = array_filter(['kereso' => $kereso]);
?>
<div class="card">
    <h2>Admin felhasználók</h2>
    <form method="get" class="toolbar">
        <input type="search" name="kereso" placeholder="Név, felhasználónév vagy e-mail..." value="<?= h($kereso) ?>">
        <button type="submit" class="btn btn-primary">Keresés</button>
        <a href="<?= h(nextgen_url('admin/adminok/letrehoz.php')) ?>" class="btn btn-primary">Új admin</a>
    </form>
    <div class="table-wrap">
        <table class="sortable-table">
            <thead>
                <tr>
                    <th><?= sort_th('Név', 'név', $order, $dir_param, $get_params) ?></th>
                    <th><?= sort_th('Felhasználónév', 'felhasználónév', $order, $dir_param, $get_params) ?></th>
                    <th><?= sort_th('E-mail', 'email', $order, $dir_param, $get_params) ?></th>
                    <th><?= sort_th('Szint', 'szint', $order, $dir_param, $get_params) ?></th>
                    <th>Státusz</th>
                    <th><?= sort_th('Létrehozva', 'létrehozva', $order, $dir_param, $get_params) ?></th>
                    <th>Műveletek</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($adminok as $a): ?>
                <tr>
                    <td><?= h($a['név']) ?></td>
                    <td><?= h($a['felhasználónév']) ?></td>
                    <td><?= h($a['email'] ?? '') ?></td>
                    <td><?= (isset($a['szint']) && $a['szint'] === 'superadmin') ? 'Superadmin' : 'Admin' ?></td>
                    <td><?= $a['aktív'] ? 'Aktív' : 'Letiltva' ?></td>
                    <td><?= h($a['létrehozva']) ?></td>
                    <td class="actions">
                        <a href="<?= h(nextgen_url('admin/adminok/szerkeszt.php?id=')) ?><?= (int)$a['id'] ?>" class="btn btn-sm btn-secondary">Szerkeszt</a>
                        <?php if ((int)$a['id'] !== (int)($_SESSION['admin_id'] ?? 0)): ?>
                            <?php if ($a['aktív']): ?>
                                <a href="<?= h(nextgen_url('admin/adminok/letilt.php?id=')) ?><?= (int)$a['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Letiltja ezt az admint?');">Letiltás</a>
                            <?php else: ?>
                                <a href="<?= h(nextgen_url('admin/adminok/engedelyez.php?id=')) ?><?= (int)$a['id'] ?>" class="btn btn-sm btn-primary">Engedélyezés</a>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">(Ön)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
