<?php
declare(strict_types=1);

/**
 * Fejléc tip átkattintás-statisztika a főoldal-szerkesztőn.
 *
 * @var array{date_from: string, date_to: string, notice_id: int, lang: string, visitor: string} $noticeStatsParams
 * @var array{
 *   table_ready: bool,
 *   totals: array{
 *     clicks: int,
 *     clicks_human: int,
 *     clicks_bot: int,
 *     unique_human: int,
 *     versions_in_period: int,
 *     impressions?: int,
 *     impressions_human?: int,
 *     impressions_bot?: int
 *   },
 *   chart: array{labels: list<string>, datasets: list<array{label: string, data: list<int>, color: string, total: int}>},
 *   version_chart: array{labels: list<string>, data: list<int>, ids: list<int>},
 *   versions: list<array<string, mixed>>
 * } $noticeStatsData
 * @var list<array{id: int, notice_text: string, notice_text_en: string, notice_url: string, is_active?: bool, is_deleted?: bool}> $noticeVersionOptions
 * @var string $noticeStatsFormAction
 * @var list<array{id: string, label: string, url: string, active: bool}> $noticeStatsPresetLinks
 */

$noticeStatsParams = $noticeStatsParams ?? [
    'date_from' => '',
    'date_to' => '',
    'notice_id' => 0,
    'lang' => 'all',
    'visitor' => 'all',
];
$noticeStatsData = $noticeStatsData ?? [
    'table_ready' => false,
    'totals' => [
        'clicks' => 0,
        'clicks_human' => 0,
        'clicks_bot' => 0,
        'unique_human' => 0,
        'versions_in_period' => 0,
        'impressions' => 0,
        'impressions_human' => 0,
        'impressions_bot' => 0,
    ],
    'chart' => ['labels' => [], 'datasets' => []],
    'version_chart' => ['labels' => [], 'data' => [], 'ids' => []],
    'versions' => [],
];
$noticeVersionOptions = $noticeVersionOptions ?? [];
$noticeStatsFormAction = (string) ($noticeStatsFormAction ?? '');
$noticeStatsPresetLinks = $noticeStatsPresetLinks ?? [];

$chartPayload = $noticeStatsData['chart'] ?? ['labels' => [], 'datasets' => []];
$hasChart = ($chartPayload['labels'] ?? []) !== [] && ($chartPayload['datasets'] ?? []) !== [];
$chartJson = json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$versionChartPayload = $noticeStatsData['version_chart'] ?? ['labels' => [], 'data' => [], 'ids' => []];
$hasVersionChart = ($versionChartPayload['labels'] ?? []) !== []
    && array_sum(array_map('intval', $versionChartPayload['data'] ?? [])) > 0
    && count($versionChartPayload['labels'] ?? []) > 1;
$versionChartJson = json_encode($versionChartPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$totals = $noticeStatsData['totals'] ?? [];
$versionRows = $noticeStatsData['versions'] ?? [];
$selectedNotice = (int) ($noticeStatsParams['notice_id'] ?? 0);
$selectedLang = (string) ($noticeStatsParams['lang'] ?? 'all');
$selectedVisitor = (string) ($noticeStatsParams['visitor'] ?? 'all');
?>
<div class="card events-fooldal-notice-stats" id="notice-click-stats">
    <div class="events-list-head">
        <h2 class="events-list-title">Tip átkattintások</h2>
    </div>
    <p class="events-edit-stats__intro">
        A naptár főoldalán, a logó mellett megjelenő tipekre kattintások.
        A használatban lévő tipeket a látogatók felváltva kapják; minden tiphez külön számoljuk a megjelenítést és az átkattintást.
    </p>

    <?php if (empty($noticeStatsData['table_ready'])): ?>
        <p class="alert alert-warning">A statisztika táblák létrehozása nem sikerült. Ellenőrizd az adatbázis-jogosultságokat.</p>
    <?php else: ?>
        <form method="get" action="<?= h($noticeStatsFormAction) ?>#notice-click-stats" class="events-edit-stats__filters events-fooldal-notice-stats__filters">
            <div class="events-edit-stats__filter-grid">
                <div class="form-group">
                    <label class="events-filter-label" for="notice_stat_date_from">Időszak tól</label>
                    <input class="events-filter-input" type="date" name="stat_date_from" id="notice_stat_date_from" value="<?= h((string) $noticeStatsParams['date_from']) ?>">
                </div>
                <div class="form-group">
                    <label class="events-filter-label" for="notice_stat_date_to">Időszak ig</label>
                    <input class="events-filter-input" type="date" name="stat_date_to" id="notice_stat_date_to" value="<?= h((string) $noticeStatsParams['date_to']) ?>">
                </div>
                <div class="form-group">
                    <label class="events-filter-label" for="notice_tip">Tip</label>
                    <select class="events-filter-input" name="notice_tip" id="notice_tip">
                        <option value="0"<?= $selectedNotice === 0 ? ' selected' : '' ?>>Összes tip</option>
                        <?php foreach ($noticeVersionOptions as $opt): ?>
                            <?php
                            $labelText = trim((string) $opt['notice_text']);
                            if ($labelText === '') {
                                $labelText = trim((string) $opt['notice_text_en']);
                            }
                            if ($labelText === '') {
                                $labelText = '(üres tip)';
                            }
                            $optLabel = events_public_home_notice_truncate($labelText, 56);
                            if (!empty($opt['is_deleted'])) {
                                $optLabel = 'Törölt · ' . $optLabel;
                            } elseif (!empty($opt['is_active'])) {
                                $optLabel = 'Használatban · ' . $optLabel;
                            } else {
                                $optLabel = 'Kikapcsolva · ' . $optLabel;
                            }
                            ?>
                            <option value="<?= (int) $opt['id'] ?>"<?= $selectedNotice === (int) $opt['id'] ? ' selected' : '' ?>><?= h($optLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="events-filter-label" for="notice_lang">Nyelv</label>
                    <select class="events-filter-input" name="notice_lang" id="notice_lang">
                        <option value="all"<?= $selectedLang === 'all' ? ' selected' : '' ?>>Összes</option>
                        <option value="hu"<?= $selectedLang === 'hu' ? ' selected' : '' ?>>Magyar</option>
                        <option value="en"<?= $selectedLang === 'en' ? ' selected' : '' ?>>Angol</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="events-filter-label" for="notice_visitor">Látogató</label>
                    <select class="events-filter-input" name="notice_visitor" id="notice_visitor">
                        <option value="all"<?= $selectedVisitor === 'all' ? ' selected' : '' ?>>Összes</option>
                        <option value="human"<?= $selectedVisitor === 'human' ? ' selected' : '' ?>>Emberi</option>
                        <option value="bot"<?= $selectedVisitor === 'bot' ? ' selected' : '' ?>>Bot</option>
                    </select>
                </div>
                <div class="form-group events-edit-stats__filter-actions">
                    <button type="submit" class="btn btn-secondary btn-sm">Megjelenítés</button>
                    <a class="btn btn-secondary btn-sm" href="<?= h($noticeStatsFormAction) ?>#notice-click-stats">Szűrés törlése</a>
                </div>
            </div>
            <?php if ($noticeStatsPresetLinks !== []): ?>
                <div class="events-edit-stats__presets" role="group" aria-label="Gyors időszak">
                    <?php foreach ($noticeStatsPresetLinks as $presetLink): ?>
                        <a
                            class="btn btn-sm <?= !empty($presetLink['active']) ? 'btn-primary' : 'btn-secondary' ?>"
                            href="<?= h((string) $presetLink['url']) ?>"
                        ><?= h((string) $presetLink['label']) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </form>

        <div class="events-edit-stats__cards">
            <?php
            $impressionsHuman = (int) ($totals['impressions_human'] ?? 0);
            $clicksHumanCard = (int) ($totals['clicks_human'] ?? 0);
            $ctrLabel = '—';
            if ($impressionsHuman > 0) {
                $ctrLabel = rtrim(rtrim(number_format(($clicksHumanCard / $impressionsHuman) * 100, 1, ',', ' '), '0'), ',') . '%';
            }
            $statCards = [
                [
                    'label' => 'Emberi',
                    'value' => $clicksHumanCard,
                    'hint' => 'Átkattintás (nem bot)',
                ],
                [
                    'label' => 'Megjelenítés',
                    'value' => $impressionsHuman,
                    'hint' => 'Hányszor látták (emberi)',
                ],
                [
                    'label' => 'CTR',
                    'value' => $ctrLabel,
                    'hint' => 'Emberi átkattintás / megjelenítés',
                    'raw' => true,
                ],
                [
                    'label' => 'Bot',
                    'value' => (int) ($totals['clicks_bot'] ?? 0),
                    'hint' => 'Robot / crawler kattintás',
                ],
                [
                    'label' => 'Egyedi látogató',
                    'value' => (int) ($totals['unique_human'] ?? 0),
                    'hint' => 'Emberi, IP-hash alapján',
                ],
                [
                    'label' => 'Tipek',
                    'value' => (int) ($totals['versions_in_period'] ?? 0),
                    'hint' => 'A szűrt listában',
                ],
            ];
            foreach ($statCards as $card):
            ?>
                <div class="events-edit-stats__card">
                    <p class="events-edit-stats__card-label"><?= h((string) $card['label']) ?></p>
                    <p class="events-edit-stats__card-value"><?= !empty($card['raw']) ? h((string) $card['value']) : (int) $card['value'] ?></p>
                    <p class="events-edit-stats__card-hint"><?= h((string) $card['hint']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($hasChart): ?>
            <div class="events-edit-stats__chart-wrap">
                <div class="events-edit-stats__chart-head">
                    <div>
                        <h3 class="events-edit-stats__chart-title">Napi átkattintások</h3>
                        <p class="events-edit-stats__chart-hint">A választott időszak és szűrők szerint.</p>
                    </div>
                </div>
                <div class="events-edit-stats__chart-canvas">
                    <canvas id="fooldal-notice-clicks-chart" aria-label="Tip átkattintások grafikonja"></canvas>
                </div>
            </div>
            <script type="application/json" id="fooldal-notice-clicks-chart-data"><?= $chartJson ?></script>
        <?php else: ?>
            <p class="help events-edit-stats__empty">Nincs naplózott átkattintás a választott szűrőkkel.</p>
        <?php endif; ?>

        <?php if ($hasVersionChart): ?>
            <div class="events-edit-stats__chart-wrap events-fooldal-notice-stats__version-chart">
                <div class="events-edit-stats__chart-head">
                    <div>
                        <h3 class="events-edit-stats__chart-title">Tipenként</h3>
                        <p class="events-edit-stats__chart-hint">Melyik használatban lévő (vagy korábbi) tipre mennyit kattintottak az időszakban.</p>
                    </div>
                </div>
                <div class="events-edit-stats__chart-canvas events-fooldal-notice-stats__version-canvas">
                    <canvas id="fooldal-notice-versions-chart" aria-label="Tip szövegek átkattintásai"></canvas>
                </div>
            </div>
            <script type="application/json" id="fooldal-notice-versions-chart-data"><?= $versionChartJson ?></script>
        <?php endif; ?>

        <h3 class="events-fooldal-notice-stats__table-title">Tipenként</h3>
        <?php if ($versionRows === []): ?>
            <p class="help">Nincs tip a választott szűrőkkel.</p>
        <?php else: ?>
            <div class="table-wrap events-admin-table-wrap">
                <table class="events-admin-table events-fooldal-notice-stats__table">
                    <thead>
                        <tr>
                            <th scope="col">Állapot</th>
                            <th scope="col">Magyar szöveg</th>
                            <th scope="col">Angol szöveg</th>
                            <th scope="col">URL</th>
                            <th class="th-center" scope="col">Megjelenítés</th>
                            <th class="th-center" scope="col">Ember</th>
                            <th class="th-center" scope="col">CTR</th>
                            <th class="th-center" scope="col">Bot</th>
                            <th class="th-center" scope="col">Össz</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($versionRows as $row): ?>
                            <?php
                            $url = trim((string) ($row['notice_url'] ?? ''));
                            $isActive = !empty($row['is_active']) || !empty($row['is_current']);
                            $isDeleted = !empty($row['is_deleted']);
                            $imprHuman = (int) ($row['impressions_human'] ?? 0);
                            $ctr = $row['ctr_human'] ?? null;
                            ?>
                            <tr class="<?= $isActive ? 'is-current' : '' ?>">
                                <td class="events-fooldal-notice-stats__period">
                                    <?php if ($isDeleted): ?>
                                        <span class="events-fooldal-notice-stats__badge">Törölt</span>
                                    <?php elseif ($isActive): ?>
                                        <span class="events-fooldal-notice-stats__badge">Használatban</span>
                                    <?php else: ?>
                                        <span class="events-fooldal-notice-stats__badge">Kikapcsolva</span>
                                    <?php endif; ?>
                                </td>
                                <td class="events-fooldal-notice-stats__text"><?php
                                    $huText = trim((string) ($row['notice_text'] ?? ''));
                                    echo $huText !== '' ? h($huText) : '<span class="text-muted">—</span>';
                                ?></td>
                                <td class="events-fooldal-notice-stats__text"><?php
                                    $enText = trim((string) ($row['notice_text_en'] ?? ''));
                                    echo $enText !== '' ? h($enText) : '<span class="text-muted">—</span>';
                                ?></td>
                                <td class="events-fooldal-notice-stats__url">
                                    <?php if ($url !== ''): ?>
                                        <a href="<?= h($url) ?>" target="_blank" rel="noopener noreferrer"><?= h(events_public_home_notice_truncate($url, 42)) ?></a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="th-center"><?= $imprHuman ?></td>
                                <td class="th-center"><?= (int) ($row['clicks_human'] ?? 0) ?></td>
                                <td class="th-center"><?php
                                    if ($ctr === null || $imprHuman <= 0) {
                                        echo '<span class="text-muted">—</span>';
                                    } else {
                                        echo h(rtrim(rtrim(number_format((float) $ctr, 1, ',', ' '), '0'), ',') . '%');
                                    }
                                ?></td>
                                <td class="th-center"><?= (int) ($row['clicks_bot'] ?? 0) ?></td>
                                <td class="th-center"><strong><?= (int) ($row['clicks'] ?? 0) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($hasChart || $hasVersionChart): ?>
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js" crossorigin="anonymous"></script>
            <script>
            (function () {
                if (typeof Chart === 'undefined') return;

                var dailyEl = document.getElementById('fooldal-notice-clicks-chart-data');
                var dailyCanvas = document.getElementById('fooldal-notice-clicks-chart');
                if (dailyEl && dailyCanvas) {
                    var dailyPayload;
                    try { dailyPayload = JSON.parse(dailyEl.textContent || '{}'); } catch (e) { dailyPayload = null; }
                    if (dailyPayload) {
                        var labels = dailyPayload.labels || [];
                        var datasets = (dailyPayload.datasets || []).map(function (ds) {
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
                        new Chart(dailyCanvas.getContext('2d'), {
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
                                    }
                                },
                                scales: {
                                    x: { ticks: { maxRotation: 45, minRotation: 0, autoSkip: true, maxTicksLimit: 20 } },
                                    y: { beginAtZero: true, ticks: { precision: 0 } }
                                }
                            }
                        });
                    }
                }

                var verEl = document.getElementById('fooldal-notice-versions-chart-data');
                var verCanvas = document.getElementById('fooldal-notice-versions-chart');
                if (verEl && verCanvas) {
                    var verPayload;
                    try { verPayload = JSON.parse(verEl.textContent || '{}'); } catch (e) { verPayload = null; }
                    if (verPayload) {
                        var colors = ['#3d6b4f', '#c4a35a', '#4f6d8a', '#8a4f6d', '#5a8a6a', '#6d5a8a', '#8a6d4f'];
                        new Chart(verCanvas.getContext('2d'), {
                            type: 'bar',
                            data: {
                                labels: verPayload.labels || [],
                                datasets: [{
                                    label: 'Átkattintás',
                                    data: verPayload.data || [],
                                    backgroundColor: (verPayload.labels || []).map(function (_, i) {
                                        return colors[i % colors.length];
                                    })
                                }]
                            },
                            options: {
                                indexAxis: 'y',
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { display: false } },
                                scales: {
                                    x: { beginAtZero: true, ticks: { precision: 0 } }
                                }
                            }
                        });
                    }
                }
            })();
            </script>
        <?php endif; ?>
    <?php endif; ?>
</div>
