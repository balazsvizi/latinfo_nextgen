<?php
declare(strict_types=1);

/**
 * Latinfo.hu alkalmazás – kezdőoldal, statok, slug, partnereink, adatok, CSV import.
 * URL: /nextgen/latinfo/
 */

require_once dirname(__DIR__) . '/init.php';
requireLogin();

$pageTitle = 'Latinfo.hu';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<div class="card">
    <h2>Latinfo.hu</h2>
    <p>Nyilvános latinfo.hu felület: kezdőoldal, statisztikák, slug, partnerek, adatok és CSV import.</p>
</div>

<div class="dash-cards dash-cards-apps">
    <a href="<?= h(nextgen_url('site/')) ?>" class="dash-card">
        <h3>Latinfo.hu kezdőoldal</h3>
        <div class="num">→</div>
        <p>Előnézet és szerkesztés – <code>site/</code></p>
    </a>
    <a href="<?= h(nextgen_url('events/events_statisztika.php')) ?>" class="dash-card">
        <h3>Statok</h3>
        <div class="num">→</div>
        <p>Statisztikák, publikus oldalak, valós idejű áttekintés</p>
    </a>
    <a href="<?= h(nextgen_url('events/slug_atiranyitasok.php')) ?>" class="dash-card">
        <h3>Slug</h3>
        <div class="num">→</div>
        <p>Slug átirányítások kezelése</p>
    </a>
    <a href="<?= h(nextgen_url('events/partnerek_szerkeszt.php')) ?>" class="dash-card">
        <h3>Partnereink</h3>
        <div class="num">→</div>
        <p>Nyilvános partnereink oldal blokkjai</p>
    </a>
    <?php if (isSuperadmin()): ?>
    <a href="<?= h(nextgen_url('events/adatok.php')) ?>" class="dash-card">
        <h3>Adatok</h3>
        <div class="num">→</div>
        <p>Táblák áttekintése és ürítés – csak superadmin</p>
    </a>
    <?php endif; ?>
    <a href="<?= h(nextgen_url('events/import_csv.php')) ?>" class="dash-card">
        <h3>CSV import</h3>
        <div class="num">→</div>
        <p>Esemény- és törzsadat import CSV-ből</p>
    </a>
</div>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
