<?php
declare(strict_types=1);

/**
 * Szervezők statisztikája — ugyanaz a nézet, mint a Stat oldal, szervezőkre szűrhetően.
 * A lista szervezőnként aggregál a választott időszakra.
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/event_edit_stats.php';
require_once __DIR__ . '/lib/admin_stats.php';
requireLogin();

$db = getDb();
events_view_tracking_ensure_bot_column($db);

$organizerOptions = events_admin_stats_organizer_options($db);
$selectedOrganizerIds = events_admin_stats_organizer_ids_from_request();
$validIds = [];
foreach ($organizerOptions as $opt) {
    $validIds[(int) $opt['id']] = true;
}
$selectedOrganizerIds = array_values(array_filter(
    $selectedOrganizerIds,
    static fn (int $id): bool => isset($validIds[$id])
));

$statsParams = events_edit_stats_params_from_request($_GET);
if ($selectedOrganizerIds === []) {
    $statsAllDateFrom = events_edit_stats_earliest_view_date_all($db);
    $statsData = events_edit_stats_for_all_events($db, $statsParams);
} else {
    $statsAllDateFrom = events_edit_stats_earliest_view_date_for_organizers($db, $selectedOrganizerIds);
    $statsData = events_edit_stats_for_organizers($db, $selectedOrganizerIds, $statsParams);
}
$statsEventRows = [];
$statsPreferPartnerLinks = false;
$statsShowEventRowActions = false;
$statsHideEventsList = true;
$statsFilterExtraQuery = $selectedOrganizerIds !== [] ? ['org_id' => $selectedOrganizerIds] : [];

$organizerStatRows = events_edit_stats_organizers_period_rows($db, $selectedOrganizerIds, $statsParams);

$selectedCount = count($selectedOrganizerIds);
$totalOrgCount = count($organizerOptions);
$pickerSummary = $selectedCount === 0
    ? 'Összes szervező (nincs szűrés)'
    : ($selectedCount . ' / ' . $totalOrgCount . ' szervező');

ob_start();
?>
<details class="events-stats-org-picker"<?= $selectedCount > 0 ? ' open' : '' ?>>
    <summary class="events-stats-org-picker__summary">
        Szervezők: <strong><?= h($pickerSummary) ?></strong>
    </summary>
    <div class="events-stats-org-picker__body">
        <p class="events-stats-org-picker__hint">
            Több szervezőt is bejelölhetsz. Ha egyik sincs kiválasztva, a lista az időszakban forgalommal rendelkező szervezőket mutatja.
        </p>
        <div class="events-stats-org-picker__toolbar">
            <button type="button" class="btn btn-sm btn-secondary" data-org-picker-all>Összes be</button>
            <button type="button" class="btn btn-sm btn-secondary" data-org-picker-none>Összes ki</button>
            <input type="search" class="events-filter-input events-stats-org-picker__search" placeholder="Szervező keresése…" autocomplete="off" data-org-picker-search>
        </div>
        <div class="events-stats-org-picker__list" role="group" aria-label="Szervezők">
            <?php foreach ($organizerOptions as $opt): ?>
                <?php
                $oid = (int) $opt['id'];
                $checked = in_array($oid, $selectedOrganizerIds, true);
                $search = mb_strtolower((string) $opt['name'], 'UTF-8');
                ?>
                <label class="events-stats-org-picker__item" data-org-name="<?= h($search) ?>">
                    <input type="checkbox" name="org_id[]" value="<?= $oid ?>"<?= $checked ? ' checked' : '' ?>>
                    <span><?= h((string) $opt['name']) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
</details>
<script>
(function () {
    var root = document.querySelector('.events-stats-org-picker');
    if (!root) return;
    var items = Array.prototype.slice.call(root.querySelectorAll('.events-stats-org-picker__item'));
    var search = root.querySelector('[data-org-picker-search]');
    var allBtn = root.querySelector('[data-org-picker-all]');
    var noneBtn = root.querySelector('[data-org-picker-none]');

    function setAll(checked) {
        items.forEach(function (item) {
            if (item.hidden) return;
            var cb = item.querySelector('input[type="checkbox"]');
            if (cb) cb.checked = checked;
        });
    }

    if (allBtn) allBtn.addEventListener('click', function () { setAll(true); });
    if (noneBtn) noneBtn.addEventListener('click', function () { setAll(false); });
    if (search) {
        search.addEventListener('input', function () {
            var q = (search.value || '').trim().toLowerCase();
            items.forEach(function (item) {
                var name = item.getAttribute('data-org-name') || '';
                item.hidden = q !== '' && name.indexOf(q) === -1;
            });
        });
    }
})();
</script>
<?php
$statsFormExtraHtml = (string) ob_get_clean();

$statsFormAction = events_url('events_szervezok_statisztika.php');
$statsChartDomId = 'events-admin-organizer-stats-chart';
$statsPageTitle = 'Szervezők statisztika';
$statsIntro = $selectedCount === 0
    ? 'Alapból az összes forgalom. A lista a választott időszakra szervezőnként aggregál.'
    : 'A kiválasztott szervező(k) eseményeire aggregált forgalom a választott időszakban.';
$statsEventListHint = '';
$statsEmptyEventsMessage = '';

$dateFrom = (string) $statsParams['date_from'];
$dateTo = (string) $statsParams['date_to'];
$orgFilterBaseQuery = [
    'stat_date_from' => $dateFrom,
    'stat_date_to' => $dateTo,
];

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Szervezők statisztika';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="events-stat-page-head">
    <div>
        <h1 class="events-stat-page-title">Szervezők statisztika</h1>
        <p class="events-stat-page-lead">
            Grafikon a kiválasztott körre; alatta a szervezők aggregált számai
            <strong><?= h($dateFrom) ?></strong> – <strong><?= h($dateTo) ?></strong> között.
        </p>
    </div>
    <div class="events-stat-page-actions">
        <a href="<?= h(events_url('events_statisztika.php')) ?>" class="btn btn-secondary btn-sm">Összes stat</a>
        <a href="<?= h(events_url('events_lista_stat.php')) ?>" class="btn btn-secondary btn-sm">Lista stat</a>
        <a href="<?= h(events_url('events_realtime.php')) ?>" class="btn btn-secondary btn-sm">Valós idejű</a>
    </div>
</div>

<?php require __DIR__ . '/organizers/partials/dashboard_stats.php'; ?>

<div class="card events-edit-stats events-edit-stats--organizer-list">
    <h3 class="events-edit-stats__events-title">Szervezők a választott időszakban</h3>
    <p class="events-edit-stats__events-hint">
        Oldalmegnyitás, egyedi látogató, előnézet és további info szervezőnként aggregálva.
        A névre kattintva csak az adott szervezőre szűrsz.
    </p>

    <?php if ($organizerStatRows === []): ?>
        <p class="help events-edit-stats__empty">Nincs forgalom a választott időszakban / szervezőknél.</p>
    <?php else: ?>
        <div class="events-org-stats-list-controls" id="organizer-agg-list-controls">
            <div class="form-group" style="max-width:18rem;margin:0 0 0.75rem;">
                <label class="events-filter-label" for="org_agg_filter_search">Keresés</label>
                <input class="events-filter-input" type="search" id="org_agg_filter_search" placeholder="Szervező neve…" autocomplete="off">
            </div>
            <p class="events-org-stats-list-count" aria-live="polite">
                <strong><span id="org-agg-visible-count"><?= count($organizerStatRows) ?></span></strong>
                / <?= count($organizerStatRows) ?> szervező
            </p>
        </div>
        <div class="table-wrap events-admin-table-wrap">
            <table class="sortable-table events-admin-table events-edit-stats__events-table" id="organizer-agg-table">
                <thead>
                    <tr class="events-stats-thead-primary">
                        <th scope="col" rowspan="2">Szervező</th>
                        <th class="th-center" scope="col" rowspan="2" title="Események, amelyekhez volt megtekintés / előnézet / átkattintás az időszakban">Esemény</th>
                        <th class="th-center events-stats-th-group events-stats-th-group--unique" colspan="2" scope="colgroup">Egyedi</th>
                        <th class="th-center events-stats-th-group events-stats-th-group--page" colspan="2" scope="colgroup">Oldalmegnyitás</th>
                        <th class="th-center events-stats-th-group events-stats-th-group--preview" colspan="2" scope="colgroup">Előnézet</th>
                        <th class="th-center events-stats-th-group events-stats-th-group--external" colspan="2" scope="colgroup">Átkatt</th>
                    </tr>
                    <tr class="events-stats-thead-secondary">
                        <th class="th-center events-stats-th-sub events-stats-th-sub--unique events-stats-th-sub--human">Ember</th>
                        <th class="th-center events-stats-th-sub events-stats-th-sub--unique">Bot</th>
                        <th class="th-center events-stats-th-sub events-stats-th-sub--page events-stats-th-sub--human">Ember</th>
                        <th class="th-center events-stats-th-sub events-stats-th-sub--page">Bot</th>
                        <th class="th-center events-stats-th-sub events-stats-th-sub--preview events-stats-th-sub--human">Ember</th>
                        <th class="th-center events-stats-th-sub events-stats-th-sub--preview">Bot</th>
                        <th class="th-center events-stats-th-sub events-stats-th-sub--external events-stats-th-sub--human">Ember</th>
                        <th class="th-center events-stats-th-sub events-stats-th-sub--external">Bot</th>
                    </tr>
                </thead>
                <tbody id="organizer-agg-tbody">
                    <?php foreach ($organizerStatRows as $row): ?>
                        <?php
                        $oid = (int) $row['id'];
                        $filterQuery = array_merge($orgFilterBaseQuery, ['org_id' => [$oid]]);
                        $filterUrl = events_url('events_szervezok_statisztika.php') . '?' . http_build_query($filterQuery);
                        $editUrl = events_url('organizer_szerkeszt.php?id=') . $oid;
                        $searchName = mb_strtolower((string) $row['name'], 'UTF-8');
                        ?>
                        <tr data-org-agg-row data-search="<?= h($searchName) ?>">
                            <td>
                                <div class="events-action-icons" style="display:inline-flex;flex-direction:row;gap:0.25rem;margin-right:0.35rem;vertical-align:middle;">
                                    <a href="<?= h($editUrl) ?>" class="events-icon-action" title="Szervező szerkesztése" aria-label="Szervező szerkesztése">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" aria-hidden="true"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </a>
                                </div>
                                <a href="<?= h($filterUrl) ?>"><?= h((string) $row['name']) ?></a>
                            </td>
                            <td class="text-center"><?= (int) $row['events_with_views'] ?></td>
                            <td class="text-center events-stats-cell--human"><?= (int) $row['egyedi_latogatok_human'] ?></td>
                            <td class="text-center events-stats-cell--bot"><?= (int) $row['egyedi_latogatok_bot'] ?></td>
                            <td class="text-center events-stats-cell--human"><?= (int) $row['megtekintesek_human'] ?></td>
                            <td class="text-center events-stats-cell--bot"><?= (int) $row['megtekintesek_bot'] ?></td>
                            <td class="text-center events-stats-cell--human"><?= (int) $row['naptar_elonezetek_human'] ?></td>
                            <td class="text-center events-stats-cell--bot"><?= (int) $row['naptar_elonezetek_bot'] ?></td>
                            <td class="text-center events-stats-cell--human"><?= (int) $row['tovabbi_info_kattintasok_human'] ?></td>
                            <td class="text-center events-stats-cell--bot"><?= (int) $row['tovabbi_info_kattintasok_bot'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr id="organizer-agg-empty" hidden>
                        <td colspan="10" class="events-org-stats-list-empty">Nincs találat a keresésre.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <script>
        (function () {
            var input = document.getElementById('org_agg_filter_search');
            var tbody = document.getElementById('organizer-agg-tbody');
            var visibleEl = document.getElementById('org-agg-visible-count');
            var emptyRow = document.getElementById('organizer-agg-empty');
            if (!input || !tbody) return;
            var rows = Array.prototype.slice.call(tbody.querySelectorAll('[data-org-agg-row]'));
            var timer = null;
            function apply() {
                var q = (input.value || '').trim().toLowerCase();
                var visible = 0;
                rows.forEach(function (row) {
                    var name = row.getAttribute('data-search') || '';
                    var show = q === '' || name.indexOf(q) !== -1;
                    row.hidden = !show;
                    if (show) visible++;
                });
                if (visibleEl) visibleEl.textContent = String(visible);
                if (emptyRow) emptyRow.hidden = visible > 0;
            }
            input.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(apply, 100);
            });
        })();
        </script>
    <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
