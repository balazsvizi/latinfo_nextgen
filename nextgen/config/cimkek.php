<?php
$pageTitle = 'Címkék';
require_once __DIR__ . '/../partials/header.php';

$db = getDb();
$alap_szin = '#6366F1';
$hiba = '';
$hasCimkeSzin = cimkek_has_szin($db);
if (!$hasCimkeSzin) {
    try {
        $db->exec("ALTER TABLE címkék ADD COLUMN szín CHAR(7) NOT NULL DEFAULT '#6366F1' AFTER név");
        $hasCimkeSzin = true;
    } catch (Throwable $e) {
        // Ha nincs jogosultság ALTER-re, fallback marad (csak név menthető).
    }
}

$mod = $_POST['mod'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mod === 'create') {
    $név = trim($_POST['új_címke'] ?? '');
    $szin = normalize_hex_color($_POST['új_szin'] ?? '', $alap_szin);
    if ($név === '') {
        $hiba = 'A címke neve kötelező.';
    } else {
        try {
            if ($hasCimkeSzin) {
                $db->prepare('INSERT INTO címkék (név, szín) VALUES (?, ?)')->execute([$név, $szin]);
            } else {
                $db->prepare('INSERT INTO címkék (név) VALUES (?)')->execute([$név]);
            }
            rendszer_log('címke', (int)$db->lastInsertId(), 'Létrehozva', 'Név: ' . $név . ', Szín: ' . $szin);
            flash('success', 'Címke felvéve.');
            redirect(nextgen_url('config/cimkek.php'));
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $hiba = 'Ilyen címke már létezik.';
            } else {
                $hiba = $e->getMessage();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mod === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $név = trim($_POST['név'] ?? '');
    $szin = normalize_hex_color($_POST['szín'] ?? '', $alap_szin);
    if ($id <= 0 || $név === '') {
        $hiba = 'A címke mentéséhez név és azonosító szükséges.';
    } else {
        try {
            if ($hasCimkeSzin) {
                $db->prepare('UPDATE címkék SET név = ?, szín = ? WHERE id = ?')->execute([$név, $szin, $id]);
            } else {
                $db->prepare('UPDATE címkék SET név = ? WHERE id = ?')->execute([$név, $id]);
            }
            rendszer_log('címke', $id, 'Módosítva', 'Név: ' . $név . ', Szín: ' . $szin);
            flash('success', 'Címke mentve.');
            redirect(nextgen_url('config/cimkek.php'));
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $hiba = 'Ilyen címke név már létezik.';
            } else {
                $hiba = $e->getMessage();
            }
        }
    }
}

$kereso = trim($_GET['kereso'] ?? '');
$allowedOrders = $hasCimkeSzin ? ['név', 'szín', 'létrehozva'] : ['név', 'létrehozva'];
$order = isset($_GET['order']) && in_array($_GET['order'], $allowedOrders, true) ? $_GET['order'] : 'név';
$dir_param = isset($_GET['dir']) && $_GET['dir'] === 'desc' ? 'desc' : 'asc';
$dir = $dir_param === 'desc' ? 'DESC' : 'ASC';

$where = '';
$params = [];
if ($kereso !== '') {
    $where = 'WHERE név LIKE ?';
    $params = ['%' . $kereso . '%'];
}
$selectSql = $hasCimkeSzin
    ? "SELECT id, név, COALESCE(szín, ?) AS szín, létrehozva FROM címkék $where ORDER BY $order $dir"
    : "SELECT id, név, ? AS szín, létrehozva FROM címkék $where ORDER BY $order $dir";
$stmt = $db->prepare($selectSql);
$stmt->execute(array_merge([$alap_szin], $params));
$címkék = $stmt->fetchAll();

$get_params = array_filter(['kereso' => $kereso]);
?>
<div class="card">
    <h2>Címkék</h2>
    <p>Új címke felvétele (máshol is használható, pl. szervezőknél).</p>
    <?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
    <?php if ($hiba): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>

    <form method="post" class="cimke-form-uj">
        <input type="hidden" name="mod" value="create">
        <div class="form-group cimke-form-nev">
            <label for="uj">Új címke neve</label>
            <input type="text" id="uj" name="új_címke" placeholder="Pl. Tánciskola" required>
        </div>
        <div class="form-group">
            <label for="uj-szin">Szín</label>
            <input type="color" id="uj-szin" name="új_szin" value="<?= h($alap_szin) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Felvétel</button>
    </form>

    <form method="get" class="toolbar">
        <input type="search" name="kereso" placeholder="Címke neve..." value="<?= h($kereso) ?>">
        <button type="submit" class="btn btn-primary">Keresés</button>
    </form>

    <div class="table-wrap">
    <table class="sortable-table">
        <thead><tr>
            <th><?= sort_th('Név', 'név', $order, $dir_param, $get_params) ?></th>
            <th><?= $hasCimkeSzin ? sort_th('Szín', 'szín', $order, $dir_param, $get_params) : 'Szín' ?></th>
            <th>Megjelenés</th>
            <th><?= sort_th('Létrehozva', 'létrehozva', $order, $dir_param, $get_params) ?></th>
            <th>Művelet</th>
        </tr></thead>
        <tbody>
            <?php foreach ($címkék as $c): ?>
            <?php
                $szin = normalize_hex_color($c['szín'], $alap_szin);
                $textColor = contrast_text_color($szin);
            ?>
            <tr>
                <td>
                    <input type="text" class="cimke-tabla-nev-input" form="cimke-form-<?= (int)$c['id'] ?>" name="név" value="<?= h($c['név']) ?>" required>
                </td>
                <td>
                    <div class="cimke-color-cell">
                        <input type="color" form="cimke-form-<?= (int)$c['id'] ?>" name="szín" value="<?= h($szin) ?>">
                        <span class="text-muted"><?= h($szin) ?></span>
                    </div>
                </td>
                <td>
                    <span class="cimke-badge" style="--badge-bg: <?= h($szin) ?>; --badge-text: <?= h($textColor) ?>;">
                        <?= h($c['név']) ?>
                    </span>
                </td>
                <td><?= h($c['létrehozva']) ?></td>
                <td>
                    <form method="post" id="cimke-form-<?= (int)$c['id'] ?>" class="inline-form">
                        <input type="hidden" name="mod" value="update">
                        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-secondary">Mentés</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<p><a href="<?= h(nextgen_url('organizers/')) ?>">← Vissza a szervezőkhöz</a></p>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
