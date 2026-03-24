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
$k = $db->prepare('SELECT * FROM kontaktok WHERE id = ?');
$k->execute([$id]);
$k = $k->fetch();
if (!$k) {
    flash('error', 'Kontakt nem található.');
    redirect(nextgen_url('contacts/'));
}

// Megjegyzés hozzáadása
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['megjegyzes_szoveg'])) {
    $szoveg = trim($_POST['megjegyzes_szoveg']);
    if ($szoveg !== '') {
        $db->prepare('INSERT INTO kontakt_megjegyzések (kontakt_id, megjegyzés, admin_id) VALUES (?, ?, ?)')
            ->execute([$id, $szoveg, $_SESSION['admin_id'] ?? null]);
        rendszer_log('kontakt_megjegyzés', (int)$db->lastInsertId(), 'Felvéve', null);
        flash('success', 'Megjegyzés hozzáadva.');
        redirect(nextgen_url('contacts/megtekint.php?id=') . $id . '#megjegyzesek');
    }
}

$megjegyzesek = $db->prepare('SELECT m.*, a.név AS admin_név FROM kontakt_megjegyzések m LEFT JOIN adminok a ON a.id = m.admin_id WHERE m.kontakt_id = ? ORDER BY m.létrehozva DESC');
$megjegyzesek->execute([$id]);
$megjegyzesek = $megjegyzesek->fetchAll();

$szervezok = $db->prepare('SELECT sz.id, sz.név FROM szervező_kontakt sk JOIN szervezők sz ON sz.id = sk.szervező_id WHERE sk.kontakt_id = ? ORDER BY sz.név');
$szervezok->execute([$id]);
$szervezok = $szervezok->fetchAll();

$tipusok = $db->prepare('SELECT t.név FROM kontakt_típus_kapcsolat kt JOIN kontakt_típusok t ON t.id = kt.típus_id WHERE kt.kontakt_id = ? ORDER BY t.név');
$tipusok->execute([$id]);
$tipusok = $tipusok->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = $k['név'];
require_once __DIR__ . '/../partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<div class="card">
    <h2><?= h($k['név']) ?></h2>
    <p>
        <a href="<?= h(nextgen_url('contacts/szerkeszt.php?id=')) ?><?= $id ?>" class="btn btn-primary">Szerkesztés</a>
        <a href="<?= h(nextgen_url('contacts/')) ?>" class="btn btn-secondary">← Lista</a>
    </p>
    <table>
        <tr><th>E-mail</th><td><?= h($k['email'] ?? '') ?></td></tr>
        <tr><th>Telefon</th><td><?= h($k['telefon'] ?? '') ?></td></tr>
        <tr><th>Facebook</th><td><?= h($k['fb'] ?? '') ?></td></tr>
        <tr><th>Egyéb kontakt</th><td><?= h($k['egyéb_kontakt'] ?? '') ?></td></tr>
        <tr><th>Kontakt típus(ok)</th><td><?= $tipusok ? h(implode(', ', $tipusok)) : '–' ?></td></tr>
    </table>
</div>
<div class="card">
    <h2>Szervezők (hozzárendelve)</h2>
    <ul>
        <?php foreach ($szervezok as $sz): ?>
            <li><a href="<?= h(nextgen_url('organizers/megtekint.php?id=')) ?><?= (int)$sz['id'] ?>"><?= h($sz['név']) ?></a></li>
        <?php endforeach; ?>
    </ul>
    <?php if (empty($szervezok)): ?><p>Nincs hozzárendelt szervező.</p><?php endif; ?>
</div>
<div class="card" id="megjegyzesek">
    <h2>Megjegyzések</h2>
    <form method="post" style="margin-bottom:1rem;">
        <div class="form-group">
            <label>Új megjegyzés</label>
            <textarea name="megjegyzes_szoveg" rows="2" placeholder="Megjegyzés..."></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Hozzáadás</button>
    </form>
    <div class="notes-list">
        <?php foreach ($megjegyzesek as $m): ?>
        <div class="note-item">
            <span class="note-date"><?= h($m['létrehozva']) ?> <?= $m['admin_név'] ? '(' . h($m['admin_név']) . ')' : '' ?></span>
            <p style="margin:0.25rem 0 0;"><?= nl2br(h($m['megjegyzés'])) ?></p>
        </div>
        <?php endforeach; ?>
        <?php if (empty($megjegyzesek)): ?><p>Még nincs megjegyzés.</p><?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
