<?php
declare(strict_types=1);

/**
 * Event Admin Statisztikák — partnerportál-szerű grafikon az összes (vagy szervezőre szűrt) eseményre.
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/event_edit_stats.php';
require_once __DIR__ . '/lib/admin_stats.php';
requireLogin();

$db = getDb();
events_view_tracking_ensure_bot_column($db);

$statsParams = events_edit_stats_params_from_request($_GET);
$organizerOptions = events_admin_stats_organizer_options($db);
$validIds = [];
foreach ($organizerOptions as $opt) {
    $validIds[(int) $opt['id']] = (string) ($opt['name'] ?? '');
}
$selectedOrganizerIds = array_values(array_filter(
    events_admin_stats_organizer_ids_from_request(),
    static fn (int $id): bool => isset($validIds[$id])
));

if ($selectedOrganizerIds === []) {
    $statsAllDateFrom = events_edit_stats_earliest_view_date_all($db);
    $statsData = events_edit_stats_for_all_events($db, $statsParams);
    $filterLabel = 'összes eseményre';
} else {
    $statsAllDateFrom = events_edit_stats_earliest_view_date_for_organizers($db, $selectedOrganizerIds);
    $statsData = events_edit_stats_for_organizers($db, $selectedOrganizerIds, $statsParams);
    $names = [];
    foreach ($selectedOrganizerIds as $oid) {
        $names[] = $validIds[$oid] !== '' ? $validIds[$oid] : ('#' . $oid);
    }
    $filterLabel = count($names) === 1
        ? ('szervező: ' . $names[0])
        : (count($names) . ' szervezőre');
}

$statsEventRows = $statsData['event_rows'] ?? [];
$statsPreferPartnerLinks = false;
$statsShowEventRowActions = true;
$statsFilterExtraQuery = $selectedOrganizerIds !== [] ? ['org_id' => $selectedOrganizerIds] : [];
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
$statsIntro = 'Naptár előnézet, további információ kattintás és oldalmegtekintés adatai a választott időszakban ('
    . $filterLabel . ').';
$statsEventListHint = 'A táblázat a periódusban legtöbb oldalmegtekintéssel rendelkező eseményeket mutatja. A teljes lista a Lista stat oldalon van.';
$statsEmptyEventsMessage = 'Nincs megtekintett esemény a választott időszakban.';

$szervezokStatUrl = events_url('events_szervezok_statisztika.php?' . http_build_query(array_filter([
    'stat_date_from' => $statsParams['date_from'],
    'stat_date_to' => $statsParams['date_to'],
    'stat_mode' => $statsParams['mode'] ?? 'smart',
    'stat_custom_rates' => !empty($statsParams['custom_rates']) ? '1' : null,
    'stat_page_ft' => !empty($statsParams['custom_rates']) ? ($statsParams['page_unit_ft'] ?? null) : null,
    'stat_click_ft' => !empty($statsParams['custom_rates']) ? ($statsParams['click_unit_ft'] ?? null) : null,
], static fn ($v): bool => $v !== null && $v !== '')));

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Statisztikák';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="events-stat-page-head">
    <div>
        <h1 class="events-stat-page-title">Statisztikák</h1>
        <p class="events-stat-page-lead">
            <?php if ($selectedOrganizerIds === []): ?>
                Ugyanaz a nézet, mint a partnerportál statisztikáinál, csak az <strong>összes eseményre</strong>.
            <?php else: ?>
                Szűrve: <strong><?= h($filterLabel) ?></strong>
                · <?= h((string) $statsParams['date_from']) ?> – <?= h((string) $statsParams['date_to']) ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="events-stat-page-actions">
        <a href="<?= h($szervezokStatUrl) ?>" class="btn btn-secondary btn-sm">Szervezők stat</a>
        <?php if ($selectedOrganizerIds !== []): ?>
            <a href="<?= h(events_url('events_statisztika.php?' . http_build_query([
                'stat_date_from' => $statsParams['date_from'],
                'stat_date_to' => $statsParams['date_to'],
                'stat_mode' => $statsParams['mode'] ?? 'smart',
            ]))) ?>" class="btn btn-secondary btn-sm">Szűrés törlése</a>
        <?php endif; ?>
        <a href="<?= h(events_url('events_stat.php')) ?>" class="btn btn-secondary btn-sm">Áttekintés</a>
        <a href="<?= h(events_url('events_lista_stat.php')) ?>" class="btn btn-secondary btn-sm">Lista stat</a>
        <a href="<?= h(events_url('events_realtime.php')) ?>" class="btn btn-secondary btn-sm">Valós idejű</a>
    </div>
</div>

<?php require __DIR__ . '/organizers/partials/dashboard_stats.php'; ?>

<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
