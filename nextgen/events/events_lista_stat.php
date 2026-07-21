<?php
declare(strict_types=1);

/**
 * Esemény lista – megtekintés / előnézet statisztikák.
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/admin_event_filters.php';
require_once __DIR__ . '/lib/admin_event_calendar.php';
require_once __DIR__ . '/lib/event_view_tracking.php';
requireLogin();

$db = getDb();
events_view_tracking_ensure_bot_column($db);
$filters = events_admin_filters_from_request($db);
$get_params = $filters['get_params'];

$allowedOrder = [
    'id', 'organizer', 'name', 'start', 'status',
    'cal_previews',
    'views', 'views_human', 'views_bot',
];
if (isset($_GET['order']) && in_array((string) $_GET['order'], $allowedOrder, true)) {
    $order = (string) $_GET['order'];
    $dir_param = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'asc' : 'desc';
} else {
    $order = 'views';
    $dir_param = 'desc';
}

$whereSql = $filters['where'] !== [] ? 'WHERE ' . implode(' AND ', $filters['where']) : '';
$params = $filters['params'];
$poolFromSql = events_admin_list_pool_from_sql($filters['list_limit']);

$dirSql = $dir_param === 'asc' ? 'ASC' : 'DESC';
$orderSql = match ($order) {
    'id' => "e.id {$dirSql}",
    'organizer' => "(organizer_name IS NULL OR organizer_name = ''), organizer_name {$dirSql}",
    'name' => "e.event_name {$dirSql}",
    'start' => "e.event_start IS NULL, e.event_start {$dirSql}",
    'status' => "e.event_status {$dirSql}",
    'cal_previews' => "naptar_elonezetek {$dirSql}",
    'views' => "megtekintesek {$dirSql}",
    'views_human' => "megtekintesek_human {$dirSql}",
    'views_bot' => "megtekintesek_bot {$dirSql}",
    default => 'megtekintesek DESC',
};

$countSql = "SELECT COUNT(*) FROM {$poolFromSql} {$whereSql}";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$listDisplayedCount = (int) $countStmt->fetchColumn();
$listLimitValue = $filters['list_limit_value'];
$listTotalInDb = events_admin_table_total_count($db, 'events_calendar_events');

$botColumnReady = events_view_tracking_bot_column_ready($db);
$pageCounts = events_view_metric_count_selects(EVENTS_VIEW_METRIC_PAGE, $botColumnReady);
$previewCounts = events_view_metric_count_selects(EVENTS_VIEW_METRIC_CALENDAR_PREVIEW, $botColumnReady);

$sql = "
    SELECT e.id, e.event_name, e.event_slug, e.event_status, e.event_start, e.event_end, e.event_allday,
        (SELECT GROUP_CONCAT(o.name ORDER BY eo.sort_order ASC, o.name ASC SEPARATOR ', ')
         FROM `events_calendar_event_organizers` eo
         INNER JOIN `events_organizers` o ON o.id = eo.organizer_id
         WHERE eo.event_id = e.id) AS organizer_name,
        {$pageCounts['human']} AS megtekintesek_human,
        {$pageCounts['bot']} AS megtekintesek_bot,
        {$pageCounts['total']} AS megtekintesek,
        {$previewCounts['total']} AS naptar_elonezetek
    FROM {$poolFromSql}
    {$whereSql}
    ORDER BY {$orderSql}
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statsSummary = [
    'events' => count($rows),
    'preview_total' => 0,
    'page_human' => 0,
    'page_bot' => 0,
    'page_total' => 0,
];
foreach ($rows as $summaryRow) {
    $preview = events_view_metric_counts_from_row($summaryRow, 'naptar_elonezetek');
    $page = events_view_metric_counts_from_row($summaryRow, 'megtekintesek');
    $statsSummary['preview_total'] += $preview['total'];
    $statsSummary['page_human'] += $page['human'];
    $statsSummary['page_bot'] += $page['bot'];
    $statsSummary['page_total'] += $page['total'];
}

$editBase = events_url('szerkeszt.php?id=');
$filterFormAction = events_url('events_lista_stat.php');
$filterClearUrl = events_url('events_lista_stat.php');
$listViewUrl = events_admin_list_view_url($get_params);
$filtersActive = events_admin_filters_are_active($filters);
$listLimitDefault = EVENTS_ADMIN_EVENTS_LIST_DEFAULT_LIMIT;
$tagsAvailable = $filters['tagsAvailable'];
$tagOptions = $filters['tagOptions'];
$djsAvailable = $filters['djsAvailable'];
$stylesAvailable = $filters['stylesAvailable'];

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Esemény lista stat';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="card events-admin-card events-stats-page">
    <form method="get" action="<?= h($filterFormAction) ?>" class="events-admin-form events-cal-page" id="events-admin-filter-form">
        <div class="events-list-head events-cal-page__head">
            <div class="events-cal-page__head-start">
                <div class="events-cal-view-switch-row">
                    <?php
                    $listLimitInForm = true;
                    require __DIR__ . '/partials/admin_list_display_limit.php';
                    ?>
                </div>
                <button
                    type="button"
                    class="events-cal-filters-toggle<?= $filtersActive ? ' is-active' : '' ?>"
                    id="events-cal-filters-toggle"
                    aria-expanded="<?= $filtersActive ? 'true' : 'false' ?>"
                    aria-controls="events-cal-filters-panel"
                >
                    <span>Keresés</span>
                    <?php if ($filtersActive): ?>
                        <span class="events-cal-filters-panel__badge">Aktív</span>
                    <?php endif; ?>
                    <span class="events-cal-filters-toggle__chevron" aria-hidden="true">▾</span>
                </button>
                <h2 class="events-list-title">Esemény lista stat</h2>
            </div>
            <div class="events-list-actions">
                <a href="<?= h($filterClearUrl) ?>" class="btn btn-secondary btn-sm">Szűrők törlése</a>
                <a href="<?= h($listViewUrl) ?>" class="btn btn-secondary btn-sm">Események lista</a>
            </div>
        </div>

        <p class="events-stats-page__intro">Naptár előnézet (összes) és oldalmegtekintés emberi / bot / össz bontásban. Alapból oldal össz szerint csökkenő.</p>

        <?php if ($rows !== []): ?>
            <div class="events-stats-summary" aria-label="Összesítés a megjelenített listára">
                <div class="events-stats-summary__card">
                    <p class="events-stats-summary__label">Esemény</p>
                    <p class="events-stats-summary__value"><?= (int) $statsSummary['events'] ?></p>
                    <p class="events-stats-summary__hint">megjelenítve</p>
                </div>
                <div class="events-stats-summary__card events-stats-summary__card--preview">
                    <p class="events-stats-summary__label">Előnézet</p>
                    <p class="events-stats-summary__value"><?= (int) $statsSummary['preview_total'] ?></p>
                    <p class="events-stats-summary__hint">naptár előnézet megnyitás</p>
                </div>
                <div class="events-stats-summary__card events-stats-summary__card--page">
                    <p class="events-stats-summary__label">Oldal</p>
                    <p class="events-stats-summary__value"><?= (int) $statsSummary['page_total'] ?></p>
                    <p class="events-stats-summary__hint">
                        <span class="events-stats-summary__chip events-stats-summary__chip--human"><?= (int) $statsSummary['page_human'] ?> ember</span>
                        <span class="events-stats-summary__chip events-stats-summary__chip--bot"><?= (int) $statsSummary['page_bot'] ?> bot</span>
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <div
            class="events-cal-filters-panel"
            id="events-cal-filters-panel"
            <?= $filtersActive ? '' : 'hidden' ?>
        >
            <div class="events-cal-filters-panel__body">
                <?php if ($filtersActive): ?>
                    <div class="events-cal-filters-panel__toolbar">
                        <a href="<?= h($filterClearUrl) ?>" class="events-cal-filters-panel__clear">Szűrők törlése</a>
                    </div>
                <?php endif; ?>
                <?php require __DIR__ . '/partials/admin_event_filters.php'; ?>
            </div>
        </div>

        <?php require __DIR__ . '/partials/admin_event_stats_list_table.php'; ?>
    </form>
</div>
<?php require __DIR__ . '/partials/admin_event_filters_script.php'; ?>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
