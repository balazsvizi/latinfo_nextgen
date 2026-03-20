<?php
/**
 * Exporter – kapcsolat szerkesztése
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/email.php';

requireLogin();
requireSuperadmin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Érvénytelen kapcsolat.');
    redirect(BASE_URL . '/admin/exporter/connections.php');
}

$db = getDb();
$stmt = $db->prepare('SELECT id, név, host, port, dbname, felhasználó FROM exporter_connections WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
    flash('error', 'Kapcsolat nem található.');
    redirect(BASE_URL . '/admin/exporter/connections.php');
}

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
        if ($jelszó !== '') {
            $jelszó_enc = email_jelszo_titkosit($jelszó);
            $stmt = $db->prepare('UPDATE exporter_connections SET név=?, host=?, port=?, dbname=?, felhasználó=?, jelszó_titkosított=? WHERE id=?');
            $stmt->execute([$név, $host ?: 'localhost', $port ?: 3306, $dbname, $felhasználó, $jelszó_enc, $id]);
        } else {
            $stmt = $db->prepare('UPDATE exporter_connections SET név=?, host=?, port=?, dbname=?, felhasználó=? WHERE id=?');
            $stmt->execute([$név, $host ?: 'localhost', $port ?: 3306, $dbname, $felhasználó, $id]);
        }
        flash('success', 'Kapcsolat mentve.');
        redirect(BASE_URL . '/admin/exporter/connections.php');
    }
    $row = ['név' => $név, 'host' => $host, 'port' => $port, 'dbname' => $dbname, 'felhasználó' => $felhasználó];
}

$pageTitle = 'Exporter – Kapcsolat szerkesztése';
require_once __DIR__ . '/../../partials/header.php';
?>
<div class="card card-narrow">
    <h2>Kapcsolat szerkesztése</h2>
    <?php if ($hiba): ?><p class="msg msg-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label>Megjelenített név *</label>
            <input type="text" name="név" value="<?= h($row['név']) ?>" required>
        </div>
        <div class="form-group">
            <label>Host</label>
            <input type="text" name="host" value="<?= h($row['host']) ?>" placeholder="localhost">
        </div>
        <div class="form-group">
            <label>Port</label>
            <input type="number" name="port" value="<?= (int)$row['port'] ?>" min="1" max="65535" placeholder="3306">
        </div>
        <div class="form-group">
            <label>Adatbázis (dbname) *</label>
            <input type="text" name="dbname" value="<?= h($row['dbname']) ?>" required>
        </div>
        <div class="form-group">
            <label>Felhasználónév</label>
            <input type="text" name="felhasználó" value="<?= h($row['felhasználó']) ?>" autocomplete="off">
        </div>
        <div class="form-group">
            <label>Jelszó</label>
            <input type="password" name="jelszó" value="" placeholder="üres = nem változik" autocomplete="new-password">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Mentés</button>
            <a href="<?= h(BASE_URL) ?>/admin/exporter/connections.php" class="btn btn-secondary">Mégse</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
