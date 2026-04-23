<?php
require_once __DIR__ . '/../../../nextgen/core/database.php';
require_once __DIR__ . '/../../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../../nextgen/includes/functions.php';
require_once __DIR__ . '/../../../nextgen/includes/email.php';
requireSuperadmin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Érvénytelen fiók.');
    redirect(nextgen_url('admin/email/'));
}

$db = getDb();
$stmt = $db->prepare('SELECT id, név, host, port, titkosítás, felhasználó, from_email, from_name, alapértelmezett FROM finance_email_accounts WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
    flash('error', 'SMTP fiók nem található.');
    redirect(nextgen_url('admin/email/'));
}

$hiba = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $név = trim($_POST['név'] ?? '');
    $host = trim($_POST['host'] ?? '');
    $port = (int) ($_POST['port'] ?? 587);
    $titkosítás = isset($_POST['titkosítás']) && in_array($_POST['titkosítás'], ['', 'tls', 'ssl'], true) ? $_POST['titkosítás'] : 'tls';
    $felhasználó = trim($_POST['felhasználó'] ?? '');
    $jelszó = $_POST['jelszó'] ?? '';
    $from_email = trim($_POST['from_email'] ?? '');
    $from_name = trim($_POST['from_name'] ?? '');
    $alapértelmezett = !empty($_POST['alapértelmezett']);

    if ($név === '' || $host === '' || $from_email === '') {
        $hiba = 'Név, host és Feladó e-mail megadása kötelező.';
    } elseif (!filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
        $hiba = 'Érvénytelen feladó e-mail cím.';
    } else {
        if ($alapértelmezett) {
            $db->exec('UPDATE finance_email_accounts SET alapértelmezett = 0');
        }
        if ($jelszó !== '') {
            $jelszó_enc = email_jelszo_titkosit($jelszó);
            $stmt = $db->prepare('UPDATE finance_email_accounts SET név=?, host=?, port=?, titkosítás=?, felhasználó=?, jelszó_titkosított=?, from_email=?, from_name=?, alapértelmezett=? WHERE id=?');
            $stmt->execute([$név, $host, $port ?: 587, $titkosítás, $felhasználó, $jelszó_enc, $from_email, $from_name, $alapértelmezett ? 1 : 0, $id]);
        } else {
            $stmt = $db->prepare('UPDATE finance_email_accounts SET név=?, host=?, port=?, titkosítás=?, felhasználó=?, from_email=?, from_name=?, alapértelmezett=? WHERE id=?');
            $stmt->execute([$név, $host, $port ?: 587, $titkosítás, $felhasználó, $from_email, $from_name, $alapértelmezett ? 1 : 0, $id]);
        }
        rendszer_log('email_config', $id, 'SMTP fiók módosítva', null);
        flash('success', 'SMTP fiók mentve.');
        redirect(nextgen_url('admin/email/'));
    }
    $row = [
        'név' => $név ?? $row['név'],
        'host' => $host ?? $row['host'],
        'port' => $port ?? $row['port'],
        'titkosítás' => $titkosítás ?? $row['titkosítás'],
        'felhasználó' => $felhasználó ?? $row['felhasználó'],
        'from_email' => $from_email ?? $row['from_email'],
        'from_name' => $from_name ?? $row['from_name'],
        'alapértelmezett' => isset($alapértelmezett) ? $alapértelmezett : (bool)$row['alapértelmezett'],
    ];
} else {
    $row = [
        'név' => $row['név'],
        'host' => $row['host'],
        'port' => $row['port'],
        'titkosítás' => $row['titkosítás'],
        'felhasználó' => $row['felhasználó'],
        'from_email' => $row['from_email'],
        'from_name' => $row['from_name'],
        'alapértelmezett' => (bool)$row['alapértelmezett'],
    ];
}

$pageTitle = 'SMTP fiók szerkesztése';
require_once __DIR__ . '/../../partials/header.php';
?>
<div class="card card-narrow">
    <h2>SMTP fiók szerkesztése</h2>
    <?php if ($hiba): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label>Megjelenített név *</label>
            <input type="text" name="név" value="<?= h($row['név']) ?>" required>
        </div>
        <div class="form-group">
            <label>SMTP host *</label>
            <input type="text" name="host" value="<?= h($row['host']) ?>" required>
        </div>
        <div class="form-group">
            <label>Port</label>
            <input type="number" name="port" value="<?= (int)$row['port'] ?>" min="1" max="65535">
        </div>
        <div class="form-group">
            <label>Titkosítás</label>
            <select name="titkosítás">
                <option value="tls" <?= $row['titkosítás'] === 'tls' ? 'selected' : '' ?>>TLS</option>
                <option value="ssl" <?= $row['titkosítás'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
                <option value="" <?= $row['titkosítás'] === '' ? 'selected' : '' ?>>Nincs</option>
            </select>
        </div>
        <div class="form-group">
            <label>Felhasználónév (SMTP auth)</label>
            <input type="text" name="felhasználó" value="<?= h($row['felhasználó']) ?>" autocomplete="off">
        </div>
        <div class="form-group">
            <label>Jelszó (üres = nem változik)</label>
            <input type="password" name="jelszó" value="" placeholder="Új jelszó csak ha cserélni szeretné" autocomplete="new-password">
        </div>
        <div class="form-group">
            <label>Feladó e-mail (From) *</label>
            <input type="email" name="from_email" value="<?= h($row['from_email']) ?>" required>
        </div>
        <div class="form-group">
            <label>Feladó név (From name)</label>
            <input type="text" name="from_name" value="<?= h($row['from_name']) ?>">
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="alapértelmezett" value="1" <?= $row['alapértelmezett'] ? 'checked' : '' ?>> Alapértelmezett fiók</label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Mentés</button>
            <a href="<?= h(nextgen_url('admin/email/')) ?>" class="btn btn-secondary">Mégse</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
