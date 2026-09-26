<?php
declare(strict_types=1);

/**
 * CMS alkalmazás hub – cikkek, témák, képek, stat.
 * URL: /nextgen/cms/
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
requireLogin();

$db = getDb();
cms_ensure_schema($db);
$counts = cms_tables_available($db) ? cms_posts_status_counts($db) : ['total' => 0, 'draft' => 0, 'publish' => 0, 'other' => 0];

$pageTitle = 'CMS';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<div class="card">
    <h2>CMS</h2>
    <p>WordPress-szerű tartalomkezelő: cikkek, témák, HTML szöveg saját képtárral, közös címkék az Event Adminnal.</p>
</div>

<div class="dash-cards dash-cards-apps">
    <a href="<?= h(cms_url('posts.php')) ?>" class="dash-card">
        <h3>Cikkek</h3>
        <div class="num"><?= (int) $counts['total'] ?></div>
        <p><?= (int) $counts['publish'] ?> publikus · <?= (int) $counts['draft'] ?> draft · <?= (int) $counts['other'] ?> egyéb</p>
    </a>
    <a href="<?= h(cms_url('letrehoz.php')) ?>" class="dash-card">
        <h3>Új cikk</h3>
        <div class="num">+</div>
        <p>Új tartalom létrehozása</p>
    </a>
    <a href="<?= h(cms_url('temak.php')) ?>" class="dash-card">
        <h3>Témák</h3>
        <div class="num">→</div>
        <p>Szótár – választható témák a cikkekhez</p>
    </a>
    <a href="<?= h(cms_url('kepek.php')) ?>" class="dash-card">
        <h3>Képek</h3>
        <div class="num">→</div>
        <p>CMS képtár (elkülönül az eventpics-től)</p>
    </a>
    <a href="<?= h(cms_url('stat.php')) ?>" class="dash-card">
        <h3>Statisztika</h3>
        <div class="num">→</div>
        <p>Cikk megtekintések</p>
    </a>
    <a href="<?= h(cms_url('lista.php')) ?>" class="dash-card" target="_blank" rel="noopener">
        <h3>Nyilvános lista</h3>
        <div class="num">↗</div>
        <p>Publikus cikkek megtekintése</p>
    </a>
</div>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
