<?php
require_once __DIR__ . '/../../../nextgen/core/database.php';
require_once __DIR__ . '/../../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../../nextgen/includes/functions.php';
requireSuperadmin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    flash('error', 'Hiányzó azonosító.');
    redirect(nextgen_url('admin/adminok/'));
}

$db = getDb();
try {
    $col = $db->query("SHOW COLUMNS FROM nextgen_admins LIKE 'email'")->fetch();
    if (!$col) {
        $db->exec("ALTER TABLE nextgen_admins ADD COLUMN email VARCHAR(255) NULL AFTER felhasználónév");
    }
} catch (Throwable $e) {
    // nincs ALTER jog: migrációval felvehető
}
$stmt = $db->prepare('SELECT id, név, felhasználónév, email, szint, aktív FROM nextgen_admins WHERE id = ?');
$stmt->execute([$id]);
$admin = $stmt->fetch();
if (!$admin) {
    flash('error', 'Admin nem található.');
    redirect(nextgen_url('admin/adminok/'));
}

$hiba = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $név = trim($_POST['név'] ?? '');
    $fh = trim($_POST['felhasználónév'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $jelszo = $_POST['jelszó'] ?? '';
    $jelszo2 = $_POST['jelszó_ujra'] ?? '';
    $szint = ($_POST['szint'] ?? '') === 'superadmin' ? 'superadmin' : 'admin';
    $aktív = isset($_POST['aktív']) ? 1 : 0;

    if ($név === '' || $fh === '') {
        $hiba = 'Név és felhasználónév megadása kötelező.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $hiba = 'Érvényes e-mail címet adj meg.';
    } elseif ($jelszo !== '' && strlen($jelszo) < 6) {
        $hiba = 'A jelszónak legalább 6 karakter hosszúnak kell lennie.';
    } elseif ($jelszo !== '' && $jelszo !== $jelszo2) {
        $hiba = 'A két jelszó nem egyezik.';
    } else {
        $check = $db->prepare('SELECT id FROM nextgen_admins WHERE felhasználónév = ? AND id != ?');
        $check->execute([$fh, $id]);
        if ($check->fetch()) {
            $hiba = 'Ez a felhasználónév már foglalt.';
        } else {
            if ($jelszo !== '') {
                $hash = password_hash($jelszo, PASSWORD_DEFAULT);
                $db->prepare('UPDATE nextgen_admins SET név = ?, felhasználónév = ?, email = ?, jelszó_hash = ?, szint = ?, aktív = ? WHERE id = ?')
                    ->execute([$név, $fh, ($email !== '' ? $email : null), $hash, $szint, $aktív, $id]);
            } else {
                $db->prepare('UPDATE nextgen_admins SET név = ?, felhasználónév = ?, email = ?, szint = ?, aktív = ? WHERE id = ?')
                    ->execute([$név, $fh, ($email !== '' ? $email : null), $szint, $aktív, $id]);
            }
            rendszer_log('admin', $id, 'Módosítva', null);
            if ((int)$id === (int)($_SESSION['admin_id'] ?? 0)) {
                $_SESSION['admin_nev'] = $név;
                $_SESSION['admin_felhasznalonev'] = $fh;
                $_SESSION['admin_email'] = ($email !== '' ? $email : null);
                $_SESSION['admin_szint'] = $szint;
            }
            flash('success', 'Mentve.');
            redirect(nextgen_url('admin/adminok/'));
        }
    }
    $admin = array_merge($admin, [
        'név' => $név ?: $admin['név'],
        'felhasználónév' => $fh ?: $admin['felhasználónév'],
        'email' => $email,
        'szint' => $szint,
        'aktív' => $aktív,
    ]);
}

$pageTitle = 'Admin szerkesztése: ' . $admin['név'];
require_once __DIR__ . '/../../partials/header.php';
?>
<div class="card">
    <h2>Admin szerkesztése</h2>
    <?php if ($hiba): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label>Név *</label>
            <input type="text" name="név" value="<?= h($admin['név']) ?>" required>
        </div>
        <div class="form-group">
            <label>Felhasználónév *</label>
            <input type="text" name="felhasználónév" value="<?= h($admin['felhasználónév']) ?>" required autocomplete="username">
        </div>
        <div class="form-group">
            <label>E-mail cím</label>
            <input type="email" name="email" value="<?= h($admin['email'] ?? '') ?>" placeholder="nev@pelda.hu">
        </div>
        <div class="form-group">
            <label>Új jelszó</label>
            <input type="password" name="jelszó" minlength="6" autocomplete="new-password" placeholder="Hagyd üresen, ha nem változik">
            <p class="help">Csak akkor töltsd ki, ha meg akarod változtatni a jelszót (min. 6 karakter).</p>
        </div>
        <div class="form-group">
            <label>Új jelszó újra</label>
            <input type="password" name="jelszó_ujra" minlength="6" autocomplete="new-password" placeholder="Ugyanaz, mint fent">
        </div>
        <div class="form-group">
            <label>Szint</label>
            <select name="szint">
                <option value="admin" <?= ($admin['szint'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="superadmin" <?= ($admin['szint'] ?? '') === 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
            </select>
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" name="aktív" value="1" <?= ($admin['aktív'] ?? 1) ? 'checked' : '' ?>>
                Aktív (bejelentkezhet)
            </label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Mentés</button>
            <a href="<?= h(nextgen_url('admin/adminok/')) ?>" class="btn btn-secondary">Mégse</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
