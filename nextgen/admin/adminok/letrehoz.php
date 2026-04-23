<?php
require_once __DIR__ . '/../../../nextgen/core/database.php';
require_once __DIR__ . '/../../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../../nextgen/includes/functions.php';
requireSuperadmin();

$db = getDb();
$hiba = '';
try {
    $col = $db->query("SHOW COLUMNS FROM nextgen_admins LIKE 'email'")->fetch();
    if (!$col) {
        $db->exec("ALTER TABLE nextgen_admins ADD COLUMN email VARCHAR(255) NULL AFTER felhasználónév");
    }
} catch (Throwable $e) {
    // nincs ALTER jog: migrációval felvehető
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $név = trim($_POST['név'] ?? '');
    $fh = trim($_POST['felhasználónév'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $jelszo = $_POST['jelszó'] ?? '';
    $jelszo2 = $_POST['jelszó_ujra'] ?? '';
    $szint = ($_POST['szint'] ?? '') === 'superadmin' ? 'superadmin' : 'admin';

    if ($név === '' || $fh === '') {
        $hiba = 'Név és felhasználónév megadása kötelező.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $hiba = 'Érvényes e-mail címet adj meg.';
    } elseif (strlen($jelszo) < 6) {
        $hiba = 'A jelszónak legalább 6 karakter hosszúnak kell lennie.';
    } elseif ($jelszo !== $jelszo2) {
        $hiba = 'A két jelszó nem egyezik.';
    } else {
        $check = $db->prepare('SELECT id FROM nextgen_admins WHERE felhasználónév = ?');
        $check->execute([$fh]);
        if ($check->fetch()) {
            $hiba = 'Ez a felhasználónév már foglalt.';
        } else {
            $hash = password_hash($jelszo, PASSWORD_DEFAULT);
            $db->prepare('INSERT INTO nextgen_admins (név, felhasználónév, email, jelszó_hash, szint, aktív) VALUES (?, ?, ?, ?, ?, 1)')
                ->execute([$név, $fh, ($email !== '' ? $email : null), $hash, $szint]);
            rendszer_log('admin', (int)$db->lastInsertId(), 'Létrehozva', null);
            flash('success', 'Admin létrehozva.');
            redirect(nextgen_url('admin/adminok/'));
        }
    }
}

$pageTitle = 'Új admin';
require_once __DIR__ . '/../../partials/header.php';
?>
<div class="card">
    <h2>Új admin felhasználó</h2>
    <?php if ($hiba): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post">
        <div class="form-group"><label>Név *</label><input type="text" name="név" value="<?= h($_POST['név'] ?? '') ?>" required></div>
        <div class="form-group"><label>Felhasználónév *</label><input type="text" name="felhasználónév" value="<?= h($_POST['felhasználónév'] ?? '') ?>" required autocomplete="username"></div>
        <div class="form-group"><label>E-mail cím</label><input type="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" placeholder="nev@pelda.hu"></div>
        <div class="form-group"><label>Jelszó * (min. 6 karakter)</label><input type="password" name="jelszó" required minlength="6" autocomplete="new-password"></div>
        <div class="form-group"><label>Jelszó újra *</label><input type="password" name="jelszó_ujra" required minlength="6" autocomplete="new-password"></div>
        <div class="form-group">
            <label>Szint</label>
            <select name="szint">
                <option value="admin" <?= ($_POST['szint'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="superadmin" <?= ($_POST['szint'] ?? '') === 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
            </select>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Mentés</button>
            <a href="<?= h(nextgen_url('admin/adminok/')) ?>" class="btn btn-secondary">Mégse</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
