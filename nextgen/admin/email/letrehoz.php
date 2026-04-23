<?php
require_once __DIR__ . '/../../../nextgen/core/database.php';
require_once __DIR__ . '/../../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../../nextgen/includes/functions.php';
require_once __DIR__ . '/../../../nextgen/includes/email.php';
requireSuperadmin();

$db = getDb();
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
        $jelszó_enc = $jelszó !== '' ? email_jelszo_titkosit($jelszó) : null;
        $stmt = $db->prepare('INSERT INTO finance_email_accounts (név, host, port, titkosítás, felhasználó, jelszó_titkosított, from_email, from_name, alapértelmezett)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$név, $host, $port ?: 587, $titkosítás, $felhasználó, $jelszó_enc, $from_email, $from_name, $alapértelmezett ? 1 : 0]);
        rendszer_log('email_config', (int)$db->lastInsertId(), 'SMTP fiók létrehozva', null);
        flash('success', 'SMTP fiók létrehozva.');
        redirect(nextgen_url('admin/email/'));
    }
}

$pageTitle = 'Új SMTP fiók';
require_once __DIR__ . '/../../partials/header.php';
?>
<div class="card card-narrow">
    <h2>Új SMTP fiók</h2>
    <?php if ($hiba): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label>Megjelenített név *</label>
            <input type="text" name="név" value="<?= h($_POST['név'] ?? '') ?>" required placeholder="pl. Latinfo SMTP">
        </div>
        <div class="form-group">
            <label>SMTP host *</label>
            <input type="text" name="host" value="<?= h($_POST['host'] ?? '') ?>" required placeholder="pl. smtp.gmail.com">
        </div>
        <div class="form-group">
            <label>Port</label>
            <input type="number" name="port" value="<?= h($_POST['port'] ?? '587') ?>" min="1" max="65535" placeholder="587">
        </div>
        <div class="form-group">
            <label>Titkosítás</label>
            <select name="titkosítás">
                <option value="tls" <?= ($_POST['titkosítás'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS (ajánlott)</option>
                <option value="ssl" <?= ($_POST['titkosítás'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                <option value="" <?= ($_POST['titkosítás'] ?? '') === '' ? 'selected' : '' ?>>Nincs</option>
            </select>
        </div>
        <div class="form-group">
            <label>Felhasználónév (SMTP auth)</label>
            <input type="text" name="felhasználó" value="<?= h($_POST['felhasználó'] ?? '') ?>" placeholder="üres = nincs auth" autocomplete="off">
        </div>
        <div class="form-group">
            <label>Jelszó (SMTP auth)</label>
            <input type="password" name="jelszó" value="" placeholder="üres = nem változik" autocomplete="new-password">
        </div>
        <div class="form-group">
            <label>Feladó e-mail (From) *</label>
            <input type="email" name="from_email" value="<?= h($_POST['from_email'] ?? '') ?>" required placeholder="noreply@example.com">
        </div>
        <div class="form-group">
            <label>Feladó név (From name)</label>
            <input type="text" name="from_name" value="<?= h($_POST['from_name'] ?? '') ?>" placeholder="pl. Latinfo.hu">
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="alapértelmezett" value="1" <?= !empty($_POST['alapértelmezett']) ? 'checked' : '' ?>> Alapértelmezett fiók (ehhez küld a program, ha nincs megadva fiók)</label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Mentés</button>
            <a href="<?= h(nextgen_url('admin/email/')) ?>" class="btn btn-secondary">Mégse</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
