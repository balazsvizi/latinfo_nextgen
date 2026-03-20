<?php
/**
 * Exporter – új adatbázis kapcsolat
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/email.php';

requireLogin();
requireSuperadmin();

$db = getDb();
$hiba = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $név = trim($_POST['név'] ?? '');
    $host = trim($_POST['host'] ?? 'localhost');
    $port = (int) ($_POST['port'] ?? 3306);
    $dbname = trim($_POST['dbname'] ?? '');
    $felhasználó = trim($_POST['felhasználó'] ?? '');
    $jelszó = $_POST['jelszó'] ?? '';

    if ($név === '' || $dbname === '') {
        $hiba = 'A név és az adatbázis megadása kötelező.';
    } else {
        $jelszó_enc = $jelszó !== '' ? email_jelszo_titkosit($jelszó) : null;
        $stmt = $db->prepare('INSERT INTO exporter_connections (név, host, port, dbname, felhasználó, jelszó_titkosított) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$név, $host ?: 'localhost', $port ?: 3306, $dbname, $felhasználó, $jelszó_enc]);
        flash('success', 'Kapcsolat létrehozva.');
        redirect(BASE_URL . '/admin/exporter/connections.php');
    }
}

$pageTitle = 'Exporter – Új kapcsolat';
require_once __DIR__ . '/../../partials/header.php';
?>
<div class="card card-narrow">
    <h2>Új adatbázis kapcsolat</h2>
    <?php if ($hiba): ?><p class="msg msg-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label>Megjelenített név *</label>
            <input type="text" name="név" value="<?= h($_POST['név'] ?? '') ?>" required placeholder="pl. Éles MySQL">
        </div>
        <div class="form-group">
            <label>Host</label>
            <input type="text" name="host" value="<?= h($_POST['host'] ?? 'localhost') ?>" placeholder="localhost">
        </div>
        <div class="form-group">
            <label>Port</label>
            <input type="number" name="port" value="<?= h($_POST['port'] ?? '3306') ?>" min="1" max="65535" placeholder="3306">
        </div>
        <div class="form-group">
            <label>Adatbázis (dbname) *</label>
            <input type="text" name="dbname" value="<?= h($_POST['dbname'] ?? '') ?>" required placeholder="pl. mydb">
        </div>
        <div class="form-group">
            <label>Felhasználónév</label>
            <input type="text" name="felhasználó" value="<?= h($_POST['felhasználó'] ?? '') ?>" placeholder="MySQL felhasználó" autocomplete="off">
        </div>
        <div class="form-group">
            <label>Jelszó</label>
            <input type="password" name="jelszó" value="" placeholder="üres = nincs jelszó" autocomplete="new-password">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Mentés</button>
            <a href="<?= h(BASE_URL) ?>/admin/exporter/connections.php" class="btn btn-secondary">Mégse</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
