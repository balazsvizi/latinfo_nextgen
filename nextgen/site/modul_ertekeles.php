<?php
declare(strict_types=1);

/**
 * Értékelés modul – áttekintő + csillag-stat + előző időszak.
 */

require_once dirname(__DIR__) . '/init.php';
requireLogin();
require_once dirname(__DIR__) . '/events/bootstrap.php';
require_once dirname(__DIR__) . '/events/lib/event_edit_stats.php';
require_once __DIR__ . '/lib/site_modules.php';

$db = getDb();
$schemaOk = latinfo_home_modules_ensure_schema($db);
$formAction = latinfo_home_module_edit_url('rating');
$hiba = '';
$postedPriors = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require('latinfo_home_rating', '_csrf', $formAction);
    $action = (string) ($_POST['action'] ?? '');

    if (!$schemaOk) {
        $hiba = 'A kezdőoldal táblái nem hozhatók létre.';
    } else {
        try {
            if ($action === 'save_priors') {
                latinfo_home_rating_priors_save($db, $_POST);
                if (function_exists('rendszer_log')) {
                    $saved = latinfo_home_rating_priors_normalize($_POST);
                    $priorTotal = array_sum($saved);
                    rendszer_log('kezdőoldal_értékelés', 1, 'Előző időszak', 'összesen=' . $priorTotal);
                }
                flash('success', 'Az előző időszak értékelései mentve.');
                redirect($formAction);
            }
            $hiba = 'Ismeretlen művelet.';
        } catch (Throwable $e) {
            error_log('latinfo rating priors: ' . $e->getMessage());
            $hiba = 'A mentés nem sikerült.';
            $postedPriors = $_POST;
        }
    }
}

$ratingStatsParams = latinfo_home_rating_stats_params_from_request($_GET);
$ratingStats = $schemaOk
    ? latinfo_home_rating_admin_stats($db, $ratingStatsParams)
    : ['table_ready' => false, 'totals' => [], 'stars' => [], 'chart' => ['labels' => [], 'data' => []]];
$liveSummary = $schemaOk
    ? latinfo_home_rating_summary($db, true)
    : [
        'average' => 0.0,
        'count' => 0,
        'live_average' => 0.0,
        'live_count' => 0,
        'prior_average' => 0.0,
        'prior_count' => 0,
    ];
$ratingPriors = $schemaOk
    ? latinfo_home_rating_priors_load($db)
    : latinfo_home_rating_priors_defaults();
if (is_array($postedPriors)) {
    $ratingPriors = latinfo_home_rating_priors_normalize($postedPriors);
}

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
$extraHead = '<style>
.lh-rating-priors-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(7.5rem,1fr));gap:.75rem;max-width:42rem;margin:0 0 1rem}
.lh-rating-priors-grid .form-group{margin:0}
.lh-rating-priors-grid input{max-width:100%}
</style>';

require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>

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
        Az előző időszak darabszámai is beleszámítanak a megjelenő átlagba.
    </p>
    <div class="events-edit-stats__cards">
        <div class="events-edit-stats__card">
            <div class="events-edit-stats__card-label">Megjelenő átlag</div>
            <div class="events-edit-stats__card-value"><?= h(number_format((float) $liveSummary['average'], 1, ',', ' ')) ?></div>
        </div>
        <div class="events-edit-stats__card">
            <div class="events-edit-stats__card-label">Összes darab</div>
            <div class="events-edit-stats__card-value"><?= number_format((int) $liveSummary['count'], 0, ',', ' ') ?></div>
        </div>
        <div class="events-edit-stats__card">
            <div class="events-edit-stats__card-label">Új (élő) átlag</div>
            <div class="events-edit-stats__card-value"><?= h(number_format((float) ($liveSummary['live_average'] ?? 0), 1, ',', ' ')) ?></div>
        </div>
        <div class="events-edit-stats__card">
            <div class="events-edit-stats__card-label">Új darab</div>
            <div class="events-edit-stats__card-value"><?= number_format((int) ($liveSummary['live_count'] ?? 0), 0, ',', ' ') ?></div>
        </div>
        <div class="events-edit-stats__card">
            <div class="events-edit-stats__card-label">Előző időszak átlag</div>
            <div class="events-edit-stats__card-value"><?= h(number_format((float) ($liveSummary['prior_average'] ?? 0), 1, ',', ' ')) ?></div>
        </div>
        <div class="events-edit-stats__card">
            <div class="events-edit-stats__card-label">Előző időszak darab</div>
            <div class="events-edit-stats__card-value"><?= number_format((int) ($liveSummary['prior_count'] ?? 0), 0, ',', ' ') ?></div>
        </div>
    </div>
</div>

<?php if ($schemaOk): ?>
<div class="card lh-admin" id="rating-priors">
    <div class="events-list-head">
        <h2 class="events-list-title">Előző időszak értékelései</h2>
    </div>
    <p class="text-muted" style="margin-top:0">
        A régi rendszer / más forrás értékeléseinek darabszámai csillagonként.
        Ezek nem jelennek meg a napi statisztikában, de beleszámítanak a kezdőoldalon látható átlagba és darabszámba.
    </p>
    <form method="post" action="<?= h($formAction) ?>#rating-priors">
        <?= csrf_input('latinfo_home_rating') ?>
        <input type="hidden" name="action" value="save_priors">
        <div class="lh-rating-priors-grid">
            <?php for ($i = 5; $i >= 1; $i--): ?>
                <div class="form-group">
                    <label for="prior_stars_<?= $i ?>"><?= $i ?> ★</label>
                    <input
                        type="number"
                        id="prior_stars_<?= $i ?>"
                        name="stars_<?= $i ?>"
                        min="0"
                        max="9999999"
                        step="1"
                        value="<?= (int) ($ratingPriors['stars_' . $i] ?? 0) ?>"
                    >
                </div>
            <?php endfor; ?>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Mentés</button>
    </form>
</div>
<?php endif; ?>

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
