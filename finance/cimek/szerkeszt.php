<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    flash('error', 'Hiányzó azonosító.');
    redirect(BASE_URL . '/organizers/');
}
$db = getDb();
$cim = $db->prepare('SELECT * FROM számlázási_címek WHERE id = ?');
$cim->execute([$id]);
$cim = $cim->fetch();
if (!$cim) {
    flash('error', 'Cím nem található.');
    redirect(BASE_URL . '/organizers/');
}
$szervezo_id = (int)$cim['szervező_id'];

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
        $db->prepare('UPDATE számlázási_címek SET név=?, ország=?, irsz=?, település=?, cím=?, adószám=?, megjegyzés=?, alapértelmezett=? WHERE id=?')
            ->execute([$név, $ország, $irsz, $település, $cím, $adószám ?: null, $megjegyzés ?: null, $alapértelmezett, $id]);
        rendszer_log('számlázási_cím', $id, 'Módosítva', null);
        flash('success', 'Mentve.');
        redirect(BASE_URL . '/organizers/megtekint.php?id=' . $szervezo_id);
    }
    $cim = array_merge($cim, $_POST);
}

$pageTitle = 'Számlázási cím szerkesztése';
require_once __DIR__ . '/../../partials/header.php';
?>
<div class="card">
    <h2>Számlázási cím szerkesztése</h2>
    <?php if ($hiba): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post">
        <div class="form-group"><label>Név *</label><input type="text" name="név" value="<?= h($cim['név']) ?>" required></div>
        <div class="form-group"><label>Ország *</label><input type="text" name="ország" value="<?= h($cim['ország']) ?>" required></div>
        <div class="form-group"><label>Irányítószám *</label><input type="text" name="irsz" value="<?= h($cim['irsz']) ?>" required></div>
        <div class="form-group"><label>Település</label><input type="text" name="település" value="<?= h($cim['település'] ?? '') ?>" placeholder="Pl. Budapest"></div>
        <div class="form-group"><label>Cím *</label><input type="text" name="cím" value="<?= h($cim['cím']) ?>" required></div>
        <div class="form-group"><label>Adószám</label><input type="text" name="adószám" value="<?= h($cim['adószám'] ?? '') ?>"></div>
        <div class="form-group"><label>Megjegyzés</label><textarea name="megjegyzés" rows="2" placeholder="Opcionális megjegyzés"><?= h($cim['megjegyzés'] ?? '') ?></textarea></div>
        <div class="form-group">
            <label><input type="checkbox" name="alapértelmezett" value="1" <?= $cim['alapértelmezett'] ? 'checked' : '' ?>> Alapértelmezett (primary)</label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Mentés</button>
            <a href="<?= h(BASE_URL) ?>/organizers/megtekint.php?id=<?= $szervezo_id ?>" class="btn btn-secondary">Mégse</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
