<?php
/**
 * Dashboard – URL: /nextgen/index.php (Apache: DocumentRoot szabályok – lásd nextgen/apache-document-root.example.txt).
 * Vendég: a header requireLogin() → /nextgen/login.php.
 */
require_once __DIR__ . '/init.php';

$pageTitle = 'Dashboard';
require_once __DIR__ . '/partials/header.php';

$db = getDb();

$szervezo_count = $db->query('SELECT COUNT(*) FROM szervezők')->fetchColumn();

$szamlazando_osszes = (int) $db->query('SELECT COUNT(*) FROM számlázandó WHERE (COALESCE(törölve,0) = 0)')->fetchColumn();
$szamlazando_nem_szamla = (int) $db->query('SELECT COUNT(*) FROM számlázandó WHERE számla_id IS NULL AND (COALESCE(törölve,0) = 0)')->fetchColumn();

$szamla_statuszok = ['generált' => 'Generált', 'kiküldve' => 'Kiküldve', 'kiegyenlítve' => 'Kiegyenlítve', 'egyéb' => 'Egyéb', 'KP' => 'KP', 'sztornó' => 'Sztornó'];
$szamla_by_status = [];
$szamla_osszes_db = 0;
$szamla_osszes_osszeg = 0;
foreach (array_keys($szamla_statuszok) as $st) {
    $stmt = $db->prepare('SELECT COUNT(*), COALESCE(SUM(összeg), 0) FROM számlák WHERE státusz = ? AND (COALESCE(törölve,0) = 0)');
    $stmt->execute([$st]);
    $row = $stmt->fetch(PDO::FETCH_NUM);
    $szamla_by_status[$st] = ['db' => (int) $row[0], 'osszeg' => (float) $row[1]];
    if ($st !== 'kiegyenlítve' && $st !== 'sztornó') {
        $szamla_osszes_db += (int) $row[0];
        $szamla_osszes_osszeg += (float) $row[1];
    }
}
?>
<?php if ($err = flash('error')): ?><p class="alert alert-error"><?= h($err) ?></p><?php endif; ?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<div class="dash-cards">
    <a href="<?= h(nextgen_url('organizers/')) ?>" class="dash-card">
        <h3>Szervezők</h3>
        <div class="num"><?= (int) $szervezo_count ?></div>
        <p>Szervezők listája, keresés, szűrés</p>
    </a>
    <a href="<?= h(nextgen_url('finance/szamlazando/')) ?>" class="dash-card">
        <h3>Számlázandó</h3>
        <div class="num"><?= (int) $szamlazando_nem_szamla ?> / <?= (int) $szamlazando_osszes ?></div>
        <p>Nem számlázott / összes</p>
    </a>
    <a href="<?= h(nextgen_url('finance/szamlak/')) ?>" class="dash-card dash-card-szamlak">
        <h3>Számlák</h3>
        <table class="dash-szamla-table">
            <thead>
                <tr>
                    <th></th>
                    <th class="th-right">db</th>
                    <th class="th-right">összeg</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($szamla_by_status as $st => $d):
                    if ($st === 'kiegyenlítve' || $st === 'sztornó') continue;
                    $l = $szamla_statuszok[$st];
                ?>
                <tr>
                    <td><?= h($l) ?></td>
                    <td class="text-right"><?= $d['db'] ?></td>
                    <td class="text-right"><?= number_format($d['osszeg'], 0, ',', ' ') ?> Ft</td>
                </tr>
                <?php endforeach; ?>
                <tr class="dash-szamla-osszes">
                    <td>Összesen</td>
                    <td class="text-right"><?= $szamla_osszes_db ?></td>
                    <td class="text-right"><?= number_format($szamla_osszes_osszeg, 0, ',', ' ') ?> Ft</td>
                </tr>
                <?php $d = $szamla_by_status['kiegyenlítve'] ?? ['db' => 0, 'osszeg' => 0]; $l = $szamla_statuszok['kiegyenlítve'] ?? 'Kiegyenlítve'; ?>
                <tr class="dash-szamla-kiegyenlitve">
                    <td><?= h($l) ?> 🎉</td>
                    <td class="text-right"><?= $d['db'] ?></td>
                    <td class="text-right"><?= number_format($d['osszeg'], 0, ',', ' ') ?> Ft</td>
                </tr>
            </tbody>
        </table>
    </a>
</div>

<div class="card">
    <h2>Gyors menük</h2>
    <p>
        <a href="<?= h(nextgen_url('organizers/')) ?>" class="btn btn-primary">Szervezők</a>
        <a href="<?= h(nextgen_url('organizers/letrehoz.php')) ?>" class="btn btn-secondary">Új szervező</a>
        <a href="<?= h(nextgen_url('contacts/')) ?>" class="btn btn-secondary">Kontaktok</a>
        <a href="<?= h(nextgen_url('finance/szamlak/')) ?>" class="btn btn-secondary">Számlák</a>
        <a href="<?= h(nextgen_url('finance/szamlazando/')) ?>" class="btn btn-secondary">Számlázandó</a>
        <a href="<?= h(nextgen_url('admin/log.php')) ?>" class="btn btn-secondary">Rendszer log</a>
    </p>
</div>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
