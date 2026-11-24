<?php
declare(strict_types=1);

/**
 * Havi főstílus-statisztika (súlyozott eseményszám).
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/event_style_monthly_stats.php';
requireLogin();

$db = getDb();
$statsParams = events_style_monthly_stats_params_from_request($_GET);
$statsData = events_style_monthly_stats($db, $statsParams);
$statsFormAction = events_url('events_stilus_havi_stat.php');

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Főstílus havi stat';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="events-stat-page-head">
    <div>
        <h1 class="events-stat-page-title">Főstílus havi statisztika</h1>
        <p class="events-stat-page-lead">
            <?= (int) $statsParams['year'] ?> hónapról hónapra: hány buli jut a fő stílusokra.
            Több fő stílus esetén az esemény egyenlően oszlik el (pl. 0,5–0,5).
        </p>
    </div>
    <div class="events-stat-page-actions">
        <a href="<?= h(events_url('events_havi_stat.php')) ?>" class="btn btn-secondary btn-sm">Havi esemény stat</a>
        <a href="<?= h(events_url('events_evi_stat.php')) ?>" class="btn btn-secondary btn-sm">Év/év stat</a>
        <a href="<?= h(events_url('styles.php')) ?>" class="btn btn-secondary btn-sm">Stílusok</a>
        <a href="<?= h(events_url('events_statisztika.php')) ?>" class="btn btn-secondary btn-sm">Esemény stat</a>
        <a href="<?= h(events_url('events_stat.php')) ?>" class="btn btn-secondary btn-sm">Áttekintés</a>
    </div>
</div>

<?php require __DIR__ . '/partials/style_monthly_stats.php'; ?>

<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
