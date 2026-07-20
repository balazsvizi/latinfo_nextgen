<?php
declare(strict_types=1);
/** @var array{date_from: string, date_to: string} $statsParams */
/** @var array<string, mixed> $statsData */
/** @var list<array<string, mixed>> $statsEventRows */
/** @var string $statsFormAction */
/** @var string $statsChartDomId */

$chartPayload = $statsData['chart'] ?? ['labels' => [], 'datasets' => []];
$hasChart = ($chartPayload['labels'] ?? []) !== [] && ($chartPayload['datasets'] ?? []) !== [];
$chartJson = json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$eventsTotal = (int) ($statsData['totals']['events_total'] ?? count($statsEventRows));
$eventsWithViews = (int) ($statsData['totals']['events_with_views'] ?? 0);
$publishedStatus = events_public_post_status();

$statusOptions = [];
foreach ($statsEventRows as $row) {
    $st = (string) ($row['event_status'] ?? '');
    if ($st !== '') {
        $statusOptions[$st] = events_post_status_label($st);
    }
}
asort($statusOptions);

/**
 * @param array<string, mixed> $row
 */
$eventPublicUrl = static function (array $row) use ($publishedStatus): ?string {
    $st = (string) ($row['event_status'] ?? '');
    $slug = trim((string) ($row['event_slug'] ?? ''));
    if ($st !== $publishedStatus || $slug === '') {
        return null;
    }

    return events_public_canonical_url($slug);
};

/** @var (callable(array<string,mixed>): ?string)|null $statsEventDetailUrl */
$statsEventDetailUrl = $statsEventDetailUrl ?? null;
$statsPreferPartnerLinks = !empty($statsPreferPartnerLinks);

$eventDateYmd = static function (array $row, string $key): string {
    $raw = trim((string) ($row[$key] ?? ''));
    if ($raw === '') {
        return '';
    }
    try {
        return (new DateTimeImmutable($raw))->format('Y-m-d');
    } catch (Throwable) {
        return '';
    }
};
?>
<div class="card events-edit-stats events-edit-stats--organizer">
    <h2 class="card-title">Statisztika</h2>
    <p class="events-edit-stats__intro">Az eseményeid naptár előnézet és oldalmegtekintés adatai a választott időszakban.</p>

    <form method="get" action="<?= h($statsFormAction) ?>" class="events-edit-stats__filters">
        <div class="events-edit-stats__filter-grid">
            <div class="form-group">
                <label class="events-filter-label" for="stat_date_from">Időszak tól</label>
                <input class="events-filter-input" type="date" name="stat_date_from" id="stat_date_from" value="<?= h($statsParams['date_from']) ?>">
            </div>
            <div class="form-group">
                <label class="events-filter-label" for="stat_date_to">Időszak ig</label>
                <input class="events-filter-input" type="date" name="stat_date_to" id="stat_date_to" value="<?= h($statsParams['date_to']) ?>">
            </div>
            <div class="form-group events-edit-stats__filter-actions">
                <button type="submit" class="btn btn-secondary btn-sm">Megjelenítés</button>
                <a class="btn btn-secondary btn-sm" href="<?= h($statsFormAction) ?>">Utolsó 30 nap</a>
            </div>
        </div>
    </form>

    <div class="events-edit-stats__cards">
        <div class="events-edit-stats__card">
            <p class="events-edit-stats__card-label">Események</p>
            <p class="events-edit-stats__card-value"><?= $eventsWithViews ?> / <?= $eventsTotal ?></p>
            <p class="events-edit-stats__card-hint">Megtekintett / összes</p>
        </div>
        <?php foreach ($chartPayload['datasets'] as $dataset): ?>
            <div class="events-edit-stats__card">
                <p class="events-edit-stats__card-label"><?= h((string) ($dataset['label'] ?? '')) ?></p>
                <p class="events-edit-stats__card-value"><?= (int) ($dataset['total'] ?? 0) ?></p>
                <p class="events-edit-stats__card-hint">Összesen az időszakban</p>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($hasChart): ?>
        <div class="events-edit-stats__chart-wrap">
            <h3 class="events-edit-stats__chart-title">Megtekintések alakulása</h3>
            <p class="events-edit-stats__chart-hint">Napi bontás — emberi és bot forgalom külön, az eseményeidre.</p>
            <div class="events-edit-stats__chart-canvas">
                <canvas id="<?= h($statsChartDomId) ?>" aria-label="Megtekintések grafikonja"></canvas>
            </div>
        </div>
        <script type="application/json" id="<?= h($statsChartDomId) ?>-data"><?= $chartJson ?></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js" crossorigin="anonymous"></script>
        <script>
        (function () {
            var dataEl = document.getElementById(<?= json_encode($statsChartDomId . '-data', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
            var canvas = document.getElementById(<?= json_encode($statsChartDomId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
            if (!dataEl || !canvas || typeof Chart === 'undefined') return;
            var payload;
            try { payload = JSON.parse(dataEl.textContent || '{}'); } catch (e) { return; }
            var labels = payload.labels || [];
            var datasets = (payload.datasets || []).map(function (ds) {
                return {
                    label: ds.label,
                    data: ds.data,
                    borderColor: ds.color,
                    backgroundColor: (ds.color || '#3d6b4f') + '22',
                    borderWidth: 2,
                    tension: 0.25,
                    pointRadius: labels.length > 45 ? 0 : 3,
                    pointHoverRadius: 5,
                    fill: false
                };
            });
            new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: { labels: labels, datasets: datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { boxWidth: 12, padding: 14, font: { size: 11 } }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    var v = ctx.parsed.y;
                                    if (v == null) return ctx.dataset.label;
                                    return ctx.dataset.label + ': ' + v;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: { maxRotation: 45, minRotation: 0, autoSkip: true, maxTicksLimit: 20 }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 }
                        }
                    }
                }
            });
        })();
        </script>
    <?php else: ?>
        <p class="help events-edit-stats__empty">Nincs naplózott megtekintés a választott időszakban.</p>
    <?php endif; ?>

    <h3 class="events-edit-stats__events-title">Események</h3>
    <p class="events-edit-stats__events-hint"><?= $statsPreferPartnerLinks
        ? 'Kattints az eseményre a partner részletekhez. A nyilvános oldal a részletek oldalon érhető el.'
        : 'Alapból az időszakban megtekintéssel rendelkező események. A közzétett eseményekre kattintva a nyilvános oldal nyílik meg.' ?></p>

    <?php if ($statsEventRows === []): ?>
        <p class="help events-edit-stats__empty">Nincs közzétett eseményed.</p>
    <?php else: ?>
        <div class="events-org-stats-list-controls" id="organizer-stats-list-controls">
            <div class="events-org-stats-list-controls__row">
                <fieldset class="events-org-stats-fieldset">
                    <legend>Megjelenítés</legend>
                    <label class="events-org-stats-radio">
                        <input type="radio" name="org_stats_scope" value="chart" checked>
                        Grafikon eseményei
                    </label>
                    <label class="events-org-stats-radio">
                        <input type="radio" name="org_stats_scope" value="all">
                        Összes esemény
                    </label>
                </fieldset>
                <fieldset class="events-org-stats-fieldset">
                    <legend>Szűrés</legend>
                    <label class="events-org-stats-radio">
                        <input type="radio" name="org_stats_filter_mode" value="mind" checked>
                        Mind
                    </label>
                    <label class="events-org-stats-radio">
                        <input type="radio" name="org_stats_filter_mode" value="filtered">
                        Szűrt
                    </label>
                </fieldset>
            </div>
            <div class="events-org-stats-list-filters" id="organizer-stats-list-filters" hidden>
                <div class="events-org-stats-list-filters__grid">
                    <div class="form-group">
                        <label class="events-filter-label" for="org_stats_filter_search">Név</label>
                        <input class="events-filter-input" type="search" id="org_stats_filter_search" placeholder="Keresés…" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label class="events-filter-label" for="org_stats_filter_status">Státusz</label>
                        <select class="events-filter-input" id="org_stats_filter_status">
                            <option value="">Bármely</option>
                            <?php foreach ($statusOptions as $statusValue => $statusLabel): ?>
                                <option value="<?= h($statusValue) ?>"><?= h($statusLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="events-filter-label" for="org_stats_filter_min_page">Min. oldal</label>
                        <input class="events-filter-input" type="number" id="org_stats_filter_min_page" min="0" step="1" placeholder="0">
                    </div>
                    <div class="form-group">
                        <label class="events-filter-label" for="org_stats_filter_min_preview">Min. előnézet</label>
                        <input class="events-filter-input" type="number" id="org_stats_filter_min_preview" min="0" step="1" placeholder="0">
                    </div>
                    <div class="form-group">
                        <label class="events-filter-label" for="org_stats_filter_event_from">Esemény tól</label>
                        <input class="events-filter-input" type="date" id="org_stats_filter_event_from">
                    </div>
                    <div class="form-group">
                        <label class="events-filter-label" for="org_stats_filter_event_to">Esemény ig</label>
                        <input class="events-filter-input" type="date" id="org_stats_filter_event_to">
                    </div>
                </div>
            </div>
            <p class="events-org-stats-list-count" aria-live="polite">
                <strong><span id="organizer-stats-visible-count">0</span></strong>
                / <span id="organizer-stats-total-count"><?= count($statsEventRows) ?></span> esemény
            </p>
        </div>

        <div class="table-wrap events-admin-table-wrap">
            <table class="sortable-table events-admin-table events-edit-stats__events-table">
                <thead>
                    <tr>
                        <th>Dátum</th>
                        <th>Név</th>
                        <th>Státusz</th>
                        <th class="th-center" title="Előnézet — emberi">Előn. ember</th>
                        <th class="th-center" title="Előnézet — bot">Előn. bot</th>
                        <th class="th-center" title="Előnézet — összesen">Előn. össz</th>
                        <th class="th-center" title="Oldal — emberi">Oldal ember</th>
                        <th class="th-center" title="Oldal — bot">Oldal bot</th>
                        <th class="th-center" title="Oldal — összesen">Oldal össz</th>
                    </tr>
                </thead>
                <tbody id="organizer-stats-events-tbody">
                    <?php foreach ($statsEventRows as $row): ?>
                        <?php
                        $st = (string) ($row['event_status'] ?? '');
                        $badgeClass = events_post_status_badge_class($st);
                        $pageCounts = function_exists('events_view_metric_counts_from_row')
                            ? events_view_metric_counts_from_row($row, 'megtekintesek')
                            : ['human' => (int) ($row['megtekintesek'] ?? 0), 'bot' => 0, 'total' => (int) ($row['megtekintesek'] ?? 0)];
                        $previewCounts = function_exists('events_view_metric_counts_from_row')
                            ? events_view_metric_counts_from_row($row, 'naptar_elonezetek')
                            : ['human' => (int) ($row['naptar_elonezetek'] ?? 0), 'bot' => 0, 'total' => (int) ($row['naptar_elonezetek'] ?? 0)];
                        $pageViews = (int) $pageCounts['total'];
                        $previewViews = (int) $previewCounts['total'];
                        $hasViews = ($pageViews + $previewViews) > 0 ? '1' : '0';
                        $eventStart = $eventDateYmd($row, 'event_start');
                        $eventEnd = $eventDateYmd($row, 'event_end');
                        if ($eventEnd === '' && $eventStart !== '') {
                            $eventEnd = $eventStart;
                        }
                        $searchName = mb_strtolower((string) ($row['event_name'] ?? ''), 'UTF-8');
                        $publicUrl = $eventPublicUrl($row);
                        $detailUrl = is_callable($statsEventDetailUrl) ? $statsEventDetailUrl($row) : null;
                        $primaryUrl = $statsPreferPartnerLinks
                            ? ($detailUrl ?? $publicUrl)
                            : ($publicUrl ?? $detailUrl);
                        $eventName = (string) ($row['event_name'] ?? '');
                        ?>
                        <tr
                            data-org-event-row
                            data-has-views="<?= $hasViews ?>"
                            data-status="<?= h($st) ?>"
                            data-search="<?= h($searchName) ?>"
                            data-event-start="<?= h($eventStart) ?>"
                            data-event-end="<?= h($eventEnd) ?>"
                            data-page-views="<?= $pageViews ?>"
                            data-preview-views="<?= $previewViews ?>"
                        >
                            <td><?= h(events_admin_format_datum_cell($row)) ?></td>
                            <td>
                                <?php if ($primaryUrl !== null): ?>
                                    <a href="<?= h($primaryUrl) ?>"<?= (!$statsPreferPartnerLinks && $publicUrl !== null) ? ' target="_blank" rel="noopener"' : '' ?>><?= h($eventName) ?></a>
                                <?php else: ?>
                                    <?= h($eventName) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="event-status-badge <?= h($badgeClass) ?>"><?= h(events_post_status_label($st)) ?></span>
                            </td>
                            <td class="text-center"><?= (int) $previewCounts['human'] ?></td>
                            <td class="text-center"><?= (int) $previewCounts['bot'] ?></td>
                            <td class="text-center"><?= (int) $previewCounts['total'] ?></td>
                            <td class="text-center"><?= (int) $pageCounts['human'] ?></td>
                            <td class="text-center"><?= (int) $pageCounts['bot'] ?></td>
                            <td class="text-center"><?= (int) $pageCounts['total'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr id="organizer-stats-events-empty" hidden>
                        <td colspan="9" class="events-org-stats-list-empty">Nincs találat a szűrőkre.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <script>
        (function () {
            var controls = document.getElementById('organizer-stats-list-controls');
            var tbody = document.getElementById('organizer-stats-events-tbody');
            if (!controls || !tbody) return;

            var rows = Array.prototype.slice.call(tbody.querySelectorAll('[data-org-event-row]'));
            var emptyRow = document.getElementById('organizer-stats-events-empty');
            var visibleCountEl = document.getElementById('organizer-stats-visible-count');
            var filtersPanel = document.getElementById('organizer-stats-list-filters');
            var searchInput = document.getElementById('org_stats_filter_search');
            var statusSelect = document.getElementById('org_stats_filter_status');
            var minPageInput = document.getElementById('org_stats_filter_min_page');
            var minPreviewInput = document.getElementById('org_stats_filter_min_preview');
            var eventFromInput = document.getElementById('org_stats_filter_event_from');
            var eventToInput = document.getElementById('org_stats_filter_event_to');
            var searchTimer = null;

            function getScope() {
                var checked = controls.querySelector('input[name="org_stats_scope"]:checked');
                return checked ? checked.value : 'chart';
            }

            function getFilterMode() {
                var checked = controls.querySelector('input[name="org_stats_filter_mode"]:checked');
                return checked ? checked.value : 'mind';
            }

            function parseMin(value) {
                if (value === '' || value == null) return null;
                var n = parseInt(value, 10);
                return isNaN(n) ? null : Math.max(0, n);
            }

            function eventOverlapsFilter(row, fromYmd, toYmd) {
                var start = row.getAttribute('data-event-start') || '';
                var end = row.getAttribute('data-event-end') || start;
                if (fromYmd && end !== '' && end < fromYmd) return false;
                if (toYmd && start !== '' && start > toYmd) return false;
                if ((fromYmd || toYmd) && start === '' && end === '') return false;
                return true;
            }

            function rowMatches(row) {
                if (getScope() === 'chart' && row.getAttribute('data-has-views') !== '1') {
                    return false;
                }
                if (getFilterMode() !== 'filtered') {
                    return true;
                }

                var search = searchInput ? searchInput.value.trim().toLowerCase() : '';
                if (search !== '' && (row.getAttribute('data-search') || '').indexOf(search) === -1) {
                    return false;
                }

                var status = statusSelect ? statusSelect.value : '';
                if (status !== '' && row.getAttribute('data-status') !== status) {
                    return false;
                }

                var minPage = minPageInput ? parseMin(minPageInput.value) : null;
                if (minPage !== null && parseInt(row.getAttribute('data-page-views') || '0', 10) < minPage) {
                    return false;
                }

                var minPreview = minPreviewInput ? parseMin(minPreviewInput.value) : null;
                if (minPreview !== null && parseInt(row.getAttribute('data-preview-views') || '0', 10) < minPreview) {
                    return false;
                }

                var eventFrom = eventFromInput ? eventFromInput.value : '';
                var eventTo = eventToInput ? eventToInput.value : '';
                if (!eventOverlapsFilter(row, eventFrom, eventTo)) {
                    return false;
                }

                return true;
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

            function syncFiltersPanel() {
                if (filtersPanel) {
                    filtersPanel.hidden = getFilterMode() !== 'filtered';
                }
            }

            controls.addEventListener('change', function (e) {
                if (e.target && e.target.name === 'org_stats_filter_mode') {
                    syncFiltersPanel();
                }
                applyFilters();
            });

            [statusSelect, eventFromInput, eventToInput].forEach(function (el) {
                if (!el) return;
                el.addEventListener('change', applyFilters);
            });

            [minPageInput, minPreviewInput].forEach(function (el) {
                if (!el) return;
                el.addEventListener('input', applyFilters);
            });

            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(applyFilters, 120);
                });
            }

            syncFiltersPanel();
            applyFilters();
        })();
        </script>
    <?php endif; ?>
</div>
