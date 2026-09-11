<?php
declare(strict_types=1);

/**
 * Egyszeri: hiányzó DJ profil oszlopok létrehozása (pl. mixcloud_url).
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/tag_profile.php';
requireLogin();

$db = getDb();
$pageTitle = 'DJ profil oszlopok';

$result = [
    'missing' => events_tag_profile_column_names(),
    'errors' => ['_init' => 'Nem futott.'],
];
$ran = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('dj_profile_migrate')) {
        flash('error', 'Lejárt vagy érvénytelen munkamenet.');
        redirect(events_url('migrate_tag_profile_columns.php'));
    }
    $result = events_tags_ensure_profile_columns($db);
    $ran = true;
    if ($result['missing'] === []) {
        flash('success', 'Minden profil-oszlop megvan.');
        redirect(events_url('djs_admin.php'));
    }
}

$present = events_tag_profile_present_columns($db, true);
$missing = array_values(array_diff(events_tag_profile_column_names(), $present));
$manualSql = events_tag_profile_manual_alter_sql($missing);

require_once dirname(__DIR__) . '/partials/header.php';
?>
<div class="card events-admin-card">
    <h1><?= h($pageTitle) ?></h1>
    <p class="help">Ez a lap létrehozza a hiányzó <code>events_tags</code> profil-oszlopokat (Mixcloud, fotó fókusz stb.).</p>

    <?php if ($ran && $result['missing'] !== []): ?>
        <p class="alert alert-error">Néhány oszlop továbbra is hiányzik. Ha jogosultsági / sorméret hiba van, futtasd a SQL-t phpMyAdminban (root / ALTER joggal).</p>
        <?php if ($result['errors'] !== []): ?>
            <ul>
                <?php foreach ($result['errors'] as $col => $err): ?>
                    <li><code><?= h((string) $col) ?></code>: <?= h((string) $err) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>

    <p><strong>Megvan:</strong> <?= (int) count($present) ?> / <?= (int) count(events_tag_profile_column_names()) ?></p>
    <?php if ($missing === []): ?>
        <p class="alert alert-success">Nincs hiányzó oszlop.</p>
        <p><a class="btn btn-secondary" href="<?= h(events_url('djs_admin.php')) ?>">← DJ-k</a></p>
    <?php else: ?>
        <p><strong>Hiányzik:</strong> <?= h(implode(', ', $missing)) ?></p>
        <form method="post" class="toolbar">
            <?= csrf_input('dj_profile_migrate') ?>
            <button type="submit" class="btn btn-primary">Oszlopok létrehozása most</button>
            <a class="btn btn-secondary" href="<?= h(events_url('djs_admin.php')) ?>">← Vissza</a>
        </form>
        <?php if ($manualSql !== []): ?>
            <h2>Kézi SQL (phpMyAdmin)</h2>
            <pre style="white-space:pre-wrap;background:#f4f6f3;padding:0.85rem;border-radius:8px;border:1px solid #d8e0d4;"><?= h(implode("\n", $manualSql)) ?></pre>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php
require_once dirname(__DIR__) . '/partials/footer.php';
