<?php
declare(strict_types=1);

/**
 * Év/év esemény-összehasonlító UI.
 *
 * @var array{year_from:int,year_to:int,bucket_by:string,status:string} $statsParams
 * @var array<string, mixed> $statsData
 * @var string $statsFormAction
 */

$statsParams = $statsParams ?? [
    'year_from' => (int) date('Y') - 4,
    'year_to' => (int) date('Y'),
    'bucket_by' => 'event_start',
    'status' => 'public',
    'mode' => 'smart',
];
$statsData = is_array($statsData ?? null) ? $statsData : [];
$statsFormAction = (string) ($statsFormAction ?? '');

$summary = is_array($statsData['summary'] ?? null) ? $statsData['summary'] : [];
$years = is_array($statsData['years'] ?? null) ? $statsData['years'] : [];
$chart = is_array($statsData['chart'] ?? null) ? $statsData['chart'] : [];
$insights = is_array($statsData['insights'] ?? null) ? $statsData['insights'] : [];
$availableYears = is_array($statsData['available_years'] ?? null) ? $statsData['available_years'] : [];
$yearFrom = (int) ($statsData['year_from'] ?? $statsParams['year_from']);
$yearTo = (int) ($statsData['year_to'] ?? $statsParams['year_to']);
$bucketBy = (string) ($statsParams['bucket_by'] ?? 'event_start');
$status = (string) ($statsParams['status'] ?? 'public');
$statsMode = events_edit_stats_normalize_mode($statsParams['mode'] ?? ($statsData['mode'] ?? 'smart'));
$isSmartMode = $statsMode === 'smart';
$clicksLabel = $isSmartMode ? 'Kattintások (ember)' : 'Kattintások (összes)';

$eventsCount = (int) ($summary['events_count'] ?? 0);
$clicks = (int) ($summary['total_clicks_human'] ?? 0);
$clicksPerEvent = $summary['clicks_per_event'] ?? null;
$avgLead = $summary['avg_lead_days'] ?? null;
$externalCtr = $summary['external_ctr_pct'] ?? null;

$bucketLabel = $bucketBy === 'published_at' ? 'publikálás dátuma' : 'esemény dátuma';
$statusLabel = match ($status) {
    'publish' => 'csak közzétéve',
    'all' => 'összes (lomtár nélkül)',
    default => 'közzétéve + előzetes',
};

$fmtPct = static function (?float $v): string {
    if ($v === null) {
        return '—';
    }
    $sign = $v > 0 ? '+' : '';

    return $sign . rtrim(rtrim(number_format($v, 1, ',', ' '), '0'), ',') . '%';
};
$pctClass = static function (?float $v): string {
    if ($v === null) {
        return '';
    }
    if ($v > 0) {
        return ' events-yearly-stats__delta--up';
    }
    if ($v < 0) {
        return ' events-yearly-stats__delta--down';
    }

    return '';
};

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$hasChart = $eventsCount > 0 || $clicks > 0;

$buildUrl = static function (array $overrides) use ($statsFormAction, $statsParams): string {
    $q = array_merge($statsParams, $overrides);

    return $statsFormAction . '?' . http_build_query([
        'stat_year_from' => (int) $q['year_from'],
        'stat_year_to' => (int) $q['year_to'],
        'stat_bucket' => (string) $q['bucket_by'],
        'stat_status' => (string) $q['status'],
        'stat_mode' => events_edit_stats_normalize_mode($q['mode'] ?? 'smart'),
    ]);
};

$helpTexts = [
    'Évtől' => 'A tartomány kezdő éve. A gyorsgombok gyakori intervallumokat állítanak be.',
    'Évig' => 'A tartomány záró éve (beleértve).',
    'Év szerint' => 'Esemény dátuma: az év az event_start alapján. Publikálás dátuma: az év az event_published_at (első közzététel) alapján.',
    'Státusz' => 'Közzétéve + előzetes: nyilvános események. Csak közzétéve: publish. Összes: lomtár és auto-draft nélkül.',
    'Számítási mód' => 'Latinfo.hu smart stat: csak emberi forgalom, és csak az esemény záró napján vagy azelőtt. Összes: botok és esemény utáni kattintások is beleszámítanak.',
    'Események' => 'A választott évtartományban, az év-szabály és státuszszűrő szerint számolt események összesen.',
    'Kattintások (ember)' => 'Smart mód: botok nélkül, csak az esemény záró napjáig. Oldalmegnyitás + „További információ” az adott év eseményein.',
    'Kattintások (összes)' => 'Összes mód: botok és esemény utáni kattintások is. Oldalmegnyitás + „További információ” az adott év eseményein.',
    'Kattintás / esemény' => '(Oldalnézet + további info kattintás) ÷ eseményszám a teljes tartományra.',
    'Átlag lead time' => 'Átlagos napok a publikálás és az esemény napja között a tartomány eseményein. Csak ahol a publikálás nem későbbi, mint az esemény.',
    'Külső CTR' => 'További info kattintások ÷ oldalnézetek × 100 a teljes tartományra.',
    'Adatminőség' => 'Helyszínnel, illetve külső URL-lel rendelkező események aránya az összeshez képest a tartományban.',
    'Események és forgalom' => 'Oszlop: éves eseményszám. Vonalak: emberi oldalnézet és további info kattintás az év eseményein (életciklus).',
    'Kattintás / esemény grafikon' => 'Éves kattintás/esemény arány időbeli alakulása.',
    'Lead time (nap)' => 'Éves átlagos előkészítési idő: publikálás és esemény napja közötti napok.',
    'Év/év változás' => 'Az eseményszám százalékos változása az előző évhez képest, évenként.',
    'Év' => 'A naptári év a választott tartományban.',
    'Esemény' => 'Az adott évbe sorolt események száma (az „Év szerint” szűrő alapján).',
    'Év/év' => 'Az eseményszám százalékos változása az előző évhez képest.',
    'Katt. év/év' => 'Az emberi kattintások (oldal + további info) százalékos változása az előző évhez képest.',
    'Oldal' => 'Emberi oldalmegnyitások az adott évbe sorolt eseményeken, a teljes életciklus alatt.',
    'További info' => 'Emberi „További információ” / külső link átkattintások az adott év eseményein (életciklus).',
    'Katt / esemény' => '(Oldal + További info) ÷ Esemény. Üres, ha nincs esemény az évben.',
    'CTR %' => 'További info ÷ Oldal × 100. Üres, ha nincs oldalnézet.',
    'Lead nap' => 'Átlagos napok a publikálás és az esemény között abban az évben.',
    'Szervező' => 'Egyedi szervezők száma, akikhez legalább egy esemény tartozik az adott évben.',
    'Helyszín %' => 'Helyszínhez (venue) kötött események aránya az év eseményei között.',
    'URL %' => 'Külső URL-lel (event_url) rendelkező események aránya az év eseményei között.',
];

$renderHelp = static function (string $key, bool $wide = false) use ($helpTexts): void {
    $help = $helpTexts[$key] ?? '';
    if ($help === '') {
        return;
    }
    $helpId = 'yearly-help-' . substr(sha1($key), 0, 10);
    ?>
    <span class="events-edit-stats__info">
        <button
            type="button"
            class="events-edit-stats__info-btn"
            aria-label="Segítség: <?= h($key) ?>"
            aria-expanded="false"
            aria-controls="<?= h($helpId) ?>"
        >i</button>
        <span
            class="events-edit-stats__info-popover<?= $wide ? ' events-edit-stats__info-popover--wide' : '' ?>"
            id="<?= h($helpId) ?>"
            role="tooltip"
            hidden
        ><?= h($help) ?></span>
    </span>
    <?php
};

$thHelp = static function (string $label, string $helpKey, string $extraClass = 'th-center') use ($renderHelp): void {
    ?>
    <th class="<?= h($extraClass) ?> events-yearly-stats__th" scope="col">
        <span class="events-yearly-stats__th-inner">
            <span><?= h($label) ?></span>
            <?php $renderHelp($helpKey); ?>
        </span>
    </th>
    <?php
};

$presetRanges = [];
if ($availableYears !== []) {
    $maxAvail = max(array_map('intval', $availableYears));
    $minAvail = min(array_map('intval', $availableYears));
    $presetRanges = [
        ['label' => '5 év', 'from' => max($minAvail, $maxAvail - 4), 'to' => $maxAvail],
        ['label' => '3 év', 'from' => max($minAvail, $maxAvail - 2), 'to' => $maxAvail],
        ['label' => 'Összes', 'from' => $minAvail, 'to' => $maxAvail],
    ];
}
?>
<div class="card events-edit-stats events-yearly-stats">
    <p class="events-edit-stats__intro">
        Évek összehasonlítása: eseményszám, megtekintések / kattintások, kattintás/esemény arány,
        valamint a publikálás és az esemény napja közötti átlagos előkészítési idő.
        A forgalmi adatok az adott évbe sorolt események metrikái
        <?php if ($isSmartMode): ?>
            (<strong>Latinfo.hu smart</strong>: emberi, esemény záró napjáig).
        <?php else: ?>
            (<strong>összes</strong>: botokkal és esemény utáni kattintásokkal).
        <?php endif; ?>
        Az <strong>év/év</strong> oszlop mindig az előző évhez viszonyít.
    </p>

    <form method="get" action="<?= h($statsFormAction) ?>" class="events-edit-stats__filters">
        <div class="events-edit-stats__mode-bar<?= $isSmartMode ? ' events-edit-stats__mode-bar--smart' : ' events-edit-stats__mode-bar--all' ?>">
            <div class="events-edit-stats__mode-bar-top">
                <div class="events-edit-stats__mode-bar-copy">
                    <p class="events-edit-stats__mode-bar-title events-yearly-stats__title-with-info">
                        <span>Számítási mód</span>
                        <?php $renderHelp('Számítási mód', true); ?>
                    </p>
                    <p class="events-edit-stats__mode-bar-hint">
                        <?php if ($isSmartMode): ?>
                            <strong>Latinfo.hu smart stat</strong> — botok nélkül, csak az esemény záró napján vagy azelőtt.
                        <?php else: ?>
                            <strong>Összes</strong> — botok és esemény utáni megtekintések/kattintások is beleszámítanak.
                        <?php endif; ?>
                    </p>
                </div>
                <div class="events-edit-stats__mode-toggle" role="group" aria-label="Számítási mód">
                    <label class="events-edit-stats__mode-option<?= $isSmartMode ? ' is-active' : '' ?>">
                        <input type="radio" name="stat_mode" value="smart"<?= $isSmartMode ? ' checked' : '' ?>>
                        <span>Latinfo.hu smart stat</span>
                    </label>
                    <label class="events-edit-stats__mode-option<?= !$isSmartMode ? ' is-active' : '' ?>">
                        <input type="radio" name="stat_mode" value="all"<?= !$isSmartMode ? ' checked' : '' ?>>
                        <span>Összes</span>
                    </label>
                </div>
            </div>
        </div>

        <?php if ($presetRanges !== []): ?>
            <div class="events-edit-stats__presets-row">
                <span class="events-filter-label">Tartomány</span>
                <?php foreach ($presetRanges as $preset): ?>
                    <?php
                    $active = $yearFrom === (int) $preset['from'] && $yearTo === (int) $preset['to'];
                    ?>
                    <a
                        class="btn btn-sm <?= $active ? 'btn-primary' : 'btn-secondary' ?>"
                        href="<?= h($buildUrl(['year_from' => $preset['from'], 'year_to' => $preset['to']])) ?>"
                    ><?= h((string) $preset['label']) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="events-edit-stats__filter-grid">
            <div class="form-group">
                <label class="events-filter-label events-yearly-stats__label-with-info" for="stat_year_from">
                    <span>Évtől</span>
                    <?php $renderHelp('Évtől'); ?>
                </label>
                <input
                    class="events-filter-input"
                    type="number"
                    name="stat_year_from"
                    id="stat_year_from"
                    min="2000"
                    max="<?= (int) date('Y') + 5 ?>"
                    value="<?= $yearFrom ?>"
                >
            </div>
            <div class="form-group">
                <label class="events-filter-label events-yearly-stats__label-with-info" for="stat_year_to">
                    <span>Évig</span>
                    <?php $renderHelp('Évig'); ?>
                </label>
                <input
                    class="events-filter-input"
                    type="number"
                    name="stat_year_to"
                    id="stat_year_to"
                    min="2000"
                    max="<?= (int) date('Y') + 5 ?>"
                    value="<?= $yearTo ?>"
                >
            </div>
            <div class="form-group">
                <label class="events-filter-label events-yearly-stats__label-with-info" for="stat_bucket">
                    <span>Év szerint</span>
                    <?php $renderHelp('Év szerint', true); ?>
                </label>
                <select class="events-filter-input" name="stat_bucket" id="stat_bucket">
                    <option value="event_start"<?= $bucketBy === 'event_start' ? ' selected' : '' ?>>Esemény dátuma</option>
                    <option value="published_at"<?= $bucketBy === 'published_at' ? ' selected' : '' ?>>Publikálás dátuma</option>
                </select>
            </div>
            <div class="form-group">
                <label class="events-filter-label events-yearly-stats__label-with-info" for="stat_status">
                    <span>Státusz</span>
                    <?php $renderHelp('Státusz', true); ?>
                </label>
                <select class="events-filter-input" name="stat_status" id="stat_status">
                    <option value="public"<?= $status === 'public' ? ' selected' : '' ?>>Közzétéve + előzetes</option>
                    <option value="publish"<?= $status === 'publish' ? ' selected' : '' ?>>Csak közzétéve</option>
                    <option value="all"<?= $status === 'all' ? ' selected' : '' ?>>Összes (lomtár nélkül)</option>
                </select>
            </div>
            <div class="form-group events-edit-stats__filter-actions">
                <button type="submit" class="btn btn-primary btn-sm">Szűrés</button>
                <a class="btn btn-secondary btn-sm" href="<?= h($statsFormAction) ?>">Alapértelmezés</a>
            </div>
        </div>
        <p class="events-edit-stats__filter-hint">
            Aktív nézet: <strong><?= $yearFrom ?>–<?= $yearTo ?></strong> · <?= h($bucketLabel) ?> · <?= h($statusLabel) ?>
            · <?= $isSmartMode ? 'smart' : 'összes' ?>.
        </p>
    </form>
    <script>
    (function () {
        var form = document.querySelector('.events-yearly-stats form.events-edit-stats__filters');
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
    })();
    </script>

    <div class="events-edit-stats__cards">
        <div class="events-edit-stats__card">
            <p class="events-edit-stats__card-label-wrap">
                <span class="events-edit-stats__card-label">Események</span>
                <?php $renderHelp('Események', true); ?>
            </p>
            <p class="events-edit-stats__card-value"><?= $eventsCount ?></p>
            <?php if (($summary['range_events_change_pct'] ?? null) !== null): ?>
                <p class="events-edit-stats__card-hint<?= $pctClass(isset($summary['range_events_change_pct']) ? (float) $summary['range_events_change_pct'] : null) ?>">
                    <?= h($fmtPct(isset($summary['range_events_change_pct']) ? (float) $summary['range_events_change_pct'] : null)) ?>
                    első→utolsó év
                </p>
            <?php endif; ?>
        </div>
        <div class="events-edit-stats__card">
            <p class="events-edit-stats__card-label-wrap">
                <span class="events-edit-stats__card-label"><?= h($clicksLabel) ?></span>
                <?php $renderHelp($clicksLabel, true); ?>
            </p>
            <p class="events-edit-stats__card-value"><?= $clicks ?></p>
            <dl class="events-edit-stats__card-split">
                <dt>Oldal</dt>
                <dt>További info</dt>
                <dd><?= (int) ($summary['page_views_human'] ?? 0) ?></dd>
                <dd><?= (int) ($summary['external_clicks_human'] ?? 0) ?></dd>
            </dl>
        </div>
        <div class="events-edit-stats__card">
            <p class="events-edit-stats__card-label-wrap">
                <span class="events-edit-stats__card-label">Kattintás / esemény</span>
                <?php $renderHelp('Kattintás / esemény', true); ?>
            </p>
            <p class="events-edit-stats__card-value"><?= $clicksPerEvent !== null ? h((string) $clicksPerEvent) : '—' ?></p>
            <p class="events-edit-stats__card-hint">oldal + további info / eseményszám</p>
        </div>
        <div class="events-edit-stats__card">
            <p class="events-edit-stats__card-label-wrap">
                <span class="events-edit-stats__card-label">Átlag lead time</span>
                <?php $renderHelp('Átlag lead time', true); ?>
            </p>
            <p class="events-edit-stats__card-value"><?= $avgLead !== null ? h((string) $avgLead) : '—' ?></p>
            <p class="events-edit-stats__card-hint">nap publikálás → esemény</p>
        </div>
        <div class="events-edit-stats__card">
            <p class="events-edit-stats__card-label-wrap">
                <span class="events-edit-stats__card-label">Külső CTR</span>
                <?php $renderHelp('Külső CTR', true); ?>
            </p>
            <p class="events-edit-stats__card-value"><?= $externalCtr !== null ? h((string) $externalCtr) . '%' : '—' ?></p>
            <p class="events-edit-stats__card-hint">további info / oldalnézet</p>
        </div>
        <div class="events-edit-stats__card">
            <p class="events-edit-stats__card-label-wrap">
                <span class="events-edit-stats__card-label">Adatminőség</span>
                <?php $renderHelp('Adatminőség', true); ?>
            </p>
            <p class="events-edit-stats__card-value"><?= ($summary['venue_pct'] ?? null) !== null ? h((string) $summary['venue_pct']) . '%' : '—' ?></p>
            <dl class="events-edit-stats__card-split">
                <dt>Helyszín</dt>
                <dt>URL</dt>
                <dd><?= ($summary['venue_pct'] ?? null) !== null ? h((string) $summary['venue_pct']) . '%' : '—' ?></dd>
                <dd><?= ($summary['url_pct'] ?? null) !== null ? h((string) $summary['url_pct']) . '%' : '—' ?></dd>
            </dl>
        </div>
    </div>

    <?php if ($eventsCount === 0): ?>
        <p class="help events-edit-stats__empty">Nincs esemény a választott évtartományban / szűrőkkel.</p>
    <?php else: ?>
        <?php
        $peakEvents = is_array($insights['peak_events'] ?? null) ? $insights['peak_events'] : null;
        $peakCpe = is_array($insights['peak_clicks_per_event'] ?? null) ? $insights['peak_clicks_per_event'] : null;
        $peakLead = is_array($insights['peak_lead_days'] ?? null) ? $insights['peak_lead_days'] : null;
        ?>
        <?php if ($peakEvents || $peakCpe || $peakLead): ?>
            <ul class="events-yearly-stats__insights">
                <?php if ($peakEvents): ?>
                    <li>
                        Legtöbb esemény:
                        <strong><?= (int) ($peakEvents['year'] ?? 0) ?></strong>
                        (<?= (int) ($peakEvents['events_count'] ?? 0) ?> db)
                    </li>
                <?php endif; ?>
                <?php if ($peakCpe): ?>
                    <li>
                        Legjobb kattintás/esemény:
                        <strong><?= (int) ($peakCpe['year'] ?? 0) ?></strong>
                        (<?= h((string) ($peakCpe['clicks_per_event'] ?? '')) ?>)
                    </li>
                <?php endif; ?>
                <?php if ($peakLead): ?>
                    <li>
                        Leghosszabb előkészítés:
                        <strong><?= (int) ($peakLead['year'] ?? 0) ?></strong>
                        (átlag <?= h((string) ($peakLead['avg_lead_days'] ?? '')) ?> nap)
                    </li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($hasChart): ?>
        <div class="events-edit-stats__chart-wrap">
            <div class="events-edit-stats__chart-head">
                <div>
                    <h3 class="events-edit-stats__chart-title events-yearly-stats__title-with-info">
                        <span>Események és forgalom</span>
                        <?php $renderHelp('Események és forgalom', true); ?>
                    </h3>
                    <p class="events-edit-stats__chart-hint">
                        Oszlop: eseményszám. Vonal: emberi oldalnézet és további info kattintás.
                    </p>
                </div>
            </div>
            <div class="events-edit-stats__chart-canvas">
                <canvas id="yearly-stats-main-chart" aria-label="Éves események és forgalom"></canvas>
            </div>
        </div>
        <script type="application/json" id="yearly-stats-main-chart-data"><?= json_encode($chart, $jsonFlags) ?></script>

        <div class="events-public-traffic-stats__chart-grid">
            <div class="events-edit-stats__chart-wrap">
                <div class="events-edit-stats__chart-head">
                    <div>
                        <h3 class="events-edit-stats__chart-title events-yearly-stats__title-with-info">
                            <span>Kattintás / esemény</span>
                            <?php $renderHelp('Kattintás / esemény grafikon', true); ?>
                        </h3>
                        <p class="events-edit-stats__chart-hint">Hány emberi interakció jut egy eseményre évente.</p>
                    </div>
                </div>
                <div class="events-edit-stats__chart-canvas">
                    <canvas id="yearly-stats-cpe-chart" aria-label="Kattintás per esemény évente"></canvas>
                </div>
            </div>
            <div class="events-edit-stats__chart-wrap">
                <div class="events-edit-stats__chart-head">
                    <div>
                        <h3 class="events-edit-stats__chart-title events-yearly-stats__title-with-info">
                            <span>Lead time (nap)</span>
                            <?php $renderHelp('Lead time (nap)', true); ?>
                        </h3>
                        <p class="events-edit-stats__chart-hint">Átlagos napok a publikálás és az esemény között.</p>
                    </div>
                </div>
                <div class="events-edit-stats__chart-canvas">
                    <canvas id="yearly-stats-lead-chart" aria-label="Lead time napokban évente"></canvas>
                </div>
            </div>
        </div>

        <div class="events-edit-stats__chart-wrap">
            <div class="events-edit-stats__chart-head">
                <div>
                    <h3 class="events-edit-stats__chart-title events-yearly-stats__title-with-info">
                        <span>Év/év eseményszám változás</span>
                        <?php $renderHelp('Év/év változás', true); ?>
                    </h3>
                    <p class="events-edit-stats__chart-hint">Százalékos változás az előző évhez képest.</p>
                </div>
            </div>
            <div class="events-edit-stats__chart-canvas">
                <canvas id="yearly-stats-yoy-chart" aria-label="Év/év változás"></canvas>
            </div>
        </div>
    <?php endif; ?>

    <h3 class="events-edit-stats__events-title">Éves táblázat</h3>
    <p class="events-edit-stats__events-hint">
        Év/év: változás az előző évhez. Az „i” gombok magyarázzák az oszlopokat.
    </p>
    <div class="table-wrap events-admin-table-wrap">
        <table class="events-admin-table events-yearly-stats__table">
            <thead>
                <tr>
                    <?php $thHelp('Év', 'Év', ''); ?>
                    <?php $thHelp('Esemény', 'Esemény'); ?>
                    <?php $thHelp('Év/év', 'Év/év'); ?>
                    <?php $thHelp('Katt. év/év', 'Katt. év/év'); ?>
                    <?php $thHelp('Oldal', 'Oldal'); ?>
                    <?php $thHelp('További info', 'További info'); ?>
                    <?php $thHelp('Katt / esemény', 'Katt / esemény'); ?>
                    <?php $thHelp('CTR %', 'CTR %'); ?>
                    <?php $thHelp('Lead nap', 'Lead nap'); ?>
                    <?php $thHelp('Szervező', 'Szervező'); ?>
                    <?php $thHelp('Helyszín %', 'Helyszín %'); ?>
                    <?php $thHelp('URL %', 'URL %'); ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($years as $row): ?>
                    <?php
                    $hasData = (int) ($row['events_count'] ?? 0) > 0
                        || (int) ($row['total_clicks_human'] ?? 0) > 0;
                    $haviUrl = events_url('events_havi_stat.php?' . http_build_query([
                        'stat_year' => (int) ($row['year'] ?? 0),
                        'stat_bucket' => $bucketBy,
                        'stat_status' => $status,
                        'stat_mode' => $statsMode,
                    ]));
                    ?>
                    <tr<?= $hasData ? '' : ' class="events-yearly-stats__row--empty"' ?>>
                        <td>
                            <a href="<?= h($haviUrl) ?>"><?= (int) ($row['year'] ?? 0) ?></a>
                        </td>
                        <td class="text-center events-stats-cell--human"><?= (int) ($row['events_count'] ?? 0) ?></td>
                        <td class="text-center<?= $pctClass(isset($row['yoy_events_pct']) ? (float) $row['yoy_events_pct'] : null) ?>">
                            <?= h($fmtPct(isset($row['yoy_events_pct']) ? (float) $row['yoy_events_pct'] : null)) ?>
                        </td>
                        <td class="text-center<?= $pctClass(isset($row['yoy_clicks_pct']) ? (float) $row['yoy_clicks_pct'] : null) ?>">
                            <?= h($fmtPct(isset($row['yoy_clicks_pct']) ? (float) $row['yoy_clicks_pct'] : null)) ?>
                        </td>
                        <td class="text-center"><?= (int) ($row['page_views_human'] ?? 0) ?></td>
                        <td class="text-center"><?= (int) ($row['external_clicks_human'] ?? 0) ?></td>
                        <td class="text-center">
                            <?= ($row['clicks_per_event'] ?? null) !== null ? h((string) $row['clicks_per_event']) : '—' ?>
                        </td>
                        <td class="text-center">
                            <?= ($row['external_ctr_pct'] ?? null) !== null ? h((string) $row['external_ctr_pct']) : '—' ?>
                        </td>
                        <td class="text-center">
                            <?= ($row['avg_lead_days'] ?? null) !== null ? h((string) $row['avg_lead_days']) : '—' ?>
                        </td>
                        <td class="text-center"><?= (int) ($row['unique_organizers'] ?? 0) ?></td>
                        <td class="text-center">
                            <?= ($row['venue_pct'] ?? null) !== null ? h((string) $row['venue_pct']) : '—' ?>
                        </td>
                        <td class="text-center">
                            <?= ($row['url_pct'] ?? null) !== null ? h((string) $row['url_pct']) : '—' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if ($eventsCount > 0): ?>
                <tfoot>
                    <tr>
                        <th scope="row">Összesen (<?= $yearFrom ?>–<?= $yearTo ?>)</th>
                        <th class="th-center"><?= $eventsCount ?></th>
                        <th class="th-center">—</th>
                        <th class="th-center">—</th>
                        <th class="th-center"><?= (int) ($summary['page_views_human'] ?? 0) ?></th>
                        <th class="th-center"><?= (int) ($summary['external_clicks_human'] ?? 0) ?></th>
                        <th class="th-center"><?= $clicksPerEvent !== null ? h((string) $clicksPerEvent) : '—' ?></th>
                        <th class="th-center"><?= $externalCtr !== null ? h((string) $externalCtr) : '—' ?></th>
                        <th class="th-center"><?= $avgLead !== null ? h((string) $avgLead) : '—' ?></th>
                        <th class="th-center">—</th>
                        <th class="th-center"><?= ($summary['venue_pct'] ?? null) !== null ? h((string) $summary['venue_pct']) : '—' ?></th>
                        <th class="th-center"><?= ($summary['url_pct'] ?? null) !== null ? h((string) $summary['url_pct']) : '—' ?></th>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php if ($hasChart): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js" crossorigin="anonymous"></script>
    <script>
    (function () {
        if (typeof Chart === 'undefined') return;

        function parseJson(id) {
            var el = document.getElementById(id);
            if (!el) return null;
            try { return JSON.parse(el.textContent || '{}'); } catch (e) { return null; }
        }

        var payload = parseJson('yearly-stats-main-chart-data');
        if (!payload) return;
        var labels = payload.labels || [];

        var main = document.getElementById('yearly-stats-main-chart');
        if (main) {
            new Chart(main.getContext('2d'), {
                data: {
                    labels: labels,
                    datasets: [
                        {
                            type: 'bar',
                            label: 'Események',
                            data: payload.events || [],
                            backgroundColor: 'rgba(61, 107, 79, 0.55)',
                            borderColor: '#3d6b4f',
                            borderWidth: 1,
                            yAxisID: 'y',
                            order: 2
                        },
                        {
                            type: 'line',
                            label: 'Oldalnézet',
                            data: payload.page_views || [],
                            borderColor: '#2f6f8f',
                            backgroundColor: 'rgba(47, 111, 143, 0.12)',
                            borderWidth: 2,
                            tension: 0.25,
                            pointRadius: 4,
                            yAxisID: 'y1',
                            order: 1
                        },
                        {
                            type: 'line',
                            label: 'További info',
                            data: payload.external_clicks || [],
                            borderColor: '#c45c26',
                            backgroundColor: 'rgba(196, 92, 38, 0.12)',
                            borderWidth: 2,
                            tension: 0.25,
                            pointRadius: 4,
                            yAxisID: 'y1',
                            order: 0
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { position: 'bottom' } },
                    scales: {
                        y: {
                            type: 'linear',
                            position: 'left',
                            beginAtZero: true,
                            title: { display: true, text: 'Események' },
                            ticks: { precision: 0 }
                        },
                        y1: {
                            type: 'linear',
                            position: 'right',
                            beginAtZero: true,
                            grid: { drawOnChartArea: false },
                            title: { display: true, text: 'Forgalom' },
                            ticks: { precision: 0 }
                        }
                    }
                }
            });
        }

        function makeLine(canvasId, data, color, label) {
            var canvas = document.getElementById(canvasId);
            if (!canvas) return;
            new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: label,
                        data: data,
                        borderColor: color,
                        backgroundColor: color + '22',
                        borderWidth: 2,
                        tension: 0.25,
                        pointRadius: 4,
                        spanGaps: true,
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } }
                }
            });
        }

        makeLine('yearly-stats-cpe-chart', payload.clicks_per_event || [], '#6d8f63', 'Katt / esemény');
        makeLine('yearly-stats-lead-chart', payload.avg_lead_days || [], '#8b5a9e', 'Lead nap');

        var yoy = document.getElementById('yearly-stats-yoy-chart');
        if (yoy) {
            var yoyData = payload.yoy_events_pct || [];
            new Chart(yoy.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Év/év %',
                        data: yoyData,
                        backgroundColor: yoyData.map(function (v) {
                            if (v === null || v === undefined) return 'rgba(148,163,184,0.35)';
                            return v >= 0 ? 'rgba(47, 107, 58, 0.55)' : 'rgba(163, 59, 43, 0.55)';
                        }),
                        borderColor: yoyData.map(function (v) {
                            if (v === null || v === undefined) return '#94a3b8';
                            return v >= 0 ? '#2f6b3a' : '#a33b2b';
                        }),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            title: { display: true, text: '%' },
                            ticks: {
                                callback: function (v) { return v + '%'; }
                            }
                        }
                    }
                }
            });
        }
    })();
    </script>
<?php endif; ?>

<script>
(function () {
    var root = document.querySelector('.events-yearly-stats');
    if (!root) return;
    var infos = root.querySelectorAll('.events-edit-stats__info');
    if (!infos.length) return;

    function resetPopover(pop) {
        pop.style.position = '';
        pop.style.top = '';
        pop.style.left = '';
        pop.style.right = '';
        pop.style.transform = '';
        pop.style.maxWidth = '';
        pop.style.width = '';
        pop.classList.remove('events-edit-stats__info-popover--fixed');
    }

    function placeFixed(btn, pop) {
        pop.classList.add('events-edit-stats__info-popover--fixed');
        pop.style.position = 'fixed';
        pop.style.transform = 'none';
        pop.style.maxWidth = '22rem';
        var rect = btn.getBoundingClientRect();
        var inTableHead = !!btn.closest('th');
        var width = Math.min(352, Math.max(240, window.innerWidth - 24));
        var left = inTableHead
            ? rect.left
            : rect.left + (rect.width / 2) - (width / 2);
        left = Math.max(12, Math.min(left, window.innerWidth - width - 12));
        pop.style.width = width + 'px';
        pop.hidden = false;
        var popH = pop.offsetHeight || 120;
        var gap = 10;
        var top;
        if (inTableHead && rect.top > popH + gap + 12) {
            top = rect.top - popH - gap;
        } else if (rect.bottom + gap + popH <= window.innerHeight - 12) {
            top = rect.bottom + gap;
        } else {
            top = Math.max(12, rect.top - popH - gap);
        }
        pop.style.top = top + 'px';
        pop.style.left = left + 'px';
    }

    function closeAll(except) {
        infos.forEach(function (info) {
            if (except && info === except) return;
            var btn = info.querySelector('.events-edit-stats__info-btn');
            var pop = info.querySelector('.events-edit-stats__info-popover');
            if (!btn || !pop) return;
            btn.setAttribute('aria-expanded', 'false');
            pop.hidden = true;
            info.classList.remove('is-open');
            resetPopover(pop);
        });
    }

    infos.forEach(function (info) {
        var btn = info.querySelector('.events-edit-stats__info-btn');
        var pop = info.querySelector('.events-edit-stats__info-popover');
        if (!btn || !pop) return;
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var open = info.classList.contains('is-open');
            closeAll();
            if (!open) {
                info.classList.add('is-open');
                btn.setAttribute('aria-expanded', 'true');
                placeFixed(btn, pop);
            }
        });
    });

    document.addEventListener('click', function () { closeAll(); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeAll();
    });
    window.addEventListener('scroll', function () { closeAll(); }, true);
    window.addEventListener('resize', function () { closeAll(); });
})();
</script>

<style>
.events-yearly-stats__insights {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem 1.25rem;
    margin: 0 0 1.25rem;
    padding: 0.75rem 1rem;
    list-style: none;
    background: rgba(61, 107, 79, 0.06);
    border-radius: 8px;
    font-size: 0.92rem;
}
.events-yearly-stats__insights li { margin: 0; }
.events-yearly-stats__delta--up,
.events-yearly-stats td.events-yearly-stats__delta--up,
.events-yearly-stats .events-edit-stats__card-hint.events-yearly-stats__delta--up {
    color: #2f6b3a;
}
.events-yearly-stats__delta--down,
.events-yearly-stats td.events-yearly-stats__delta--down,
.events-yearly-stats .events-edit-stats__card-hint.events-yearly-stats__delta--down {
    color: #a33b2b;
}
.events-yearly-stats__row--empty td { opacity: 0.45; }
.events-yearly-stats__table th,
.events-yearly-stats__table td { white-space: nowrap; }
.events-yearly-stats__label-with-info,
.events-yearly-stats__title-with-info,
.events-yearly-stats__th-inner {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.events-yearly-stats__th-inner { justify-content: center; }
.events-yearly-stats__th:first-child .events-yearly-stats__th-inner { justify-content: flex-start; }
.events-yearly-stats .events-edit-stats__info-popover,
.events-yearly-stats .events-edit-stats__info-popover--fixed {
    z-index: 120;
    text-transform: none;
    letter-spacing: normal;
    font-weight: 400;
    font-size: 0.8125rem;
    font-style: normal;
    line-height: 1.45;
    color: #1f2937;
    background: #fff;
    white-space: normal;
    text-align: left;
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.16);
}
.events-yearly-stats .events-edit-stats__info-popover--fixed::before,
.events-yearly-stats th .events-edit-stats__info-popover::before {
    display: none;
}
.events-yearly-stats__table th .events-edit-stats__info {
    text-transform: none;
}
</style>
