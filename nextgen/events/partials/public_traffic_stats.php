<?php
declare(strict_types=1);

/**
 * Nyilvános forgalom statisztika – szűrők, KPI, grafikonok, táblázatok.
 *
 * @var array{date_from: string, date_to: string, page: string, lang: string, visitor: string, device: string, kind: string} $statsParams
 * @var array<string, mixed> $statsData
 * @var string $statsFormAction
 * @var list<array{id: string, label: string, url: string, active: bool}> $statsPresetLinks
 */

$statsParams = $statsParams ?? [
    'date_from' => '',
    'date_to' => '',
    'page' => 'all',
    'lang' => 'all',
    'visitor' => 'all',
    'device' => 'all',
    'kind' => 'all',
];
$statsData = is_array($statsData ?? null) ? $statsData : [];
$statsFormAction = (string) ($statsFormAction ?? '');
$statsPresetLinks = is_array($statsPresetLinks ?? null) ? $statsPresetLinks : [];

$totals = is_array($statsData['totals'] ?? null) ? $statsData['totals'] : [];
$pageRows = is_array($statsData['pages'] ?? null) ? $statsData['pages'] : [];
$navRows = is_array($statsData['nav'] ?? null) ? $statsData['nav'] : [];
$deviceRows = is_array($statsData['devices'] ?? null) ? $statsData['devices'] : [];
$referrerRows = is_array($statsData['referrers'] ?? null) ? $statsData['referrers'] : [];
$granularity = (string) ($statsData['granularity'] ?? 'day');
$granularityLabel = match ($granularity) {
    'hour' => 'óránkénti',
    'month' => 'havi',
    'week' => 'heti',
    default => 'napi',
};

$pageHuman = (int) ($totals['page_views_human'] ?? 0);
$pageBot = (int) ($totals['page_views_bot'] ?? 0);
$navHuman = (int) ($totals['nav_clicks_human'] ?? 0);
$navBot = (int) ($totals['nav_clicks_bot'] ?? 0);
$uniqueHuman = (int) ($totals['unique_human'] ?? 0);
$uniqueBot = (int) ($totals['unique_bot'] ?? 0);
$langHu = (int) ($totals['lang_hu'] ?? 0);
$langEn = (int) ($totals['lang_en'] ?? 0);
$pagePerUnique = ($uniqueHuman > 0 && $pageHuman > 0) ? round($pageHuman / $uniqueHuman, 1) : null;

$chartPayload = is_array($statsData['chart'] ?? null) ? $statsData['chart'] : ['labels' => [], 'datasets' => []];
$navChartPayload = is_array($statsData['nav_chart'] ?? null) ? $statsData['nav_chart'] : ['labels' => [], 'datasets' => []];
$visitorChartPayload = is_array($statsData['visitor_chart'] ?? null) ? $statsData['visitor_chart'] : ['labels' => [], 'datasets' => []];
$pageShare = is_array($statsData['page_share'] ?? null) ? $statsData['page_share'] : ['labels' => [], 'data' => [], 'colors' => []];
$deviceShare = is_array($statsData['device_share'] ?? null) ? $statsData['device_share'] : ['labels' => [], 'data' => [], 'colors' => []];
$langShare = is_array($statsData['lang_share'] ?? null) ? $statsData['lang_share'] : ['labels' => [], 'data' => [], 'colors' => []];
$hourChart = is_array($statsData['hour_chart'] ?? null) ? $statsData['hour_chart'] : ['labels' => [], 'data' => []];

$hasTrend = ($chartPayload['labels'] ?? []) !== [] && ($chartPayload['datasets'] ?? []) !== [];
$hasNavTrend = ($navChartPayload['labels'] ?? []) !== [] && ($navChartPayload['datasets'] ?? []) !== [];
$hasVisitorTrend = ($visitorChartPayload['labels'] ?? []) !== [] && ($visitorChartPayload['datasets'] ?? []) !== [];
$hasPageShare = array_sum(array_map('intval', $pageShare['data'] ?? [])) > 0;
$hasDeviceShare = array_sum(array_map('intval', $deviceShare['data'] ?? [])) > 0;
$hasLangShare = array_sum(array_map('intval', $langShare['data'] ?? [])) > 0;
$hasHour = array_sum(array_map('intval', $hourChart['data'] ?? [])) > 0;
$hasAnyChart = $hasTrend || $hasNavTrend || $hasVisitorTrend || $hasPageShare || $hasDeviceShare || $hasLangShare || $hasHour;

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$pageCatalog = events_public_traffic_page_catalog();
?>
<div class="card events-edit-stats events-public-traffic-stats">
    <p class="events-edit-stats__intro">
        A beégetett nyilvános oldalak (főoldal, naptár, eseménylista, DJ lista, szervezők, partnerek) megtekintései.
        A menükattintások a fejléc főmenüjét, a naptár/lista nézetváltót, a logót és a nyelvváltót mérik.
        Admin és partner munkamenetből nem számolunk.
    </p>

    <?php if (empty($statsData['table_ready'])): ?>
        <p class="alert alert-warning">A forgalom-tábla létrehozása nem sikerült. Ellenőrizd az adatbázis-jogosultságokat.</p>
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
                    <label class="events-filter-label" for="traf_date_from">Tól</label>
                    <input class="events-filter-input" type="date" name="stat_date_from" id="traf_date_from" value="<?= h((string) $statsParams['date_from']) ?>">
                </div>
                <div class="form-group">
                    <label class="events-filter-label" for="traf_date_to">Ig</label>
                    <input class="events-filter-input" type="date" name="stat_date_to" id="traf_date_to" value="<?= h((string) $statsParams['date_to']) ?>">
                </div>
                <div class="form-group">
                    <label class="events-filter-label" for="traf_page">Oldal</label>
                    <select class="events-filter-input" name="page" id="traf_page">
                        <option value="all"<?= $statsParams['page'] === 'all' ? ' selected' : '' ?>>Összes oldal</option>
                        <?php foreach ($pageCatalog as $pageKey => $meta): ?>
                            <option value="<?= h($pageKey) ?>"<?= $statsParams['page'] === $pageKey ? ' selected' : '' ?>><?= h((string) $meta['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="events-filter-label" for="traf_lang">Nyelv</label>
                    <select class="events-filter-input" name="traf_lang" id="traf_lang">
                        <option value="all"<?= $statsParams['lang'] === 'all' ? ' selected' : '' ?>>Összes</option>
                        <option value="hu"<?= $statsParams['lang'] === 'hu' ? ' selected' : '' ?>>Magyar</option>
                        <option value="en"<?= $statsParams['lang'] === 'en' ? ' selected' : '' ?>>Angol</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="events-filter-label" for="traf_visitor">Látogató</label>
                    <select class="events-filter-input" name="visitor" id="traf_visitor">
                        <option value="all"<?= $statsParams['visitor'] === 'all' ? ' selected' : '' ?>>Ember + bot</option>
                        <option value="human"<?= $statsParams['visitor'] === 'human' ? ' selected' : '' ?>>Csak ember</option>
                        <option value="bot"<?= $statsParams['visitor'] === 'bot' ? ' selected' : '' ?>>Csak bot</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="events-filter-label" for="traf_device">Eszköz</label>
                    <select class="events-filter-input" name="device" id="traf_device">
                        <option value="all"<?= $statsParams['device'] === 'all' ? ' selected' : '' ?>>Összes</option>
                        <option value="desktop"<?= $statsParams['device'] === 'desktop' ? ' selected' : '' ?>>Asztali</option>
                        <option value="mobile"<?= $statsParams['device'] === 'mobile' ? ' selected' : '' ?>>Mobil</option>
                        <option value="tablet"<?= $statsParams['device'] === 'tablet' ? ' selected' : '' ?>>Tablet</option>
                        <option value="unknown"<?= $statsParams['device'] === 'unknown' ? ' selected' : '' ?>>Ismeretlen</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="events-filter-label" for="traf_kind">Esemény</label>
                    <select class="events-filter-input" name="kind" id="traf_kind">
                        <option value="all"<?= $statsParams['kind'] === 'all' ? ' selected' : '' ?>>Megtekintés + menü</option>
                        <option value="page_view"<?= $statsParams['kind'] === 'page_view' ? ' selected' : '' ?>>Csak oldalmegtekintés</option>
                        <option value="nav_click"<?= $statsParams['kind'] === 'nav_click' ? ' selected' : '' ?>>Csak menükattintás</option>
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
                <p class="events-edit-stats__card-label">Oldalmegnyitás</p>
                <p class="events-edit-stats__card-value"><?= $pageHuman + $pageBot ?></p>
                <dl class="events-edit-stats__card-split">
                    <dt>Ember</dt>
                    <dt>Bot</dt>
                    <dd><?= $pageHuman ?></dd>
                    <dd><?= $pageBot ?></dd>
                </dl>
            </div>
            <div class="events-edit-stats__card">
                <p class="events-edit-stats__card-label">Egyedi látogató</p>
                <p class="events-edit-stats__card-value"><?= $uniqueHuman ?></p>
                <dl class="events-edit-stats__card-split">
                    <dt>Ember</dt>
                    <dt>Bot</dt>
                    <dd><?= $uniqueHuman ?></dd>
                    <dd><?= $uniqueBot ?></dd>
                </dl>
                <?php if ($pagePerUnique !== null): ?>
                    <p class="events-edit-stats__card-hint">≈ <?= h((string) $pagePerUnique) ?> megnyitás / egyedi ember</p>
                <?php endif; ?>
            </div>
            <div class="events-edit-stats__card">
                <p class="events-edit-stats__card-label">Menükattintás</p>
                <p class="events-edit-stats__card-value"><?= $navHuman + $navBot ?></p>
                <dl class="events-edit-stats__card-split">
                    <dt>Ember</dt>
                    <dt>Bot</dt>
                    <dd><?= $navHuman ?></dd>
                    <dd><?= $navBot ?></dd>
                </dl>
            </div>
            <div class="events-edit-stats__card">
                <p class="events-edit-stats__card-label">Nyelv</p>
                <p class="events-edit-stats__card-value"><?= $langHu + $langEn ?></p>
                <dl class="events-edit-stats__card-split">
                    <dt>HU</dt>
                    <dt>EN</dt>
                    <dd><?= $langHu ?></dd>
                    <dd><?= $langEn ?></dd>
                </dl>
            </div>
        </div>

        <?php if ($pageHuman + $pageBot + $navHuman + $navBot === 0): ?>
            <p class="help events-edit-stats__empty">
                Még nincs mért forgalom a választott szűrőkkel. A mérés a funkció élesítésétől gyűlik (főoldal, naptár, lista, DJ-k és a többi nyilvános oldal).
            </p>
        <?php endif; ?>

        <?php if ($hasTrend): ?>
            <div class="events-edit-stats__chart-wrap">
                <div class="events-edit-stats__chart-head">
                    <div>
                        <h3 class="events-edit-stats__chart-title">Oldalmegnyitások alakulása</h3>
                        <p class="events-edit-stats__chart-hint"><?= h(ucfirst($granularityLabel)) ?> bontás — emberi forgalom oldaltípusonként.</p>
                    </div>
                </div>
                <div class="events-edit-stats__chart-canvas">
                    <canvas id="public-traffic-pages-chart" aria-label="Oldalmegnyitások grafikonja"></canvas>
                </div>
            </div>
            <script type="application/json" id="public-traffic-pages-chart-data"><?= json_encode($chartPayload, $jsonFlags) ?></script>
        <?php endif; ?>

        <?php if ($hasNavTrend): ?>
            <div class="events-edit-stats__chart-wrap">
                <div class="events-edit-stats__chart-head">
                    <div>
                        <h3 class="events-edit-stats__chart-title">Menükattintások alakulása</h3>
                        <p class="events-edit-stats__chart-hint"><?= h(ucfirst($granularityLabel)) ?> bontás menüpontonként.</p>
                    </div>
                </div>
                <div class="events-edit-stats__chart-canvas">
                    <canvas id="public-traffic-nav-chart" aria-label="Menükattintások grafikonja"></canvas>
                </div>
            </div>
            <script type="application/json" id="public-traffic-nav-chart-data"><?= json_encode($navChartPayload, $jsonFlags) ?></script>
        <?php endif; ?>

        <?php if ($hasVisitorTrend): ?>
            <div class="events-edit-stats__chart-wrap">
                <div class="events-edit-stats__chart-head">
                    <div>
                        <h3 class="events-edit-stats__chart-title">Ember és bot</h3>
                        <p class="events-edit-stats__chart-hint">Oldalmegnyitások <?= h($granularityLabel) ?> bontásban.</p>
                    </div>
                </div>
                <div class="events-edit-stats__chart-canvas">
                    <canvas id="public-traffic-visitor-chart" aria-label="Ember és bot grafikon"></canvas>
                </div>
            </div>
            <script type="application/json" id="public-traffic-visitor-chart-data"><?= json_encode($visitorChartPayload, $jsonFlags) ?></script>
        <?php endif; ?>

        <div class="events-public-traffic-stats__chart-grid">
            <?php if ($hasPageShare): ?>
                <div class="events-edit-stats__chart-wrap">
                    <div class="events-edit-stats__chart-head">
                        <div>
                            <h3 class="events-edit-stats__chart-title">Oldalak aránya</h3>
                            <p class="events-edit-stats__chart-hint">Emberi megtekintés.</p>
                        </div>
                    </div>
                    <div class="events-edit-stats__chart-canvas events-public-traffic-stats__doughnut">
                        <canvas id="public-traffic-page-share" aria-label="Oldalak aránya"></canvas>
                    </div>
                </div>
                <script type="application/json" id="public-traffic-page-share-data"><?= json_encode($pageShare, $jsonFlags) ?></script>
            <?php endif; ?>
            <?php if ($hasDeviceShare): ?>
                <div class="events-edit-stats__chart-wrap">
                    <div class="events-edit-stats__chart-head">
                        <div>
                            <h3 class="events-edit-stats__chart-title">Eszközök</h3>
                            <p class="events-edit-stats__chart-hint">Oldalmegnyitás eszköz szerint.</p>
                        </div>
                    </div>
                    <div class="events-edit-stats__chart-canvas events-public-traffic-stats__doughnut">
                        <canvas id="public-traffic-device-share" aria-label="Eszközök aránya"></canvas>
                    </div>
                </div>
                <script type="application/json" id="public-traffic-device-share-data"><?= json_encode($deviceShare, $jsonFlags) ?></script>
            <?php endif; ?>
            <?php if ($hasLangShare): ?>
                <div class="events-edit-stats__chart-wrap">
                    <div class="events-edit-stats__chart-head">
                        <div>
                            <h3 class="events-edit-stats__chart-title">Nyelv</h3>
                            <p class="events-edit-stats__chart-hint">HU / EN oldalmegnyitás.</p>
                        </div>
                    </div>
                    <div class="events-edit-stats__chart-canvas events-public-traffic-stats__doughnut">
                        <canvas id="public-traffic-lang-share" aria-label="Nyelvek aránya"></canvas>
                    </div>
                </div>
                <script type="application/json" id="public-traffic-lang-share-data"><?= json_encode($langShare, $jsonFlags) ?></script>
            <?php endif; ?>
        </div>

        <?php if ($hasHour): ?>
            <div class="events-edit-stats__chart-wrap">
                <div class="events-edit-stats__chart-head">
                    <div>
                        <h3 class="events-edit-stats__chart-title">Óránkénti forgalom</h3>
                        <p class="events-edit-stats__chart-hint">Oldalmegnyitások a nap 24 órájában (szerveridő).</p>
                    </div>
                </div>
                <div class="events-edit-stats__chart-canvas">
                    <canvas id="public-traffic-hour-chart" aria-label="Óránkénti forgalom"></canvas>
                </div>
            </div>
            <script type="application/json" id="public-traffic-hour-chart-data"><?= json_encode($hourChart, $jsonFlags) ?></script>
        <?php endif; ?>

        <h3 class="events-edit-stats__events-title">Oldalanként</h3>
        <?php if ($pageRows === []): ?>
            <p class="help">Nincs oldalmegtekintés a szűrőkkel.</p>
        <?php else: ?>
            <div class="table-wrap events-admin-table-wrap">
                <table class="events-admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Oldal</th>
                            <th class="th-center" scope="col">Ember</th>
                            <th class="th-center" scope="col">Egyedi</th>
                            <th class="th-center" scope="col">Bot</th>
                            <th class="th-center" scope="col">Össz</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pageRows as $row): ?>
                            <tr>
                                <td><?= h((string) ($row['label'] ?? '')) ?></td>
                                <td class="text-center events-stats-cell--human"><?= (int) ($row['human_count'] ?? 0) ?></td>
                                <td class="text-center"><?= (int) ($row['unique_human'] ?? 0) ?></td>
                                <td class="text-center events-stats-cell--bot"><?= (int) ($row['bot_count'] ?? 0) ?></td>
                                <td class="text-center"><strong><?= (int) ($row['total'] ?? 0) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <h3 class="events-edit-stats__events-title">Menükattintások</h3>
        <?php if ($navRows === []): ?>
            <p class="help">Nincs menükattintás a szűrőkkel.</p>
        <?php else: ?>
            <div class="table-wrap events-admin-table-wrap">
                <table class="events-admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Menüpont</th>
                            <th class="th-center" scope="col">Ember</th>
                            <th class="th-center" scope="col">Egyedi</th>
                            <th class="th-center" scope="col">Bot</th>
                            <th class="th-center" scope="col">Össz</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($navRows as $row): ?>
                            <tr>
                                <td><?= h((string) ($row['label'] ?? '')) ?></td>
                                <td class="text-center events-stats-cell--human"><?= (int) ($row['human_count'] ?? 0) ?></td>
                                <td class="text-center"><?= (int) ($row['unique_human'] ?? 0) ?></td>
                                <td class="text-center events-stats-cell--bot"><?= (int) ($row['bot_count'] ?? 0) ?></td>
                                <td class="text-center"><strong><?= (int) ($row['total'] ?? 0) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <h3 class="events-edit-stats__events-title">Eszközök</h3>
        <?php if ($deviceRows === []): ?>
            <p class="help">Nincs eszközadat.</p>
        <?php else: ?>
            <div class="table-wrap events-admin-table-wrap">
                <table class="events-admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Eszköz</th>
                            <th class="th-center" scope="col">Ember</th>
                            <th class="th-center" scope="col">Bot</th>
                            <th class="th-center" scope="col">Össz</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deviceRows as $row): ?>
                            <tr>
                                <td><?= h((string) ($row['label'] ?? '')) ?></td>
                                <td class="text-center events-stats-cell--human"><?= (int) ($row['human_count'] ?? 0) ?></td>
                                <td class="text-center events-stats-cell--bot"><?= (int) ($row['bot_count'] ?? 0) ?></td>
                                <td class="text-center"><strong><?= (int) ($row['total'] ?? 0) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <h3 class="events-edit-stats__events-title">Források (referrer)</h3>
        <p class="events-edit-stats__events-hint">Külső domain, ahonnan az oldalmegnyitás jött. A saját domain üres (közvetlen).</p>
        <?php if ($referrerRows === []): ?>
            <p class="help">Nincs referrer adat.</p>
        <?php else: ?>
            <div class="table-wrap events-admin-table-wrap">
                <table class="events-admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Forrás</th>
                            <th class="th-center" scope="col">Ember</th>
                            <th class="th-center" scope="col">Bot</th>
                            <th class="th-center" scope="col">Össz</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($referrerRows as $row): ?>
                            <tr>
                                <td><?= h((string) ($row['host'] ?? '')) ?></td>
                                <td class="text-center events-stats-cell--human"><?= (int) ($row['human_count'] ?? 0) ?></td>
                                <td class="text-center events-stats-cell--bot"><?= (int) ($row['bot_count'] ?? 0) ?></td>
                                <td class="text-center"><strong><?= (int) ($row['total'] ?? 0) ?></strong></td>
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

                function makeDoughnut(canvasId, dataId) {
                    var canvas = document.getElementById(canvasId);
                    var payload = parseJson(dataId);
                    if (!canvas || !payload) return;
                    new Chart(canvas.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: payload.labels || [],
                            datasets: [{
                                data: payload.data || [],
                                backgroundColor: payload.colors || [],
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

                makeLine('public-traffic-pages-chart', 'public-traffic-pages-chart-data');
                makeLine('public-traffic-nav-chart', 'public-traffic-nav-chart-data');
                makeLine('public-traffic-visitor-chart', 'public-traffic-visitor-chart-data');
                makeDoughnut('public-traffic-page-share', 'public-traffic-page-share-data');
                makeDoughnut('public-traffic-device-share', 'public-traffic-device-share-data');
                makeDoughnut('public-traffic-lang-share', 'public-traffic-lang-share-data');

                var hourCanvas = document.getElementById('public-traffic-hour-chart');
                var hourPayload = parseJson('public-traffic-hour-chart-data');
                if (hourCanvas && hourPayload) {
                    new Chart(hourCanvas.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: hourPayload.labels || [],
                            datasets: [{
                                label: 'Oldalmegnyitás',
                                data: hourPayload.data || [],
                                backgroundColor: '#3d6b4f99',
                                borderColor: '#3d6b4f',
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
