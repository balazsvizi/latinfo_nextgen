<?php
declare(strict_types=1);

/**
 * Modul elemeinek kattintás-statja a modul szerkesztő alján.
 *
 * @var string $moduleItemStatsTitle
 * @var string $moduleItemStatsIntro
 * @var array{date_from: string, date_to: string, visitor: string, lang?: string, device?: string} $moduleItemStatsParams
 * @var array<string, mixed> $moduleItemStatsData
 * @var string $moduleItemStatsFormAction
 * @var list<array{id: string, label: string, url: string, active: bool}> $moduleItemStatsPresetLinks
 */

$moduleItemStatsTitle = (string) ($moduleItemStatsTitle ?? 'Kattintás-statisztika');
$moduleItemStatsIntro = (string) ($moduleItemStatsIntro ?? '');
$moduleItemStatsParams = $moduleItemStatsParams ?? [
    'date_from' => '',
    'date_to' => '',
    'visitor' => 'human',
];
$moduleItemStatsData = is_array($moduleItemStatsData ?? null) ? $moduleItemStatsData : [];
$moduleItemStatsFormAction = (string) ($moduleItemStatsFormAction ?? '');
$moduleItemStatsPresetLinks = is_array($moduleItemStatsPresetLinks ?? null) ? $moduleItemStatsPresetLinks : [];

$totals = is_array($moduleItemStatsData['totals'] ?? null) ? $moduleItemStatsData['totals'] : [];
$items = is_array($moduleItemStatsData['items'] ?? null) ? $moduleItemStatsData['items'] : [];
$chartPayload = is_array($moduleItemStatsData['chart'] ?? null) ? $moduleItemStatsData['chart'] : ['labels' => [], 'datasets' => []];
$hasChart = ($chartPayload['labels'] ?? []) !== [] && ($chartPayload['datasets'] ?? []) !== [];
$chartJson = json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$selectedVisitor = (string) ($moduleItemStatsParams['visitor'] ?? 'human');
?>
<div class="card events-edit-stats lh-module-item-stats" id="module-item-stats">
    <div class="events-list-head">
        <h2 class="events-list-title"><?= h($moduleItemStatsTitle) ?></h2>
    </div>
    <?php if ($moduleItemStatsIntro !== ''): ?>
        <p class="events-edit-stats__intro"><?= h($moduleItemStatsIntro) ?></p>
    <?php endif; ?>

    <?php if (empty($moduleItemStatsData['table_ready'])): ?>
        <p class="alert alert-warning">A statisztika tábla nem érhető el.</p>
    <?php else: ?>
        <form method="get" action="<?= h($moduleItemStatsFormAction) ?>#module-item-stats" class="events-edit-stats__filters">
            <?php if ($moduleItemStatsPresetLinks !== []): ?>
                <div class="events-edit-stats__presets-row">
                    <span class="events-filter-label">Időszak</span>
                    <?php foreach ($moduleItemStatsPresetLinks as $presetLink): ?>
                        <a
                            class="btn btn-sm <?= !empty($presetLink['active']) ? 'btn-primary' : 'btn-secondary' ?>"
                            href="<?= h((string) $presetLink['url']) ?>#module-item-stats"
                        ><?= h((string) $presetLink['label']) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="events-edit-stats__filter-grid">
                <div class="form-group">
                    <label class="events-filter-label" for="mod_stat_from">Tól</label>
                    <input class="events-filter-input" type="date" name="stat_date_from" id="mod_stat_from" value="<?= h((string) $moduleItemStatsParams['date_from']) ?>">
                </div>
                <div class="form-group">
                    <label class="events-filter-label" for="mod_stat_to">Ig</label>
                    <input class="events-filter-input" type="date" name="stat_date_to" id="mod_stat_to" value="<?= h((string) $moduleItemStatsParams['date_to']) ?>">
                </div>
                <div class="form-group">
                    <label class="events-filter-label" for="mod_visitor">Látogató</label>
                    <select class="events-filter-input" name="visitor" id="mod_visitor">
                        <option value="human"<?= $selectedVisitor === 'human' ? ' selected' : '' ?>>Csak ember</option>
                        <option value="bot"<?= $selectedVisitor === 'bot' ? ' selected' : '' ?>>Csak bot</option>
                        <option value="all"<?= $selectedVisitor === 'all' ? ' selected' : '' ?>>Ember + bot</option>
                    </select>
                </div>
                <div class="form-group events-edit-stats__filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm">Szűrés</button>
                    <a class="btn btn-secondary btn-sm" href="<?= h($moduleItemStatsFormAction) ?>#module-item-stats">Szűrés törlése</a>
                </div>
            </div>
        </form>

        <div class="events-edit-stats__cards">
            <div class="events-edit-stats__card">
                <div class="events-edit-stats__card-label">Emberi kattintás</div>
                <div class="events-edit-stats__card-value"><?= number_format((int) ($totals['clicks_human'] ?? 0), 0, ',', ' ') ?></div>
            </div>
            <div class="events-edit-stats__card">
                <div class="events-edit-stats__card-label">Bot kattintás</div>
                <div class="events-edit-stats__card-value"><?= number_format((int) ($totals['clicks_bot'] ?? 0), 0, ',', ' ') ?></div>
            </div>
            <div class="events-edit-stats__card">
                <div class="events-edit-stats__card-label">Egyedi ember</div>
                <div class="events-edit-stats__card-value"><?= number_format((int) ($totals['unique_human'] ?? 0), 0, ',', ' ') ?></div>
            </div>
            <div class="events-edit-stats__card">
                <div class="events-edit-stats__card-label">Elemek</div>
                <div class="events-edit-stats__card-value"><?= number_format((int) ($totals['items'] ?? 0), 0, ',', ' ') ?></div>
            </div>
        </div>

        <?php if ($hasChart && $chartJson !== false): ?>
            <div class="events-edit-stats__chart-wrap">
                <canvas id="lh-module-item-chart" height="120" aria-label="Napi kattintások"></canvas>
            </div>
            <script>
            (function () {
                var payload = <?= $chartJson ?>;
                function boot() {
                    var canvas = document.getElementById('lh-module-item-chart');
                    if (!canvas || !window.Chart || !payload.labels || !payload.labels.length) return;
                    var datasets = (payload.datasets || []).map(function (ds) {
                        return {
                            label: ds.label || '',
                            data: ds.data || [],
                            borderColor: ds.color || '#3d6b4f',
                            backgroundColor: (ds.color || '#3d6b4f') + '33',
                            tension: 0.25,
                            fill: true
                        };
                    });
                    new Chart(canvas, {
                        type: 'line',
                        data: { labels: payload.labels, datasets: datasets },
                        options: {
                            responsive: true,
                            plugins: { legend: { display: datasets.length > 1 } },
                            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                        }
                    });
                }
                if (window.Chart) {
                    boot();
                    return;
                }
                var s = document.createElement('script');
                s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
                s.onload = boot;
                document.head.appendChild(s);
            })();
            </script>
        <?php endif; ?>

        <div class="lh-item-stats-list-controls" id="lh-item-stats-list-controls"<?= $items === [] ? ' hidden' : '' ?>>
            <div class="events-edit-stats__filter-grid lh-item-stats-list-filters">
                <div class="form-group">
                    <label class="events-filter-label" for="lh_item_filter_search">Keresés</label>
                    <input class="events-filter-input" type="search" id="lh_item_filter_search" placeholder="Elem neve…" autocomplete="off">
                </div>
                <div class="form-group">
                    <label class="events-filter-label" for="lh_item_filter_min_human">Min. ember</label>
                    <input class="events-filter-input" type="number" id="lh_item_filter_min_human" min="0" step="1" placeholder="0">
                </div>
                <div class="form-group events-edit-stats__filter-actions">
                    <button type="button" class="btn btn-secondary btn-sm" id="lh_item_filter_clear">Szűrés törlése</button>
                </div>
            </div>
            <p class="lh-item-stats-list-count" aria-live="polite">
                <strong><span id="lh-item-stats-visible-count"><?= count($items) ?></span></strong>
                / <span id="lh-item-stats-total-count"><?= count($items) ?></span> elem
            </p>
        </div>

        <div class="table-wrap" style="margin-top:1rem">
            <table class="sortable-table" id="lh-module-item-stats-table">
                <thead>
                    <tr>
                        <th scope="col">
                            <button type="button" class="th-sort" data-sort="label" aria-pressed="false">Elem</button>
                        </th>
                        <th scope="col">
                            <button type="button" class="th-sort is-active" data-sort="human" aria-pressed="true">Ember ↓</button>
                        </th>
                        <th scope="col">
                            <button type="button" class="th-sort" data-sort="bot" aria-pressed="false">Bot</button>
                        </th>
                        <th scope="col">
                            <button type="button" class="th-sort" data-sort="unique" aria-pressed="false">Egyedi</button>
                        </th>
                        <th scope="col">
                            <button type="button" class="th-sort" data-sort="total" aria-pressed="false">Összesen</button>
                        </th>
                    </tr>
                </thead>
                <tbody id="lh-module-item-stats-tbody">
                    <?php if ($items === []): ?>
                        <tr id="lh-module-item-stats-empty"><td colspan="5" class="text-muted">Nincs kattintás a kiválasztott időszakban.</td></tr>
                    <?php else: ?>
                        <?php foreach ($items as $row): ?>
                            <?php
                            $itemLabel = (string) ($row['label'] ?? '');
                            $itemKey = (string) ($row['item_key'] ?? '');
                            $searchHaystack = mb_strtolower($itemLabel . ' ' . $itemKey, 'UTF-8');
                            $clicksHuman = (int) ($row['clicks_human'] ?? 0);
                            $clicksBot = (int) ($row['clicks_bot'] ?? 0);
                            $uniqueHuman = (int) ($row['unique_human'] ?? 0);
                            $clicksTotal = (int) ($row['clicks'] ?? 0);
                            ?>
                            <tr
                                data-item-row
                                data-search="<?= h($searchHaystack) ?>"
                                data-human="<?= $clicksHuman ?>"
                                data-bot="<?= $clicksBot ?>"
                                data-unique="<?= $uniqueHuman ?>"
                                data-total="<?= $clicksTotal ?>"
                                data-label="<?= h(mb_strtolower($itemLabel, 'UTF-8')) ?>"
                            >
                                <td><?= h($itemLabel) ?></td>
                                <td><?= number_format($clicksHuman, 0, ',', ' ') ?></td>
                                <td><?= number_format($clicksBot, 0, ',', ' ') ?></td>
                                <td><?= number_format($uniqueHuman, 0, ',', ' ') ?></td>
                                <td><?= number_format($clicksTotal, 0, ',', ' ') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr id="lh-module-item-stats-empty" hidden><td colspan="5" class="text-muted">Nincs találat a szűrésre.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($items !== []): ?>
            <style>
            .lh-item-stats-list-controls{margin-top:1rem}
            .lh-item-stats-list-filters{margin:0}
            .lh-item-stats-list-count{margin:.65rem 0 0;font-size:.8125rem;color:var(--text-muted,#6b7280)}
            </style>
            <script>
            (function () {
                var table = document.getElementById('lh-module-item-stats-table');
                var tbody = document.getElementById('lh-module-item-stats-tbody');
                var controls = document.getElementById('lh-item-stats-list-controls');
                if (!table || !tbody || !controls) return;

                var rows = Array.prototype.slice.call(tbody.querySelectorAll('[data-item-row]'));
                var emptyRow = document.getElementById('lh-module-item-stats-empty');
                var visibleCountEl = document.getElementById('lh-item-stats-visible-count');
                var searchInput = document.getElementById('lh_item_filter_search');
                var minHumanInput = document.getElementById('lh_item_filter_min_human');
                var clearBtn = document.getElementById('lh_item_filter_clear');
                var searchTimer = null;
                var sortKey = 'human';
                var sortDir = 'desc';

                function parseMin(value) {
                    if (value === '' || value == null) return null;
                    var n = parseInt(value, 10);
                    return isNaN(n) ? null : Math.max(0, n);
                }

                function rowMatches(row) {
                    var search = searchInput ? searchInput.value.trim().toLowerCase() : '';
                    if (search !== '' && (row.getAttribute('data-search') || '').indexOf(search) === -1) {
                        return false;
                    }
                    var minHuman = minHumanInput ? parseMin(minHumanInput.value) : null;
                    if (minHuman !== null && parseInt(row.getAttribute('data-human') || '0', 10) < minHuman) {
                        return false;
                    }
                    return true;
                }

                function sortValue(row, key) {
                    if (key === 'label') {
                        return row.getAttribute('data-label') || '';
                    }
                    return parseInt(row.getAttribute('data-' + key) || '0', 10);
                }

                function updateSortHeaders() {
                    table.querySelectorAll('thead .th-sort[data-sort]').forEach(function (btn) {
                        var key = btn.getAttribute('data-sort') || '';
                        var active = key === sortKey;
                        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
                        btn.classList.toggle('is-active', active);
                        var label = (btn.getAttribute('data-label') || btn.textContent || '').replace(/\s*[↑↓]\s*$/, '').trim();
                        btn.setAttribute('data-label', label);
                        btn.textContent = active ? (label + (sortDir === 'asc' ? ' ↑' : ' ↓')) : label;
                    });
                }

                function applySort() {
                    rows.sort(function (a, b) {
                        var va = sortValue(a, sortKey);
                        var vb = sortValue(b, sortKey);
                        var cmp = 0;
                        if (typeof va === 'number' && typeof vb === 'number') {
                            cmp = va - vb;
                        } else {
                            cmp = String(va).localeCompare(String(vb), 'hu', { sensitivity: 'base', numeric: true });
                        }
                        if (cmp === 0) {
                            cmp = (a.getAttribute('data-label') || '').localeCompare(
                                b.getAttribute('data-label') || '',
                                'hu',
                                { sensitivity: 'base' }
                            );
                        }
                        return sortDir === 'asc' ? cmp : -cmp;
                    });
                    rows.forEach(function (row) {
                        tbody.insertBefore(row, emptyRow || null);
                    });
                    updateSortHeaders();
                }

                function applyFilters() {
                    var visible = 0;
                    rows.forEach(function (row) {
                        var show = rowMatches(row);
                        row.hidden = !show;
                        if (show) visible++;
                    });
                    if (visibleCountEl) visibleCountEl.textContent = String(visible);
                    if (emptyRow) emptyRow.hidden = visible > 0;
                }

                function refresh() {
                    applySort();
                    applyFilters();
                }

                table.addEventListener('click', function (e) {
                    var btn = e.target.closest('.th-sort[data-sort]');
                    if (!btn || !table.contains(btn)) return;
                    var key = btn.getAttribute('data-sort') || '';
                    if (key === '') return;
                    if (sortKey === key) {
                        sortDir = sortDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        sortKey = key;
                        sortDir = key === 'label' ? 'asc' : 'desc';
                    }
                    refresh();
                });

                if (searchInput) {
                    searchInput.addEventListener('input', function () {
                        clearTimeout(searchTimer);
                        searchTimer = setTimeout(applyFilters, 120);
                    });
                }
                if (minHumanInput) {
                    minHumanInput.addEventListener('input', applyFilters);
                }
                if (clearBtn) {
                    clearBtn.addEventListener('click', function () {
                        if (searchInput) searchInput.value = '';
                        if (minHumanInput) minHumanInput.value = '';
                        applyFilters();
                        if (searchInput) searchInput.focus();
                    });
                }

                updateSortHeaders();
                applyFilters();
            })();
            </script>
        <?php endif; ?>
    <?php endif; ?>
</div>
