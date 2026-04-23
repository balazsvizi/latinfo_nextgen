<?php
require_once __DIR__ . '/../../nextgen/core/database.php';
require_once __DIR__ . '/../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../nextgen/includes/functions.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    flash('error', 'Hiányzó azonosító.');
    redirect(nextgen_url('organizers/'));
}

$db = getDb();
$szervezo = $db->prepare('SELECT * FROM finance_organizers WHERE id = ?');
$szervezo->execute([$id]);
$szervezo = $szervezo->fetch();
if (!$szervezo) {
    flash('error', 'Szervező nem található.');
    redirect(nextgen_url('organizers/'));
}

$hasCimkeSzin = cimkek_has_szin($db);
$cimkeSql = $hasCimkeSzin
    ? 'SELECT id, név, COALESCE(szín, "#6366F1") AS szín FROM finance_tags ORDER BY név'
    : 'SELECT id, név, "#6366F1" AS szín FROM finance_tags ORDER BY név';
$címkék = $db->query($cimkeSql)->fetchAll();
$kivalasztott = $db->prepare('SELECT címke_id FROM finance_organizer_tags WHERE szervező_id = ?');
$kivalasztott->execute([$id]);
$kivalasztott = $kivalasztott->fetchAll(PDO::FETCH_COLUMN);

$hiba = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $név = trim($_POST['név'] ?? '');
    $címke_ids = array_filter(array_map('intval', $_POST['címkék'] ?? []));

    if ($név === '') {
        $hiba = 'A név megadása kötelező.';
    } else {
        try {
            $db->beginTransaction();
            $db->prepare('UPDATE finance_organizers SET név = ? WHERE id = ?')->execute([$név, $id]);
            $db->prepare('DELETE FROM finance_organizer_tags WHERE szervező_id = ?')->execute([$id]);
            foreach ($címke_ids as $cid) {
                $db->prepare('INSERT INTO finance_organizer_tags (szervező_id, címke_id) VALUES (?, ?)')->execute([$id, $cid]);
            }
            rendszer_log('szervező', $id, 'Módosítva', 'Név: ' . $név);
            $db->prepare('INSERT INTO finance_organizer_activity_log (szervező_id, esemény, részletek, admin_id) VALUES (?, ?, ?, ?)')
                ->execute([$id, 'Szervező adatai módosítva', null, $_SESSION['admin_id'] ?? null]);
            $db->commit();
            flash('success', 'Mentve.');
            redirect(nextgen_url('organizers/megtekint.php?id=') . $id);
        } catch (Exception $e) {
            $db->rollBack();
            $hiba = 'Hiba: ' . $e->getMessage();
        }
    }
    $kivalasztott = $címke_ids;
}
$pageTitle = 'Szervező szerkesztése: ' . $szervezo['név'];
require_once __DIR__ . '/../partials/header.php';
?>
<div class="card">
    <h2>Szervező szerkesztése</h2>
    <?php if ($hiba): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label for="nev">Név *</label>
            <input type="text" id="nev" name="név" value="<?= h($szervezo['név']) ?>" required>
        </div>
        <div class="form-group">
            <label>Címkék</label>
            <div class="cimke-valaszto">
                <?php foreach ($címkék as $c): ?>
                    <?php
                        $szin = normalize_hex_color($c['szín'], '#6366F1');
                        $textColor = contrast_text_color($szin);
                    ?>
                    <label class="cimke-tag" style="--tag-color: <?= h($szin) ?>; --tag-text: <?= h($textColor) ?>;">
                        <input type="checkbox" name="címkék[]" value="<?= (int)$c['id'] ?>" <?= in_array((int)$c['id'], $kivalasztott) ? 'checked' : '' ?>>
                        <span><?= h($c['név']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="help"><a href="<?= h(nextgen_url('config/cimkek.php')) ?>">Új címke felvétele</a></p>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Mentés</button>
            <a href="<?= h(nextgen_url('organizers/megtekint.php?id=')) ?><?= $id ?>" class="btn btn-secondary">Mégse</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
