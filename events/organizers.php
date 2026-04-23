<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/nextgen/includes/auth.php';
requireLogin();

$db = getDb();
$stmt = $db->query('SELECT id, name FROM events_organizers ORDER BY name ASC, id ASC');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Szervezők';
require_once dirname(__DIR__) . '/nextgen/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="card">
    <h1 class="card-title">Esemény szervezők</h1>
    <p class="text-muted" style="margin-bottom:1rem;">A szervezők az esemény űrlapokon és a CSV importban választhatók. Szerkesztő felület később bővíthető.</p>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Név</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="2">Nincs szervező. Adhatsz hozzá CSV importtal vagy közvetlenül az adatbázisban.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= (int) $r['id'] ?></td>
                            <td><?= h((string) $r['name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="toolbar" style="margin-top:1rem;">
        <a href="<?= h(events_url('import_csv.php')) ?>" class="btn btn-secondary">CSV import</a>
        <a href="<?= h(events_url('events_admin.php')) ?>" class="btn btn-secondary">Események</a>
    </p>
</div>
