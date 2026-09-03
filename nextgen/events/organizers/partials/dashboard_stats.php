<?php
declare(strict_types=1);
/** @var array{date_from: string, date_to: string} $statsParams */
/** @var array<string, mixed> $statsData */
/** @var list<array<string, mixed>> $statsEventRows */
/** @var string $statsFormAction */
/** @var string $statsChartDomId */
/** @var string|null $statsAllDateFrom */
/** @var array<string, scalar|null> $statsFilterExtraQuery */

$statsAllDateFrom = $statsAllDateFrom ?? null;
$statsFilterExtraQuery = $statsFilterExtraQuery ?? [];
$statsPreset30 = events_edit_stats_range_for_preset('30');
$statsPresetYear = events_edit_stats_range_for_preset('year');
$statsPresetAll = events_edit_stats_range_for_preset('all', $statsAllDateFrom);
$statsActivePreset = events_edit_stats_detect_preset($statsParams, $statsAllDateFrom);
$statsPresetUrl30 = events_edit_stats_filter_url($statsFormAction, $statsPreset30, $statsFilterExtraQuery);
$statsPresetUrlYear = events_edit_stats_filter_url($statsFormAction, $statsPresetYear, $statsFilterExtraQuery);
$statsPresetUrlAll = events_edit_stats_filter_url($statsFormAction, $statsPresetAll, $statsFilterExtraQuery);

$chartPayload = $statsData['chart'] ?? ['labels' => [], 'datasets' => [], 'modes' => []];
$hasChart = ($chartPayload['labels'] ?? []) !== []
    && (
        ($chartPayload['modes']['human']['datasets'] ?? []) !== []
        || ($chartPayload['datasets'] ?? []) !== []
    );
$chartJson = json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$totals = $statsData['totals'] ?? [];
$eventsInPeriod = (int) ($totals['events_in_period'] ?? 0);
$eventsOpened = (int) ($totals['events_opened'] ?? $totals['events_with_views'] ?? 0);
$uniqueHuman = (int) ($totals['unique_visitors_human'] ?? $totals['unique_visitors'] ?? 0);
$uniqueBot = (int) ($totals['unique_visitors_bot'] ?? 0);
$pageHuman = (int) ($totals['page_views_human'] ?? 0);
$pageBot = (int) ($totals['page_views_bot'] ?? 0);
$previewHuman = (int) ($totals['calendar_previews_human'] ?? 0);
$previewBot = (int) ($totals['calendar_previews_bot'] ?? 0);
$externalHuman = (int) ($totals['external_info_clicks_human'] ?? 0);
$externalBot = (int) ($totals['external_info_clicks_bot'] ?? 0);
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

$botHelpSuffix = ' A botok User-Agent alapján kerülnek jelölésre (keresőrobotok, AI search, közösségi előnézet, scraperek).';

$statsCardHelp = [
    'Események' => 'Időszakban: azok az események, amelyek naptár-dátuma (kezdés–vég) a választott időszakba esik. Összes: ahány eseménynél volt legalább egy megtekintés, előnézet vagy további info kattintás ugyanebben az időszakban (megnyitottak).',
    'Egyedi látogató' => 'Különböző IP-címek száma oldalmegtekintés alapján, emberi és bot bontásban.' . $botHelpSuffix,
    'Oldal' => 'A nyilvános eseményoldal megnyitásainak száma emberi és bot bontásban a választott időszakban.' . $botHelpSuffix,
    'Előnézet' => 'A naptárban vagy listában megnyitott előnézet-panelek száma emberi és bot bontásban.' . $botHelpSuffix,
    'További info' => 'A „További információ” / külső link átkattintások száma emberi és bot bontásban.' . $botHelpSuffix,
];

$renderStatsCardHelp = static function (string $label) use ($statsCardHelp): void {
    $help = $statsCardHelp[$label] ?? '';
    if ($help === '') {
        return;
    }
    $helpId = 'stats-help-' . substr(sha1($label), 0, 10);
    ?>
    <span class="events-edit-stats__info">
        <button
            type="button"
            class="events-edit-stats__info-btn"
            aria-label="Segítség: <?= h($label) ?>"
            aria-expanded="false"
            aria-controls="<?= h($helpId) ?>"
        >i</button>
        <span class="events-edit-stats__info-popover" id="<?= h($helpId) ?>" role="tooltip" hidden><?= h($help) ?></span>
    </span>
    <?php
};

$renderSplit = static function (string $humanLabel, int $human, string $botLabel, int $bot): void {
    ?>
    <dl class="events-edit-stats__card-split">
        <div>
            <dt><?= h($humanLabel) ?></dt>
            <dd><?= $human ?></dd>
        </div>
        <div>
            <dt><?= h($botLabel) ?></dt>
            <dd><?= $bot ?></dd>
        </div>
    </dl>
    <?php
};
?>
<div class="card events-edit-stats events-edit-stats--organizer">
    <h2 class="card-title">Statisztika</h2>
    <p class="events-edit-stats__intro">Az eseményeid naptár előnézet, további információ kattintás és oldalmegtekintés adatai a választott időszakban.</p>

    <form method="get" action="<?= h($statsFormAction) ?>" class="events-edit-stats__filters">
        <div class="events-edit-stats__presets-row">
            <span class="events-filter-label">Gyors időszak</span>
            <div class="events-edit-stats__presets" role="group" aria-label="Gyors időszak">
                <a
                    class="btn btn-sm <?= $statsActivePreset === '30' ? 'btn-primary' : 'btn-secondary' ?>"
                    href="<?= h($statsPresetUrl30) ?>"
                >Utolsó 30 nap</a>
                <a
                    class="btn btn-sm <?= $statsActivePreset === 'year' ? 'btn-primary' : 'btn-secondary' ?>"
                    href="<?= h($statsPresetUrlYear) ?>"
                >Utolsó 1 év</a>
                <a
                    class="btn btn-sm <?= $statsActivePreset === 'all' ? 'btn-primary' : 'btn-secondary' ?>"
                    href="<?= h($statsPresetUrlAll) ?>"
                >Összes</a>
            </div>
        </div>
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
            </div>
        </div>
    </form>

    <div class="events-edit-stats__cards">
        <div class="events-edit-stats__card">
            <p class="events-edit-stats__card-label-wrap">
                <span class="events-edit-stats__card-label">Események</span>
                <?php $renderStatsCardHelp('Események'); ?>
            </p>
            <?php $renderSplit('Időszakban', $eventsInPeriod, 'Összes', $eventsOpened); ?>
        </div>
        <div class="events-edit-stats__card">
            <p class="events-edit-stats__card-label-wrap">
                <span class="events-edit-stats__card-label">Egyedi látogató</span>
                <?php $renderStatsCardHelp('Egyedi látogató'); ?>
            </p>
            <?php $renderSplit('Ember', $uniqueHuman, 'Bot (AI Search stb.)', $uniqueBot); ?>
        </div>
        <div class="events-edit-stats__card">
            <p class="events-edit-stats__card-label-wrap">
                <span class="events-edit-stats__card-label">Oldal</span>
                <?php $renderStatsCardHelp('Oldal'); ?>
            </p>
            <?php $renderSplit('Ember', $pageHuman, 'Bot (AI Search stb.)', $pageBot); ?>
        </div>
        <div class="events-edit-stats__card">
            <p class="events-edit-stats__card-label-wrap">
                <span class="events-edit-stats__card-label">Előnézet</span>
                <?php $renderStatsCardHelp('Előnézet'); ?>
            </p>
            <?php $renderSplit('Ember', $previewHuman, 'Bot (AI Search stb.)', $previewBot); ?>
        </div>
        <div class="events-edit-stats__card">
            <p class="events-edit-stats__card-label-wrap">
                <span class="events-edit-stats__card-label">További info</span>
                <?php $renderStatsCardHelp('További info'); ?>
            </p>
            <?php $renderSplit('Ember', $externalHuman, 'Bot (AI Search stb.)', $externalBot); ?>
        </div>
    </div>
    <script>
    (function () {
        var infos = document.querySelectorAll('.events-edit-stats--organizer .events-edit-stats__info');
        if (!infos.length) return;

        function closeAll(except) {
            infos.forEach(function (info) {
                if (except && info === except) return;
                var btn = info.querySelector('.events-edit-stats__info-btn');
                var pop = info.querySelector('.events-edit-stats__info-popover');
                if (!btn || !pop) return;
                btn.setAttribute('aria-expanded', 'false');
                pop.hidden = true;
                info.classList.remove('is-open');
            });
        }

        infos.forEach(function (info) {
            var btn = info.querySelector('.events-edit-stats__info-btn');
            var pop = info.querySelector('.events-edit-stats__info-popover');
            if (!btn || !pop) return;

            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var open = info.classList.contains('is-open');
                closeAll();
                if (!open) {
                    info.classList.add('is-open');
                    btn.setAttribute('aria-expanded', 'true');
                    pop.hidden = false;
                }
            });
        });

        document.addEventListener('click', function () {
            closeAll();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeAll();
        });
    })();
    </script>

    <?php if ($hasChart): ?>
        <div class="events-edit-stats__chart-wrap">
            <div class="events-edit-stats__chart-head">
                <div>
                    <h3 class="events-edit-stats__chart-title">Megtekintések alakulása</h3>
                    <p class="events-edit-stats__chart-hint" id="<?= h($statsChartDomId) ?>-hint">Napi bontás — alapértelmezés: emberi forgalom.</p>
                </div>
                <fieldset class="events-edit-stats__chart-mode" id="<?= h($statsChartDomId) ?>-mode">
                    <legend class="visually-hidden">Grafikon mód</legend>
                    <label class="events-org-stats-radio">
                        <input type="radio" name="<?= h($statsChartDomId) ?>_mode" value="human" checked>
                        Ember
                    </label>
                    <label class="events-org-stats-radio">
                        <input type="radio" name="<?= h($statsChartDomId) ?>_mode" value="total">
                        Összes
                    </label>
                </fieldset>
            </div>
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
            var modeWrap = document.getElementById(<?= json_encode($statsChartDomId . '-mode', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
            var hintEl = document.getElementById(<?= json_encode($statsChartDomId . '-hint', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
            if (!dataEl || !canvas || typeof Chart === 'undefined') return;
            var payload;
            try { payload = JSON.parse(dataEl.textContent || '{}'); } catch (e) { return; }
            var labels = payload.labels || [];
            var modes = payload.modes || {};
            var chart = null;

            function mapDatasets(list) {
                return (list || []).map(function (ds) {
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
            }

            function datasetsForMode(mode) {
                if (modes[mode] && modes[mode].datasets && modes[mode].datasets.length) {
                    return mapDatasets(modes[mode].datasets);
                }
                return mapDatasets(payload.datasets || []);
            }

            function currentMode() {
                if (!modeWrap) return 'human';
                var checked = modeWrap.querySelector('input[type="radio"]:checked');
                return checked ? checked.value : 'human';
            }

            function render() {
                var mode = currentMode();
                if (hintEl) {
                    hintEl.textContent = mode === 'total'
                        ? 'Napi bontás — emberi + bot együtt (összes).'
                        : 'Napi bontás — csak emberi forgalom.';
                }
                var datasets = datasetsForMode(mode);
                if (!chart) {
                    chart = new Chart(canvas.getContext('2d'), {
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
                    return;
                }
                chart.data.datasets = datasets;
                chart.update();
            }

            if (modeWrap) {
                modeWrap.addEventListener('change', render);
            }
            render();
        })();
        </script>
    <?php else: ?>
        <p class="help events-edit-stats__empty">Nincs naplózott megtekintés a választott időszakban.</p>
    <?php endif; ?>

    <h3 class="events-edit-stats__events-title">Események</h3>
    <p class="events-edit-stats__events-hint"><?= $statsPreferPartnerLinks
        ? 'Kattints az eseményre a partner részletekhez. A „Napok kint” a közzétett oldal napjait mutatja a választott időszakban (ha később került fel, kevesebb nap).'
        : 'Alapból az időszakban megtekintéssel rendelkező események. A „Napok kint” a közzétett oldal napjait mutatja a választott időszakban (ha később került fel, kevesebb nap).' ?></p>

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
                        <label class="events-filter-label" for="org_stats_filter_min_page">Min. oldal (össz)</label>
                        <input class="events-filter-input" type="number" id="org_stats_filter_min_page" min="0" step="1" placeholder="0">
                    </div>
                    <div class="form-group">
                        <label class="events-filter-label" for="org_stats_filter_min_preview">Min. előnézet (össz)</label>
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
            <table class="sortable-table events-admin-table events-edit-stats__events-table" id="organizer-stats-events-table">
                <thead>
                    <tr>
                        <th scope="col">
                            <button type="button" class="th-sort" data-sort="date" aria-pressed="false">Dátum</button>
                        </th>
                        <th scope="col">
                            <button type="button" class="th-sort" data-sort="name" aria-pressed="false">Név</button>
                        </th>
                        <th scope="col">
                            <button type="button" class="th-sort" data-sort="status" aria-pressed="false">Státusz</button>
                        </th>
                        <th class="th-center" scope="col" title="Hány napig volt közzétéve az oldal a választott időszakban">
                            <button type="button" class="th-sort" data-sort="live_days" aria-pressed="false">Napok kint</button>
                        </th>
                        <th class="th-center" scope="col" title="Egyedi emberi oldal-látogató (IP)">
                            <button type="button" class="th-sort" data-sort="unique_human" aria-pressed="false">Egyedi ember</button>
                        </th>
                        <th class="th-center" scope="col" title="Egyedi bot oldal-látogató (IP)">
                            <button type="button" class="th-sort" data-sort="unique_bot" aria-pressed="false">Egyedi bot</button>
                        </th>
                        <th class="th-center" scope="col" title="Oldal — emberi">
                            <button type="button" class="th-sort" data-sort="page_human" aria-pressed="false">Oldal ember</button>
                        </th>
                        <th class="th-center" scope="col" title="Oldal — bot">
                            <button type="button" class="th-sort" data-sort="page_bot" aria-pressed="false">Oldal bot</button>
                        </th>
                        <th class="th-center" scope="col" title="Előnézet — emberi">
                            <button type="button" class="th-sort" data-sort="preview_human" aria-pressed="false">Előnézet ember</button>
                        </th>
                        <th class="th-center" scope="col" title="Előnézet — bot">
                            <button type="button" class="th-sort" data-sort="preview_bot" aria-pressed="false">Előnézet bot</button>
                        </th>
                        <th class="th-center" scope="col" title="További info átkattintás — emberi">
                            <button type="button" class="th-sort" data-sort="external_human" aria-pressed="false">Átkatt. ember</button>
                        </th>
                        <th class="th-center" scope="col" title="További info átkattintás — bot">
                            <button type="button" class="th-sort" data-sort="external_bot" aria-pressed="false">Átkatt. bot</button>
                        </th>
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
                        $externalCounts = function_exists('events_view_metric_counts_from_row')
                            ? events_view_metric_counts_from_row($row, 'tovabbi_info_kattintasok')
                            : ['human' => (int) ($row['tovabbi_info_kattintasok'] ?? 0), 'bot' => 0, 'total' => (int) ($row['tovabbi_info_kattintasok'] ?? 0)];
                        $uniqueHumanRow = (int) ($row['egyedi_latogatok_human'] ?? $row['egyedi_latogatok'] ?? 0);
                        $uniqueBotRow = (int) ($row['egyedi_latogatok_bot'] ?? 0);
                        $liveDays = (int) ($row['live_days'] ?? 0);
                        $pageViews = (int) $pageCounts['total'];
                        $previewViews = (int) $previewCounts['total'];
                        $externalClicks = (int) $externalCounts['total'];
                        $hasViews = ($pageViews + $previewViews + $externalClicks) > 0 ? '1' : '0';
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
                            data-live-days="<?= $liveDays ?>"
                            data-page-views="<?= $pageViews ?>"
                            data-page-human="<?= (int) $pageCounts['human'] ?>"
                            data-page-bot="<?= (int) $pageCounts['bot'] ?>"
                            data-preview-views="<?= $previewViews ?>"
                            data-preview-human="<?= (int) $previewCounts['human'] ?>"
                            data-preview-bot="<?= (int) $previewCounts['bot'] ?>"
                            data-external-clicks="<?= $externalClicks ?>"
                            data-external-human="<?= (int) $externalCounts['human'] ?>"
                            data-external-bot="<?= (int) $externalCounts['bot'] ?>"
                            data-unique-human="<?= $uniqueHumanRow ?>"
                            data-unique-bot="<?= $uniqueBotRow ?>"
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
                            <td class="text-center"><?= $liveDays ?></td>
                            <td class="text-center"><?= $uniqueHumanRow ?></td>
                            <td class="text-center"><?= $uniqueBotRow ?></td>
                            <td class="text-center"><?= (int) $pageCounts['human'] ?></td>
                            <td class="text-center"><?= (int) $pageCounts['bot'] ?></td>
                            <td class="text-center"><?= (int) $previewCounts['human'] ?></td>
                            <td class="text-center"><?= (int) $previewCounts['bot'] ?></td>
                            <td class="text-center"><?= (int) $externalCounts['human'] ?></td>
                            <td class="text-center"><?= (int) $externalCounts['bot'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr id="organizer-stats-events-empty" hidden>
                        <td colspan="12" class="events-org-stats-list-empty">Nincs találat a szűrőkre.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <script>
        (function () {
            var controls = document.getElementById('organizer-stats-list-controls');
            var table = document.getElementById('organizer-stats-events-table');
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
            var sortKey = 'date';
            var sortDir = 'desc';

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

            var numericSortKeys = {
                live_days: 'data-live-days',
                unique_human: 'data-unique-human',
                unique_bot: 'data-unique-bot',
                page_human: 'data-page-human',
                page_bot: 'data-page-bot',
                preview_human: 'data-preview-human',
                preview_bot: 'data-preview-bot',
                external_human: 'data-external-human',
                external_bot: 'data-external-bot',
                preview: 'data-preview-views',
                external: 'data-external-clicks',
                unique: 'data-unique-human',
                page: 'data-page-views'
            };

            function sortValue(row, key) {
                if (key === 'date') {
                    return row.getAttribute('data-event-start') || '';
                }
                if (key === 'name') {
                    return row.getAttribute('data-search') || '';
                }
                if (key === 'status') {
                    return row.getAttribute('data-status') || '';
                }
                if (Object.prototype.hasOwnProperty.call(numericSortKeys, key)) {
                    return parseInt(row.getAttribute(numericSortKeys[key]) || '0', 10);
                }
                return '';
            }

            function updateSortHeaders() {
                if (!table) return;
                var buttons = table.querySelectorAll('thead .th-sort[data-sort]');
                buttons.forEach(function (btn) {
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
                        var sa = String(va);
                        var sb = String(vb);
                        if (sa === '' && sb !== '') cmp = 1;
                        else if (sa !== '' && sb === '') cmp = -1;
                        else cmp = sa.localeCompare(sb, 'hu', { sensitivity: 'base', numeric: true });
                    }
                    if (cmp === 0) {
                        cmp = (a.getAttribute('data-search') || '').localeCompare(
                            b.getAttribute('data-search') || '',
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

            function syncFiltersPanel() {
                if (filtersPanel) {
                    filtersPanel.hidden = getFilterMode() !== 'filtered';
                }
            }

            if (table) {
                table.addEventListener('click', function (e) {
                    var btn = e.target.closest('.th-sort[data-sort]');
                    if (!btn || !table.contains(btn)) return;
                    e.preventDefault();
                    var key = btn.getAttribute('data-sort') || '';
                    if (key === '') return;
                    if (sortKey === key) {
                        sortDir = sortDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        sortKey = key;
                        sortDir = (key === 'name' || key === 'status') ? 'asc' : 'desc';
                    }
                    applySort();
                    applyFilters();
                });
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
            updateSortHeaders();
            applyFilters();
        })();
        </script>
    <?php endif; ?>
</div>
