<?php
declare(strict_types=1);

/**
 * Esemény előtti 30 nap oldalbetöltés-statisztika.
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/event_pre_event_stats.php';
requireLogin();

$db = getDb();
$statsParams = events_pre_event_stats_params_from_request($_GET);
$statsData = events_pre_event_stats($db, $statsParams);
$statsFormAction = events_url('events_elotti_stat.php');

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = '30 nap';
require_once dirname(__DIR__) . '/partials/header.php';

$visitorLabel = match ($statsParams['visitor']) {
    'bot' => 'csak robot',
    'all' => 'ember + robot',
    default => 'csak ember',
};
?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="events-stat-page-head">
    <div>
        <h1 class="events-stat-page-title">30 nap</h1>
        <p class="events-stat-page-lead">
            Az esemény napját megelőző 30 naptári nap oldalbetöltései eseményenként,
            és a relatív napok százalékos eloszlása (<?= h($visitorLabel) ?>).
        </p>
    </div>
    <div class="events-stat-page-actions">
        <a href="<?= h(events_url('events_havi_stat.php')) ?>" class="btn btn-secondary btn-sm">Havi stat</a>
        <a href="<?= h(events_url('events_evi_stat.php')) ?>" class="btn btn-secondary btn-sm">Év/év stat</a>
        <a href="<?= h(events_url('events_statisztika.php')) ?>" class="btn btn-secondary btn-sm">Esemény stat</a>
        <a href="<?= h(events_url('events_public_stat.php')) ?>" class="btn btn-secondary btn-sm">Publikus oldalak</a>
        <a href="<?= h(events_url('events_stat.php')) ?>" class="btn btn-secondary btn-sm">Áttekintés</a>
    </div>
</div>

<?php require __DIR__ . '/partials/pre_event_stats.php'; ?>

<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
