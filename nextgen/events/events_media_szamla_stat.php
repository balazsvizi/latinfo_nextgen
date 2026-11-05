<?php
declare(strict_types=1);

/**
 * Szervezőnkénti havi médiaérték és leszámlázott összeg.
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/organizer_media_invoice_stats.php';
requireLogin();

$db = getDb();
$statsParams = organizer_media_invoice_stats_params_from_request($_GET);
$statsData = organizer_media_invoice_stats($db, $statsParams);
$statsFormAction = events_url('events_media_szamla_stat.php');

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Médiaérték és számla';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="events-stat-page-head">
    <div>
        <h1 class="events-stat-page-title">Médiaérték és számla</h1>
        <p class="events-stat-page-lead">
            <?= (int) $statsParams['year'] ?> szervezőnként, havi bontásban: generált médiaérték,
            leszámlázott összeg és éves szumma.
        </p>
    </div>
    <div class="events-stat-page-actions">
        <a href="<?= h(events_url('events_szervezok_statisztika.php')) ?>" class="btn btn-secondary btn-sm">Szervezők stat</a>
        <a href="<?= h(events_url('events_havi_stat.php')) ?>" class="btn btn-secondary btn-sm">Havi esemény stat</a>
        <a href="<?= h(events_url('events_statisztika.php')) ?>" class="btn btn-secondary btn-sm">Esemény stat</a>
        <a href="<?= h(nextgen_url('admin/partnerek/mediaertek.php')) ?>" class="btn btn-secondary btn-sm">Egységárak</a>
    </div>
</div>

<?php require __DIR__ . '/partials/organizer_media_invoice_stats.php'; ?>

<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
