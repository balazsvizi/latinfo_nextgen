<?php
declare(strict_types=1);

/**
 * Mobilapp / PWA használat és telepítés statisztika.
 */

require_once dirname(__DIR__) . '/init.php';
requireLogin();
require_once dirname(__DIR__) . '/events/bootstrap.php';
require_once dirname(__DIR__) . '/events/lib/event_edit_stats.php';
require_once __DIR__ . '/lib/mobilapp_stats.php';

$db = getDb();
$statsParams = latinfo_mobilapp_stats_params_from_request($_GET);
$statsData = latinfo_mobilapp_overview_stats($db, $statsParams);
$statsFormAction = latinfo_mobilapp_stat_url();
$statsAllDateFrom = latinfo_mobilapp_stats_earliest_date($db);
$statsActivePreset = events_edit_stats_detect_preset($statsParams, $statsAllDateFrom);
$statsFilterExtraQuery = array_filter([
    'visitor' => $statsParams['visitor'] !== 'human' ? $statsParams['visitor'] : null,
    'device' => $statsParams['device'] !== 'all' ? $statsParams['device'] : null,
], static fn ($v): bool => $v !== null && $v !== '');

$statsPresetLinks = [];
foreach (events_edit_stats_presets() as $preset) {
    $presetId = (string) $preset['id'];
    $statsPresetLinks[] = [
        'id' => $presetId,
        'label' => (string) $preset['label'],
        'url' => events_edit_stats_filter_url(
            $statsFormAction,
            events_edit_stats_range_for_preset($presetId, $statsAllDateFrom),
            $statsFilterExtraQuery
        ),
        'active' => $statsActivePreset === $presetId,
    ];
}

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Mobilapp stat';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<div class="events-stat-page-head">
    <div>
        <h1 class="events-stat-page-title">Mobilapp</h1>
        <p class="events-stat-page-lead">
            Telepítések, a /mobilapp/ oldal, valamint a telepített app kezdőoldali használata.
            <?= h((string) $statsParams['date_from']) ?> – <?= h((string) $statsParams['date_to']) ?>.
        </p>
    </div>
    <div class="events-stat-page-actions">
        <a href="<?= h(site_url('mobilapp/')) ?>" class="btn btn-secondary btn-sm" target="_blank" rel="noopener">Mobilapp oldal</a>
        <a href="<?= h(latinfo_home_modules_stat_url('surface=app')) ?>" class="btn btn-secondary btn-sm">Kezdőoldal (app)</a>
        <a href="<?= h(nextgen_url('config/lanueva.php')) ?>" class="btn btn-secondary btn-sm">Visszajelzések</a>
        <a href="<?= h(events_url('events_public_stat.php')) ?>" class="btn btn-secondary btn-sm">Publikus oldalak</a>
    </div>
</div>

<?php require __DIR__ . '/partials/mobilapp_stats.php'; ?>

<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
