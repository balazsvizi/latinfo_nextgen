<?php
require_once __DIR__ . '/../../nextgen/core/database.php';
require_once __DIR__ . '/../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../nextgen/includes/functions.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    flash('error', 'Hiányzó azonosító.');
    redirect(nextgen_url('contacts/'));
}
$db = getDb();
$k = $db->prepare('SELECT * FROM finance_contacts WHERE id = ?');
$k->execute([$id]);
$k = $k->fetch();
if (!$k) {
    flash('error', 'Kontakt nem található.');
    redirect(nextgen_url('contacts/'));
}

$tipusok = $db->query('SELECT id, név, leírás FROM finance_contact_types ORDER BY név')->fetchAll();
$kivalasztott_tipusok = $db->prepare('SELECT típus_id FROM finance_contact_type_links WHERE kontakt_id = ?');
$kivalasztott_tipusok->execute([$id]);
$kivalasztott_tipusok = array_column($kivalasztott_tipusok->fetchAll(PDO::FETCH_ASSOC), 'típus_id');

$hiba = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $név = trim($_POST['név'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefon = trim($_POST['telefon'] ?? '');
    $fb = trim($_POST['fb'] ?? '');
    $egyeb = trim($_POST['egyéb_kontakt'] ?? '');
    $tipus_ids = array_filter(array_map('intval', $_POST['kontakt_tipusok'] ?? []));
    if ($név === '') {
        $hiba = 'A név megadása kötelező.';
    } else {
        $db->prepare('UPDATE finance_contacts SET név=?, email=?, telefon=?, fb=?, egyéb_kontakt=? WHERE id=?')
            ->execute([$név, $email ?: null, $telefon ?: null, $fb ?: null, $egyeb ?: null, $id]);
        $db->prepare('DELETE FROM finance_contact_type_links WHERE kontakt_id = ?')->execute([$id]);
        foreach ($tipus_ids as $tid) {
            $db->prepare('INSERT IGNORE INTO finance_contact_type_links (kontakt_id, típus_id) VALUES (?, ?)')
                ->execute([$id, $tid]);
        }
        rendszer_log('kontakt', $id, 'Módosítva', null);
        flash('success', 'Mentve.');
        redirect(nextgen_url('contacts/megtekint.php?id=') . $id);
    }
    $k = array_merge($k, $_POST);
    $kivalasztott_tipusok = $tipus_ids;
}

$pageTitle = 'Kontakt szerkesztése: ' . $k['név'];
require_once __DIR__ . '/../partials/header.php';
?>
<div class="card">
    <h2>Kontakt szerkesztése</h2>
    <?php if ($hiba): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post">
        <div class="form-group"><label>Név *</label><input type="text" name="név" value="<?= h($k['név']) ?>" required></div>
        <div class="form-group"><label>E-mail</label><input type="email" name="email" value="<?= h($k['email'] ?? '') ?>"></div>
        <div class="form-group"><label>Telefon</label><input type="text" name="telefon" value="<?= h($k['telefon'] ?? '') ?>"></div>
        <div class="form-group"><label>Facebook</label><input type="text" name="fb" value="<?= h($k['fb'] ?? '') ?>"></div>
        <div class="form-group"><label>Egyéb kontakt</label><input type="text" name="egyéb_kontakt" value="<?= h($k['egyéb_kontakt'] ?? '') ?>"></div>
        <div class="form-group">
            <label>Kontakt típus(ok)</label>
            <div class="cimke-valaszto cimke-valaszto-column">
                <?php foreach ($tipusok as $t): ?>
                    <div class="kontakt-tipus-sor">
                        <label class="cimke-tag">
                            <input type="checkbox" name="kontakt_tipusok[]" value="<?= (int)$t['id'] ?>" <?= in_array((int)$t['id'], $kivalasztott_tipusok, true) ? 'checked' : '' ?>>
                            <span><?= h($t['név']) ?></span>
                        </label>
                        <?php if (!empty(trim($t['leírás'] ?? ''))): ?>
                            <span class="kontakt-tipus-leiras"><?= h($t['leírás']) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="help">A típusokat a Config &rarr; Kontakt típusok menüben tudod bővíteni.</p>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Mentés</button>
            <a href="<?= h(nextgen_url('contacts/megtekint.php?id=')) ?><?= $id ?>" class="btn btn-secondary">Mégse</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
