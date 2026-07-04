<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/admin_event_filters.php';
requireLogin();

$db = getDb();

$listLimitParsed = events_admin_list_limit_from_get();
$list_limit = $listLimitParsed['sql_limit'];
$listLimitValue = $listLimitParsed['value'];
$listTotalInDb = events_admin_table_total_count($db, 'events_organizers');
$poolFrom = events_admin_table_pool_from_sql('events_organizers', 'o', $list_limit);

$stmt = $db->query('SELECT o.`id`, o.`name` FROM ' . $poolFrom . ' ORDER BY o.`name` ASC, o.`id` ASC');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$listDisplayedCount = count($rows);

$pageTitle = 'Szervezők';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="card events-admin-card">
    <div class="events-list-head">
        <div class="events-list-head__start">
            <h1 class="events-list-title card-title" style="margin:0;">Esemény szervezők</h1>
            <?php
            $listLimitInForm = false;
            $listLimitStandalone = true;
            require __DIR__ . '/partials/admin_list_display_limit.php';
            ?>
        </div>
    </div>
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
        <a href="<?= h(events_url('organizer_letrehoz.php')) ?>" class="btn btn-primary">Új szervező</a>
        <a href="<?= h(events_url('events_admin.php')) ?>" class="btn btn-secondary">Események</a>
    </p>
</div>
<?php require __DIR__ . '/partials/admin_list_display_limit_script.php'; ?>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
