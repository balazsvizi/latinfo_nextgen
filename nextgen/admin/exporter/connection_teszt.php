<?php
/**
 * Exporter – kapcsolat teszt (elérhető-e az adatbázis)
 */
require_once __DIR__ . '/../../../nextgen/core/config.php';
require_once __DIR__ . '/../../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../../nextgen/core/database.php';

requireLogin();
requireSuperadmin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    flash('error', 'Érvénytelen kapcsolat.');
    redirect(nextgen_url('admin/exporter/connections.php'));
}

$appDb = getDb();
$stmt = $appDb->prepare('SELECT id, név, host, port, dbname, felhasználó, jelszó_titkosított FROM exporter_connections WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
    flash('error', 'Kapcsolat nem található.');
    redirect(nextgen_url('admin/exporter/connections.php'));
}

require_once __DIR__ . '/../../../nextgen/includes/email.php';
$jelszo = !empty($row['jelszó_titkosított']) ? email_jelszo_visszafejt($row['jelszó_titkosított']) : '';
$dsn = 'mysql:host=' . $row['host'] . ';port=' . (int) $row['port'] . ';dbname=' . $row['dbname'] . ';charset=utf8mb4';
$opts = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

$siker = false;
$uzenet = '';
try {
    $pdo = new PDO($dsn, $row['felhasználó'], $jelszo, $opts);
    $pdo->query('SELECT 1');
    $siker = true;
    $uzenet = 'A kapcsolat sikeres. Az adatbázis elérhető.';
} catch (PDOException $e) {
    $uzenet = 'Kapcsolat hiba: ' . $e->getMessage();
}

$pageTitle = 'Kapcsolat teszt';
require_once __DIR__ . '/../../partials/header.php';
?>
<div class="card card-narrow">
    <h2>Kapcsolat teszt: <?= h($row['név']) ?></h2>
    <p class="text-muted"><?= h($row['host']) ?>:<?= (int)$row['port'] ?> / <?= h($row['dbname']) ?></p>
    <?php if ($siker): ?>
        <p class="msg msg-success"><?= h($uzenet) ?></p>
    <?php else: ?>
        <p class="msg msg-error"><?= h($uzenet) ?></p>
    <?php endif; ?>
    <div class="form-actions">
        <a href="<?= h(nextgen_url('admin/exporter/connections.php')) ?>" class="btn btn-primary">← Vissza a kapcsolatokhoz</a>
        <a href="<?= h(nextgen_url('admin/exporter/connection_szerkeszt.php?id=')) ?><?= (int)$row['id'] ?>" class="btn btn-secondary">Szerkeszt</a>
    </div>
</div>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
