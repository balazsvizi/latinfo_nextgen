<?php
declare(strict_types=1);
/** @var int $id */
/** @var array{date_from: string, date_to: string} $statsParams */
/** @var array{
 *   table_ready: bool,
 *   totals: array{page_views: int, calendar_previews: int, events_total?: int, events_with_views?: int},
 *   event_rows?: list<array<string, mixed>>
 *   chart: array{labels: list<string>, datasets: list<array{label: string, data: list<int>, color: string, total: int}>}
 * } $statsData */
/** @var list<array<string, mixed>> $statsEventRows */

$statsFormAction = events_url('organizer_szerkeszt.php');
$statsChartDomId = 'organizer-edit-stats-chart';
$chartPayload = $statsData['chart'] ?? ['labels' => [], 'datasets' => []];
$hasChart = ($chartPayload['labels'] ?? []) !== [] && ($chartPayload['datasets'] ?? []) !== [];
$chartJson = json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$editBase = events_url('szerkeszt.php?id=');
$eventsTotal = (int) ($statsData['totals']['events_total'] ?? count($statsEventRows));
$eventsWithViews = (int) ($statsData['totals']['events_with_views'] ?? 0);
?>
<div class="card events-edit-stats events-edit-stats--organizer">
    <h2 class="card-title">Statisztika</h2>
    <p class="events-edit-stats__intro">A szervező összes eseményére vonatkozó naptár előnézet és oldalmegtekintés az időszakban.</p>

    <?php if (empty($statsData['table_ready'])): ?>
        <p class="alert alert-warning events-edit-stats__migration">
            A részletes metrikákhoz futtasd: <code>events/sql/migration_event_view_metrics.sql</code>
            (addig csak az összesített oldalmegtekintés érhető el).
        </p>
    <?php endif; ?>

    <form method="get" action="<?= h($statsFormAction) ?>" class="events-edit-stats__filters">
        <input type="hidden" name="id" value="<?= (int) $id ?>">
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
                <a class="btn btn-secondary btn-sm" href="<?= h($statsFormAction . '?id=' . (int) $id) ?>">Utolsó 30 nap</a>
            </div>
        </div>
    </form>

    <div class="events-edit-stats__cards">
        <div class="events-edit-stats__card">
            <p class="events-edit-stats__card-label">Események</p>
            <p class="events-edit-stats__card-value"><?= $eventsWithViews ?> / <?= $eventsTotal ?></p>
            <p class="events-edit-stats__card-hint">Megjelenített / összes</p>
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
            <p class="events-edit-stats__chart-hint">Napi bontás — összesítve a szervező eseményeire.</p>
            <div class="events-edit-stats__chart-canvas">
                <canvas id="<?= h($statsChartDomId) ?>" aria-label="Szervező megtekintések grafikonja"></canvas>
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
    <p class="events-edit-stats__events-hint">A szervező összes eseménye. Megtekintésszámok a fenti stat időszakra vonatkoznak (ugyanaz, amiből a grafikon készül).</p>

    <?php if ($statsEventRows === []): ?>
        <p class="help events-edit-stats__empty">Nincs esemény ehhez a szervezőhöz.</p>
    <?php else: ?>
        <div class="table-wrap events-admin-table-wrap">
            <table class="sortable-table events-admin-table events-edit-stats__events-table">
                <thead>
                    <tr>
                        <th class="events-th-actions" scope="col"><span class="visually-hidden">Műveletek</span></th>
                        <th>Dátum</th>
                        <th>Név</th>
                        <th>Státusz</th>
                        <th class="th-center">Előnézet</th>
                        <th class="th-center">Oldal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($statsEventRows as $row): ?>
                        <?php
                        $eid = (int) ($row['id'] ?? 0);
                        $edit = $editBase . $eid;
                        $st = (string) ($row['event_status'] ?? '');
                        $badgeClass = events_post_status_badge_class($st);
                        ?>
                        <tr>
                            <td class="events-td-actions">
                                <a href="<?= h($edit) ?>" class="events-icon-action" title="Szerkesztés" aria-label="Szerkesztés">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" aria-hidden="true"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </a>
                            </td>
                            <td><a class="events-cell-edit" href="<?= h($edit) ?>"><?= h(events_admin_format_datum_cell($row)) ?></a></td>
                            <td><a class="events-cell-edit" href="<?= h($edit) ?>"><?= h((string) ($row['event_name'] ?? '')) ?></a></td>
                            <td>
                                <a class="events-cell-edit events-cell-edit--badge" href="<?= h($edit) ?>">
                                    <span class="event-status-badge <?= h($badgeClass) ?>"><?= h(events_post_status_label($st)) ?></span>
                                </a>
                            </td>
                            <td class="text-center"><?= (int) ($row['naptar_elonezetek'] ?? 0) ?></td>
                            <td class="text-center"><?= (int) ($row['megtekintesek'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
