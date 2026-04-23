<?php
$pageTitle = 'Jelszó módosítása';
require_once __DIR__ . '/partials/header.php';

requireLogin();
$admin_id = (int) $_SESSION['admin_id'];
$db = getDb();

$hiba = '';
$siker = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jelenlegi = $_POST['jelenlegi_jelszo'] ?? '';
    $uj = $_POST['uj_jelszo'] ?? '';
    $uj2 = $_POST['uj_jelszo_ujra'] ?? '';

    if ($jelenlegi === '') {
        $hiba = 'A jelenlegi jelszó megadása kötelező.';
    } elseif (strlen($uj) < 6) {
        $hiba = 'Az új jelszónak legalább 6 karakter hosszúnak kell lennie.';
    } elseif ($uj !== $uj2) {
        $hiba = 'Az új jelszó és a megerősítés nem egyezik.';
    } else {
        $stmt = $db->prepare('SELECT jelszó_hash FROM nextgen_admins WHERE id = ?');
        $stmt->execute([$admin_id]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($jelenlegi, $row['jelszó_hash'])) {
            $hiba = 'A jelenlegi jelszó nem helyes.';
        } else {
            $hash = password_hash($uj, PASSWORD_DEFAULT);
            $db->prepare('UPDATE nextgen_admins SET jelszó_hash = ? WHERE id = ?')->execute([$hash, $admin_id]);
            rendszer_log('admin', $admin_id, 'Jelszó módosítva', null);
            flash('success', 'A jelszó sikeresen megváltozott.');
            redirect(nextgen_url('apps.php'));
        }
    }
}
?>
<div class="card card-narrow">
    <h2>Jelszó módosítása</h2>
    <p>Bejelentkezve: <strong><?= h($_SESSION['admin_nev']) ?></strong></p>
    <?php if ($hiba): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post" autocomplete="off">
        <div class="form-group">
            <label>Jelenlegi jelszó *</label>
            <input type="password" name="jelenlegi_jelszo" required autocomplete="current-password">
        </div>
        <div class="form-group">
            <label>Új jelszó *</label>
            <input type="password" name="uj_jelszo" minlength="6" required autocomplete="new-password">
            <p class="help">Legalább 6 karakter.</p>
        </div>
        <div class="form-group">
            <label>Új jelszó újra *</label>
            <input type="password" name="uj_jelszo_ujra" minlength="6" required autocomplete="new-password">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Jelszó módosítása</button>
            <a href="<?= h(nextgen_url('index.php')) ?>" class="btn btn-secondary">Mégse</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
