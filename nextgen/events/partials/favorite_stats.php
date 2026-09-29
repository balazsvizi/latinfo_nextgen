<?php
declare(strict_types=1);

/**
 * Kedvencek (szívecskék) statisztika UI.
 *
 * @var array{date_from:string,date_to:string,entity_type:string,actor:string} $statsParams
 * @var array<string, mixed> $statsData
 * @var string $statsFormAction
 * @var list<array{id:string,label:string,url:string,active:bool}> $statsPresetLinks
 */

$statsParams = $statsParams ?? [
    'date_from' => '',
    'date_to' => '',
    'entity_type' => 'all',
    'actor' => 'all',
];
$statsData = is_array($statsData ?? null) ? $statsData : [];
$statsFormAction = (string) ($statsFormAction ?? '');
$statsPresetLinks = is_array($statsPresetLinks ?? null) ? $statsPresetLinks : [];
$typeLabels = latinfo_favorite_stats_type_labels('hu');

$totals = is_array($statsData['totals'] ?? null) ? $statsData['totals'] : [];
$typeRows = is_array($statsData['type_rows'] ?? null) ? $statsData['type_rows'] : [];
$topEntities = is_array($statsData['top_entities'] ?? null) ? $statsData['top_entities'] : [];
$recent = is_array($statsData['recent'] ?? null) ? $statsData['recent'] : [];
$granularity = (string) ($statsData['granularity'] ?? 'day');
$granularityLabel = match ($granularity) {
    'hour' => 'óránkénti',
    'month' => 'havi',
    'week' => 'heti',
    default => 'napi',
};

$hearts = (int) ($totals['hearts'] ?? 0);
$uniqueActors = (int) ($totals['unique_actors'] ?? 0);
$uniqueEntities = (int) ($totals['unique_entities'] ?? 0);
$userHearts = (int) ($totals['user_hearts'] ?? 0);
$visitorHearts = (int) ($totals['visitor_hearts'] ?? 0);
$allTime = (int) ($totals['all_time'] ?? 0);
$perActor = ($uniqueActors > 0 && $hearts > 0) ? round($hearts / $uniqueActors, 1) : null;

$chartPayload = is_array($statsData['chart'] ?? null) ? $statsData['chart'] : ['labels' => [], 'datasets' => []];
$actorChartPayload = is_array($statsData['actor_chart'] ?? null) ? $statsData['actor_chart'] : ['labels' => [], 'datasets' => []];
$typeShare = is_array($statsData['type_share'] ?? null) ? $statsData['type_share'] : ['labels' => [], 'data' => [], 'colors' => []];
$hourChart = is_array($statsData['hour_chart'] ?? null) ? $statsData['hour_chart'] : ['labels' => [], 'data' => []];

$hasTrend = ($chartPayload['labels'] ?? []) !== [] && ($chartPayload['datasets'] ?? []) !== [] && $hearts > 0;
$hasActorTrend = ($actorChartPayload['labels'] ?? []) !== [] && ($actorChartPayload['datasets'] ?? []) !== [] && $hearts > 0;
$hasTypeShare = array_sum(array_map('intval', $typeShare['data'] ?? [])) > 0;
$hasHour = array_sum(array_map('intval', $hourChart['data'] ?? [])) > 0;
$hasAnyChart = $hasTrend || $hasActorTrend || $hasTypeShare || $hasHour;
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<div class="card events-edit-stats events-favorite-stats">
    <p class="events-edit-stats__intro">
        A publikus szívecskék mentései: bejelentkezett felhasználók (<code>u:</code>) és vendégek (<code>v:</code>).
        A törölt szívecskék nem jelennek meg (csak az aktuálisan elmentett kedvencek időbélyege számít).
    </p>

    <?php if (empty($statsData['table_ready'])): ?>
        <p class="alert alert-warning">A kedvencek tábla nem érhető el. Nyisd meg egyszer a Config → Kedvencek oldalt, vagy ellenőrizd az adatbázis-jogosultságokat.</p>
    <?php else: ?>
        <form method="get" action="<?= h($statsFormAction) ?>" class="events-edit-stats__filters">
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
                    <label class="events-filter-label" for="fav_date_from">Tól</label>
                    <input class="events-filter-input" type="date" name="stat_date_from" id="fav_date_from" value="<?= h((string) $statsParams['date_from']) ?>">
                </div>
                <div class="form-group">
                    <label class="events-filter-label" for="fav_date_to">Ig</label>
                    <input class="events-filter-input" type="date" name="stat_date_to" id="fav_date_to" value="<?= h((string) $statsParams['date_to']) ?>">
                </div>
                <div class="form-group">
                    <label class="events-filter-label" for="fav_type">Entitás</label>
                    <select class="events-filter-input" name="fav_type" id="fav_type">
                        <option value="all"<?= $statsParams['entity_type'] === 'all' ? ' selected' : '' ?>>Összes típus</option>
                        <?php foreach ($typeLabels as $typeKey => $typeLabel): ?>
                            <option value="<?= h($typeKey) ?>"<?= $statsParams['entity_type'] === $typeKey ? ' selected' : '' ?>><?= h($typeLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="events-filter-label" for="fav_actor">Ki szívecskézett</label>
                    <select class="events-filter-input" name="fav_actor" id="fav_actor">
                        <option value="all"<?= $statsParams['actor'] === 'all' ? ' selected' : '' ?>>Összes</option>
                        <option value="user"<?= $statsParams['actor'] === 'user' ? ' selected' : '' ?>>Bejelentkezett</option>
                        <option value="visitor"<?= $statsParams['actor'] === 'visitor' ? ' selected' : '' ?>>Vendég</option>
                    </select>
                </div>
                <div class="form-group events-edit-stats__filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm">Szűrés</button>
                    <a class="btn btn-secondary btn-sm" href="<?= h($statsFormAction) ?>">Szűrés törlése</a>
                </div>
            </div>
        </form>

        <div class="events-edit-stats__cards">
            <div class="events-edit-stats__card">
                <p class="events-edit-stats__card-label">Szívecskék</p>
                <p class="events-edit-stats__card-value"><?= $hearts ?></p>
                <?php if ($perActor !== null): ?>
                    <p class="events-edit-stats__card-hint">≈ <?= h((string) $perActor) ?> / személy</p>
                <?php endif; ?>
            </div>
            <div class="events-edit-stats__card">
                <p class="events-edit-stats__card-label">Egyedi személy</p>
                <p class="events-edit-stats__card-value"><?= $uniqueActors ?></p>
            </div>
            <div class="events-edit-stats__card">
                <p class="events-edit-stats__card-label">Egyedi entitás</p>
                <p class="events-edit-stats__card-value"><?= $uniqueEntities ?></p>
            </div>
            <div class="events-edit-stats__card">
                <p class="events-edit-stats__card-label">Fiók / vendég</p>
                <p class="events-edit-stats__card-value"><?= $userHearts + $visitorHearts ?></p>
                <dl class="events-edit-stats__card-split">
                    <dt>Fiók</dt>
                    <dt>Vendég</dt>
                    <dd><?= $userHearts ?></dd>
                    <dd><?= $visitorHearts ?></dd>
                </dl>
            </div>
            <div class="events-edit-stats__card">
                <p class="events-edit-stats__card-label">Összes idő</p>
                <p class="events-edit-stats__card-value"><?= $allTime ?></p>
                <p class="events-edit-stats__card-hint">teljes adatbázis</p>
            </div>
        </div>

        <?php if ($hearts === 0): ?>
            <p class="help events-edit-stats__empty">
                Nincs szívecske a választott szűrőkkel. A mérés a kedvencek funkció élesítésétől gyűlik.
            </p>
        <?php endif; ?>

        <?php if ($hasTrend): ?>
            <div class="events-edit-stats__chart-wrap">
                <div class="events-edit-stats__chart-head">
                    <div>
                        <h3 class="events-edit-stats__chart-title">Szívecskék alakulása</h3>
                        <p class="events-edit-stats__chart-hint"><?= h(ucfirst($granularityLabel)) ?> bontás — entitástípusonként.</p>
                    </div>
                </div>
                <div class="events-edit-stats__chart-canvas">
                    <canvas id="favorite-stats-trend-chart" aria-label="Szívecskék grafikonja"></canvas>
                </div>
            </div>
            <script type="application/json" id="favorite-stats-trend-chart-data"><?= json_encode($chartPayload, $jsonFlags) ?></script>
        <?php endif; ?>

        <?php if ($hasActorTrend): ?>
            <div class="events-edit-stats__chart-wrap">
                <div class="events-edit-stats__chart-head">
                    <div>
                        <h3 class="events-edit-stats__chart-title">Bejelentkezett és vendég</h3>
                        <p class="events-edit-stats__chart-hint"><?= h(ucfirst($granularityLabel)) ?> bontás — ki mentette a szívecskét.</p>
                    </div>
                </div>
                <div class="events-edit-stats__chart-canvas">
                    <canvas id="favorite-stats-actor-chart" aria-label="Fiók és vendég grafikon"></canvas>
                </div>
            </div>
            <script type="application/json" id="favorite-stats-actor-chart-data"><?= json_encode($actorChartPayload, $jsonFlags) ?></script>
        <?php endif; ?>

        <div class="events-public-traffic-stats__chart-grid">
            <?php if ($hasTypeShare): ?>
                <div class="events-edit-stats__chart-wrap">
                    <div class="events-edit-stats__chart-head">
                        <div>
                            <h3 class="events-edit-stats__chart-title">Típusok aránya</h3>
                            <p class="events-edit-stats__chart-hint">Szívecskék entitástípus szerint.</p>
                        </div>
                    </div>
                    <div class="events-edit-stats__chart-canvas events-public-traffic-stats__doughnut">
                        <canvas id="favorite-stats-type-share" aria-label="Típusok aránya"></canvas>
                    </div>
                </div>
                <script type="application/json" id="favorite-stats-type-share-data"><?= json_encode($typeShare, $jsonFlags) ?></script>
            <?php endif; ?>
            <?php if ($hasHour): ?>
                <div class="events-edit-stats__chart-wrap">
                    <div class="events-edit-stats__chart-head">
                        <div>
                            <h3 class="events-edit-stats__chart-title">Óránként</h3>
                            <p class="events-edit-stats__chart-hint">A nap 24 órájában (szerveridő).</p>
                        </div>
                    </div>
                    <div class="events-edit-stats__chart-canvas">
                        <canvas id="favorite-stats-hour-chart" aria-label="Óránkénti szívecskék"></canvas>
                    </div>
                </div>
                <script type="application/json" id="favorite-stats-hour-chart-data"><?= json_encode($hourChart, $jsonFlags) ?></script>
            <?php endif; ?>
        </div>

        <h3 class="events-edit-stats__events-title">Típusonként</h3>
        <?php if ($typeRows === []): ?>
            <p class="help">Nincs adat.</p>
        <?php else: ?>
            <div class="table-wrap events-admin-table-wrap">
                <table class="events-admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Típus</th>
                            <th class="th-center" scope="col">Szívecske</th>
                            <th class="th-center" scope="col">Arány</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($typeRows as $row): ?>
                            <?php
                            $cnt = (int) ($row['count'] ?? 0);
                            $pct = $hearts > 0 ? round(100 * $cnt / $hearts, 1) : 0;
                            ?>
                            <tr>
                                <td>
                                    <span class="events-favorite-stats__swatch" style="background:<?= h((string) ($row['color'] ?? '#6b7280')) ?>"></span>
                                    <?= h((string) ($row['label'] ?? '')) ?>
                                </td>
                                <td class="text-center events-stats-cell--human"><?= $cnt ?></td>
                                <td class="text-center"><?= h((string) $pct) ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <h3 class="events-edit-stats__events-title">Top entitások</h3>
        <p class="events-edit-stats__events-hint">A legtöbb szívecskét kapott elemek a választott időszakban (max. 50).</p>
        <?php if ($topEntities === []): ?>
            <p class="help">Nincs top lista a szűrőkkel.</p>
        <?php else: ?>
            <div class="table-wrap events-admin-table-wrap">
                <table class="events-admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Típus</th>
                            <th scope="col">Név</th>
                            <th class="th-center" scope="col">Szívecske</th>
                            <th class="th-center" scope="col">Egyedi</th>
                            <th scope="col">Utolsó</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topEntities as $row): ?>
                            <tr>
                                <td><?= h((string) ($row['type_label'] ?? '')) ?></td>
                                <td>
                                    <?php if (($row['url'] ?? '') !== ''): ?>
                                        <a href="<?= h((string) $row['url']) ?>" target="_blank" rel="noopener"><?= h((string) ($row['label'] ?? '')) ?></a>
                                    <?php else: ?>
                                        <?= h((string) ($row['label'] ?? '')) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center events-stats-cell--human"><?= (int) ($row['count'] ?? 0) ?></td>
                                <td class="text-center"><?= (int) ($row['unique_actors'] ?? 0) ?></td>
                                <td><?= h((string) ($row['last_at'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <h3 class="events-edit-stats__events-title">Legutóbbi szívecskék</h3>
        <?php if ($recent === []): ?>
            <p class="help">Nincs friss szívecske a szűrőkkel.</p>
        <?php else: ?>
            <div class="table-wrap events-admin-table-wrap">
                <table class="events-admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Idő</th>
                            <th scope="col">Típus</th>
                            <th scope="col">Név</th>
                            <th scope="col">Ki</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $row): ?>
                            <tr>
                                <td><?= h((string) ($row['created_at'] ?? '')) ?></td>
                                <td><?= h((string) ($row['type_label'] ?? '')) ?></td>
                                <td>
                                    <?php if (($row['url'] ?? '') !== ''): ?>
                                        <a href="<?= h((string) $row['url']) ?>" target="_blank" rel="noopener"><?= h((string) ($row['label'] ?? '')) ?></a>
                                    <?php else: ?>
                                        <?= h((string) ($row['label'] ?? '')) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= ($row['actor_kind'] ?? '') === 'user' ? 'Fiók' : 'Vendég' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($hasAnyChart): ?>
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js" crossorigin="anonymous"></script>
            <script>
            (function () {
                if (typeof Chart === 'undefined') return;

                function parseJson(id) {
                    var el = document.getElementById(id);
                    if (!el) return null;
                    try { return JSON.parse(el.textContent || '{}'); } catch (e) { return null; }
                }

                function lineDatasets(list, labels) {
                    return (list || []).map(function (ds) {
                        return {
                            label: ds.label,
                            data: ds.data,
                            borderColor: ds.color || '#3d6b4f',
                            backgroundColor: (ds.color || '#3d6b4f') + '22',
                            borderWidth: 2,
                            tension: 0.25,
                            pointRadius: (labels || []).length > 45 ? 0 : 3,
                            pointHoverRadius: 5,
                            fill: false
                        };
                    });
                }

                function makeLine(canvasId, dataId) {
                    var canvas = document.getElementById(canvasId);
                    var payload = parseJson(dataId);
                    if (!canvas || !payload) return;
                    var labels = payload.labels || [];
                    new Chart(canvas.getContext('2d'), {
                        type: 'line',
                        data: { labels: labels, datasets: lineDatasets(payload.datasets, labels) },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 14, font: { size: 11 } } }
                            },
                            scales: {
                                x: { ticks: { maxRotation: 45, minRotation: 0, autoSkip: true, maxTicksLimit: 20 } },
                                y: { beginAtZero: true, ticks: { precision: 0 } }
                            }
                        }
                    });
                }

                makeLine('favorite-stats-trend-chart', 'favorite-stats-trend-chart-data');
                makeLine('favorite-stats-actor-chart', 'favorite-stats-actor-chart-data');

                var shareCanvas = document.getElementById('favorite-stats-type-share');
                var sharePayload = parseJson('favorite-stats-type-share-data');
                if (shareCanvas && sharePayload) {
                    new Chart(shareCanvas.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: sharePayload.labels || [],
                            datasets: [{
                                data: sharePayload.data || [],
                                backgroundColor: sharePayload.colors || [],
                                borderWidth: 1,
                                borderColor: '#fff'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12, font: { size: 11 } } }
                            }
                        }
                    });
                }

                var hourCanvas = document.getElementById('favorite-stats-hour-chart');
                var hourPayload = parseJson('favorite-stats-hour-chart-data');
                if (hourCanvas && hourPayload) {
                    new Chart(hourCanvas.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: hourPayload.labels || [],
                            datasets: [{
                                label: 'Szívecske',
                                data: hourPayload.data || [],
                                backgroundColor: '#c0395a99',
                                borderColor: '#c0395a',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: { beginAtZero: true, ticks: { precision: 0 } }
                            }
                        }
                    });
                }
            })();
            </script>
        <?php endif; ?>
    <?php endif; ?>
</div>
<style>
.events-favorite-stats__swatch {
    display: inline-block;
    width: 0.65rem;
    height: 0.65rem;
    border-radius: 999px;
    margin-right: 0.4rem;
    vertical-align: middle;
}
</style>
