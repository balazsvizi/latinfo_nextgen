<?php
declare(strict_types=1);

/**
 * Szervezők statisztikája — összes szervező időszakos aggregátuma.
 * A lista szervezőnként aggregál; kapcsolókkal azonnal a grafikonra tehető a teljesítmény.
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
$statsEventRows = [];
$statsPreferPartnerLinks = false;
$statsShowEventRowActions = false;
$statsHideEventsList = true;
$statsExposeChartApi = true;
$statsFilterExtraQuery = [];

$organizerStatRows = events_edit_stats_organizers_period_rows($db, [], $statsParams);
$seriesOrgIds = array_values(array_map(
    static fn (array $row): int => (int) $row['id'],
    $organizerStatRows
));
$organizerChartSeries = events_edit_stats_organizers_daily_page_series($db, $seriesOrgIds, $statsParams);

$palette = [
    '#c45c26', '#2f6f8f', '#8b5a9e', '#b8860b', '#2e8b57',
    '#cd5c5c', '#4682b4', '#d2691e', '#6b8e23', '#708090',
    '#bc8f8f', '#5f9ea0', '#a0522d', '#6a5acd', '#20b2aa',
];
$colorByOrg = [];
foreach ($seriesOrgIds as $idx => $oid) {
    $colorByOrg[(string) $oid] = $palette[$idx % count($palette)];
}

$statsFormAction = events_url('events_szervezok_statisztika.php');
$statsChartDomId = 'events-admin-organizer-stats-chart';
$statsPageTitle = 'Szervezők statisztika';
$statsIntro = 'Kapcsold be a szervezőket a listában — azonnal megjelennek a grafikonon. A Szumma az összes szervező összesített vonala.';
$statsEventListHint = '';
$statsEmptyEventsMessage = '';

$dateFrom = (string) $statsParams['date_from'];
$dateTo = (string) $statsParams['date_to'];
[$orgMediaPageUnitFt, $orgMediaClickUnitFt] = events_edit_stats_resolve_media_units($statsParams);
$orgFilterBaseQuery = [
    'stat_date_from' => $dateFrom,
    'stat_date_to' => $dateTo,
    'stat_mode' => (string) ($statsParams['mode'] ?? 'smart'),
];
if (!empty($statsParams['custom_rates'])) {
    $orgFilterBaseQuery['stat_custom_rates'] = '1';
    $orgFilterBaseQuery['stat_page_ft'] = $orgMediaPageUnitFt;
    $orgFilterBaseQuery['stat_click_ft'] = $orgMediaClickUnitFt;
}

$chartOverlayJson = json_encode([
    'chartId' => $statsChartDomId,
    'series' => $organizerChartSeries['organizers'] ?? [],
    'colors' => $colorByOrg,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Szervezők statisztika';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="events-stat-page-head">
    <div>
        <h1 class="events-stat-page-title">Szervezők statisztika</h1>
        <p class="events-stat-page-lead">
            Grafikon összehasonlítás szervezőnként
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
    <div class="events-org-chart-toggles">
        <label class="events-org-chart-toggle events-org-chart-toggle--sum">
            <input type="checkbox" id="org-chart-sum-toggle" checked>
            <span class="events-org-chart-toggle__swatch" style="background:#3d6b4f;" aria-hidden="true"></span>
            <span>Szumma a grafikonon</span>
        </label>
        <span class="events-org-chart-toggles__hint">A listában a kapcsolók azonnal hozzáadják / elveszik a szervező vonalát.</span>
    </div>

    <h3 class="events-edit-stats__events-title">Szervezők a választott időszakban</h3>
    <p class="events-edit-stats__events-hint">
        Oldalmegnyitás aggregátum időszakra. Kapcsoló = megjelenítés a grafikonon (napi oldalmegnyitás).
        A névre kattintva az esemény-stat nyílik meg, ugyanerre az időszakra, csak erre a szervezőre szűrve.
    </p>

    <?php if ($organizerStatRows === []): ?>
        <p class="help events-edit-stats__empty">Nincs forgalom a választott időszakban.</p>
    <?php else: ?>
        <div class="events-org-stats-list-controls" id="organizer-agg-list-controls">
            <div class="events-org-agg-toolbar">
                <div class="form-group" style="max-width:18rem;margin:0;">
                    <label class="events-filter-label" for="org_agg_filter_search">Keresés</label>
                    <input class="events-filter-input" type="search" id="org_agg_filter_search" placeholder="Szervező neve…" autocomplete="off">
                </div>
                <div class="events-org-agg-toolbar__actions">
                    <button type="button" class="btn btn-sm btn-secondary" id="org-chart-all-on">Összes a grafikonra</button>
                    <button type="button" class="btn btn-sm btn-secondary" id="org-chart-all-off">Grafikon ürítése</button>
                </div>
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
                        <th scope="col" rowspan="2" class="events-th-actions"><span class="visually-hidden">Grafikon</span></th>
                        <th scope="col" rowspan="2">Szervező</th>
                        <th class="th-center" scope="col" rowspan="2" title="Események, amelyekhez volt megtekintés / előnézet / átkattintás az időszakban">Esemény</th>
                        <th class="th-center events-stats-th-group events-stats-th-group--unique" colspan="2" scope="colgroup">Egyedi</th>
                        <th class="th-center events-stats-th-group events-stats-th-group--page" colspan="2" scope="colgroup">Oldalmegnyitás</th>
                        <th class="th-center events-stats-th-group events-stats-th-group--preview" colspan="2" scope="colgroup">Előnézet</th>
                        <th class="th-center events-stats-th-group events-stats-th-group--external" colspan="2" scope="colgroup">Átkatt</th>
                        <th class="th-center events-stats-th-group events-stats-th-group--media" colspan="2" scope="colgroup">Médiaérték</th>
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
                        <th
                            class="th-center events-stats-th-sub events-stats-th-sub--media events-stats-th-sub--human"
                            title="Oldalmegnyitás (ember) × <?= (int) $orgMediaPageUnitFt ?> Ft"
                        >Oldal Ft</th>
                        <th
                            class="th-center events-stats-th-sub events-stats-th-sub--media events-stats-th-sub--human"
                            title="További info (ember) × <?= (int) $orgMediaClickUnitFt ?> Ft"
                        >Átkatt Ft</th>
                    </tr>
                </thead>
                <tbody id="organizer-agg-tbody">
                    <?php foreach ($organizerStatRows as $row): ?>
                        <?php
                        $oid = (int) $row['id'];
                        $oidKey = (string) $oid;
                        $color = $colorByOrg[$oidKey] ?? '#666666';
                        $filterQuery = array_merge($orgFilterBaseQuery, ['org_id' => [$oid]]);
                        $filterUrl = events_url('events_statisztika.php') . '?' . http_build_query($filterQuery);
                        $editUrl = events_url('organizer_szerkeszt.php?id=') . $oid;
                        $searchName = mb_strtolower((string) $row['name'], 'UTF-8');
                        $rowMediaValue = events_edit_stats_media_value(
                            (int) $row['megtekintesek_human'],
                            (int) $row['tovabbi_info_kattintasok_human'],
                            !empty($statsParams['custom_rates']) ? $orgMediaPageUnitFt : null,
                            !empty($statsParams['custom_rates']) ? $orgMediaClickUnitFt : null
                        );
                        ?>
                        <tr data-org-agg-row data-search="<?= h($searchName) ?>" data-org-id="<?= $oid ?>">
                            <td class="events-td-actions">
                                <label class="events-org-chart-toggle events-org-chart-toggle--row" title="Mutatás a grafikonon">
                                    <input type="checkbox" data-org-chart-toggle value="<?= $oid ?>">
                                    <span class="events-org-chart-toggle__swatch" style="background:<?= h($color) ?>;" aria-hidden="true"></span>
                                    <span class="visually-hidden">Grafikon</span>
                                </label>
                            </td>
                            <td>
                                <div class="events-action-icons" style="display:inline-flex;flex-direction:row;gap:0.25rem;margin-right:0.35rem;vertical-align:middle;">
                                    <a href="<?= h($editUrl) ?>" class="events-icon-action" title="Szervező szerkesztése" aria-label="Szervező szerkesztése">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" aria-hidden="true"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </a>
                                </div>
                                <a href="<?= h($filterUrl) ?>" title="Esemény-stat erre a szervezőre"><?= h((string) $row['name']) ?></a>
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
                            <td class="text-center events-stats-cell--media" title="<?= h(events_edit_stats_format_media_ft((int) $rowMediaValue['total_ft'])) ?>">
                                <?= h(events_edit_stats_format_media_ft((int) $rowMediaValue['page_value_ft'])) ?>
                            </td>
                            <td class="text-center events-stats-cell--media">
                                <?= h(events_edit_stats_format_media_ft((int) $rowMediaValue['click_value_ft'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr id="organizer-agg-empty" hidden>
                        <td colspan="13" class="events-org-stats-list-empty">Nincs találat a keresésre.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <script type="application/json" id="org-chart-overlay-data"><?= $chartOverlayJson ?></script>
        <script>
        (function () {
            var input = document.getElementById('org_agg_filter_search');
            var tbody = document.getElementById('organizer-agg-tbody');
            var visibleEl = document.getElementById('org-agg-visible-count');
            var emptyRow = document.getElementById('organizer-agg-empty');
            var sumToggle = document.getElementById('org-chart-sum-toggle');
            var allOnBtn = document.getElementById('org-chart-all-on');
            var allOffBtn = document.getElementById('org-chart-all-off');
            var dataEl = document.getElementById('org-chart-overlay-data');
            if (!tbody || !dataEl) return;

            var cfg;
            try { cfg = JSON.parse(dataEl.textContent || '{}'); } catch (e) { return; }
            var chartId = cfg.chartId || '';
            var series = cfg.series || {};
            var colors = cfg.colors || {};
            var rows = Array.prototype.slice.call(tbody.querySelectorAll('[data-org-agg-row]'));
            var orgToggles = Array.prototype.slice.call(tbody.querySelectorAll('[data-org-chart-toggle]'));
            var timer = null;

            function findPageDataset(list) {
                var arr = list || [];
                for (var i = 0; i < arr.length; i++) {
                    var label = String(arr[i].label || '');
                    if (label === 'Oldal' || label.indexOf('Oldal') === 0) return arr[i];
                }
                return arr[0] || null;
            }

            function activeOrgIds() {
                return orgToggles.filter(function (cb) { return cb.checked; }).map(function (cb) {
                    return String(cb.value);
                });
            }

            function applyOverlay() {
                var api = window.EventsStatsCharts && window.EventsStatsCharts[chartId];
                if (!api || typeof api.setOverlayBuilder !== 'function') return;
                api.setOverlayBuilder(function (mode, helpers) {
                    var active = activeOrgIds();
                    var sumOn = !!(sumToggle && sumToggle.checked);
                    var mapDatasets = helpers.mapDatasets;
                    var pointRadius = helpers.pointRadius;
                    var base = helpers.baseDatasets || [];

                    if (active.length === 0) {
                        if (!sumOn) {
                            return [];
                        }
                        return null; // eredeti teljes szumma (Oldal + Előnézet + További)
                    }

                    var out = [];
                    if (sumOn) {
                        var page = findPageDataset(base);
                        if (page) {
                            out.push({
                                label: 'Szumma',
                                data: page.data,
                                color: page.borderColor || '#3d6b4f',
                                borderWidth: 3
                            });
                        }
                    }
                    active.forEach(function (id) {
                        var s = series[id];
                        if (!s) return;
                        out.push({
                            label: s.name || ('#' + id),
                            data: mode === 'total' ? (s.total || []) : (s.human || []),
                            color: colors[id] || '#666666',
                            borderWidth: 2
                        });
                    });
                    return mapDatasets(out, pointRadius);
                });
            }

            function bindChart() {
                if (window.EventsStatsCharts && window.EventsStatsCharts[chartId]) {
                    applyOverlay();
                    return true;
                }
                return false;
            }

            if (!bindChart()) {
                document.addEventListener('events-stats-chart-ready', function (ev) {
                    if (ev.detail && ev.detail.chartId === chartId) {
                        applyOverlay();
                    }
                });
            }

            orgToggles.forEach(function (cb) {
                cb.addEventListener('change', applyOverlay);
            });
            if (sumToggle) sumToggle.addEventListener('change', applyOverlay);
            if (allOnBtn) {
                allOnBtn.addEventListener('click', function () {
                    orgToggles.forEach(function (cb) {
                        var row = cb.closest('[data-org-agg-row]');
                        if (row && row.hidden) return;
                        cb.checked = true;
                    });
                    applyOverlay();
                });
            }
            if (allOffBtn) {
                allOffBtn.addEventListener('click', function () {
                    orgToggles.forEach(function (cb) { cb.checked = false; });
                    applyOverlay();
                });
            }

            function applySearch() {
                if (!input) return;
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
            if (input) {
                input.addEventListener('input', function () {
                    clearTimeout(timer);
                    timer = setTimeout(applySearch, 100);
                });
            }
        })();
        </script>
    <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
