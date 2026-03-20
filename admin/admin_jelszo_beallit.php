<?php
/**
 * Admin jelszó beállító segéd.
 * Csak superadmin (weben), vagy CLI alatt fut.
 * Futtatás után célszerű törölni.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    requireSuperadmin();
}

$jelszo = 'password';
$hash = password_hash($jelszo, PASSWORD_DEFAULT);

$db = getDb();
$stmt = $db->prepare('UPDATE adminok SET jelszó_hash = ? WHERE felhasználónév = ?');
$stmt->execute([$hash, 'admin']);
$n = $stmt->rowCount();

if ($n > 0) {
    $uzenet = "Kész. Az 'admin' felhasználó jelszava most: " . $jelszo;
} else {
    $stmt = $db->prepare("INSERT INTO adminok (név, felhasználónév, jelszó_hash, szint, aktív) VALUES (?, ?, ?, 'superadmin', 1)");
    $stmt->execute(['Főadmin', 'admin', $hash]);
    $uzenet = "Kész. Új admin létrehozva: felhasználónév = admin, jelszó = " . $jelszo;
}

rendszer_log('admin', null, 'Jelszó visszaállítás segéd futtatva', "Cél felhasználó: admin");

if ($isCli) {
    echo $uzenet . PHP_EOL;
    echo "Biztonság: töröld ezt a fájlt (admin/admin_jelszo_beallit.php) használat után." . PHP_EOL;
    exit;
}

$pageTitle = 'Admin jelszó beállítás';
require_once __DIR__ . '/../partials/header.php';
?>
<div class="card card-narrow">
    <h2>Admin jelszó beállítás</h2>
    <p class="alert alert-success"><?= h($uzenet) ?></p>
    <p class="alert alert-error">Biztonság: használat után töröld ezt a fájlt (`admin/admin_jelszo_beallit.php`).</p>
    <p><a href="<?= h(BASE_URL) ?>/admin/adminok/" class="btn btn-secondary">Vissza az adminokhoz</a></p>
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
