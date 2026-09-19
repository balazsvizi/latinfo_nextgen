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

        <div class="table-wrap" style="margin-top:1rem">
            <table class="sortable-table">
                <thead>
                    <tr>
                        <th>Elem</th>
                        <th>Ember</th>
                        <th>Bot</th>
                        <th>Egyedi</th>
                        <th>Összesen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($items === []): ?>
                        <tr><td colspan="5" class="text-muted">Nincs kattintás a kiválasztott időszakban.</td></tr>
                    <?php else: ?>
                        <?php foreach ($items as $row): ?>
                            <tr>
                                <td><?= h((string) ($row['label'] ?? '')) ?></td>
                                <td><?= number_format((int) ($row['clicks_human'] ?? 0), 0, ',', ' ') ?></td>
                                <td><?= number_format((int) ($row['clicks_bot'] ?? 0), 0, ',', ' ') ?></td>
                                <td><?= number_format((int) ($row['unique_human'] ?? 0), 0, ',', ' ') ?></td>
                                <td><?= number_format((int) ($row['clicks'] ?? 0), 0, ',', ' ') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
