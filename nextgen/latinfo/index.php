<?php
declare(strict_types=1);

/**
 * Latinfo.hu alkalmazás – előadók, tánciskolák, tánctanárok, kezdőoldal, statok, slug, partnereink, levélsablonok, adatok, CSV import.
 * URL: /nextgen/latinfo/
 */

require_once dirname(__DIR__) . '/init.php';
requireLogin();

$pageTitle = 'Latinfo.hu';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<div class="card">
    <h2>Latinfo.hu</h2>
    <p>Nyilvános latinfo.hu felület: előadók, tánciskolák / tánctanárok (egyelőre csak admin), kezdőoldal, statisztikák, slug, partnerek, levélsablonok, adatok és CSV import.</p>
</div>

<div class="dash-cards dash-cards-apps">
    <a href="<?= h(function_exists('events_public_zenekarok_hub_canonical_url') ? events_public_zenekarok_hub_canonical_url() : nextgen_url('events/zenekarok.php')) ?>" class="dash-card">
        <h3>Előadók kezdőoldal</h3>
        <div class="num">→</div>
        <p>Nyilvános lista – <code>/eloadok/</code></p>
    </a>
    <a href="<?= h(nextgen_url('events/zenekarok_admin.php')) ?>" class="dash-card">
        <h3>Előadók admin</h3>
        <div class="num">→</div>
        <p>Lista, szerkesztés, oldal szövegek</p>
    </a>
    <a href="<?= h(nextgen_url('events/tanciskolak_admin.php')) ?>" class="dash-card">
        <h3>Tánciskolák</h3>
        <div class="num">→</div>
        <p>Admin lista – helyszínek, órák, workshopok (még nem publikus)</p>
    </a>
    <a href="<?= h(nextgen_url('events/tanarok_admin.php')) ?>" class="dash-card">
        <h3>Tánctanárok</h3>
        <div class="num">→</div>
        <p>Admin lista – kontakt, stílusok, magánóra (még nem publikus)</p>
    </a>
    <a href="<?= h(nextgen_url('admin/users/')) ?>" class="dash-card">
        <h3>Userek</h3>
        <div class="num">→</div>
        <p>Publikus fiókok listája, keresés és kedvencek (szívecskék)</p>
    </a>
    <a href="<?= h(nextgen_url('events/events_statisztika.php')) ?>" class="dash-card">
        <h3>Statok</h3>
        <div class="num">→</div>
        <p>Statisztikák, publikus oldalak, kedvencek, valós idejű áttekintés</p>
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
    <a href="<?= h(nextgen_url('config/levelsablonok/')) ?>" class="dash-card">
        <h3>Levélsablonok</h3>
        <div class="num">→</div>
        <p>Esemény-értesítő és egyéb e-mail sablonok</p>
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
