<?php
declare(strict_types=1);

/**
 * Event Admin Statisztikák — partnerportál-szerű grafikon az összes eseményre.
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/event_edit_stats.php';
requireLogin();

$db = getDb();
events_view_tracking_ensure_bot_column($db);

$statsParams = events_edit_stats_params_from_request($_GET);
$statsAllDateFrom = events_edit_stats_earliest_view_date_all($db);
$statsData = events_edit_stats_for_all_events($db, $statsParams);
$statsEventRows = $statsData['event_rows'] ?? [];
$statsPreferPartnerLinks = false;
$statsShowEventRowActions = true;
$statsEventDetailUrl = static function (array $row): ?string {
    $id = (int) ($row['id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    return events_url('szerkeszt.php?id=') . $id;
};

$statsFormAction = events_url('events_statisztika.php');
$statsChartDomId = 'events-admin-stats-chart';
$statsPageTitle = 'Statisztika';
$statsIntro = 'Az összes esemény naptár előnézet, további információ kattintás és oldalmegtekintés adatai a választott időszakban.';
$statsEventListHint = 'A táblázat a periódusban legtöbb oldalmegtekintéssel rendelkező eseményeket mutatja. A teljes lista a Lista stat oldalon van.';
$statsEmptyEventsMessage = 'Nincs megtekintett esemény a választott időszakban.';

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Statisztikák';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="events-stat-page-head">
    <div>
        <h1 class="events-stat-page-title">Statisztikák</h1>
        <p class="events-stat-page-lead">
            Ugyanaz a nézet, mint a partnerportál statisztikáinál, csak az <strong>összes eseményre</strong>.
        </p>
    </div>
    <div class="events-stat-page-actions">
        <a href="<?= h(events_url('events_szervezok_statisztika.php')) ?>" class="btn btn-secondary btn-sm">Szervezők stat</a>
        <a href="<?= h(events_url('events_stat.php')) ?>" class="btn btn-secondary btn-sm">Áttekintés</a>
        <a href="<?= h(events_url('events_lista_stat.php')) ?>" class="btn btn-secondary btn-sm">Lista stat</a>
        <a href="<?= h(events_url('events_realtime.php')) ?>" class="btn btn-secondary btn-sm">Valós idejű</a>
    </div>
</div>

<?php require __DIR__ . '/organizers/partials/dashboard_stats.php'; ?>

<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
