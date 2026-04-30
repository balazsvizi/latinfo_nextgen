<?php
declare(strict_types=1);
/**
 * Központi belépő – Finance és Event Admin választása (közös nextgen login után).
 * URL: /nextgen/apps.php
 */

require_once __DIR__ . '/init.php';

$pageTitle = 'Alkalmazások';
require_once __DIR__ . '/partials/header.php';

$db = getDb();
$eventCount = 0;
try {
    $eventCount = (int) $db->query('SELECT COUNT(*) FROM `events_calendar_events`')->fetchColumn();
} catch (Throwable $e) {
    // tábla még nincs – hub továbbra is működik
}
?>
<div class="card">
    <h2>Alkalmazások</h2>
</div>

<div class="dash-cards dash-cards-apps">
    <a href="<?= h(nextgen_url('index.php')) ?>" class="dash-card dash-card-finance">
        <h3>Finance</h3>
        <div class="num">→</div>
        <p>Szervezők, finance_contacts, finance_billing_items, finance_invoices – <code>nextgen/</code></p>
    </a>
    <a href="<?= h(site_url('events/events_admin.php')) ?>" class="dash-card dash-card-events">
        <h3>Event Admin</h3>
        <div class="num"><?= $eventCount ?></div>
        <p>Események naptár – <code>events/</code></p>
    </a>
    <a href="<?= h(nextgen_url('config/cimkek.php')) ?>" class="dash-card dash-card-nextgen">
        <h3>NextGen</h3>
        <div class="num">→</div>
        <p>Config, admin, levélsablonok – <code>nextgen/config/</code>, <code>nextgen/admin/</code></p>
    </a>
</div>

<div class="card">
    <h2>Gyors linkek</h2>
    <p>
        <a href="<?= h(nextgen_url('organizers/')) ?>" class="btn btn-secondary">Szervezők</a>
        <a href="<?= h(nextgen_url('finance/szamlazando/')) ?>" class="btn btn-secondary">Számlázandó</a>
        <a href="<?= h(site_url('events/letrehoz.php')) ?>" class="btn btn-secondary">Új esemény</a>
        <a href="<?= h(site_url('events/import_csv.php')) ?>" class="btn btn-secondary">CSV import</a>
        <a href="<?= h(nextgen_url('config/cimkek.php')) ?>" class="btn btn-secondary">NextGen – Címkék</a>
        <a href="<?= h(nextgen_url('admin/log.php')) ?>" class="btn btn-secondary">NextGen – Logok</a>
    </p>
</div>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
