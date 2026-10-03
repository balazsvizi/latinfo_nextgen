<?php
declare(strict_types=1);

/**
 * Éves / havi esemény-összehasonlító UI.
 *
 * @var array{year:int,bucket_by:string,status:string} $statsParams
 * @var array<string, mixed> $statsData
 * @var string $statsFormAction
 */

$statsParams = $statsParams ?? [
    'year' => (int) date('Y'),
    'bucket_by' => 'event_start',
    'status' => 'public',
];
$statsData = is_array($statsData ?? null) ? $statsData : [];
$statsFormAction = (string) ($statsFormAction ?? '');

$summary = is_array($statsData['summary'] ?? null) ? $statsData['summary'] : [];
$months = is_array($statsData['months'] ?? null) ? $statsData['months'] : [];
$chart = is_array($statsData['chart'] ?? null) ? $statsData['chart'] : [];
$insights = is_array($statsData['insights'] ?? null) ? $statsData['insights'] : [];
$availableYears = is_array($statsData['available_years'] ?? null) ? $statsData['available_years'] : [];
$year = (int) ($statsData['year'] ?? $statsParams['year']);
$prevYear = (int) ($statsData['prev_year'] ?? ($year - 1));
$bucketBy = (string) ($statsParams['bucket_by'] ?? 'event_start');
$status = (string) ($statsParams['status'] ?? 'public');

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
        return ' events-monthly-stats__delta--up';
    }
    if ($v < 0) {
        return ' events-monthly-stats__delta--down';
    }

    return '';
};

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$hasChart = $eventsCount > 0 || $clicks > 0;

$buildUrl = static function (array $overrides) use ($statsFormAction, $statsParams): string {
    $q = array_merge($statsParams, $overrides);

    return $statsFormAction . '?' . http_build_query([
        'stat_year' => (int) $q['year'],
        'stat_bucket' => (string) $q['bucket_by'],
        'stat_status' => (string) $q['status'],
    ]);
};

$helpTexts = [
    'Év' => 'A vizsgált naptári év. A gyorsgombok a rendelkezésre álló évek között váltanak.',
    'Hónap szerint' => 'Esemény dátuma: a hónap az event_start alapján. Publikálás dátuma: a hónap az event_published_at (első közzététel) alapján.',
    'Státusz' => 'Közzétéve + előzetes: nyilvános események. Csak közzétéve: publish. Összes: lomtár és auto-draft nélkül.',
    'Események' => 'A választott évben, a hónap-szabály és státuszszűrő szerint számolt események összesen. Az év/év százalék az előző évhez viszonyít.',
    'Kattintások (ember)' => 'Botok nélkül: oldalmegnyitás + „További információ” kattintás. Az adott hónapba sorolt események teljes életciklusú forgalma (nem csak a hónap napjain mért).',
    'Kattintás / esemény' => '(Oldalnézet + további info kattintás) ÷ eseményszám. Megmutatja, átlagosan hány emberi interakció jut egy eseményre.',
    'Átlag lead time' => 'Átlagos napok a publikálás (event_published_at) és az esemény napja (event_start) között. Csak ahol van publikálási dátum, és az nem későbbi, mint az esemény.',
    'Külső CTR' => 'További info kattintások ÷ oldalnézetek × 100. Az eseményoldalról a külső linkre („További információ”) váltás aránya.',
    'Adatminőség' => 'Kitöltöttség: helyszínnel, illetve külső URL-lel rendelkező események aránya az összeshez képest.',
    'Események és forgalom' => 'Oszlopok: eseményszám az aktuális és az előző évben. Vonalak: emberi oldalnézet és további info kattintás az adott hónap eseményein (életciklus).',
    'Kattintás / esemény grafikon' => 'Havi kattintás/esemény arány időbeli alakulása — (oldal + további info) / esemény.',
    'Lead time (nap)' => 'Havi átlagos előkészítési idő: publikálás és esemény napja közötti napok száma.',
    'Hónap' => 'A naptári hónap a választott évben.',
    'Esemény' => 'Az adott hónapba sorolt események száma (a „Hónap szerint” szűrő alapján).',
    'Hó/hó' => 'Az eseményszám százalékos változása az előző hónaphoz képest. Januárnál az előző év decemberéhez viszonyít.',
    'Év/év' => 'Az eseményszám százalékos változása az előző év ugyanazon hónapjához képest.',
    'Oldal' => 'Emberi (nem bot) oldalmegnyitások az adott hónapba sorolt eseményeken, a teljes életciklus alatt.',
    'További info' => 'Emberi „További információ” / külső link átkattintások az adott hónap eseményein (életciklus).',
    'Katt / esemény' => '(Oldal + További info) ÷ Esemény. Üres, ha nincs esemény a hónapban.',
    'CTR %' => 'További info ÷ Oldal × 100. Üres, ha nincs oldalnézet.',
    'Lead nap' => 'Átlagos napok a publikálás és az esemény között abban a hónapban.',
    'Szervező' => 'Egyedi szervezők száma, akikhez legalább egy esemény tartozik az adott hónapban.',
    'Helyszín %' => 'Helyszínhez (venue) kötött események aránya a hónap eseményei között.',
    'URL %' => 'Külső URL-lel (event_url) rendelkező események aránya a hónap eseményei között.',
];

$renderHelp = static function (string $key, bool $wide = false) use ($helpTexts): void {
    $help = $helpTexts[$key] ?? '';
    if ($help === '') {
        return;
    }
    $helpId = 'monthly-help-' . substr(sha1($key), 0, 10);
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
    <th class="<?= h($extraClass) ?> events-monthly-stats__th" scope="col">
        <span class="events-monthly-stats__th-inner">
            <span><?= h($label) ?></span>
            <?php $renderHelp($helpKey); ?>
        </span>
    </th>
    <?php
};
?>
<div class="card events-edit-stats events-monthly-stats">
    <p class="events-edit-stats__intro">
        Éves áttekintés havi bontásban: eseményszám, emberi megtekintések / kattintások, kattintás/esemény arány,
        valamint a publikálás és az esemény napja közötti átlagos előkészítési idő.
        A forgalmi adatok az adott hónapba sorolt események <strong>teljes életciklusú</strong> (emberi) metrikái.
        Összehasonlítás: előző hónap (hó/hó) és <?= (int) $prevYear ?> ugyanaz a hónapja (év/év).
    </p>

    <form method="get" action="<?= h($statsFormAction) ?>" class="events-edit-stats__filters">
        <div class="events-edit-stats__presets-row">
            <span class="events-filter-label">Év</span>
            <?php if ($availableYears !== []): ?>
                <?php foreach ($availableYears as $y): ?>
                    <?php $y = (int) $y; ?>
                    <a
                        class="btn btn-sm <?= $y === $year ? 'btn-primary' : 'btn-secondary' ?>"
                        href="<?= h($buildUrl(['year' => $y])) ?>"
                    ><?= $y ?></a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="events-edit-stats__filter-grid">
            <div class="form-group">
                <label class="events-filter-label events-monthly-stats__label-with-info" for="stat_year">
                    <span>Év</span>
                    <?php $renderHelp('Év'); ?>
                </label>
                <input
                    class="events-filter-input"
                    type="number"
                    name="stat_year"
                    id="stat_year"
                    min="2000"
                    max="<?= (int) date('Y') + 5 ?>"
                    value="<?= $year ?>"
                >
            </div>
            <div class="form-group">
                <label class="events-filter-label events-monthly-stats__label-with-info" for="stat_bucket">
                    <span>Hónap szerint</span>
                    <?php $renderHelp('Hónap szerint', true); ?>
                </label>
                <select class="events-filter-input" name="stat_bucket" id="stat_bucket">
                    <option value="event_start"<?= $bucketBy === 'event_start' ? ' selected' : '' ?>>Esemény dátuma</option>
                    <option value="published_at"<?= $bucketBy === 'published_at' ? ' selected' : '' ?>>Publikálás dátuma</option>
                </select>
            </div>
            <div class="form-group">
                <label class="events-filter-label events-monthly-stats__label-with-info" for="stat_status">
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
            Aktív nézet: <strong><?= $year ?></strong> · <?= h($bucketLabel) ?> · <?= h($statusLabel) ?>.
        </p>
    </form>

    <div class="events-edit-stats__cards">
        <div class="events-edit-stats__card">
            <p class="events-edit-stats__card-label-wrap">
                <span class="events-edit-stats__card-label">Események</span>
                <?php $renderHelp('Események', true); ?>
            </p>
            <p class="events-edit-stats__card-value"><?= $eventsCount ?></p>
            <?php if (($summary['yoy_events_pct'] ?? null) !== null): ?>
                <p class="events-edit-stats__card-hint<?= $pctClass(isset($summary['yoy_events_pct']) ? (float) $summary['yoy_events_pct'] : null) ?>">
                    <?= h($fmtPct(isset($summary['yoy_events_pct']) ? (float) $summary['yoy_events_pct'] : null)) ?> vs <?= $prevYear ?>
                </p>
            <?php endif; ?>
        </div>
        <div class="events-edit-stats__card">
            <p class="events-edit-stats__card-label-wrap">
                <span class="events-edit-stats__card-label">Kattintások (ember)</span>
                <?php $renderHelp('Kattintások (ember)', true); ?>
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
            <p class="events-edit-stats__card-hint">
                oldal + további info / eseményszám
            </p>
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
        <p class="help events-edit-stats__empty">
            Nincs esemény a választott évben / szűrőkkel.
        </p>
    <?php else: ?>
        <?php
        $peakEvents = is_array($insights['peak_events'] ?? null) ? $insights['peak_events'] : null;
        $peakCpe = is_array($insights['peak_clicks_per_event'] ?? null) ? $insights['peak_clicks_per_event'] : null;
        $peakLead = is_array($insights['peak_lead_days'] ?? null) ? $insights['peak_lead_days'] : null;
        ?>
        <?php if ($peakEvents || $peakCpe || $peakLead): ?>
            <ul class="events-monthly-stats__insights">
                <?php if ($peakEvents): ?>
                    <li>
                        Legtöbb esemény:
                        <strong><?= h((string) ($peakEvents['label_full'] ?? '')) ?></strong>
                        (<?= (int) ($peakEvents['events_count'] ?? 0) ?> db)
                    </li>
                <?php endif; ?>
                <?php if ($peakCpe): ?>
                    <li>
                        Legjobb kattintás/esemény:
                        <strong><?= h((string) ($peakCpe['label_full'] ?? '')) ?></strong>
                        (<?= h((string) ($peakCpe['clicks_per_event'] ?? '')) ?>)
                    </li>
                <?php endif; ?>
                <?php if ($peakLead): ?>
                    <li>
                        Leghosszabb előkészítés:
                        <strong><?= h((string) ($peakLead['label_full'] ?? '')) ?></strong>
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
                    <h3 class="events-edit-stats__chart-title events-monthly-stats__title-with-info">
                        <span>Események és forgalom</span>
                        <?php $renderHelp('Események és forgalom', true); ?>
                    </h3>
                    <p class="events-edit-stats__chart-hint">
                        Oszlop: eseményszám (<?= $year ?> vs <?= $prevYear ?>). Vonal: emberi oldalnézet és további info kattintás.
                    </p>
                </div>
            </div>
            <div class="events-edit-stats__chart-canvas">
                <canvas id="monthly-stats-main-chart" aria-label="Havi események és forgalom"></canvas>
            </div>
        </div>
        <script type="application/json" id="monthly-stats-main-chart-data"><?= json_encode($chart, $jsonFlags) ?></script>

        <div class="events-public-traffic-stats__chart-grid">
            <div class="events-edit-stats__chart-wrap">
                <div class="events-edit-stats__chart-head">
                    <div>
                        <h3 class="events-edit-stats__chart-title events-monthly-stats__title-with-info">
                            <span>Kattintás / esemény</span>
                            <?php $renderHelp('Kattintás / esemény grafikon', true); ?>
                        </h3>
                        <p class="events-edit-stats__chart-hint">Hány emberi interakció jut egy eseményre.</p>
                    </div>
                </div>
                <div class="events-edit-stats__chart-canvas">
                    <canvas id="monthly-stats-cpe-chart" aria-label="Kattintás per esemény"></canvas>
                </div>
            </div>
            <div class="events-edit-stats__chart-wrap">
                <div class="events-edit-stats__chart-head">
                    <div>
                        <h3 class="events-edit-stats__chart-title events-monthly-stats__title-with-info">
                            <span>Lead time (nap)</span>
                            <?php $renderHelp('Lead time (nap)', true); ?>
                        </h3>
                        <p class="events-edit-stats__chart-hint">Átlagos napok a publikálás és az esemény között.</p>
                    </div>
                </div>
                <div class="events-edit-stats__chart-canvas">
                    <canvas id="monthly-stats-lead-chart" aria-label="Lead time napokban"></canvas>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <h3 class="events-edit-stats__events-title">Havi táblázat</h3>
    <p class="events-edit-stats__events-hint">
        Hó/hó: változás az előző hónaphoz. Év/év: változás <?= $prevYear ?> ugyanazon hónapjához. Az „i” gombok magyarázzák az oszlopokat.
    </p>
    <div class="table-wrap events-admin-table-wrap">
        <table class="events-admin-table events-monthly-stats__table">
            <thead>
                <tr>
                    <?php $thHelp('Hónap', 'Hónap', ''); ?>
                    <?php $thHelp('Esemény', 'Esemény'); ?>
                    <?php $thHelp('Hó/hó', 'Hó/hó'); ?>
                    <?php $thHelp('Év/év', 'Év/év'); ?>
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
                <?php foreach ($months as $row): ?>
                    <?php
                    $hasData = (int) ($row['events_count'] ?? 0) > 0
                        || (int) ($row['total_clicks_human'] ?? 0) > 0;
                    ?>
                    <tr<?= $hasData ? '' : ' class="events-monthly-stats__row--empty"' ?>>
                        <td><?= h((string) ($row['label_full'] ?? '')) ?></td>
                        <td class="text-center events-stats-cell--human"><?= (int) ($row['events_count'] ?? 0) ?></td>
                        <td class="text-center<?= $pctClass(isset($row['mom_events_pct']) ? (float) $row['mom_events_pct'] : null) ?>">
                            <?= h($fmtPct(isset($row['mom_events_pct']) ? (float) $row['mom_events_pct'] : null)) ?>
                        </td>
                        <td class="text-center<?= $pctClass(isset($row['yoy_events_pct']) ? (float) $row['yoy_events_pct'] : null) ?>">
                            <?= h($fmtPct(isset($row['yoy_events_pct']) ? (float) $row['yoy_events_pct'] : null)) ?>
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
                        <th scope="row">Éves összesen</th>
                        <th class="th-center"><?= $eventsCount ?></th>
                        <th class="th-center">—</th>
                        <th class="th-center<?= $pctClass(isset($summary['yoy_events_pct']) ? (float) $summary['yoy_events_pct'] : null) ?>">
                            <?= h($fmtPct(isset($summary['yoy_events_pct']) ? (float) $summary['yoy_events_pct'] : null)) ?>
                        </th>
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

        var payload = parseJson('monthly-stats-main-chart-data');
        if (!payload) return;
        var labels = payload.labels || [];

        var main = document.getElementById('monthly-stats-main-chart');
        if (main) {
            new Chart(main.getContext('2d'), {
                data: {
                    labels: labels,
                    datasets: [
                        {
                            type: 'bar',
                            label: 'Események <?= (int) $year ?>',
                            data: payload.events || [],
                            backgroundColor: 'rgba(61, 107, 79, 0.55)',
                            borderColor: '#3d6b4f',
                            borderWidth: 1,
                            yAxisID: 'y',
                            order: 2
                        },
                        {
                            type: 'bar',
                            label: 'Események <?= (int) $prevYear ?>',
                            data: payload.prev_events || [],
                            backgroundColor: 'rgba(148, 163, 184, 0.35)',
                            borderColor: '#94a3b8',
                            borderWidth: 1,
                            yAxisID: 'y',
                            order: 3
                        },
                        {
                            type: 'line',
                            label: 'Oldalnézet',
                            data: payload.page_views || [],
                            borderColor: '#2f6f8f',
                            backgroundColor: 'rgba(47, 111, 143, 0.12)',
                            borderWidth: 2,
                            tension: 0.25,
                            pointRadius: 3,
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
                            pointRadius: 3,
                            yAxisID: 'y1',
                            order: 0
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'bottom' }
                    },
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
                        pointRadius: 3,
                        spanGaps: true,
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        makeLine('monthly-stats-cpe-chart', payload.clicks_per_event || [], '#6d8f63', 'Katt / esemény');
        makeLine('monthly-stats-lead-chart', payload.avg_lead_days || [], '#8b5a9e', 'Lead nap');
    })();
    </script>
<?php endif; ?>

<script>
(function () {
    var root = document.querySelector('.events-monthly-stats');
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
        pop.classList.remove('events-edit-stats__info-popover--fixed');
    }

    function placeFixed(btn, pop) {
        pop.classList.add('events-edit-stats__info-popover--fixed');
        pop.style.position = 'fixed';
        pop.style.transform = 'none';
        pop.style.maxWidth = '22rem';
        var rect = btn.getBoundingClientRect();
        var width = Math.min(352, Math.max(220, window.innerWidth - 24));
        var left = rect.left + (rect.width / 2) - (width / 2);
        left = Math.max(12, Math.min(left, window.innerWidth - width - 12));
        var top = rect.bottom + 8;
        pop.style.width = width + 'px';
        pop.hidden = false;
        var popH = pop.offsetHeight || 120;
        if (top + popH > window.innerHeight - 12 && rect.top > popH + 16) {
            top = rect.top - popH - 8;
        }
        pop.style.top = Math.max(12, top) + 'px';
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
                // Táblázat overflow miatt fixed; máshol is biztonságos.
                placeFixed(btn, pop);
            }
        });
    });

    document.addEventListener('click', function () {
        closeAll();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeAll();
    });
    window.addEventListener('scroll', function () {
        closeAll();
    }, true);
    window.addEventListener('resize', function () {
        closeAll();
    });
})();
</script>

<style>
.events-monthly-stats__insights {
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
.events-monthly-stats__insights li {
    margin: 0;
}
.events-monthly-stats__delta--up,
.events-monthly-stats td.events-monthly-stats__delta--up,
.events-monthly-stats .events-edit-stats__card-hint.events-monthly-stats__delta--up {
    color: #2f6b3a;
}
.events-monthly-stats__delta--down,
.events-monthly-stats td.events-monthly-stats__delta--down,
.events-monthly-stats .events-edit-stats__card-hint.events-monthly-stats__delta--down {
    color: #a33b2b;
}
.events-monthly-stats__row--empty td {
    opacity: 0.45;
}
.events-monthly-stats__table th,
.events-monthly-stats__table td {
    white-space: nowrap;
}
.events-monthly-stats__label-with-info,
.events-monthly-stats__title-with-info,
.events-monthly-stats__th-inner {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.events-monthly-stats__th-inner {
    justify-content: center;
}
.events-monthly-stats__th:first-child .events-monthly-stats__th-inner {
    justify-content: flex-start;
}
.events-edit-stats__info-popover--fixed {
    z-index: 80;
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.16);
}
.events-edit-stats__info-popover--fixed::before {
    display: none;
}
</style>
