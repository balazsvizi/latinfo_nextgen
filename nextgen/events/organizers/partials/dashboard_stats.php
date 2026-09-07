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
$statsPreferPartnerLinks = !empty($statsPreferPartnerLinks);
$statsShowEventRowActions = !empty($statsShowEventRowActions);
$statsHideEventsList = !empty($statsHideEventsList);
$statsFormExtraHtml = is_string($statsFormExtraHtml ?? null) ? (string) $statsFormExtraHtml : '';
/** @var array{enabled?: bool, event_id?: int, ajax_url?: string, ymd_labels?: list<string>}|null $statsDayDrilldown */
$statsDayDrilldown = is_array($statsDayDrilldown ?? null) ? $statsDayDrilldown : null;
$statsDayDrillEnabled = !empty($statsDayDrilldown['enabled'])
    && (int) ($statsDayDrilldown['event_id'] ?? 0) > 0
    && trim((string) ($statsDayDrilldown['ajax_url'] ?? '')) !== '';
$statsDayDrillEventId = (int) ($statsDayDrilldown['event_id'] ?? 0);
$statsDayDrillAjaxUrl = trim((string) ($statsDayDrilldown['ajax_url'] ?? ''));
$statsDayDrillYmdLabels = array_values(array_filter(
    array_map('strval', $statsDayDrilldown['ymd_labels'] ?? []),
    static fn (string $d): bool => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1
));
$statsExposeChartApi = !empty($statsExposeChartApi);
$statsPageTitle = $statsPageTitle ?? 'Statisztika';
$statsIntro = $statsIntro ?? 'Az eseményeid naptár előnézet, további információ kattintás és oldalmegtekintés adatai a választott időszakban.';
$statsEmptyEventsMessage = $statsEmptyEventsMessage ?? 'Nincs közzétett eseményed.';
$statsEventListHint = $statsEventListHint ?? ($statsPreferPartnerLinks
    ? 'Kattints az eseményre a partner részletekhez. A „Napok kint” a közzétett oldal napjait mutatja a választott időszakban (ha később került fel, kevesebb nap).'
    : 'Alapból az időszakban megtekintéssel rendelkező események. A „Napok kint” a közzétett oldal napjait mutatja a választott időszakban (ha később került fel, kevesebb nap).');
$statsActivePreset = events_edit_stats_detect_preset($statsParams, $statsAllDateFrom);
$statsMode = events_edit_stats_normalize_mode($statsParams['mode'] ?? 'smart');
$statsCustomRates = !empty($statsParams['custom_rates']);
[$statsPageUnitFt, $statsClickUnitFt] = events_edit_stats_resolve_media_units($statsParams);
$statsAllowCustomMediaRates = $statsAllowCustomMediaRates ?? true;
$statsLogMediaValueTrialPartnerId = (int) ($statsLogMediaValueTrialPartnerId ?? 0);
$statsMediaValueTrialContextLabel = is_string($statsMediaValueTrialContextLabel ?? null)
    ? (string) $statsMediaValueTrialContextLabel
    : null;
$statsFilterQueryWithMode = array_merge($statsFilterExtraQuery, [
    'stat_mode' => $statsMode,
]);
if ($statsCustomRates) {
    $statsFilterQueryWithMode['stat_custom_rates'] = '1';
    $statsFilterQueryWithMode['stat_page_ft'] = $statsPageUnitFt;
    $statsFilterQueryWithMode['stat_click_ft'] = $statsClickUnitFt;
}
$statsPresetLinks = [];
foreach (events_edit_stats_presets() as $preset) {
    $presetId = (string) $preset['id'];
    $statsPresetLinks[] = [
        'id' => $presetId,
        'label' => (string) $preset['label'],
        'url' => events_edit_stats_filter_url(
            $statsFormAction,
            events_edit_stats_range_for_preset($presetId, $statsAllDateFrom),
            $statsFilterQueryWithMode
        ),
        'active' => $statsActivePreset === $presetId,
    ];
}

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
    'Egyedi látogató' => 'Különböző IP-címek száma az oldalmegnyitásokból (nem érdeklődők száma). Emberi és bot bontás.' . $botHelpSuffix,
    'Oldalmegnyitás' => 'A nyilvános eseményoldal betöltéseinek száma (minden frissítés / visszalépés számít). Nem egyenlő az érdeklődők számával.' . $botHelpSuffix,
    'Előnézet' => 'A naptárban vagy listában megnyitott előnézet-panelek száma emberi és bot bontásban.' . $botHelpSuffix,
    'További info' => 'A „További információ” / külső link átkattintások száma emberi és bot bontásban.' . $botHelpSuffix,
    'Generált médiaérték' => 'Becsült reklámérték emberi forgalom alapján, a leszűrt időszakra.'
        . ' Oldalmegnyitás (Detail Page View): a részletes eseményoldal megnyitása — '
        . events_edit_stats_media_value_page_view_ft() . ' Ft / megtekintés (elfogadható tartomány: 60–120 Ft).'
        . ' További info (Click-out): átkattintás a szervező Facebook-eseményére — '
        . events_edit_stats_media_value_intent_click_ft() . ' Ft / kattintás (elfogadható tartomány: 50–100 Ft).'
        . ' Képlet: (oldalmegnyitások × egységár) + (további info kattintások × egységár).'
        . ' A benchmark magyar Facebook CPC / landing page view piaci árakon alapul.'
        . ($statsCustomRates
            ? ' Most saját egységárakkal számol: ' . $statsPageUnitFt . ' Ft / megtekintés és ' . $statsClickUnitFt . ' Ft / átkattintás.'
            : ''),
];

$pagePerUniqueHuman = ($uniqueHuman > 0 && $pageHuman > 0)
    ? round($pageHuman / $uniqueHuman, 1)
    : null;

$mediaValue = events_edit_stats_media_value(
    $pageHuman,
    $externalHuman,
    $statsCustomRates ? $statsPageUnitFt : null,
    $statsCustomRates ? $statsClickUnitFt : null
);

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
        <span
            class="events-edit-stats__info-popover<?= $label === 'Generált médiaérték' ? ' events-edit-stats__info-popover--wide' : '' ?>"
            id="<?= h($helpId) ?>"
            role="tooltip"
            hidden
        ><?= h($help) ?></span>
    </span>
    <?php
};

$renderSplit = static function (
    string $leftLabel,
    int $left,
    string $rightLabel,
    int $right,
    bool $rightIsBot = false
): void {
    ?>
    <dl class="events-edit-stats__card-split">
        <dt><?= h($leftLabel) ?></dt>
        <dt><?= h($rightLabel) ?></dt>
        <dd><?= $left ?></dd>
        <dd<?= $rightIsBot ? ' class="events-edit-stats__card-split-value--bot"' : '' ?>><?= $right ?></dd>
    </dl>
    <?php
};
?>
<div class="card events-edit-stats events-edit-stats--organizer">
    <h2 class="card-title"><?= h($statsPageTitle) ?></h2>
    <p class="events-edit-stats__intro"><?= h($statsIntro) ?></p>

    <form method="get" action="<?= h($statsFormAction) ?>" class="events-edit-stats__filters">
        <?php
        foreach ($statsFilterExtraQuery as $extraKey => $extraValue):
            if (is_array($extraValue)) {
                // Tömbös szűrők (pl. org_id[]) — ha van form-extra is, ott ne ismételd.
                if ($statsFormExtraHtml !== '') {
                    continue;
                }
                foreach ($extraValue as $item) {
                    if ($item === null || $item === '') {
                        continue;
                    }
                    ?>
                    <input type="hidden" name="<?= h((string) $extraKey) ?>[]" value="<?= h((string) $item) ?>">
                    <?php
                }
                continue;
            }
            ?>
            <input type="hidden" name="<?= h((string) $extraKey) ?>" value="<?= h((string) $extraValue) ?>">
            <?php
        endforeach;
        ?>
        <?php if ($statsFormExtraHtml !== ''): ?>
            <div class="events-edit-stats__form-extra"><?= $statsFormExtraHtml ?></div>
        <?php endif; ?>
        <div class="events-edit-stats__presets-row">
            <span class="events-filter-label">Gyors időszak</span>
            <div class="events-edit-stats__presets" role="group" aria-label="Gyors időszak">
                <?php foreach ($statsPresetLinks as $presetLink): ?>
                    <a
                        class="btn btn-sm <?= !empty($presetLink['active']) ? 'btn-primary' : 'btn-secondary' ?>"
                        href="<?= h((string) $presetLink['url']) ?>"
                    ><?= h((string) $presetLink['label']) ?></a>
                <?php endforeach; ?>
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
        <div class="events-edit-stats__mode-bar<?= $statsMode === 'smart' ? ' events-edit-stats__mode-bar--smart' : ' events-edit-stats__mode-bar--all' ?><?= $statsCustomRates ? ' events-edit-stats__mode-bar--custom-rates' : '' ?>" id="events-stats-mode-bar">
            <div class="events-edit-stats__mode-bar-top">
                <div class="events-edit-stats__mode-bar-copy">
                    <p class="events-edit-stats__mode-bar-title">Számítási mód</p>
                    <p class="events-edit-stats__mode-bar-hint">
                        <?php if ($statsMode === 'smart'): ?>
                            <strong>Latinfo.hu smart stat</strong> — csak az esemény záró napján vagy azelőtt történt megtekintések/kattintások.
                        <?php else: ?>
                            <strong>Összes</strong> — minden megtekintés és kattintás a választott időszakban.
                        <?php endif; ?>
                        <?php if ($statsCustomRates): ?>
                            <br><strong>Saját egységárak</strong> — a médiaérték a megadott Ft-okkal számol.
                        <?php endif; ?>
                    </p>
                </div>
                <div class="events-edit-stats__mode-toggle" role="group" aria-label="Számítási mód">
                    <label class="events-edit-stats__mode-option<?= $statsMode === 'smart' ? ' is-active' : '' ?>">
                        <input type="radio" name="stat_mode" value="smart"<?= $statsMode === 'smart' ? ' checked' : '' ?>>
                        <span>Latinfo.hu smart stat</span>
                    </label>
                    <label class="events-edit-stats__mode-option<?= $statsMode === 'all' ? ' is-active' : '' ?>">
                        <input type="radio" name="stat_mode" value="all"<?= $statsMode === 'all' ? ' checked' : '' ?>>
                        <span>Összes</span>
                    </label>
                </div>
            </div>
            <?php if ($statsAllowCustomMediaRates): ?>
            <div class="events-edit-stats__mode-rates">
                <label class="events-edit-stats__rates-toggle">
                    <input
                        type="checkbox"
                        name="stat_custom_rates"
                        id="stat_custom_rates"
                        value="1"
                        <?= $statsCustomRates ? ' checked' : '' ?>
                    >
                    <span>Saját egységárak</span>
                </label>
                <div class="events-edit-stats__rates-fields" id="events-stats-rates-fields"<?= $statsCustomRates ? '' : ' hidden' ?>>
                    <div class="events-edit-stats__rates-row">
                        <div class="events-edit-stats__rates-field">
                            <label class="events-filter-label" for="stat_page_ft">Megtekintés</label>
                            <div class="events-edit-stats__rates-input-wrap">
                                <input
                                    class="events-filter-input events-edit-stats__rates-input"
                                    type="number"
                                    name="stat_page_ft"
                                    id="stat_page_ft"
                                    min="0"
                                    max="1000000"
                                    step="1"
                                    value="<?= (int) $statsPageUnitFt ?>"
                                    title="Alapértelmezés: <?= (int) events_edit_stats_media_value_page_view_ft() ?> Ft"
                                >
                                <span class="events-edit-stats__rates-unit">Ft</span>
                            </div>
                        </div>
                        <div class="events-edit-stats__rates-field">
                            <label class="events-filter-label" for="stat_click_ft">Átkattintás</label>
                            <div class="events-edit-stats__rates-input-wrap">
                                <input
                                    class="events-filter-input events-edit-stats__rates-input"
                                    type="number"
                                    name="stat_click_ft"
                                    id="stat_click_ft"
                                    min="0"
                                    max="1000000"
                                    step="1"
                                    value="<?= (int) $statsClickUnitFt ?>"
                                    title="Alapértelmezés: <?= (int) events_edit_stats_media_value_intent_click_ft() ?> Ft"
                                >
                                <span class="events-edit-stats__rates-unit">Ft</span>
                            </div>
                        </div>
                        <div class="events-edit-stats__rates-actions">
                            <button
                                type="submit"
                                class="btn btn-primary btn-sm"
                                name="stat_media_calc"
                                id="stat_media_calc"
                                value="1"
                            >Számolás</button>
                        </div>
                    </div>
                    <p class="events-edit-stats__filter-hint">
                        Alap: <?= (int) events_edit_stats_media_value_page_view_ft() ?> / <?= (int) events_edit_stats_media_value_intent_click_ft() ?> Ft
                    </p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </form>
    <script>
    (function () {
        var form = document.querySelector('.events-edit-stats--organizer form.events-edit-stats__filters');
        if (!form) return;
        form.querySelectorAll('input[name="stat_mode"]').forEach(function (input) {
            input.addEventListener('change', function () {
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            });
        });
        var customToggle = form.querySelector('#stat_custom_rates');
        var ratesFields = document.getElementById('events-stats-rates-fields');
        var modeBar = document.getElementById('events-stats-mode-bar');
        var calcBtn = form.querySelector('#stat_media_calc');
        if (customToggle && ratesFields) {
            customToggle.addEventListener('change', function () {
                var on = !!customToggle.checked;
                ratesFields.hidden = !on;
                if (modeBar) modeBar.classList.toggle('events-edit-stats__mode-bar--custom-rates', on);
            });
        }
        if (calcBtn && customToggle) {
            calcBtn.addEventListener('click', function () {
                customToggle.checked = true;
                if (ratesFields) ratesFields.hidden = false;
                if (modeBar) modeBar.classList.add('events-edit-stats__mode-bar--custom-rates');
            });
        }
    })();
    </script>

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
            <?php $renderSplit('Ember', $uniqueHuman, 'Bot (AI, search, stb)', $uniqueBot, true); ?>
        </div>
        <div class="events-edit-stats__card">
            <p class="events-edit-stats__card-label-wrap">
                <span class="events-edit-stats__card-label">Oldalmegnyitás</span>
                <?php $renderStatsCardHelp('Oldalmegnyitás'); ?>
            </p>
            <?php $renderSplit('Ember', $pageHuman, 'Bot (AI, search, stb)', $pageBot, true); ?>
            <?php if ($pagePerUniqueHuman !== null): ?>
                <p class="events-edit-stats__card-hint">≈ <?= h((string) $pagePerUniqueHuman) ?> megnyitás / egyedi ember</p>
            <?php else: ?>
                <p class="events-edit-stats__card-hint">Hit count — nem érdeklődő-szám</p>
            <?php endif; ?>
        </div>
        <div class="events-edit-stats__card">
            <p class="events-edit-stats__card-label-wrap">
                <span class="events-edit-stats__card-label">Előnézet</span>
                <?php $renderStatsCardHelp('Előnézet'); ?>
            </p>
            <?php $renderSplit('Ember', $previewHuman, 'Bot (AI, search, stb)', $previewBot, true); ?>
        </div>
        <div class="events-edit-stats__card">
            <p class="events-edit-stats__card-label-wrap">
                <span class="events-edit-stats__card-label">További info</span>
                <?php $renderStatsCardHelp('További info'); ?>
            </p>
            <?php $renderSplit('Ember', $externalHuman, 'Bot (AI, search, stb)', $externalBot, true); ?>
        </div>
        <div class="events-edit-stats__card events-edit-stats__card--media-value">
            <p class="events-edit-stats__card-label-wrap">
                <span class="events-edit-stats__card-label">Generált médiaérték</span>
                <?php $renderStatsCardHelp('Generált médiaérték'); ?>
            </p>
            <p class="events-edit-stats__card-value"><?= h(events_edit_stats_format_media_ft((int) $mediaValue['total_ft'])) ?></p>
            <dl class="events-edit-stats__card-split">
                <dt>Megtekintés</dt>
                <dt>Átkattintás</dt>
                <dd><?= h(events_edit_stats_format_media_ft((int) $mediaValue['page_value_ft'])) ?></dd>
                <dd><?= h(events_edit_stats_format_media_ft((int) $mediaValue['click_value_ft'])) ?></dd>
            </dl>
            <p class="events-edit-stats__card-hint">
                Ember: <?= (int) $mediaValue['page_views_human'] ?> × <?= (int) $mediaValue['page_unit_ft'] ?> Ft
                + <?= (int) $mediaValue['external_clicks_human'] ?> × <?= (int) $mediaValue['click_unit_ft'] ?> Ft
                · leszűrt időszak
                <?php if (!empty($mediaValue['is_custom'])): ?>
                    · <strong>saját egységár</strong>
                <?php endif; ?>
            </p>
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
            <?php if ($statsDayDrillEnabled): ?>
            <p class="events-edit-stats__chart-hint events-edit-stats__chart-hint--drill">Kattints egy napra az órás bontáshoz és a tételekhez.</p>
            <?php endif; ?>
        </div>
        <?php if ($statsDayDrillEnabled): ?>
        <div class="events-edit-stats__day-drill" id="<?= h($statsChartDomId) ?>-day-drill" hidden>
            <div class="events-edit-stats__day-drill-head">
                <h3 class="events-edit-stats__chart-title">
                    Órás bontás —
                    <span data-day-drill-label>—</span>
                </h3>
                <button type="button" class="btn btn-sm btn-secondary" data-day-drill-close>Bezárás</button>
            </div>
            <p class="events-edit-stats__chart-hint" data-day-drill-status>Betöltés…</p>
            <div class="events-edit-stats__chart-canvas events-edit-stats__chart-canvas--hourly">
                <canvas id="<?= h($statsChartDomId) ?>-hourly" aria-label="Órás megtekintések grafikonja"></canvas>
            </div>
            <h3 class="events-edit-stats__events-title">Tételek — <span data-day-drill-label>—</span></h3>
            <p class="events-edit-stats__events-hint" data-day-drill-items-hint></p>
            <div class="table-wrap events-admin-table-wrap">
                <table class="events-admin-table events-edit-stats__day-items-table">
                    <thead>
                        <tr>
                            <th>Idő</th>
                            <th>Metrika</th>
                            <th>Forrás</th>
                            <th>Típus</th>
                            <th>IP hash</th>
                        </tr>
                    </thead>
                    <tbody data-day-drill-items-body>
                        <tr><td colspan="5" class="events-org-stats-list-empty">Válassz egy napot a grafikonon.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        <script type="application/json" id="<?= h($statsChartDomId) ?>-data"><?= $chartJson ?></script>
        <?php if ($statsDayDrillEnabled): ?>
        <script type="application/json" id="<?= h($statsChartDomId) ?>-day-drill-config"><?= json_encode([
            'eventId' => $statsDayDrillEventId,
            'ajaxUrl' => $statsDayDrillAjaxUrl,
            'ymdLabels' => $statsDayDrillYmdLabels,
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
        <?php endif; ?>
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
            var hourlyChart = null;
            var drillCfg = null;
            var drillRoot = document.getElementById(<?= json_encode($statsChartDomId . '-day-drill', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
            var drillCfgEl = document.getElementById(<?= json_encode($statsChartDomId . '-day-drill-config', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
            var hourlyCanvas = document.getElementById(<?= json_encode($statsChartDomId . '-hourly', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
            var selectedDay = null;
            var drillAbort = null;
            var lastHourly = null;
            var overlayBuilder = null;
            var chartDomId = <?= json_encode($statsChartDomId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            var exposeApi = <?= $statsExposeChartApi ? 'true' : 'false' ?>;

            if (drillCfgEl) {
                try { drillCfg = JSON.parse(drillCfgEl.textContent || '{}'); } catch (e) { drillCfg = null; }
            }

            function mapDatasets(list, pointRadius) {
                var radius = pointRadius == null ? (labels.length > 45 ? 0 : 3) : pointRadius;
                return (list || []).map(function (ds) {
                    return {
                        label: ds.label,
                        data: ds.data,
                        borderColor: ds.color || ds.borderColor || '#3d6b4f',
                        backgroundColor: ((ds.color || ds.borderColor || '#3d6b4f')) + '22',
                        borderWidth: ds.borderWidth != null ? ds.borderWidth : 2,
                        tension: 0.25,
                        pointRadius: radius,
                        pointHoverRadius: 5,
                        fill: false
                    };
                });
            }

            function datasetsForMode(mode, sourcePayload, pointRadius) {
                var src = sourcePayload || payload;
                var srcModes = src.modes || {};
                if (srcModes[mode] && srcModes[mode].datasets && srcModes[mode].datasets.length) {
                    return mapDatasets(srcModes[mode].datasets, pointRadius);
                }
                return mapDatasets(src.datasets || [], pointRadius);
            }

            function currentMode() {
                if (!modeWrap) return 'human';
                var checked = modeWrap.querySelector('input[type="radio"]:checked');
                return checked ? checked.value : 'human';
            }

            function esc(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function setDrillStatus(text) {
                if (!drillRoot) return;
                var el = drillRoot.querySelector('[data-day-drill-status]');
                if (el) el.textContent = text || '';
            }

            function renderHourly(hourlyPayload) {
                if (!hourlyCanvas || typeof Chart === 'undefined') return;
                var mode = currentMode();
                var hourLabels = (hourlyPayload && hourlyPayload.labels) || [];
                var datasets = datasetsForMode(mode, hourlyPayload || {}, 3);
                if (!hourlyChart) {
                    hourlyChart = new Chart(hourlyCanvas.getContext('2d'), {
                        type: 'bar',
                        data: { labels: hourLabels, datasets: datasets },
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
                                x: {
                                    title: { display: true, text: 'Óra' },
                                    ticks: { maxRotation: 0, autoSkip: false }
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
                hourlyChart.data.labels = hourLabels;
                hourlyChart.data.datasets = datasets;
                hourlyChart.update();
            }

            function renderItems(items, truncated) {
                if (!drillRoot) return;
                var body = drillRoot.querySelector('[data-day-drill-items-body]');
                var hint = drillRoot.querySelector('[data-day-drill-items-hint]');
                if (!body) return;
                if (!items || !items.length) {
                    body.innerHTML = '<tr><td colspan="5" class="events-org-stats-list-empty">Nincs tétel ezen a napon.</td></tr>';
                    if (hint) hint.textContent = '';
                    return;
                }
                if (hint) {
                    hint.textContent = truncated
                        ? ('Az utolsó ' + items.length + ' tétel látszik (a lista csonkolva).')
                        : (items.length + ' tétel, legújabb elöl.');
                }
                body.innerHTML = items.map(function (row) {
                    var time = String(row.at || '');
                    var timeShort = time.length >= 19 ? time.slice(11, 19) : time;
                    return '<tr>'
                        + '<td>' + esc(timeShort) + '</td>'
                        + '<td>' + esc(row.metric_label || row.metric || '') + '</td>'
                        + '<td>' + esc(row.source_label || row.source || '') + '</td>'
                        + '<td>' + (row.is_bot ? '<span class="events-stats-cell--bot">Bot</span>' : 'Ember') + '</td>'
                        + '<td><code>' + esc(row.ip_short || '—') + '</code></td>'
                        + '</tr>';
                }).join('');
            }

            function loadDay(dayYmd) {
                if (!drillCfg || !drillRoot || !dayYmd) return;
                selectedDay = dayYmd;
                drillRoot.hidden = false;
                drillRoot.querySelectorAll('[data-day-drill-label]').forEach(function (el) {
                    el.textContent = dayYmd;
                });
                setDrillStatus('Betöltés…');
                renderItems([], false);
                if (drillAbort) {
                    try { drillAbort.abort(); } catch (e) {}
                }
                drillAbort = (typeof AbortController !== 'undefined') ? new AbortController() : null;
                var url = drillCfg.ajaxUrl
                    + (drillCfg.ajaxUrl.indexOf('?') >= 0 ? '&' : '?')
                    + 'id=' + encodeURIComponent(String(drillCfg.eventId))
                    + '&day=' + encodeURIComponent(dayYmd);
                fetch(url, {
                    credentials: 'same-origin',
                    signal: drillAbort ? drillAbort.signal : undefined,
                    headers: { 'Accept': 'application/json' }
                }).then(function (res) {
                    return res.json().then(function (data) {
                        return { okHttp: res.ok, data: data };
                    });
                }).then(function (pack) {
                    if (selectedDay !== dayYmd) return;
                    var data = pack.data || {};
                    if (!pack.okHttp || !data.ok) {
                        setDrillStatus(data.error || 'Nem sikerült betölteni a napot.');
                        return;
                    }
                    drillRoot.querySelectorAll('[data-day-drill-label]').forEach(function (el) {
                        el.textContent = data.day_label || dayYmd;
                    });
                    var totals = data.totals || {};
                    setDrillStatus(
                        'Oldal ' + (totals.page_views || 0)
                        + ' · Előnézet ' + (totals.calendar_previews || 0)
                        + ' · További info ' + (totals.external_info_clicks || 0)
                    );
                    renderHourly(data.hourly || {});
                    lastHourly = data.hourly || null;
                    renderItems(data.items || [], !!data.items_truncated);
                }).catch(function (err) {
                    if (err && err.name === 'AbortError') return;
                    if (selectedDay !== dayYmd) return;
                    setDrillStatus('Hálózati hiba a napi bontás betöltésekor.');
                });
            }

            function onDayClick(evt, elements, chartInstance) {
                if (!drillCfg || !chartInstance) return;
                var points = chartInstance.getElementsAtEventForMode(evt, 'index', { intersect: false }, true);
                if (!points || !points.length) return;
                var idx = points[0].index;
                var ymd = (drillCfg.ymdLabels && drillCfg.ymdLabels[idx]) || null;
                if (!ymd) return;
                loadDay(ymd);
            }

            function render() {
                var mode = currentMode();
                if (hintEl) {
                    hintEl.textContent = mode === 'total'
                        ? 'Napi bontás — emberi + bot együtt (összes).'
                        : 'Napi bontás — csak emberi forgalom.';
                }
                var pointRadius = drillCfg ? Math.max(3, labels.length > 45 ? 2 : 3) : null;
                var datasets = datasetsForMode(mode, payload, pointRadius);
                if (typeof overlayBuilder === 'function') {
                    var overlay = overlayBuilder(mode, {
                        baseDatasets: datasets,
                        labels: labels,
                        payload: payload,
                        mapDatasets: mapDatasets,
                        pointRadius: pointRadius == null ? (labels.length > 45 ? 0 : 3) : pointRadius
                    });
                    if (Array.isArray(overlay)) {
                        datasets = overlay;
                    }
                }
                if (!chart) {
                    chart = new Chart(canvas.getContext('2d'), {
                        type: 'line',
                        data: { labels: labels, datasets: datasets },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            onClick: drillCfg ? onDayClick : undefined,
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
                                        },
                                        afterBody: drillCfg ? function () {
                                            return ['Kattints a nap órás bontásához'];
                                        } : undefined
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
                    if (drillCfg) {
                        canvas.style.cursor = 'pointer';
                    }
                    if (exposeApi) {
                        registerChartApi();
                    }
                    return;
                }
                chart.data.datasets = datasets;
                chart.update();
                if (lastHourly) {
                    renderHourly(lastHourly);
                }
            }

            function registerChartApi() {
                window.EventsStatsCharts = window.EventsStatsCharts || {};
                window.EventsStatsCharts[chartDomId] = {
                    getMode: currentMode,
                    refresh: render,
                    setOverlayBuilder: function (fn) {
                        overlayBuilder = typeof fn === 'function' ? fn : null;
                        render();
                    },
                    getPayload: function () { return payload; },
                    getLabels: function () { return labels; },
                    mapDatasets: mapDatasets
                };
                document.dispatchEvent(new CustomEvent('events-stats-chart-ready', {
                    detail: { chartId: chartDomId }
                }));
            }

            if (drillRoot) {
                var closeBtn = drillRoot.querySelector('[data-day-drill-close]');
                if (closeBtn) {
                    closeBtn.addEventListener('click', function () {
                        selectedDay = null;
                        lastHourly = null;
                        drillRoot.hidden = true;
                        if (drillAbort) {
                            try { drillAbort.abort(); } catch (e) {}
                        }
                    });
                }
            }

            if (modeWrap) {
                modeWrap.addEventListener('change', render);
            }
            render();
            if (exposeApi && chart) {
                registerChartApi();
            }
        })();
        </script>
    <?php else: ?>
        <p class="help events-edit-stats__empty">Nincs naplózott megtekintés a választott időszakban.</p>
    <?php endif; ?>

    <?php if (!$statsHideEventsList): ?>
    <h3 class="events-edit-stats__events-title">Események</h3>
    <p class="events-edit-stats__events-hint"><?= h($statsEventListHint) ?></p>

    <?php if ($statsEventRows === []): ?>
        <p class="help events-edit-stats__empty"><?= h($statsEmptyEventsMessage) ?></p>
    <?php else: ?>
        <?php $statsEventsColspan = $statsShowEventRowActions ? 15 : 14; ?>
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
                    <tr class="events-stats-thead-primary">
                        <?php if ($statsShowEventRowActions): ?>
                        <th class="events-th-actions" scope="col" rowspan="2"><span class="visually-hidden">Műveletek</span></th>
                        <?php endif; ?>
                        <th scope="col" rowspan="2">
                            <button type="button" class="th-sort" data-sort="date" aria-pressed="false">Dátum</button>
                        </th>
                        <th scope="col" rowspan="2">
                            <button type="button" class="th-sort" data-sort="name" aria-pressed="false">Név</button>
                        </th>
                        <th class="th-center" scope="col" rowspan="2" title="Hány napig volt közzétéve az oldal a választott időszakban">
                            <button type="button" class="th-sort" data-sort="live_days" aria-pressed="false">Napok kint</button>
                        </th>
                        <th class="th-center events-stats-th-group events-stats-th-group--unique" colspan="2" scope="colgroup">Egyedi</th>
                        <th class="th-center events-stats-th-group events-stats-th-group--page" colspan="2" scope="colgroup">Oldal</th>
                        <th class="th-center events-stats-th-group events-stats-th-group--preview" colspan="2" scope="colgroup">Előnézet</th>
                        <th class="th-center events-stats-th-group events-stats-th-group--external" colspan="2" scope="colgroup">Átkatt</th>
                        <th class="th-center events-stats-th-group events-stats-th-group--media" colspan="2" scope="colgroup">Médiaérték</th>
                        <th class="events-stats-th-status" scope="col" rowspan="2"><span class="visually-hidden">Státusz</span></th>
                    </tr>
                    <tr class="events-stats-thead-secondary">
                        <th class="th-center events-stats-th-sub events-stats-th-sub--unique events-stats-th-sub--human" title="Egyedi emberi oldal-látogató (IP)">
                            <button type="button" class="th-sort" data-sort="unique_human" aria-pressed="false">Ember</button>
                        </th>
                        <th class="th-center events-stats-th-sub events-stats-th-sub--unique" title="Egyedi bot oldal-látogató (IP)">
                            <button type="button" class="th-sort" data-sort="unique_bot" aria-pressed="false">Bot</button>
                        </th>
                        <th class="th-center events-stats-th-sub events-stats-th-sub--page events-stats-th-sub--human" title="Oldal — emberi">
                            <button type="button" class="th-sort" data-sort="page_human" aria-pressed="false">Ember</button>
                        </th>
                        <th class="th-center events-stats-th-sub events-stats-th-sub--page" title="Oldal — bot">
                            <button type="button" class="th-sort" data-sort="page_bot" aria-pressed="false">Bot</button>
                        </th>
                        <th class="th-center events-stats-th-sub events-stats-th-sub--preview events-stats-th-sub--human" title="Előnézet — emberi">
                            <button type="button" class="th-sort" data-sort="preview_human" aria-pressed="false">Ember</button>
                        </th>
                        <th class="th-center events-stats-th-sub events-stats-th-sub--preview" title="Előnézet — bot">
                            <button type="button" class="th-sort" data-sort="preview_bot" aria-pressed="false">Bot</button>
                        </th>
                        <th class="th-center events-stats-th-sub events-stats-th-sub--external events-stats-th-sub--human" title="Átkattintás — emberi">
                            <button type="button" class="th-sort" data-sort="external_human" aria-pressed="false">Ember</button>
                        </th>
                        <th class="th-center events-stats-th-sub events-stats-th-sub--external" title="Átkattintás — bot">
                            <button type="button" class="th-sort" data-sort="external_bot" aria-pressed="false">Bot</button>
                        </th>
                        <th
                            class="th-center events-stats-th-sub events-stats-th-sub--media events-stats-th-sub--human"
                            title="Oldalmegnyitás (ember) × <?= (int) $statsPageUnitFt ?> Ft"
                        >
                            <button type="button" class="th-sort" data-sort="media_page" aria-pressed="false">Oldal Ft</button>
                        </th>
                        <th
                            class="th-center events-stats-th-sub events-stats-th-sub--media events-stats-th-sub--human"
                            title="További info (ember) × <?= (int) $statsClickUnitFt ?> Ft"
                        >
                            <button type="button" class="th-sort" data-sort="media_click" aria-pressed="false">Átkatt Ft</button>
                        </th>
                    </tr>
                </thead>
                <tbody id="organizer-stats-events-tbody">
                    <?php foreach ($statsEventRows as $row): ?>
                        <?php
                        $st = (string) ($row['event_status'] ?? '');
                        $badgeClass = events_post_status_badge_class($st);
                        $statusLabel = events_post_status_label($st);
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
                        $rowMediaValue = events_edit_stats_media_value(
                            (int) $pageCounts['human'],
                            (int) $externalCounts['human'],
                            $statsCustomRates ? $statsPageUnitFt : null,
                            $statsCustomRates ? $statsClickUnitFt : null
                        );
                        $hasViews = ($pageViews + $previewViews + $externalClicks) > 0 ? '1' : '0';
                        $eventStart = $eventDateYmd($row, 'event_start');
                        $eventEnd = $eventDateYmd($row, 'event_end');
                        if ($eventEnd === '' && $eventStart !== '') {
                            $eventEnd = $eventStart;
                        }
                        $dateDisplay = '–';
                        if ($eventStart !== '') {
                            try {
                                $dateDisplay = (new DateTimeImmutable($eventStart))->format('Y.m.d.');
                            } catch (Throwable) {
                                $dateDisplay = $eventStart;
                            }
                        }
                        $searchName = mb_strtolower((string) ($row['event_name'] ?? ''), 'UTF-8');
                        $publicUrl = $eventPublicUrl($row);
                        $detailUrl = is_callable($statsEventDetailUrl) ? $statsEventDetailUrl($row) : null;
                        $primaryUrl = $statsPreferPartnerLinks
                            ? ($detailUrl ?? $publicUrl)
                            : ($publicUrl ?? $detailUrl);
                        $eventName = (string) ($row['event_name'] ?? '');
                        $eventId = (int) ($row['id'] ?? 0);
                        $eventStatUrl = $eventId > 0
                            ? events_url('events_event_statisztika.php?id=') . $eventId
                            : null;
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
                            data-media-page="<?= (int) $rowMediaValue['page_value_ft'] ?>"
                            data-media-click="<?= (int) $rowMediaValue['click_value_ft'] ?>"
                        >
                            <?php if ($statsShowEventRowActions): ?>
                            <td class="events-td-actions">
                                <div class="events-action-icons">
                                    <?php if ($eventStatUrl !== null): ?>
                                        <a href="<?= h($eventStatUrl) ?>" class="events-icon-action" title="Esemény statisztika" aria-label="Esemény statisztika">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" aria-hidden="true"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M18 20V10M12 20V4M6 20v-6"/></svg>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($publicUrl !== null): ?>
                                        <a href="<?= h($publicUrl) ?>" class="events-icon-action" title="Esemény megtekintése (új lap)" aria-label="Esemény megtekintése új lapon" target="_blank" rel="noopener">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" aria-hidden="true"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <?php endif; ?>
                            <td class="events-stats-td-date"><?= h($dateDisplay) ?></td>
                            <td>
                                <?php if ($primaryUrl !== null): ?>
                                    <a href="<?= h($primaryUrl) ?>"<?= (!$statsPreferPartnerLinks && $publicUrl !== null) ? ' target="_blank" rel="noopener"' : '' ?>><?= h($eventName) ?></a>
                                <?php else: ?>
                                    <?= h($eventName) ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?= $liveDays ?></td>
                            <td class="text-center events-stats-cell--human"><?= $uniqueHumanRow ?></td>
                            <td class="text-center events-stats-cell--bot"><?= $uniqueBotRow ?></td>
                            <td class="text-center events-stats-cell--human"><?= (int) $pageCounts['human'] ?></td>
                            <td class="text-center events-stats-cell--bot"><?= (int) $pageCounts['bot'] ?></td>
                            <td class="text-center events-stats-cell--human"><?= (int) $previewCounts['human'] ?></td>
                            <td class="text-center events-stats-cell--bot"><?= (int) $previewCounts['bot'] ?></td>
                            <td class="text-center events-stats-cell--human"><?= (int) $externalCounts['human'] ?></td>
                            <td class="text-center events-stats-cell--bot"><?= (int) $externalCounts['bot'] ?></td>
                            <td class="text-center events-stats-cell--human events-stats-cell--media" title="Oldalmegnyitás × <?= (int) $rowMediaValue['page_unit_ft'] ?> Ft">
                                <?= h(events_edit_stats_format_media_ft((int) $rowMediaValue['page_value_ft'])) ?>
                            </td>
                            <td class="text-center events-stats-cell--human events-stats-cell--media" title="További info × <?= (int) $rowMediaValue['click_unit_ft'] ?> Ft">
                                <?= h(events_edit_stats_format_media_ft((int) $rowMediaValue['click_value_ft'])) ?>
                            </td>
                            <td class="text-center events-stats-td-status">
                                <span
                                    class="event-status-dot <?= h($badgeClass) ?>"
                                    title="<?= h($statusLabel) ?>"
                                    aria-label="<?= h($statusLabel) ?>"
                                ></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr id="organizer-stats-events-empty" hidden>
                        <td colspan="<?= (int) $statsEventsColspan ?>" class="events-org-stats-list-empty">Nincs találat a szűrőkre.</td>
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
                media_page: 'data-media-page',
                media_click: 'data-media-click',
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
    <?php endif; ?>
</div>
