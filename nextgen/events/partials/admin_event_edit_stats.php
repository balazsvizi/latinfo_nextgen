<?php
declare(strict_types=1);
/** @var int $id */
/** @var array{date_from: string, date_to: string} $statsParams */
/** @var array{
 *   table_ready: bool,
 *   totals: array{page_views: int, calendar_previews: int},
 *   chart: array{labels: list<string>, datasets: list<array{label: string, data: list<int>, color: string, total: int}>}
 * } $statsData */

$statsFormAction = events_url('szerkeszt.php');
$statsFilterExtraQuery = ['id' => (int) $id];
$statsAllDateFrom = isset($db) && $db instanceof PDO
    ? events_edit_stats_earliest_view_date_for_event($db, (int) $id)
    : null;
$statsActivePreset = events_edit_stats_detect_preset($statsParams, $statsAllDateFrom);
$statsPresetLinks = [];
foreach (events_edit_stats_presets() as $preset) {
    $presetId = (string) $preset['id'];
    $statsPresetLinks[] = [
        'id' => $presetId,
        'label' => (string) $preset['label'],
        'url' => events_edit_stats_filter_url(
            $statsFormAction,
            events_edit_stats_range_for_preset($presetId, $statsAllDateFrom),
            $statsFilterExtraQuery
        ),
        'active' => $statsActivePreset === $presetId,
    ];
}
$chartPayload = $statsData['chart'] ?? ['labels' => [], 'datasets' => []];
$hasChart = ($chartPayload['labels'] ?? []) !== [] && ($chartPayload['datasets'] ?? []) !== [];
$chartJson = json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<div class="events-edit-panel events-edit-stats">
    <h2 class="events-edit-panel__title">Statisztika</h2>
    <p class="events-edit-stats__intro">Naptár előnézet, további információ kattintás és teljes eseményoldal megtekintések az eseményhez.</p>

    <?php if (empty($statsData['table_ready'])): ?>
        <p class="alert alert-warning events-edit-stats__migration">
            A részletes metrikákhoz futtasd: <code>events/sql/migration_event_view_metrics.sql</code>
            (addig csak az összesített oldalmegtekintés érhető el). Bot bontáshoz: <code>events/sql/migration_event_view_is_bot.sql</code>.
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
                <div class="events-edit-stats__presets" role="group" aria-label="Gyors időszak">
                    <?php foreach ($statsPresetLinks as $presetLink): ?>
                        <a
                            class="btn btn-sm <?= !empty($presetLink['active']) ? 'btn-primary' : 'btn-secondary' ?>"
                            href="<?= h((string) $presetLink['url']) ?>"
                        ><?= h((string) $presetLink['label']) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </form>

    <div class="events-edit-stats__cards">
        <?php
        $totals = $statsData['totals'] ?? [];
        $statCards = [
            [
                'label' => 'Oldal — emberi',
                'value' => (int) ($totals['page_views_human'] ?? $totals['page_views'] ?? 0),
                'hint' => 'Emberi megtekintés',
            ],
            [
                'label' => 'Oldal — bot',
                'value' => (int) ($totals['page_views_bot'] ?? 0),
                'hint' => 'Robot / crawler',
            ],
            [
                'label' => 'Oldal — össz',
                'value' => (int) ($totals['page_views'] ?? 0),
                'hint' => 'Összes oldalmegtekintés',
            ],
        ];
        if (!empty($statsData['table_ready'])) {
            $statCards[] = [
                'label' => 'Előnézet',
                'value' => (int) ($totals['calendar_previews'] ?? $totals['calendar_previews_human'] ?? 0),
                'hint' => 'Naptár előnézet megnyitások',
            ];
            $statCards[] = [
                'label' => 'További info',
                'value' => (int) ($totals['external_info_clicks'] ?? $totals['external_info_clicks_human'] ?? 0),
                'hint' => 'További információ gombra kattintás',
            ];
        }
        foreach ($statCards as $card):
        ?>
            <div class="events-edit-stats__card">
                <p class="events-edit-stats__card-label"><?= h((string) $card['label']) ?></p>
                <p class="events-edit-stats__card-value"><?= (int) $card['value'] ?></p>
                <p class="events-edit-stats__card-hint"><?= h((string) $card['hint']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($hasChart): ?>
        <div class="events-edit-stats__chart-wrap">
            <h3 class="events-edit-stats__chart-title">Megtekintések alakulása</h3>
            <p class="events-edit-stats__chart-hint">Napi bontás — oldal: emberi / bot; előnézet és további info: összes.</p>
            <div class="events-edit-stats__chart-canvas">
                <canvas id="events-edit-stats-chart" aria-label="Esemény megtekintések grafikonja"></canvas>
            </div>
        </div>
        <script type="application/json" id="events-edit-stats-chart-data"><?= $chartJson ?></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js" crossorigin="anonymous"></script>
        <script>
        (function () {
            var dataEl = document.getElementById('events-edit-stats-chart-data');
            var canvas = document.getElementById('events-edit-stats-chart');
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
</div>
