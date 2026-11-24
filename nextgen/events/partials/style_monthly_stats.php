<?php
declare(strict_types=1);

/**
 * Havi főstílus-statisztika UI.
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

$year = (int) ($statsData['year'] ?? $statsParams['year']);
$bucketBy = (string) ($statsParams['bucket_by'] ?? 'event_start');
$status = (string) ($statsParams['status'] ?? 'public');
$availableYears = is_array($statsData['available_years'] ?? null) ? $statsData['available_years'] : [];
$styles = is_array($statsData['styles'] ?? null) ? $statsData['styles'] : [];
$monthTotals = is_array($statsData['month_totals'] ?? null) ? $statsData['month_totals'] : [];
$chart = is_array($statsData['chart'] ?? null) ? $statsData['chart'] : ['labels' => [], 'datasets' => []];
$stylesReady = !empty($statsData['styles_ready']);
$eventsCount = (int) ($statsData['events_count'] ?? 0);
$eventsWithStyles = (int) ($statsData['events_with_styles'] ?? 0);
$eventsWithoutStyles = (int) ($statsData['events_without_styles'] ?? 0);
$yearTotal = (float) ($statsData['year_total'] ?? 0);
$unstyledEvents = is_array($statsData['unstyled_events'] ?? null) ? $statsData['unstyled_events'] : [];
$exportUrl = events_url('events_stilus_havi_export.php') . '?' . http_build_query([
    'stat_bucket' => (string) ($statsParams['bucket_by'] ?? 'event_start'),
    'stat_status' => (string) ($statsParams['status'] ?? 'public'),
]);

$bucketLabel = $bucketBy === 'published_at' ? 'publikálás dátuma' : 'esemény dátuma';
$statusLabel = match ($status) {
    'publish' => 'csak közzétéve',
    'all' => 'összes (lomtár nélkül)',
    default => 'közzétéve + előzetes',
};

$fmt = static function (float $value): string {
    return events_style_monthly_stats_format_weight($value);
};

$buildUrl = static function (array $overrides) use ($statsFormAction, $statsParams): string {
    $q = array_merge($statsParams, $overrides);

    return $statsFormAction . '?' . http_build_query([
        'stat_year' => (int) $q['year'],
        'stat_bucket' => (string) $q['bucket_by'],
        'stat_status' => (string) $q['status'],
    ]);
};

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$hasChart = $styles !== [] && $yearTotal > 0;
?>
<div class="card events-edit-stats events-style-monthly-stats">
    <p class="events-edit-stats__intro">
        Havi bontás <strong>fő stílus</strong> szerint. Ha egy eseménynek több fő stílusa van,
        a súlya egyenlően oszlik el (2 stílus → 0,5–0,5). A hónapösszeg így megegyezik az eseményszámmal.
        A „Nincs fő stílus” sor a stílus nélküli eseményeket jelöli.
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
                <label class="events-filter-label" for="stat_year">Év</label>
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
                <label class="events-filter-label" for="stat_bucket">Hónap szerint</label>
                <select class="events-filter-input" name="stat_bucket" id="stat_bucket">
                    <option value="event_start"<?= $bucketBy === 'event_start' ? ' selected' : '' ?>>Esemény dátuma</option>
                    <option value="published_at"<?= $bucketBy === 'published_at' ? ' selected' : '' ?>>Publikálás dátuma</option>
                </select>
            </div>
            <div class="form-group">
                <label class="events-filter-label" for="stat_status">Státusz</label>
                <select class="events-filter-input" name="stat_status" id="stat_status">
                    <option value="public"<?= $status === 'public' ? ' selected' : '' ?>>Közzétéve + előzetes</option>
                    <option value="publish"<?= $status === 'publish' ? ' selected' : '' ?>>Csak közzétéve</option>
                    <option value="all"<?= $status === 'all' ? ' selected' : '' ?>>Összes (lomtár nélkül)</option>
                </select>
            </div>
            <div class="form-group events-edit-stats__filter-actions">
                <button type="submit" class="btn btn-primary btn-sm">Szűrés</button>
                <a class="btn btn-secondary btn-sm" href="<?= h($statsFormAction) ?>">Alapértelmezés</a>
                <a class="btn btn-secondary btn-sm" href="<?= h($exportUrl) ?>">Excel – összes hónap</a>
            </div>
        </div>
        <p class="events-edit-stats__filter-hint">
            Aktív nézet: <strong><?= $year ?></strong> · <?= h($bucketLabel) ?> · <?= h($statusLabel) ?>.
            Az Excel a szűrő státuszával és hónap-szabályával az összes hónapot tartalmazza: sor = hónap, oszlop = stílus.
        </p>
    </form>

    <?php if (!$stylesReady): ?>
        <p class="alert alert-error">A stílus táblák nem érhetők el. Ellenőrizd a stílus migrációt.</p>
    <?php else: ?>
        <div class="events-edit-stats__cards">
            <div class="events-edit-stats__card">
                <p class="events-edit-stats__card-label">Események</p>
                <p class="events-edit-stats__card-value"><?= $eventsCount ?></p>
                <p class="events-edit-stats__card-hint"><?= $year ?> · <?= h($bucketLabel) ?></p>
            </div>
            <div class="events-edit-stats__card">
                <p class="events-edit-stats__card-label">Fő stílussal</p>
                <p class="events-edit-stats__card-value"><?= $eventsWithStyles ?></p>
                <p class="events-edit-stats__card-hint">legalább egy fő stílus</p>
            </div>
            <div class="events-edit-stats__card">
                <p class="events-edit-stats__card-label">Stílus nélkül</p>
                <p class="events-edit-stats__card-value"><?= $eventsWithoutStyles ?></p>
                <p class="events-edit-stats__card-hint">nincs fő stílus megadva</p>
            </div>
            <div class="events-edit-stats__card">
                <p class="events-edit-stats__card-label">Stílusok</p>
                <p class="events-edit-stats__card-value"><?= count(array_filter($styles, static fn (array $s): bool => (int) ($s['id'] ?? 0) > 0)) ?></p>
                <p class="events-edit-stats__card-hint">amelyekhez van adat az évben</p>
            </div>
        </div>

        <?php if ($eventsCount === 0): ?>
            <p class="help events-edit-stats__empty">Nincs esemény a választott évben / szűrőkkel.</p>
        <?php else: ?>
            <?php if ($hasChart): ?>
                <div class="events-edit-stats__chart-wrap">
                    <div class="events-edit-stats__chart-head">
                        <div>
                            <h3 class="events-edit-stats__chart-title">Fő stílusok hónapról hónapra</h3>
                            <p class="events-edit-stats__chart-hint">
                                Halmozott oszlopok: az egy hónapba sorolt események súlyozott stílusösszege.
                            </p>
                        </div>
                    </div>
                    <div class="events-edit-stats__chart-canvas events-style-monthly-stats__chart-canvas">
                        <canvas id="style-monthly-stats-chart" aria-label="Havi főstílus eloszlás"></canvas>
                    </div>
                </div>
                <script type="application/json" id="style-monthly-stats-chart-data"><?= json_encode($chart, $jsonFlags) ?></script>
            <?php endif; ?>

            <h3 class="events-edit-stats__events-title">Havi táblázat</h3>
            <p class="events-edit-stats__events-hint">
                A tört értékek (pl. 0,5) több fő stílusos eseményekből származnak. Az oszlopösszeg = az adott hónap eseményszáma.
            </p>
            <div class="table-wrap events-admin-table-wrap">
                <table class="events-admin-table events-style-monthly-stats__table">
                    <thead>
                        <tr>
                            <th scope="col">Fő stílus</th>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <th class="th-center" scope="col" title="<?= h(events_monthly_stats_month_label_full($m)) ?>">
                                    <?= h(events_monthly_stats_month_label($m)) ?>
                                </th>
                            <?php endfor; ?>
                            <th class="th-center" scope="col">Összesen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($styles as $style): ?>
                            <?php
                            $styleId = (int) ($style['id'] ?? 0);
                            $styleName = (string) ($style['name'] ?? '');
                            $styleColor = (string) ($style['color'] ?? '#6d8f63');
                            $months = is_array($style['months'] ?? null) ? $style['months'] : [];
                            $total = (float) ($style['total'] ?? 0);
                            ?>
                            <tr<?= $styleId === 0 ? ' class="events-style-monthly-stats__row--none"' : '' ?>>
                                <th scope="row" class="events-style-monthly-stats__style">
                                    <span class="events-style-monthly-stats__swatch" style="background:<?= h($styleColor) ?>" aria-hidden="true"></span>
                                    <?= h($styleName) ?>
                                </th>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <?php $cell = (float) ($months[$m] ?? 0); ?>
                                    <td class="td-center<?= $cell > 0 ? '' : ' events-style-monthly-stats__cell--empty' ?>">
                                        <?= $cell > 0 ? h($fmt($cell)) : '—' ?>
                                    </td>
                                <?php endfor; ?>
                                <td class="td-center"><strong><?= h($fmt($total)) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row">Összesen</th>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <?php $cell = (float) ($monthTotals[$m] ?? 0); ?>
                                <td class="td-center"><strong><?= $cell > 0 ? h($fmt($cell)) : '—' ?></strong></td>
                            <?php endfor; ?>
                            <td class="td-center"><strong><?= h($fmt($yearTotal)) ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>

        <h3 class="events-edit-stats__events-title" id="stilus-nelkuli-esemenyek">Fő stílus nélküli események</h3>
        <p class="events-edit-stats__events-hint">
            <?= count($unstyledEvents) ?> esemény a választott évben és szűrőkkel, amelyhez nincs érvényes fő stílus.
        </p>
        <?php if ($unstyledEvents === []): ?>
            <p class="help events-edit-stats__empty">Nincs ilyen esemény.</p>
        <?php else: ?>
            <div class="table-wrap events-admin-table-wrap">
                <table class="events-admin-table events-style-monthly-stats__missing">
                    <thead>
                        <tr>
                            <th scope="col">Dátum</th>
                            <th scope="col">Esemény</th>
                            <th scope="col">Szervező</th>
                            <th scope="col">Státusz</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unstyledEvents as $ev): ?>
                            <?php
                            $eventId = (int) ($ev['id'] ?? 0);
                            $editUrl = events_url('szerkeszt.php?id=') . $eventId;
                            $eventName = (string) ($ev['name'] ?? '');
                            $startRaw = (string) ($ev['start'] ?? '');
                            $startTs = $startRaw !== '' ? strtotime($startRaw) : false;
                            $dateLabel = $startTs !== false
                                ? date(!empty($ev['allday']) ? 'Y.m.d.' : 'Y.m.d. H:i', $startTs)
                                : '–';
                            $statusCode = (string) ($ev['status'] ?? '');
                            $publicUrl = '';
                            $slug = (string) ($ev['slug'] ?? '');
                            if ($slug !== '' && events_is_publicly_visible_post_status($statusCode)) {
                                $publicUrl = events_megjelenit_url($slug);
                            }
                            ?>
                            <tr>
                                <td><?= h($dateLabel) ?></td>
                                <td>
                                    <a class="events-cell-edit" href="<?= h($editUrl) ?>"><?= h($eventName !== '' ? $eventName : ('#' . $eventId)) ?></a>
                                    <?php if ($publicUrl !== ''): ?>
                                        <a class="events-style-monthly-stats__public" href="<?= h($publicUrl) ?>" target="_blank" rel="noopener">nyilvános</a>
                                    <?php endif; ?>
                                </td>
                                <?php $organizers = trim((string) ($ev['organizers'] ?? '')); ?>
                                <td><?= $organizers !== '' ? h($organizers) : '–' ?></td>
                                <td><?= h(events_post_status_label($statusCode)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($hasChart): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js" crossorigin="anonymous"></script>
<script>
(function () {
    var el = document.getElementById('style-monthly-stats-chart');
    var raw = document.getElementById('style-monthly-stats-chart-data');
    if (!el || !raw || typeof Chart === 'undefined') return;
    var payload;
    try {
        payload = JSON.parse(raw.textContent || '{}');
    } catch (e) {
        return;
    }
    var labels = Array.isArray(payload.labels) ? payload.labels : [];
    var datasets = Array.isArray(payload.datasets) ? payload.datasets : [];
    if (!labels.length || !datasets.length) return;

    new Chart(el, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, boxHeight: 12, usePointStyle: true, pointStyle: 'rectRounded' }
                },
                tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            var v = ctx.parsed && typeof ctx.parsed.y === 'number' ? ctx.parsed.y : 0;
                            var text = String(v).replace('.', ',');
                            return (ctx.dataset.label || '') + ': ' + text;
                        }
                    }
                }
            },
            scales: {
                x: {
                    stacked: true,
                    grid: { display: false }
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
})();
</script>
<?php endif; ?>

<style>
.events-style-monthly-stats__chart-canvas {
    height: 320px;
}
.events-style-monthly-stats__table th,
.events-style-monthly-stats__table td {
    white-space: nowrap;
}
.events-style-monthly-stats__style {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
}
.events-style-monthly-stats__swatch {
    display: inline-block;
    width: 0.7rem;
    height: 0.7rem;
    border-radius: 0.2rem;
    flex-shrink: 0;
}
.events-style-monthly-stats__cell--empty {
    color: var(--li-text-soft, #94a3b8);
}
.events-style-monthly-stats__row--none .events-style-monthly-stats__style {
    font-weight: 500;
    color: var(--li-text-soft, #64748b);
}
.events-style-monthly-stats__public {
    margin-left: 0.55rem;
    font-size: 0.78rem;
    color: var(--li-text-soft, #64748b);
}
</style>
