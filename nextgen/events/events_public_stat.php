<?php
declare(strict_types=1);

/**
 * Nyilvános oldalak és menükattintások statisztikája.
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/event_edit_stats.php';
require_once __DIR__ . '/lib/public_traffic.php';
requireLogin();

$db = getDb();
$statsParams = events_public_traffic_params_from_request($_GET);
$statsAllDateFrom = events_public_traffic_earliest_date($db);
$statsData = events_public_traffic_stats($db, $statsParams);

$statsFormAction = events_url('events_public_stat.php');
$statsActivePreset = events_edit_stats_detect_preset($statsParams, $statsAllDateFrom);
$statsFilterExtraQuery = array_filter([
    'page' => $statsParams['page'] !== 'all' ? $statsParams['page'] : null,
    'traf_lang' => $statsParams['lang'] !== 'all' ? $statsParams['lang'] : null,
    'visitor' => $statsParams['visitor'] !== 'all' ? $statsParams['visitor'] : null,
    'device' => $statsParams['device'] !== 'all' ? $statsParams['device'] : null,
    'kind' => $statsParams['kind'] !== 'all' ? $statsParams['kind'] : null,
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
$pageTitle = 'Publikus stat';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="events-stat-page-head">
    <div>
        <h1 class="events-stat-page-title">Publikus oldalak</h1>
        <p class="events-stat-page-lead">
            Főoldal, naptár, eseménylista, DJ lista és a többi beégetett nyilvános oldal, plusz a főmenü kattintásai.
            <?= h((string) $statsParams['date_from']) ?> – <?= h((string) $statsParams['date_to']) ?>.
        </p>
    </div>
    <div class="events-stat-page-actions">
        <a href="<?= h(events_url('events_statisztika.php')) ?>" class="btn btn-secondary btn-sm">Esemény stat</a>
        <a href="<?= h(events_url('events_lista_stat.php')) ?>" class="btn btn-secondary btn-sm">Lista stat</a>
        <a href="<?= h(events_url('events_realtime.php')) ?>" class="btn btn-secondary btn-sm">Valós idejű</a>
        <a href="<?= h(events_url('events_stat.php')) ?>" class="btn btn-secondary btn-sm">Áttekintés</a>
    </div>
</div>

<?php require __DIR__ . '/partials/public_traffic_stats.php'; ?>

<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
