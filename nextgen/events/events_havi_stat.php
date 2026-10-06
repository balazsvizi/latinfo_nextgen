<?php
declare(strict_types=1);

/**
 * Éves / havi esemény-összehasonlító statisztika.
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/event_monthly_stats.php';
requireLogin();

$db = getDb();
$statsParams = events_monthly_stats_params_from_request($_GET);
$statsData = events_monthly_stats($db, $statsParams);
$statsFormAction = events_url('events_havi_stat.php');

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Havi esemény stat';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="events-stat-page-head">
    <div>
        <h1 class="events-stat-page-title">Havi esemény statisztika</h1>
        <p class="events-stat-page-lead">
            <?= (int) $statsParams['year'] ?> havi bontásban: eseményszám, kattintások, arányszámok és előkészítési idő,
            hónap–hónap és év–év összehasonlítással.
        </p>
    </div>
    <div class="events-stat-page-actions">
        <a href="<?= h(events_url('events_evi_stat.php')) ?>" class="btn btn-secondary btn-sm">Év/év stat</a>
        <a href="<?= h(events_url('events_elotti_stat.php')) ?>" class="btn btn-secondary btn-sm">30 nap</a>
        <a href="<?= h(events_url('events_statisztika.php')) ?>" class="btn btn-secondary btn-sm">Esemény stat</a>
        <a href="<?= h(events_url('events_lista_stat.php')) ?>" class="btn btn-secondary btn-sm">Lista stat</a>
        <a href="<?= h(events_url('events_public_stat.php')) ?>" class="btn btn-secondary btn-sm">Publikus oldalak</a>
        <a href="<?= h(events_url('events_kedvencek_stat.php')) ?>" class="btn btn-secondary btn-sm">Kedvencek</a>
        <a href="<?= h(events_url('events_stat.php')) ?>" class="btn btn-secondary btn-sm">Áttekintés</a>
    </div>
</div>

<?php require __DIR__ . '/partials/monthly_event_stats.php'; ?>

<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
