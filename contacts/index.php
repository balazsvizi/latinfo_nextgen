<?php
$pageTitle = 'Kontaktok';
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDb();
$kereso = trim($_GET['kereso'] ?? '');
$order = isset($_GET['order']) && in_array($_GET['order'], ['név', 'email', 'telefon', 'szervezo_nevek', 'tipusok'], true) ? $_GET['order'] : 'név';
$dir_param = isset($_GET['dir']) && $_GET['dir'] === 'desc' ? 'desc' : 'asc';
$dir = $dir_param === 'desc' ? 'DESC' : 'ASC';

$where = '';
$params = [];
if ($kereso !== '') {
    $where = 'WHERE k.név LIKE ? OR k.email LIKE ? OR k.telefon LIKE ?';
    $p = '%' . $kereso . '%';
    $params = [$p, $p, $p];
}

$order_col = $order === 'szervezo_nevek' ? 'szervezo_nevek' : ($order === 'tipusok' ? 'tipusok' : 'k.' . $order);
$stmt = $db->prepare("
    SELECT k.*,
           (SELECT GROUP_CONCAT(sz.név ORDER BY sz.név SEPARATOR ', ')
            FROM szervező_kontakt sk
            JOIN szervezők sz ON sz.id = sk.szervező_id
            WHERE sk.kontakt_id = k.id) AS szervezo_nevek,
           (SELECT GROUP_CONCAT(t.név ORDER BY t.név SEPARATOR ', ')
            FROM kontakt_típus_kapcsolat kt
            JOIN kontakt_típusok t ON t.id = kt.típus_id
            WHERE kt.kontakt_id = k.id) AS tipusok
    FROM kontaktok k
    $where
    ORDER BY $order_col $dir
");
$stmt->execute($params);
$kontaktok = $stmt->fetchAll();

$get_params = array_filter(['kereso' => $kereso]);
?>
<div class="card">
    <h2>Kontaktok</h2>
    <form method="get" class="toolbar">
        <input type="search" name="kereso" placeholder="Név, e-mail vagy telefon..." value="<?= h($kereso) ?>">
        <button type="submit" class="btn btn-primary">Keresés</button>
        <a href="<?= h(BASE_URL) ?>/contacts/letrehoz.php" class="btn btn-primary">Új kontakt</a>
    </form>
    <div class="table-wrap">
        <table class="sortable-table">
            <thead>
                <tr>
                    <th><?= sort_th('Név', 'név', $order, $dir_param, $get_params) ?></th>
                    <th><?= sort_th('E-mail', 'email', $order, $dir_param, $get_params) ?></th>
                    <th><?= sort_th('Telefon', 'telefon', $order, $dir_param, $get_params) ?></th>
                    <th><?= sort_th('Típusok', 'tipusok', $order, $dir_param, $get_params) ?></th>
                    <th><?= sort_th('Szervezők', 'szervezo_nevek', $order, $dir_param, $get_params) ?></th>
                    <th>Műveletek</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($kontaktok as $k): ?>
                <tr>
                    <td><a href="<?= h(BASE_URL) ?>/contacts/megtekint.php?id=<?= (int)$k['id'] ?>"><?= h($k['név']) ?></a></td>
                    <td><?= h($k['email'] ?? '') ?></td>
                    <td><?= h($k['telefon'] ?? '') ?></td>
                    <td><?= h($k['tipusok'] ?? '–') ?></td>
                    <td><?= h($k['szervezo_nevek'] ?? '–') ?></td>
                    <td class="actions">
                        <a href="<?= h(BASE_URL) ?>/contacts/megtekint.php?id=<?= (int)$k['id'] ?>" class="btn btn-sm btn-secondary">Megtekint</a>
                        <a href="<?= h(BASE_URL) ?>/contacts/szerkeszt.php?id=<?= (int)$k['id'] ?>" class="btn btn-sm btn-secondary">Szerkeszt</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (empty($kontaktok)): ?><p>Nincs találat.</p><?php endif; ?>
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
