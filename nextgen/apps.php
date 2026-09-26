<?php
declare(strict_types=1);
/**
 * Központi belépő – Finance, Event Admin, Latinfo.hu, CMS és NextGen (közös nextgen login után).
 * URL: /nextgen/apps.php
 */

require_once __DIR__ . '/init.php';

$pageTitle = 'Alkalmazások';
require_once __DIR__ . '/partials/header.php';

$db = getDb();
$eventCount = 0;
$cmsCount = 0;
try {
    $eventCount = (int) $db->query('SELECT COUNT(*) FROM `events_calendar_events`')->fetchColumn();
} catch (Throwable $e) {
    // tábla még nincs – hub továbbra is működik
}
try {
    require_once __DIR__ . '/cms/lib/schema.php';
    if (cms_ensure_schema($db)) {
        $cmsCount = (int) $db->query('SELECT COUNT(*) FROM `cms_posts`')->fetchColumn();
    }
} catch (Throwable $e) {
    // CMS séma még nem elérhető
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
    <a href="<?= h(nextgen_url('events/events_admin.php')) ?>" class="dash-card dash-card-events">
        <h3>Event Admin</h3>
        <div class="num"><?= $eventCount ?></div>
        <p>Események naptár – <code>events/</code></p>
    </a>
    <a href="<?= h(nextgen_url('latinfo/')) ?>" class="dash-card dash-card-latinfo">
        <h3>Latinfo.hu</h3>
        <div class="num">→</div>
        <p>Kezdőoldal, statok, slug, Partnereink, levélsablonok, adatok, CSV import</p>
    </a>
    <a href="<?= h(nextgen_url('cms/')) ?>" class="dash-card dash-card-cms">
        <h3>CMS</h3>
        <div class="num"><?= $cmsCount ?></div>
        <p>Cikkek, témák, HTML tartalom, közös címkék – <code>cms/</code></p>
    </a>
    <a href="<?= h(nextgen_url('config/cimkek.php')) ?>" class="dash-card dash-card-nextgen">
        <h3>NextGen</h3>
        <div class="num">→</div>
        <p>Config és admin – <code>nextgen/config/</code>, <code>nextgen/admin/</code></p>
    </a>
</div>

<div class="card">
    <h2>Gyors linkek</h2>
    <p>
        <a href="<?= h(nextgen_url('organizers/')) ?>" class="btn btn-secondary">Szervezők</a>
        <a href="<?= h(nextgen_url('finance/szamlazando/')) ?>" class="btn btn-secondary">Számlázandó</a>
        <a href="<?= h(nextgen_url('events/letrehoz.php')) ?>" class="btn btn-secondary">Új esemény</a>
        <a href="<?= h(nextgen_url('cms/letrehoz.php')) ?>" class="btn btn-secondary">Új CMS cikk</a>
        <a href="<?= h(nextgen_url('latinfo/')) ?>" class="btn btn-secondary">Latinfo.hu</a>
        <a href="<?= h(nextgen_url('config/cimkek.php')) ?>" class="btn btn-secondary">NextGen – Címkék</a>
        <a href="<?= h(nextgen_url('admin/log.php')) ?>" class="btn btn-secondary">NextGen – Logok</a>
    </p>
</div>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
