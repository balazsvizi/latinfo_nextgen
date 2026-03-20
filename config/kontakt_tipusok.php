<?php
$pageTitle = 'Kontakt típusok';
require_once __DIR__ . '/../partials/header.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDb();
$hiba = '';

// Új típus felvétele
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['uj_tipus_nev'])) {
    $nev = trim($_POST['uj_tipus_nev']);
    $leiras = trim($_POST['uj_tipus_leiras'] ?? '');
    if ($nev !== '') {
        try {
            $db->prepare('INSERT INTO kontakt_típusok (név, leírás) VALUES (?, ?)')->execute([$nev, $leiras ?: null]);
            rendszer_log('kontakt_típus', (int)$db->lastInsertId(), 'Létrehozva', 'Név: ' . $nev);
            flash('success', 'Kontakt típus felvéve.');
            redirect(BASE_URL . '/config/kontakt_tipusok.php');
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) $hiba = 'Ilyen kontakt típus már létezik.';
            else $hiba = $e->getMessage();
        }
    }
}

// Név és leírás módosítása
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['szerkeszt_id'], $_POST['uj_nev'])) {
    $tid = (int) $_POST['szerkeszt_id'];
    $nev = trim($_POST['uj_nev']);
    $leiras = trim($_POST['uj_leiras'] ?? '');
    if ($tid && $nev !== '') {
        try {
            $db->prepare('UPDATE kontakt_típusok SET név = ?, leírás = ? WHERE id = ?')->execute([$nev, $leiras ?: null, $tid]);
            rendszer_log('kontakt_típus', $tid, 'Módosítva', 'Új név: ' . $nev);
            flash('success', 'Kontakt típus módosítva.');
            redirect(BASE_URL . '/config/kontakt_tipusok.php');
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) $hiba = 'Ilyen kontakt típus már létezik.';
            else $hiba = $e->getMessage();
        }
    }
}

// Típus törlése – csak ha sehol nem használt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['torol_id'])) {
    $tid = (int) $_POST['torol_id'];
    if ($tid) {
        $hasznalat = $db->prepare('
            SELECT k.id, k.név
            FROM kontakt_típus_kapcsolat kt
            JOIN kontaktok k ON k.id = kt.kontakt_id
            WHERE kt.típus_id = ?
            ORDER BY k.név
        ');
        $hasznalat->execute([$tid]);
        $kontaktok = $hasznalat->fetchAll();
        if (!empty($kontaktok)) {
            $nevek = array_map(function ($r) { return $r['név']; }, $kontaktok);
            $hiba = 'A típus még használatban van, ezért nem törölhető. Kontaktok: ' . implode(', ', $nevek);
        } else {
            $db->prepare('DELETE FROM kontakt_típusok WHERE id = ?')->execute([$tid]);
            rendszer_log('kontakt_típus', $tid, 'Törölve', null);
            flash('success', 'Kontakt típus törölve.');
            redirect(BASE_URL . '/config/kontakt_tipusok.php');
        }
    }
}

$kereso = trim($_GET['kereso'] ?? '');
$order = isset($_GET['order']) && in_array($_GET['order'], ['név', 'létrehozva'], true) ? $_GET['order'] : 'név';
$dir_param = isset($_GET['dir']) && $_GET['dir'] === 'desc' ? 'desc' : 'asc';
$dir = $dir_param === 'desc' ? 'DESC' : 'ASC';

$where = '';
$params = [];
if ($kereso !== '') {
    $where = 'WHERE név LIKE ?';
    $params = ['%' . $kereso . '%'];
}
$stmt = $db->prepare("SELECT * FROM kontakt_típusok $where ORDER BY $order $dir");
$stmt->execute($params);
$tipusok = $stmt->fetchAll();

$get_params = array_filter(['kereso' => $kereso]);
?>
<div class="card">
    <h2>Kontakt típusok</h2>
    <p>Kapcsolattípusok (számlázás, naptár, vezető, egyéb stb.), amelyeket a kontaktoknál lehet beállítani.</p>
    <?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
    <?php if ($hiba): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>

    <form method="post" style="margin-bottom:1rem;">
        <div class="form-group" style="max-width:300px;">
            <label for="uj_tipus">Új kontakt típus neve</label>
            <input type="text" id="uj_tipus" name="uj_tipus_nev" placeholder="Pl. számlázás" required>
        </div>
        <div class="form-group" style="max-width:400px;">
            <label for="uj_tipus_leiras">Leírás (látszik a kontakt típus választónál)</label>
            <input type="text" id="uj_tipus_leiras" name="uj_tipus_leiras" placeholder="Opcionális rövid leírás">
        </div>
        <button type="submit" class="btn btn-primary">Felvétel</button>
    </form>

    <form method="get" class="toolbar">
        <input type="search" name="kereso" placeholder="Kontakt típus neve..." value="<?= h($kereso) ?>">
        <button type="submit" class="btn btn-primary">Keresés</button>
    </form>

    <div class="table-wrap">
        <table class="sortable-table">
            <thead>
                <tr>
                    <th><?= sort_th('Név', 'név', $order, $dir_param, $get_params) ?></th>
                    <th><?= sort_th('Létrehozva', 'létrehozva', $order, $dir_param, $get_params) ?></th>
                    <th>Műveletek</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tipusok as $t): ?>
                <tr>
                    <td>
                        <form method="post" class="inline-form inline-form-tipus">
                            <input type="hidden" name="szerkeszt_id" value="<?= (int)$t['id'] ?>">
                            <input type="text" name="uj_nev" value="<?= h($t['név']) ?>" placeholder="Név" style="max-width:160px;">
                            <input type="text" name="uj_leiras" value="<?= h($t['leírás'] ?? '') ?>" placeholder="Leírás" style="max-width:220px;">
                            <button type="submit" class="btn btn-sm btn-secondary">Mentés</button>
                        </form>
                    </td>
                    <td><?= h($t['létrehozva']) ?></td>
                    <td>
                        <form method="post" onsubmit="return confirm('Biztosan törlöd ezt a típust?');">
                            <input type="hidden" name="torol_id" value="<?= (int)$t['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-secondary">Törlés</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<p><a href="<?= h(BASE_URL) ?>/contacts/">← Vissza a kontaktokhoz</a></p>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>

