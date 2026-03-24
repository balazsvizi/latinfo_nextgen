<?php
/**
 * Exporter – adatbázis kapcsolatok listája
 */
$pageTitle = 'Exporter – Kapcsolatok';
require_once __DIR__ . '/../../partials/header.php';

requireLogin();
requireSuperadmin();

$db = getDb();
$listak = $db->query('SELECT id, név, host, port, dbname, felhasználó, létrehozva FROM exporter_connections ORDER BY név ASC')->fetchAll();
?>
<div class="card">
    <h2>Adatbázis kapcsolatok</h2>
    <p>Mentett SQL szerver kapcsolatok – ezek közül választhatsz exportáláskor. Az „Alapértelmezett” mindig az alkalmazás saját adatbázisa.</p>
    <div class="form-actions" style="margin-bottom: 1rem;">
        <a href="<?= h(nextgen_url('admin/exporter/')) ?>" class="btn btn-secondary">← SQL export</a>
        <a href="<?= h(nextgen_url('admin/exporter/connection_letrehoz.php')) ?>" class="btn btn-primary">Új kapcsolat</a>
    </div>
    <div class="table-wrap">
        <table class="sortable-table">
            <thead>
                <tr>
                    <th>Név</th>
                    <th>Host</th>
                    <th>Port</th>
                    <th>Adatbázis</th>
                    <th>Felhasználó</th>
                    <th>Műveletek</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($listak)): ?>
                <tr><td colspan="6">Még nincs mentett kapcsolat. <a href="<?= h(nextgen_url('admin/exporter/connection_letrehoz.php')) ?>">Új kapcsolat hozzáadása</a></td></tr>
                <?php else: ?>
                <?php foreach ($listak as $r): ?>
                <tr>
                    <td><?= h($r['név']) ?></td>
                    <td><?= h($r['host']) ?></td>
                    <td><?= (int)$r['port'] ?></td>
                    <td><?= h($r['dbname']) ?></td>
                    <td><?= h($r['felhasználó']) ?></td>
                    <td class="actions">
                        <a href="<?= h(nextgen_url('admin/exporter/connection_teszt.php?id=')) ?><?= (int)$r['id'] ?>" class="btn btn-sm btn-primary">Teszt</a>
                        <a href="<?= h(nextgen_url('admin/exporter/connection_szerkeszt.php?id=')) ?><?= (int)$r['id'] ?>" class="btn btn-sm btn-secondary">Szerkeszt</a>
                        <form method="post" action="connection_torles.php" style="display:inline;" onsubmit="return confirm('Biztosan törlöd ezt a kapcsolatot?');">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Törlés</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
