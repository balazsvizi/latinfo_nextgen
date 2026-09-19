<?php
declare(strict_types=1);

/**
 * DJ ajánló modul – infó + kattintás-stat.
 */

require_once dirname(__DIR__) . '/init.php';
requireLogin();
require_once dirname(__DIR__) . '/events/bootstrap.php';
require_once dirname(__DIR__) . '/events/lib/event_edit_stats.php';
require_once __DIR__ . '/lib/site_modules.php';

$db = getDb();
$schemaOk = latinfo_home_modules_ensure_schema($db);
$formAction = latinfo_home_module_edit_url('dj_spotlight');

$moduleItemStatsParams = latinfo_home_module_stats_params_from_request($_GET);
$moduleItemStatsParams['module'] = 'dj_spotlight';
$moduleItemStatsData = $schemaOk
    ? latinfo_home_module_item_stats($db, 'dj_spotlight', $moduleItemStatsParams)
    : ['table_ready' => false, 'totals' => [], 'items' => [], 'chart' => ['labels' => [], 'datasets' => []]];
$moduleItemStatsFormAction = $formAction;
$statsAllDateFrom = latinfo_home_module_stats_earliest_date($db);
$statsActivePreset = events_edit_stats_detect_preset($moduleItemStatsParams, $statsAllDateFrom);
$moduleItemStatsPresetLinks = [];
foreach (events_edit_stats_presets() as $preset) {
    $presetId = (string) $preset['id'];
    $moduleItemStatsPresetLinks[] = [
        'id' => $presetId,
        'label' => (string) $preset['label'],
        'url' => events_edit_stats_filter_url(
            $formAction,
            events_edit_stats_range_for_preset($presetId, $statsAllDateFrom),
            ['visitor' => $moduleItemStatsParams['visitor'] !== 'human' ? $moduleItemStatsParams['visitor'] : null]
        ),
        'active' => $statsActivePreset === $presetId,
    ];
}
$moduleItemStatsTitle = 'DJ ajánló kattintások';
$moduleItemStatsIntro = 'A kezdőoldali DJ ajánló kártyáira és az „Összes DJ” linkre kattintások.';

$pageTitle = 'DJ ajánló modul';
$mainContentClass = 'main-content main-content--fullwidth';

require_once dirname(__DIR__) . '/partials/header.php';
?>
<div class="card lh-admin">
    <div class="events-list-head">
        <h2 class="events-list-title">DJ ajánló</h2>
        <div class="events-list-actions">
            <a href="<?= h(latinfo_home_preview_url()) ?>" class="btn btn-secondary btn-sm">Előnézet</a>
            <a href="<?= h(latinfo_home_edit_url()) ?>" class="btn btn-secondary btn-sm">Modulok</a>
            <a href="<?= h(nextgen_url('events/djs_admin.php')) ?>" class="btn btn-secondary btn-sm">DJ katalógus</a>
        </div>
    </div>
    <p class="text-muted" style="margin-top:0">
        A kezdőoldali DJ ajánló a közzétett DJ katalógusból választ véletlenszerűen.
        A megjelenő kártyák tartalmát a DJ szerkesztőben tudod módosítani; itt a kattintások látszanak.
    </p>
    <?php if (!$schemaOk): ?>
        <p class="alert alert-error">A kezdőoldal táblái nem érhetők el.</p>
    <?php endif; ?>
</div>

<?php if ($schemaOk): ?>
    <?php require __DIR__ . '/partials/module_item_stats.php'; ?>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
