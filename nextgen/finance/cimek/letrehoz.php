<?php
require_once __DIR__ . '/../../../nextgen/core/database.php';
require_once __DIR__ . '/../../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../../nextgen/includes/functions.php';
requireLogin();

$szervezo_id = (int)($_GET['szervezo_id'] ?? 0);
if (!$szervezo_id) {
    flash('error', 'Hiányzó szervező.');
    redirect(nextgen_url('organizers/'));
}

$db = getDb();
$hiba = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $név = trim($_POST['név'] ?? '');
    $ország = trim($_POST['ország'] ?? '');
    $irsz = trim($_POST['irsz'] ?? '');
    $település = trim($_POST['település'] ?? '');
    $cím = trim($_POST['cím'] ?? '');
    $adószám = trim($_POST['adószám'] ?? '');
    $megjegyzés = trim($_POST['megjegyzés'] ?? '');
    $alapértelmezett = isset($_POST['alapértelmezett']) ? 1 : 0;

    if ($név === '' || $ország === '' || $irsz === '' || $cím === '') {
        $hiba = 'Név, ország, irányítószám és cím megadása kötelező.';
    } else {
        if ($alapértelmezett) {
            $db->prepare('UPDATE számlázási_címek SET alapértelmezett = 0 WHERE szervező_id = ?')->execute([$szervezo_id]);
        }
        $db->prepare('INSERT INTO számlázási_címek (szervező_id, név, ország, irsz, település, cím, adószám, megjegyzés, alapértelmezett) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$szervezo_id, $név, $ország, $irsz, $település, $cím, $adószám ?: null, $megjegyzés ?: null, $alapértelmezett]);
        rendszer_log('számlázási_cím', (int)$db->lastInsertId(), 'Létrehozva', null);
        flash('success', 'Cím mentve.');
        redirect(nextgen_url('organizers/megtekint.php?id=') . $szervezo_id);
    }
}

$pageTitle = 'Új számlázási cím';
require_once __DIR__ . '/../../partials/header.php';
?>
<div class="card">
    <h2>Új számlázási cím</h2>
    <?php if ($hiba): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post">
        <div class="form-group"><label>Név *</label><input type="text" name="név" value="<?= h($_POST['név'] ?? '') ?>" required></div>
        <div class="form-group"><label>Ország *</label><input type="text" name="ország" value="<?= h($_POST['ország'] ?? 'Magyarország') ?>" required></div>
        <div class="form-group"><label>Irányítószám *</label><input type="text" name="irsz" value="<?= h($_POST['irsz'] ?? '') ?>" required></div>
        <div class="form-group"><label>Település</label><input type="text" name="település" value="<?= h($_POST['település'] ?? '') ?>" placeholder="Pl. Budapest"></div>
        <div class="form-group"><label>Cím *</label><input type="text" name="cím" value="<?= h($_POST['cím'] ?? '') ?>" required></div>
        <div class="form-group"><label>Adószám</label><input type="text" name="adószám" value="<?= h($_POST['adószám'] ?? '') ?>"></div>
        <div class="form-group"><label>Megjegyzés</label><textarea name="megjegyzés" rows="2" placeholder="Opcionális megjegyzés"><?= h($_POST['megjegyzés'] ?? '') ?></textarea></div>
        <div class="form-group">
            <label><input type="checkbox" name="alapértelmezett" value="1" <?= !empty($_POST['alapértelmezett']) ? 'checked' : '' ?>> Alapértelmezett (primary) cím</label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Mentés</button>
            <a href="<?= h(nextgen_url('organizers/megtekint.php?id=')) ?><?= $szervezo_id ?>" class="btn btn-secondary">Mégse</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
