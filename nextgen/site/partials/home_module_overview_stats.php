<?php
declare(strict_types=1);

/**
 * Kezdőoldal modul-kattintás áttekintő.
 *
 * @var array{date_from: string, date_to: string, module: string, visitor: string, lang: string, device: string, surface?: string} $statsParams
 * @var array<string, mixed> $statsData
 * @var string $statsFormAction
 * @var list<array{id: string, label: string, url: string, active: bool}> $statsPresetLinks
 * @var list<array{id: string, label: string, url: string, active: bool}> $surfaceTabs
 */

$statsParams = $statsParams ?? [
    'date_from' => '',
    'date_to' => '',
    'module' => 'all',
    'visitor' => 'human',
    'lang' => 'all',
    'device' => 'all',
    'surface' => 'all',
];
if (!isset($statsParams['surface'])) {
    $statsParams['surface'] = 'all';
}
$statsData = is_array($statsData ?? null) ? $statsData : [];
$statsFormAction = (string) ($statsFormAction ?? '');
$statsPresetLinks = is_array($statsPresetLinks ?? null) ? $statsPresetLinks : [];
$surfaceTabs = is_array($surfaceTabs ?? null) ? $surfaceTabs : [];
$totals = is_array($statsData['totals'] ?? null) ? $statsData['totals'] : [];
$bySurface = is_array($statsData['by_surface'] ?? null) ? $statsData['by_surface'] : [];
$moduleRows = is_array($statsData['modules'] ?? null) ? $statsData['modules'] : [];
$chartPayload = is_array($statsData['chart'] ?? null) ? $statsData['chart'] : ['labels' => [], 'datasets' => []];
$hasChart = ($chartPayload['labels'] ?? []) !== [] && ($chartPayload['datasets'] ?? []) !== [];
$chartJson = json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$catalog = latinfo_home_module_catalog();
$granularity = (string) ($statsData['granularity'] ?? 'day');
$granularityLabel = match ($granularity) {
    'month' => 'havi',
    'week' => 'heti',
    default => 'napi',
};
$activeSurface = (string) ($statsParams['surface'] ?? 'all');
$surfaceIntro = match ($activeSurface) {
    'web' => 'Csak a webes (asztali + mobil böngésző) kezdőoldali kattintások.',
    'app' => 'Csak a telepített mobilapp / PWA kezdőoldali kattintások.',
    default => 'Web és mobilapp kattintások együtt; alább a felületi bontás is látszik.',
};
?>
<style>
.lh-surface-tabs{display:flex;flex-wrap:wrap;gap:.45rem;margin:0 0 1rem}
.lh-surface-tabs .btn{min-width:6.5rem}
</style>
<div class="card events-edit-stats lh-home-module-stats">
    <?php if ($surfaceTabs !== []): ?>
        <nav class="lh-surface-tabs" aria-label="Felület">
            <?php foreach ($surfaceTabs as $tab): ?>
                <a
                    class="btn btn-sm <?= !empty($tab['active']) ? 'btn-primary' : 'btn-secondary' ?>"
                    href="<?= h((string) $tab['url']) ?>"
                    <?= !empty($tab['active']) ? 'aria-current="page"' : '' ?>
                ><?= h((string) $tab['label']) ?></a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <p class="events-edit-stats__intro">
        <?= h($surfaceIntro) ?>
        Admin és partner munkamenetből nem számolunk. A botok külön szűrhetők.
        Időszak: <?= h((string) $statsParams['date_from']) ?> – <?= h((string) $statsParams['date_to']) ?> (<?= h($granularityLabel) ?> bontás).
        A táblában a modulnévre kattintva a modul részletes statisztikája nyílik meg.
    </p>

    <?php if (empty($statsData['table_ready'])): ?>
        <p class="alert alert-warning">A statisztika tábla nem érhető el.</p>
    <?php else: ?>
        <form method="get" action="<?= h($statsFormAction) ?>" class="events-edit-stats__filters">
            <?php if ($activeSurface !== 'all'): ?>
                <input type="hidden" name="surface" value="<?= h($activeSurface) ?>">
            <?php endif; ?>
            <?php if ($statsPresetLinks !== []): ?>
                <div class="events-edit-stats__presets-row">
                    <span class="events-filter-label">Időszak</span>
                    <?php foreach ($statsPresetLinks as $presetLink): ?>
                        <a
                            class="btn btn-sm <?= !empty($presetLink['active']) ? 'btn-primary' : 'btn-secondary' ?>"
                            href="<?= h((string) $presetLink['url']) ?>"
                        ><?= h((string) $presetLink['label']) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="events-edit-stats__filter-grid">
                <div class="form-group">
                    <label class="events-filter-label" for="lh_stat_from">Tól</label>
                    <input class="events-filter-input" type="date" name="stat_date_from" id="lh_stat_from" value="<?= h((string) $statsParams['date_from']) ?>">
                </div>
                <div class="form-group">
                    <label class="events-filter-label" for="lh_stat_to">Ig</label>
                    <input class="events-filter-input" type="date" name="stat_date_to" id="lh_stat_to" value="<?= h((string) $statsParams['date_to']) ?>">
                </div>
                <div class="form-group">
                    <label class="events-filter-label" for="lh_module">Modul</label>
                    <select class="events-filter-input" name="module" id="lh_module">
                        <option value="all"<?= $statsParams['module'] === 'all' ? ' selected' : '' ?>>Összes modul</option>
                        <?php foreach ($catalog as $key => $meta): ?>
                            <option value="<?= h($key) ?>"<?= $statsParams['module'] === $key ? ' selected' : '' ?>><?= h((string) $meta['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="events-filter-label" for="lh_visitor">Látogató</label>
                    <select class="events-filter-input" name="visitor" id="lh_visitor">
                        <option value="human"<?= $statsParams['visitor'] === 'human' ? ' selected' : '' ?>>Csak ember</option>
                        <option value="bot"<?= $statsParams['visitor'] === 'bot' ? ' selected' : '' ?>>Csak bot</option>
                        <option value="all"<?= $statsParams['visitor'] === 'all' ? ' selected' : '' ?>>Ember + bot</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="events-filter-label" for="lh_lang">Nyelv</label>
                    <select class="events-filter-input" name="traf_lang" id="lh_lang">
                        <option value="all"<?= $statsParams['lang'] === 'all' ? ' selected' : '' ?>>Összes</option>
                        <option value="hu"<?= $statsParams['lang'] === 'hu' ? ' selected' : '' ?>>Magyar</option>
                        <option value="en"<?= $statsParams['lang'] === 'en' ? ' selected' : '' ?>>Angol</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="events-filter-label" for="lh_device">Eszköz</label>
                    <select class="events-filter-input" name="device" id="lh_device">
                        <option value="all"<?= $statsParams['device'] === 'all' ? ' selected' : '' ?>>Összes</option>
                        <option value="desktop"<?= $statsParams['device'] === 'desktop' ? ' selected' : '' ?>>Asztali</option>
                        <option value="mobile"<?= $statsParams['device'] === 'mobile' ? ' selected' : '' ?>>Mobil</option>
                        <option value="tablet"<?= $statsParams['device'] === 'tablet' ? ' selected' : '' ?>>Tablet</option>
                        <option value="unknown"<?= $statsParams['device'] === 'unknown' ? ' selected' : '' ?>>Ismeretlen</option>
                    </select>
                </div>
                <div class="form-group events-edit-stats__filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm">Szűrés</button>
                    <a class="btn btn-secondary btn-sm" href="<?= h(
                        $activeSurface === 'all'
                            ? $statsFormAction
                            : events_edit_stats_filter_url($statsFormAction, [
                                'date_from' => $statsParams['date_from'],
                                'date_to' => $statsParams['date_to'],
                            ], ['surface' => $activeSurface])
                    ) ?>">Szűrés törlése</a>
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
                <div class="events-edit-stats__card-label">Érintett modul</div>
                <div class="events-edit-stats__card-value"><?= number_format((int) ($totals['modules_hit'] ?? 0), 0, ',', ' ') ?></div>
            </div>
            <?php if ($activeSurface === 'all'): ?>
                <div class="events-edit-stats__card">
                    <div class="events-edit-stats__card-label">Web (ember)</div>
                    <div class="events-edit-stats__card-value"><?= number_format((int) ($bySurface['web']['clicks_human'] ?? 0), 0, ',', ' ') ?></div>
                </div>
                <div class="events-edit-stats__card">
                    <div class="events-edit-stats__card-label">Mobilapp (ember)</div>
                    <div class="events-edit-stats__card-value"><?= number_format((int) ($bySurface['app']['clicks_human'] ?? 0), 0, ',', ' ') ?></div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($hasChart && $chartJson !== false): ?>
            <div class="events-edit-stats__chart-wrap">
                <canvas id="lh-home-module-chart" height="140" aria-label="Modul kattintások trendje"></canvas>
            </div>
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
            <script>
            (function () {
                var payload = <?= $chartJson ?>;
                var canvas = document.getElementById('lh-home-module-chart');
                if (!canvas || !window.Chart || !payload.labels || !payload.labels.length) return;
                var datasets = (payload.datasets || []).map(function (ds) {
                    return {
                        label: ds.label || '',
                        data: ds.data || [],
                        borderColor: ds.color || '#3d6b4f',
                        backgroundColor: 'transparent',
                        tension: 0.25
                    };
                });
                new Chart(canvas, {
                    type: 'line',
                    data: { labels: payload.labels, datasets: datasets },
                    options: {
                        responsive: true,
                        plugins: { legend: { position: 'bottom' } },
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                    }
                });
            })();
            </script>
        <?php endif; ?>

        <div class="lh-item-stats-list-controls" id="lh-home-module-list-controls"<?= $moduleRows === [] ? ' hidden' : '' ?>>
            <div class="events-edit-stats__filter-grid lh-item-stats-list-filters">
                <div class="form-group">
                    <label class="events-filter-label" for="lh_mod_filter_search">Keresés</label>
                    <input class="events-filter-input" type="search" id="lh_mod_filter_search" placeholder="Modul neve…" autocomplete="off">
                </div>
                <div class="form-group">
                    <label class="events-filter-label" for="lh_mod_filter_min_human">Min. ember</label>
                    <input class="events-filter-input" type="number" id="lh_mod_filter_min_human" min="0" step="1" placeholder="0">
                </div>
                <div class="form-group events-edit-stats__filter-actions">
                    <button type="button" class="btn btn-secondary btn-sm" id="lh_mod_filter_clear">Szűrés törlése</button>
                </div>
            </div>
            <p class="lh-item-stats-list-count" aria-live="polite">
                <strong><span id="lh-home-module-visible-count"><?= count($moduleRows) ?></span></strong>
                / <span id="lh-home-module-total-count"><?= count($moduleRows) ?></span> modul
            </p>
        </div>

        <div class="table-wrap" style="margin-top:1rem">
            <table class="sortable-table" id="lh-home-module-stats-table">
                <thead>
                    <tr>
                        <th scope="col">
                            <button type="button" class="th-sort" data-sort="label" aria-pressed="false">Modul</button>
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
                <tbody id="lh-home-module-stats-tbody">
                    <?php if ($moduleRows === []): ?>
                        <tr id="lh-home-module-stats-empty"><td colspan="5" class="text-muted">Nincs kattintás a kiválasztott időszakban.</td></tr>
                    <?php else: ?>
                        <?php foreach ($moduleRows as $row): ?>
                            <?php
                            $moduleKey = (string) ($row['module_key'] ?? '');
                            $moduleLabel = (string) ($row['label'] ?? '');
                            $detailUrl = $moduleKey !== ''
                                ? latinfo_home_module_item_stats_url($moduleKey, $statsParams)
                                : null;
                            $searchHaystack = mb_strtolower($moduleLabel . ' ' . $moduleKey, 'UTF-8');
                            $clicksHuman = (int) ($row['clicks_human'] ?? 0);
                            $clicksBot = (int) ($row['clicks_bot'] ?? 0);
                            $uniqueHuman = (int) ($row['unique_human'] ?? 0);
                            $clicksTotal = (int) ($row['clicks'] ?? 0);
                            ?>
                            <tr
                                data-module-row
                                data-search="<?= h($searchHaystack) ?>"
                                data-human="<?= $clicksHuman ?>"
                                data-bot="<?= $clicksBot ?>"
                                data-unique="<?= $uniqueHuman ?>"
                                data-total="<?= $clicksTotal ?>"
                                data-label="<?= h(mb_strtolower($moduleLabel, 'UTF-8')) ?>"
                            >
                                <td>
                                    <?php if ($detailUrl !== null): ?>
                                        <a class="events-cell-edit" href="<?= h($detailUrl) ?>" title="Modul statisztikái"><?= h($moduleLabel) ?></a>
                                    <?php else: ?>
                                        <?= h($moduleLabel) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= number_format($clicksHuman, 0, ',', ' ') ?></td>
                                <td><?= number_format($clicksBot, 0, ',', ' ') ?></td>
                                <td><?= number_format($uniqueHuman, 0, ',', ' ') ?></td>
                                <td><?= number_format($clicksTotal, 0, ',', ' ') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr id="lh-home-module-stats-empty" hidden><td colspan="5" class="text-muted">Nincs találat a szűrésre.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($moduleRows !== []): ?>
            <style>
            .lh-item-stats-list-controls{margin-top:1rem}
            .lh-item-stats-list-filters{margin:0}
            .lh-item-stats-list-count{margin:.65rem 0 0;font-size:.8125rem;color:var(--text-muted,#6b7280)}
            </style>
            <script>
            (function () {
                var table = document.getElementById('lh-home-module-stats-table');
                var tbody = document.getElementById('lh-home-module-stats-tbody');
                var controls = document.getElementById('lh-home-module-list-controls');
                if (!table || !tbody || !controls) return;

                var rows = Array.prototype.slice.call(tbody.querySelectorAll('[data-module-row]'));
                var emptyRow = document.getElementById('lh-home-module-stats-empty');
                var visibleCountEl = document.getElementById('lh-home-module-visible-count');
                var searchInput = document.getElementById('lh_mod_filter_search');
                var minHumanInput = document.getElementById('lh_mod_filter_min_human');
                var clearBtn = document.getElementById('lh_mod_filter_clear');
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
