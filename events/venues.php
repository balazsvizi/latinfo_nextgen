<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/nextgen/includes/auth.php';
requireLogin();

$db = getDb();
$stmt = $db->query('SELECT `id`, `name`, `slug`, `description`, `address` FROM `events_venues` ORDER BY `name` ASC, `id` ASC');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$excerpt = static function (?string $s, int $max): string {
    $s = $s ?? '';
    if (function_exists('mb_strlen') && mb_strlen($s, 'UTF-8') > $max) {
        return mb_substr($s, 0, $max, 'UTF-8') . '…';
    }
    if (strlen($s) > $max) {
        return substr($s, 0, $max) . '…';
    }
    return $s;
};

$pageTitle = 'Helyszínek';
require_once dirname(__DIR__) . '/nextgen/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="card">
    <h1 class="card-title">Helyszínek (venues)</h1>
    <p class="toolbar" style="margin-bottom:1rem;">
        <a href="<?= h(events_url('venue_letrehoz.php')) ?>" class="btn btn-primary">Új helyszín</a>
        <a href="<?= h(events_url('import_csv.php')) ?>?target_table=events_venues" class="btn btn-secondary">CSV import (helyszínek)</a>
        <a href="<?= h(events_url('events_admin.php')) ?>" class="btn btn-secondary">Események</a>
    </p>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Név</th>
                    <th>Slug</th>
                    <th>Leírás</th>
                    <th>Cím</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="6">Még nincs helyszín. Futtasd az SQL migrációt (<code>events/sql/migration_events.sql</code> vagy <code>migration_venues.sql</code>), majd vegyél fel egy helyszínt.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= (int) $r['id'] ?></td>
                            <td><?= h((string) $r['name']) ?></td>
                            <td><code><?= h((string) $r['slug']) ?></code></td>
                            <td><?= h($excerpt((string) ($r['description'] ?? ''), 120)) ?></td>
                            <td><?= h($excerpt((string) ($r['address'] ?? ''), 80)) ?></td>
                            <td><a href="<?= h(events_url('venue_szerkeszt.php?id=') . (int) $r['id']) ?>" class="btn btn-sm btn-secondary">Szerkesztés</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/nextgen/partials/footer.php'; ?>
