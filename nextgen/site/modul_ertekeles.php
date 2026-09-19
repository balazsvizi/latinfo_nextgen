<?php
declare(strict_types=1);

/**
 * Értékelés modul – áttekintő + csillag-stat.
 */

require_once dirname(__DIR__) . '/init.php';
requireLogin();
require_once dirname(__DIR__) . '/events/bootstrap.php';
require_once dirname(__DIR__) . '/events/lib/event_edit_stats.php';
require_once __DIR__ . '/lib/site_modules.php';

$db = getDb();
$schemaOk = latinfo_home_modules_ensure_schema($db);
$formAction = latinfo_home_module_edit_url('rating');

$ratingStatsParams = latinfo_home_rating_stats_params_from_request($_GET);
$ratingStats = $schemaOk
    ? latinfo_home_rating_admin_stats($db, $ratingStatsParams)
    : ['table_ready' => false, 'totals' => [], 'stars' => [], 'chart' => ['labels' => [], 'data' => []]];
$liveSummary = $schemaOk ? latinfo_home_rating_summary($db, true) : ['average' => 0.0, 'count' => 0];

$moduleItemStatsParams = latinfo_home_module_stats_params_from_request($_GET);
$moduleItemStatsParams['module'] = 'rating';
$moduleItemStatsData = $schemaOk
    ? latinfo_home_module_item_stats($db, 'rating', $moduleItemStatsParams)
    : ['table_ready' => false, 'totals' => [], 'items' => [], 'chart' => ['labels' => [], 'datasets' => []]];
$moduleItemStatsFormAction = $formAction;
$statsAllDateFrom = latinfo_home_module_stats_earliest_date($db);
$statsActivePreset = events_edit_stats_detect_preset($ratingStatsParams, $statsAllDateFrom);
$moduleItemStatsPresetLinks = [];
$ratingPresetLinks = [];
foreach (events_edit_stats_presets() as $preset) {
    $presetId = (string) $preset['id'];
    $url = events_edit_stats_filter_url(
        $formAction,
        events_edit_stats_range_for_preset($presetId, $statsAllDateFrom),
        ['visitor' => $ratingStatsParams['visitor'] !== 'human' ? $ratingStatsParams['visitor'] : null]
    );
    $link = [
        'id' => $presetId,
        'label' => (string) $preset['label'],
        'url' => $url,
        'active' => $statsActivePreset === $presetId,
    ];
    $moduleItemStatsPresetLinks[] = $link;
    $ratingPresetLinks[] = $link;
}
$moduleItemStatsTitle = 'Értékelés kattintások';
$moduleItemStatsIntro = 'Az egyes csillagértékekre kattintások (stars:1 … stars:5).';

$totals = is_array($ratingStats['totals'] ?? null) ? $ratingStats['totals'] : [];
$starsDist = is_array($ratingStats['stars'] ?? null) ? $ratingStats['stars'] : [];
$chart = is_array($ratingStats['chart'] ?? null) ? $ratingStats['chart'] : ['labels' => [], 'data' => []];
$hasChart = ($chart['labels'] ?? []) !== [] && array_sum(array_map('intval', $chart['data'] ?? [])) > 0;
$chartJson = json_encode([
    'labels' => $chart['labels'] ?? [],
    'datasets' => [[
        'label' => 'Emberi értékelések',
        'data' => $chart['data'] ?? [],
        'color' => '#c45c26',
        'total' => array_sum(array_map('intval', $chart['data'] ?? [])),
    ]],
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$pageTitle = 'Értékelés modul';
$mainContentClass = 'main-content main-content--fullwidth';

require_once dirname(__DIR__) . '/partials/header.php';
?>
<div class="card lh-admin">
    <div class="events-list-head">
        <h2 class="events-list-title">Értékelés</h2>
        <div class="events-list-actions">
            <a href="<?= h(latinfo_home_preview_url()) ?>" class="btn btn-secondary btn-sm">Előnézet</a>
            <a href="<?= h(latinfo_home_edit_url()) ?>" class="btn btn-secondary btn-sm">Modulok</a>
        </div>
    </div>
    <p class="text-muted" style="margin-top:0">
        Egyszerű 5 csillagos értékelés a Latinfo.hu oldalra. A botok User-Agent alapján kiszűrődnek az átlagból.
        Ugyanarról az IP-ről naponta egy emberi értékelés számít (felülírható).
    </p>
    <div class="events-edit-stats__cards">
        <div class="events-edit-stats__card">
            <div class="events-edit-stats__card-label">Élő átlag (ember)</div>
            <div class="events-edit-stats__card-value"><?= h(number_format((float) $liveSummary['average'], 1, ',', ' ')) ?></div>
        </div>
        <div class="events-edit-stats__card">
            <div class="events-edit-stats__card-label">Élő darabszám</div>
            <div class="events-edit-stats__card-value"><?= number_format((int) $liveSummary['count'], 0, ',', ' ') ?></div>
        </div>
    </div>
</div>

<div class="card events-edit-stats" id="rating-stats">
    <div class="events-list-head">
        <h2 class="events-list-title">Értékelések időszakonként</h2>
    </div>
    <?php if (empty($ratingStats['table_ready'])): ?>
        <p class="alert alert-warning">A értékelés-tábla nem érhető el.</p>
    <?php else: ?>
        <form method="get" action="<?= h($formAction) ?>#rating-stats" class="events-edit-stats__filters">
            <?php if ($ratingPresetLinks !== []): ?>
                <div class="events-edit-stats__presets-row">
                    <span class="events-filter-label">Időszak</span>
                    <?php foreach ($ratingPresetLinks as $presetLink): ?>
                        <a
                            class="btn btn-sm <?= !empty($presetLink['active']) ? 'btn-primary' : 'btn-secondary' ?>"
                            href="<?= h((string) $presetLink['url']) ?>#rating-stats"
                        ><?= h((string) $presetLink['label']) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="events-edit-stats__filter-grid">
                <div class="form-group">
                    <label class="events-filter-label" for="rat_from">Tól</label>
                    <input class="events-filter-input" type="date" name="stat_date_from" id="rat_from" value="<?= h((string) $ratingStatsParams['date_from']) ?>">
                </div>
                <div class="form-group">
                    <label class="events-filter-label" for="rat_to">Ig</label>
                    <input class="events-filter-input" type="date" name="stat_date_to" id="rat_to" value="<?= h((string) $ratingStatsParams['date_to']) ?>">
                </div>
                <div class="form-group">
                    <label class="events-filter-label" for="rat_visitor">Látogató</label>
                    <select class="events-filter-input" name="visitor" id="rat_visitor">
                        <option value="human"<?= $ratingStatsParams['visitor'] === 'human' ? ' selected' : '' ?>>Csak ember</option>
                        <option value="bot"<?= $ratingStatsParams['visitor'] === 'bot' ? ' selected' : '' ?>>Csak bot</option>
                        <option value="all"<?= $ratingStatsParams['visitor'] === 'all' ? ' selected' : '' ?>>Ember + bot</option>
                    </select>
                </div>
                <div class="form-group events-edit-stats__filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm">Szűrés</button>
                    <a class="btn btn-secondary btn-sm" href="<?= h($formAction) ?>#rating-stats">Szűrés törlése</a>
                </div>
            </div>
        </form>

        <div class="events-edit-stats__cards">
            <div class="events-edit-stats__card">
                <div class="events-edit-stats__card-label">Emberi értékelés</div>
                <div class="events-edit-stats__card-value"><?= number_format((int) ($totals['ratings_human'] ?? 0), 0, ',', ' ') ?></div>
            </div>
            <div class="events-edit-stats__card">
                <div class="events-edit-stats__card-label">Bot</div>
                <div class="events-edit-stats__card-value"><?= number_format((int) ($totals['ratings_bot'] ?? 0), 0, ',', ' ') ?></div>
            </div>
            <div class="events-edit-stats__card">
                <div class="events-edit-stats__card-label">Átlag (ember)</div>
                <div class="events-edit-stats__card-value"><?= h(number_format((float) ($totals['average_human'] ?? 0), 1, ',', ' ')) ?></div>
            </div>
            <div class="events-edit-stats__card">
                <div class="events-edit-stats__card-label">Egyedi ember</div>
                <div class="events-edit-stats__card-value"><?= number_format((int) ($totals['unique_human'] ?? 0), 0, ',', ' ') ?></div>
            </div>
        </div>

        <div class="table-wrap" style="margin-top:1rem">
            <table class="sortable-table">
                <thead>
                    <tr>
                        <th>Csillag</th>
                        <th>Darab</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <tr>
                            <td><?= $i ?> ★</td>
                            <td><?= number_format((int) ($starsDist[$i] ?? 0), 0, ',', ' ') ?></td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>

        <?php if ($hasChart && $chartJson !== false): ?>
            <div class="events-edit-stats__chart-wrap" style="margin-top:1rem">
                <canvas id="lh-rating-chart" height="120" aria-label="Napi értékelések"></canvas>
            </div>
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
            <script>
            (function () {
                var payload = <?= $chartJson ?>;
                var canvas = document.getElementById('lh-rating-chart');
                if (!canvas || !window.Chart || !payload.labels || !payload.labels.length) return;
                var ds = (payload.datasets || [])[0] || {};
                new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: payload.labels,
                        datasets: [{
                            label: ds.label || '',
                            data: ds.data || [],
                            borderColor: ds.color || '#c45c26',
                            backgroundColor: (ds.color || '#c45c26') + '33',
                            tension: 0.25,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                    }
                });
            })();
            </script>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($schemaOk): ?>
    <?php require __DIR__ . '/partials/module_item_stats.php'; ?>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
