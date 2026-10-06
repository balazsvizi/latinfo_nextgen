<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/event_request.php';
require_once __DIR__ . '/lib/admin_event_filters.php';
require_once __DIR__ . '/lib/admin_event_calendar.php';
require_once __DIR__ . '/lib/public_home_events_map.php';
requireLogin();

$db = getDb();
$filters = events_admin_filters_from_request($db);
$get_params = $filters['get_params'];

$rows = events_admin_fetch_filtered_events_with_venue($db, $filters);

$categoriesByEventId = [];
if ($rows !== []) {
    $eventIds = array_values(array_unique(array_map(static fn (array $r): int => (int) $r['id'], $rows)));
    $ph = implode(',', array_fill(0, count($eventIds), '?'));
    $catStmt = $db->prepare("
        SELECT ec.`event_id`, c.`id`, c.`name`, c.`color`
        FROM `events_calendar_event_categories` ec
        INNER JOIN `events_categories` c ON c.`id` = ec.`category_id`
        WHERE ec.`event_id` IN ({$ph})
        ORDER BY c.`sort_order` ASC, c.`name` ASC, c.`id` ASC
    ");
    $catStmt->execute($eventIds);
    foreach ($catStmt->fetchAll(PDO::FETCH_ASSOC) as $catRow) {
        $eid = (int) $catRow['event_id'];
        if (!isset($categoriesByEventId[$eid])) {
            $categoriesByEventId[$eid] = [];
        }
        $categoriesByEventId[$eid][] = [
            'id' => (int) $catRow['id'],
            'name' => (string) $catRow['name'],
            'color' => trim((string) ($catRow['color'] ?? '')) !== '' ? trim((string) $catRow['color']) : '#6d8f63',
        ];
    }
}

$mapPayload = events_admin_map_payload_from_rows($rows, $categoriesByEventId);

$listViewUrl = events_admin_list_view_url($get_params);
$calendarViewUrl = events_admin_calendar_view_url(events_admin_calendar_view_month_key($filters), $get_params);
$mapViewUrl = events_admin_map_view_url($get_params);
$activeView = 'map';
$filtersActive = events_admin_filters_are_active_excluding_name($filters);
$nameQuickValue = trim((string) ($filters['f_name'] ?? ''));

$filterFormAction = events_url('events_terkep.php');
$filterFormHidden = [];
$filterClearUrl = events_url('events_terkep.php');
$publicPreviewParams = $get_params;
$publicPreviewParams['view'] = 'map';
unset($publicPreviewParams['month']);
$publicHomePreviewUrl = events_public_home_url('hu', $publicPreviewParams);

$adminFloatTools = [
    [
        'href' => events_url('letrehoz.php'),
        'title' => 'Új esemény',
        'aria' => 'Új esemény létrehozása',
        'icon' => 'plus',
    ],
    [
        'href' => $listViewUrl,
        'title' => 'Eseménylista',
        'aria' => 'Átváltás lista nézetre',
        'icon' => 'list',
    ],
    [
        'href' => $publicHomePreviewUrl,
        'title' => 'Nyilvános térkép megtekintése',
        'aria' => 'Nyilvános térkép megtekintése',
        'icon' => 'eye',
    ],
];
$adminFloatToolsRequireLogin = false;

$D = [
    'map_aria' => 'Események térképen',
    'map_host_aria' => 'Interaktív eseménytérkép',
    'map_stat_pins' => 'térképen',
    'map_stat_filtered' => 'szűrt esemény',
    'map_no_coords_hint' => '%d eseménynek nincs helyszín-címe vagy GPS koordinátája.',
    'map_geocode_pending' => '%d esemény cím alapján töltődik a térképre…',
    'map_geocode_server_only' => 'A GPS nélküli helyszínek a szerveroldali geokódolás után jelennek meg.',
    'map_empty' => 'Nincs megjeleníthető esemény a jelenlegi szűréshez.',
];

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Események – térkép';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<?php require __DIR__ . '/partials/admin_float_tools.php'; ?>

<div class="card events-admin-card events-admin-card--map">
    <form method="get" action="<?= h($filterFormAction) ?>" class="events-admin-form events-cal-page" id="events-map-filter-form">
        <div class="events-list-head events-cal-page__head">
            <div class="events-cal-page__head-start">
                <?php require __DIR__ . '/partials/admin_event_view_switch.php'; ?>
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
                <input
                    type="search"
                    class="events-filter-input events-cal-filters-quick-name<?= $nameQuickValue !== '' ? ' is-active' : '' ?>"
                    id="ev-f-name-quick"
                    value="<?= h($nameQuickValue) ?>"
                    placeholder="Keresés a címben…"
                    autocomplete="off"
                    spellcheck="false"
                    aria-label="Esemény neve"
                    <?= $filtersActive ? 'hidden' : '' ?>
                >
                <h2 class="events-list-title">Események</h2>
            </div>
            <div class="events-list-actions">
                <a href="<?= h($filterClearUrl) ?>" class="btn btn-secondary btn-sm">Szűrők törlése</a>
                <a href="<?= h(events_url('letrehoz.php')) ?>" class="btn btn-primary btn-sm">Új esemény</a>
                <a href="<?= h($publicHomePreviewUrl) ?>" class="events-icon-action events-edit-preview-action" title="Nyilvános térkép megtekintése (új lap)" aria-label="Nyilvános térkép megtekintése új lapon" target="_blank" rel="noopener">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" aria-hidden="true"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg>
                </a>
            </div>
        </div>

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

        <div class="events-admin-map home-public">
            <?php require __DIR__ . '/partials/public_home_events_map.php'; ?>
        </div>
    </form>
</div>
<?php require __DIR__ . '/partials/admin_event_filters_script.php'; ?>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
