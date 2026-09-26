<?php
declare(strict_types=1);

/**
 * Kezdőoldal modul-kattintás statisztika (összes / web / mobilapp).
 */

require_once dirname(__DIR__) . '/init.php';
requireLogin();
require_once dirname(__DIR__) . '/events/bootstrap.php';
require_once dirname(__DIR__) . '/events/lib/event_edit_stats.php';
require_once __DIR__ . '/lib/site_modules.php';

$db = getDb();
$schemaOk = latinfo_home_modules_ensure_schema($db);
$statsParams = latinfo_home_module_stats_params_from_request($_GET);
$statsData = $schemaOk
    ? latinfo_home_module_overview_stats($db, $statsParams)
    : [
        'table_ready' => false,
        'totals' => [],
        'by_surface' => ['web' => [], 'app' => []],
        'modules' => [],
        'chart' => ['labels' => [], 'datasets' => []],
        'granularity' => 'day',
    ];
$statsFormAction = latinfo_home_modules_stat_url();
$statsAllDateFrom = latinfo_home_module_stats_earliest_date($db);
$statsActivePreset = events_edit_stats_detect_preset($statsParams, $statsAllDateFrom);
$statsFilterExtraQuery = array_filter([
    'module' => $statsParams['module'] !== 'all' ? $statsParams['module'] : null,
    'traf_lang' => $statsParams['lang'] !== 'all' ? $statsParams['lang'] : null,
    'visitor' => $statsParams['visitor'] !== 'human' ? $statsParams['visitor'] : null,
    'device' => $statsParams['device'] !== 'all' ? $statsParams['device'] : null,
    'surface' => $statsParams['surface'] !== 'all' ? $statsParams['surface'] : null,
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

$surfaceTabs = [];
foreach (
    [
        'all' => 'Összes',
        'web' => 'Web',
        'app' => 'Mobilapp',
    ] as $surfaceKey => $surfaceLabel
) {
    $tabExtra = $statsFilterExtraQuery;
    if ($surfaceKey === 'all') {
        unset($tabExtra['surface']);
    } else {
        $tabExtra['surface'] = $surfaceKey;
    }
    $surfaceTabs[] = [
        'id' => $surfaceKey,
        'label' => $surfaceLabel,
        'url' => events_edit_stats_filter_url(
            $statsFormAction,
            [
                'date_from' => $statsParams['date_from'],
                'date_to' => $statsParams['date_to'],
            ],
            $tabExtra
        ),
        'active' => $statsParams['surface'] === $surfaceKey,
    ];
}

$surfaceTitle = match ($statsParams['surface']) {
    'web' => 'Webes kattintások',
    'app' => 'Mobilapp kattintások',
    default => 'Összes felület',
};

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Kezdőoldal stat – ' . $surfaceTitle;
require_once dirname(__DIR__) . '/partials/header.php';
?>
<div class="events-stat-page-head">
    <div>
        <h1 class="events-stat-page-title">Kezdőoldal modulok</h1>
        <p class="events-stat-page-lead">
            <?= h($surfaceTitle) ?> a Latinfo kezdőoldalon.
            <?= h((string) $statsParams['date_from']) ?> – <?= h((string) $statsParams['date_to']) ?>.
        </p>
    </div>
    <div class="events-stat-page-actions">
        <a href="<?= h(latinfo_home_edit_url()) ?>" class="btn btn-secondary btn-sm">Modulok</a>
        <a href="<?= h(latinfo_home_preview_url()) ?>" class="btn btn-secondary btn-sm">Előnézet</a>
        <a href="<?= h(events_url('events_public_stat.php')) ?>" class="btn btn-secondary btn-sm">Publikus oldalak</a>
    </div>
</div>

<?php if (!$schemaOk): ?>
    <p class="alert alert-error">A kezdőoldal táblái nem érhetők el.</p>
<?php else: ?>
    <?php require __DIR__ . '/partials/home_module_overview_stats.php'; ?>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
