<?php
declare(strict_types=1);

/**
 * Esemény előtti 30 nap oldalbetöltés UI.
 *
 * @var array{year:int,status:string,visitor:string,event_id:int|null} $statsParams
 * @var array<string, mixed> $statsData
 * @var string $statsFormAction
 */

$statsParams = $statsParams ?? [
    'year' => (int) date('Y'),
    'status' => 'public',
    'visitor' => 'human',
    'event_id' => null,
];
$statsData = is_array($statsData ?? null) ? $statsData : [];
$statsFormAction = (string) ($statsFormAction ?? '');

$summary = is_array($statsData['summary'] ?? null) ? $statsData['summary'] : [];
$distribution = is_array($statsData['distribution'] ?? null) ? $statsData['distribution'] : [];
$chart = is_array($statsData['chart'] ?? null) ? $statsData['chart'] : [];
$events = is_array($statsData['events'] ?? null) ? $statsData['events'] : [];
$availableYears = is_array($statsData['available_years'] ?? null) ? $statsData['available_years'] : [];
$selectedEvent = is_array($statsData['selected_event'] ?? null) ? $statsData['selected_event'] : null;

$year = (int) ($statsData['year'] ?? $statsParams['year']);
$status = (string) ($statsParams['status'] ?? 'public');
$visitor = events_realtime_normalize_visitor($statsParams['visitor'] ?? 'human');
$eventId = $statsParams['event_id'] ?? null;

$pageViews = (int) ($summary['page_views'] ?? 0);
$eventsCount = (int) ($summary['events_count'] ?? 0);
$eventsWithViews = (int) ($summary['events_with_views'] ?? 0);
$avgViews = $summary['avg_views_per_event'] ?? null;
$medianViews = $summary['median_views'] ?? null;

$visitorLabel = match ($visitor) {
    'bot' => 'Csak robot',
    'all' => 'Ember + robot',
    default => 'Csak ember',
};
$statusLabel = match ($status) {
    'publish' => 'csak közzétéve',
    'all' => 'összes (lomtár nélkül)',
    default => 'közzétéve + előzetes',
};

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$hasChart = $pageViews > 0;

$buildUrl = static function (array $overrides) use ($statsFormAction, $statsParams): string {
    $q = array_merge($statsParams, $overrides);
    $query = [
        'stat_year' => (int) $q['year'],
        'stat_status' => (string) $q['status'],
        'visitor' => events_realtime_normalize_visitor($q['visitor'] ?? 'human'),
    ];
    $eid = $q['event_id'] ?? null;
    if ($eid !== null && (int) $eid > 0) {
        $query['event_id'] = (int) $eid;
    }

    return $statsFormAction . '?' . http_build_query($query);
};

$fmtPct = static function (?float $v): string {
    if ($v === null) {
        return '—';
    }

    return rtrim(rtrim(number_format($v, 2, ',', ' '), '0'), ',') . '%';
};

$fmtDate = static function (string $raw): string {
    $raw = trim($raw);
    if ($raw === '' || str_starts_with($raw, '0000-00-00')) {
        return '—';
    }
    try {
        return (new DateTimeImmutable($raw))->format('Y.m.d. H:i');
    } catch (Throwable) {
        return $raw;
    }
};

$maxDayCount = 0;
foreach ($distribution as $dRow) {
    $maxDayCount = max($maxDayCount, (int) ($dRow['count'] ?? 0));
}
?>
<div class="card events-edit-stats events-pre-event-stats">
    <p class="events-edit-stats__intro">
        Az ablak az esemény napját <strong>nem</strong> tartalmazza: a megelőző 30 naptári nap
        (<code>−30</code> … <code>−1</code>) oldalbetöltései (<code>page_view</code>).
        A <strong>publikálás előtti</strong> időszak nem számít bele — csak a publikálástól az esemény napjáig tartó forgalom.
        Az aggregált eloszlás a vizsgált események relatív napjainak százalékos megoszlását mutatja.
        Szűrő: <?= h($visitorLabel) ?>, státusz: <?= h($statusLabel) ?>.
    </p>

    <?php if (empty($statsData['table_ready'])): ?>
        <p class="alert alert-warning">Az oldalmegtekintés-tábla nem elérhető. Ellenőrizd az adatbázis-jogosultságokat.</p>
    <?php else: ?>
        <form method="get" action="<?= h($statsFormAction) ?>" class="events-edit-stats__filters">
            <?php if ($availableYears !== []): ?>
                <div class="events-edit-stats__presets-row">
                    <span class="events-filter-label">Év</span>
                    <?php foreach ($availableYears as $y): ?>
                        <a
                            class="btn btn-sm <?= (int) $y === $year ? 'btn-primary' : 'btn-secondary' ?>"
                            href="<?= h($buildUrl(['year' => (int) $y, 'event_id' => null])) ?>"
                        ><?= (int) $y ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="events-edit-stats__filter-grid">
                <div class="form-group">
                    <label class="events-filter-label" for="pre_stat_year">Év</label>
                    <select class="events-filter-input" name="stat_year" id="pre_stat_year">
                        <?php foreach ($availableYears as $y): ?>
                            <option value="<?= (int) $y ?>"<?= (int) $y === $year ? ' selected' : '' ?>><?= (int) $y ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="events-filter-label" for="pre_stat_status">Státusz</label>
                    <select class="events-filter-input" name="stat_status" id="pre_stat_status">
                        <option value="public"<?= $status === 'public' ? ' selected' : '' ?>>Közzétéve + előzetes</option>
                        <option value="publish"<?= $status === 'publish' ? ' selected' : '' ?>>Csak közzétéve</option>
                        <option value="all"<?= $status === 'all' ? ' selected' : '' ?>>Összes (lomtár nélkül)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="events-filter-label" for="pre_visitor">Látogató</label>
                    <select class="events-filter-input" name="visitor" id="pre_visitor">
                        <option value="human"<?= $visitor === 'human' ? ' selected' : '' ?>>Csak ember</option>
                        <option value="all"<?= $visitor === 'all' ? ' selected' : '' ?>>Ember + robot (összes)</option>
                        <option value="bot"<?= $visitor === 'bot' ? ' selected' : '' ?>>Csak robot</option>
                    </select>
                </div>
                <?php if ($eventId !== null): ?>
                    <input type="hidden" name="event_id" value="<?= (int) $eventId ?>">
                <?php endif; ?>
                <div class="form-group events-edit-stats__filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm">Szűrés</button>
                    <a class="btn btn-secondary btn-sm" href="<?= h($statsFormAction) ?>">Szűrés törlése</a>
                </div>
            </div>
        </form>

        <div class="events-edit-stats__cards">
            <div class="events-edit-stats__card">
                <p class="events-edit-stats__card-label">Oldalbetöltés (30 nap)</p>
                <p class="events-edit-stats__card-value"><?= number_format($pageViews, 0, ',', ' ') ?></p>
                <p class="events-edit-stats__card-hint"><?= h($visitorLabel) ?></p>
            </div>
            <div class="events-edit-stats__card">
                <p class="events-edit-stats__card-label">Események</p>
                <p class="events-edit-stats__card-value"><?= number_format($eventsCount, 0, ',', ' ') ?></p>
                <p class="events-edit-stats__card-hint">ebből forgalmas: <?= number_format($eventsWithViews, 0, ',', ' ') ?></p>
            </div>
            <div class="events-edit-stats__card">
                <p class="events-edit-stats__card-label">Átlag / esemény</p>
                <p class="events-edit-stats__card-value"><?= $avgViews !== null ? h((string) $avgViews) : '—' ?></p>
            </div>
            <div class="events-edit-stats__card">
                <p class="events-edit-stats__card-label">Medián / esemény</p>
                <p class="events-edit-stats__card-value"><?= $medianViews !== null ? h((string) $medianViews) : '—' ?></p>
            </div>
        </div>

        <?php if ($pageViews === 0): ?>
            <p class="help events-edit-stats__empty">
                Nincs oldalbetöltés a választott év eseményeinek publikálás utáni, esemény előtti 30 napjában a megadott szűrőkkel.
            </p>
        <?php endif; ?>

        <?php if ($hasChart): ?>
            <div class="events-edit-stats__chart-wrap">
                <div class="events-edit-stats__chart-head">
                    <div>
                        <h3 class="events-edit-stats__chart-title">Relatív napok eloszlása (összes esemény)</h3>
                        <p class="events-edit-stats__chart-hint">
                            −30 = 30 nappal az esemény előtt, −1 = az esemény előtti nap.
                            Oszlop: darabszám; vonal: százalék az összes előtti oldalbetöltésből.
                        </p>
                    </div>
                </div>
                <div class="events-edit-stats__chart-canvas events-pre-event-stats__chart-canvas">
                    <canvas id="pre-event-dist-chart" aria-label="Előtti 30 nap eloszlás grafikon"></canvas>
                </div>
            </div>
            <script type="application/json" id="pre-event-dist-chart-data"><?= json_encode($chart, $jsonFlags) ?></script>
        <?php endif; ?>

        <div class="events-edit-stats__table-wrap">
            <h3 class="events-edit-stats__section-title">Napi eloszlás (aggregált)</h3>
            <table class="events-table events-pre-event-stats__table">
                <thead>
                    <tr>
                        <th scope="col">Nap</th>
                        <th class="th-center" scope="col">Oldalbetöltés</th>
                        <th class="th-center" scope="col">Arány</th>
                        <th scope="col">Sáv</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($distribution as $dRow): ?>
                        <?php
                        $cnt = (int) ($dRow['count'] ?? 0);
                        $pct = $dRow['pct'] ?? null;
                        $barPct = ($maxDayCount > 0) ? round(($cnt / $maxDayCount) * 100, 1) : 0;
                        ?>
                        <tr<?= $cnt === 0 ? ' class="events-pre-event-stats__row--empty"' : '' ?>>
                            <td><?= h((string) ($dRow['label'] ?? '')) ?></td>
                            <td class="th-center"><?= number_format($cnt, 0, ',', ' ') ?></td>
                            <td class="th-center"><?= h($fmtPct(is_float($pct) || is_int($pct) ? (float) $pct : null)) ?></td>
                            <td>
                                <span class="events-pre-event-stats__bar" style="--bar: <?= h((string) $barPct) ?>%" title="<?= h($fmtPct(is_float($pct) || is_int($pct) ? (float) $pct : null)) ?>"></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($selectedEvent !== null): ?>
            <?php
            $selDist = is_array($selectedEvent['distribution'] ?? null) ? $selectedEvent['distribution'] : [];
            $selChart = is_array($selectedEvent['chart'] ?? null) ? $selectedEvent['chart'] : [];
            $selViews = (int) ($selectedEvent['page_views'] ?? 0);
            $selPublished = (string) ($selectedEvent['event_published_at'] ?? '');
            $selPublishDays = isset($selectedEvent['publish_days_before']) && $selectedEvent['publish_days_before'] !== null
                ? (int) $selectedEvent['publish_days_before']
                : null;
            $selMax = 0;
            foreach ($selDist as $sd) {
                if (!empty($sd['before_publish'])) {
                    continue;
                }
                $selMax = max($selMax, (int) ($sd['count'] ?? 0));
            }
            ?>
            <div class="events-pre-event-stats__selected">
                <div class="events-edit-stats__chart-head">
                    <div>
                        <h3 class="events-edit-stats__section-title">
                            Esemény: <?= h((string) ($selectedEvent['event_name'] ?? '')) ?>
                        </h3>
                        <p class="events-edit-stats__chart-hint">
                            Kezdés: <?= h($fmtDate((string) ($selectedEvent['event_start'] ?? ''))) ?>
                            · Publikálva: <?= h($selPublished !== '' ? $fmtDate($selPublished) : '—') ?>
                            <?php if ($selPublishDays !== null): ?>
                                <span class="events-pre-event-stats__publish-badge">
                                    <?= $selPublishDays ?> nappal az esemény előtt
                                </span>
                            <?php endif; ?>
                            · mért ablak: <?= number_format($selViews, 0, ',', ' ') ?> oldalbetöltés
                            · <?= h($visitorLabel) ?>
                        </p>
                    </div>
                    <a class="btn btn-secondary btn-sm" href="<?= h($buildUrl(['event_id' => null])) ?>">Bezárás</a>
                </div>

                <?php if ($selViews > 0): ?>
                    <div class="events-edit-stats__chart-wrap">
                        <div class="events-edit-stats__chart-canvas events-pre-event-stats__chart-canvas">
                            <canvas id="pre-event-selected-chart" aria-label="Kiválasztott esemény eloszlás"></canvas>
                        </div>
                    </div>
                    <script type="application/json" id="pre-event-selected-chart-data"><?= json_encode($selChart, $jsonFlags) ?></script>
                <?php else: ?>
                    <p class="help">Ehhez az eseményhez nincs oldalbetöltés a publikálás utáni, esemény előtti 30 napban.</p>
                <?php endif; ?>

                <div class="events-edit-stats__table-wrap">
                    <table class="events-table events-pre-event-stats__table">
                        <thead>
                            <tr>
                                <th scope="col">Nap</th>
                                <th class="th-center" scope="col">Oldalbetöltés</th>
                                <th class="th-center" scope="col">Arány</th>
                                <th scope="col">Sáv</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($selDist as $dRow): ?>
                                <?php
                                $beforePublish = !empty($dRow['before_publish']);
                                $cnt = (int) ($dRow['count'] ?? 0);
                                $pct = $dRow['pct'] ?? null;
                                $barPct = (!$beforePublish && $selMax > 0) ? round(($cnt / $selMax) * 100, 1) : 0;
                                $rowClass = $beforePublish
                                    ? 'events-pre-event-stats__row--before-publish'
                                    : ($cnt === 0 ? 'events-pre-event-stats__row--empty' : '');
                                ?>
                                <tr<?= $rowClass !== '' ? ' class="' . h($rowClass) . '"' : '' ?>>
                                    <td>
                                        <?= h((string) ($dRow['label'] ?? '')) ?>
                                        <?php if ($beforePublish): ?>
                                            <span class="events-pre-event-stats__day-note">publikálás előtt</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="th-center"><?= $beforePublish ? '—' : number_format($cnt, 0, ',', ' ') ?></td>
                                    <td class="th-center"><?= $beforePublish ? '—' : h($fmtPct(is_float($pct) || is_int($pct) ? (float) $pct : null)) ?></td>
                                    <td>
                                        <?php if (!$beforePublish): ?>
                                            <span class="events-pre-event-stats__bar" style="--bar: <?= h((string) $barPct) ?>%"></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <div class="events-edit-stats__table-wrap">
            <h3 class="events-edit-stats__section-title">Eseményenként</h3>
            <?php if ($events === []): ?>
                <p class="help">Nincs esemény a választott évben / státuszszűrővel.</p>
            <?php else: ?>
                <table class="events-table events-pre-event-stats__events-table">
                    <thead>
                        <tr>
                            <th scope="col">Esemény</th>
                            <th class="th-center" scope="col">Kezdés</th>
                            <th class="th-center" scope="col">Publikálva</th>
                            <th class="th-center" scope="col">Státusz</th>
                            <th class="th-center" scope="col">30 nap</th>
                            <th class="th-center" scope="col">Részesedés</th>
                            <th class="th-center" scope="col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $ev): ?>
                            <?php
                            $eid = (int) ($ev['id'] ?? 0);
                            $isActive = $eventId !== null && $eid === (int) $eventId;
                            $evViews = (int) ($ev['page_views'] ?? 0);
                            $share = $ev['share_pct'] ?? null;
                            $evPublished = (string) ($ev['event_published_at'] ?? '');
                            $evPublishDays = isset($ev['publish_days_before']) && $ev['publish_days_before'] !== null
                                ? (int) $ev['publish_days_before']
                                : null;
                            ?>
                            <tr<?= $isActive ? ' class="events-pre-event-stats__row--active"' : ($evViews === 0 ? ' class="events-pre-event-stats__row--empty"' : '') ?>>
                                <td><?= h((string) ($ev['event_name'] ?? '')) ?></td>
                                <td class="th-center"><?= h($fmtDate((string) ($ev['event_start'] ?? ''))) ?></td>
                                <td class="th-center">
                                    <?= h($evPublished !== '' ? $fmtDate($evPublished) : '—') ?>
                                    <?php if ($evPublishDays !== null): ?>
                                        <div class="events-pre-event-stats__publish-sub">−<?= (int) $evPublishDays ?> nap</div>
                                    <?php endif; ?>
                                </td>
                                <td class="th-center"><?= h(events_post_status_label((string) ($ev['event_status'] ?? ''))) ?></td>
                                <td class="th-center"><?= number_format($evViews, 0, ',', ' ') ?></td>
                                <td class="th-center"><?= h($fmtPct(is_float($share) || is_int($share) ? (float) $share : null)) ?></td>
                                <td class="th-center">
                                    <a
                                        class="btn btn-sm <?= $isActive ? 'btn-primary' : 'btn-secondary' ?>"
                                        href="<?= h($buildUrl(['event_id' => $isActive ? null : $eid])) ?>"
                                    ><?= $isActive ? 'Bezár' : 'Napi bontás' ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($hasChart || ($selectedEvent !== null && (int) ($selectedEvent['page_views'] ?? 0) > 0)): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js" crossorigin="anonymous"></script>
    <script>
    (function () {
        function makeCombo(canvasId, dataId) {
            var canvas = document.getElementById(canvasId);
            var dataEl = document.getElementById(dataId);
            if (!canvas || !dataEl || typeof Chart === 'undefined') return;
            var payload;
            try {
                payload = JSON.parse(dataEl.textContent || '{}');
            } catch (e) {
                return;
            }
            var labels = payload.labels || [];
            var counts = (payload.counts || []).map(function (v) {
                return v === null || typeof v === 'undefined' ? null : Number(v);
            });
            var pcts = (payload.pcts || []).map(function (v) {
                return v === null || typeof v === 'undefined' ? null : Number(v);
            });
            new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            type: 'bar',
                            label: 'Oldalbetöltés',
                            data: counts,
                            backgroundColor: 'rgba(109, 143, 99, 0.55)',
                            borderColor: '#6d8f63',
                            borderWidth: 1,
                            yAxisID: 'y',
                            order: 2,
                            spanGaps: true
                        },
                        {
                            type: 'line',
                            label: 'Arány %',
                            data: pcts,
                            borderColor: '#3d5a80',
                            backgroundColor: 'rgba(61, 90, 128, 0.15)',
                            borderWidth: 2,
                            tension: 0.25,
                            pointRadius: 2,
                            spanGaps: true,
                            yAxisID: 'y1',
                            order: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    var v = ctx.parsed.y;
                                    if (v === null || typeof v === 'undefined') return ctx.dataset.label + ': —';
                                    if (ctx.dataset.yAxisID === 'y1') {
                                        return ctx.dataset.label + ': ' + v.toFixed(2).replace('.', ',') + '%';
                                    }
                                    return ctx.dataset.label + ': ' + v;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Darab' },
                            ticks: { precision: 0 }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            grid: { drawOnChartArea: false },
                            title: { display: true, text: '%' },
                            ticks: {
                                callback: function (v) {
                                    return String(v).replace('.', ',') + '%';
                                }
                            }
                        }
                    }
                }
            });
        }

        makeCombo('pre-event-dist-chart', 'pre-event-dist-chart-data');
        makeCombo('pre-event-selected-chart', 'pre-event-selected-chart-data');
    })();
    </script>
<?php endif; ?>

<style>
.events-pre-event-stats__chart-canvas {
    min-height: 280px;
}
.events-pre-event-stats__bar {
    display: block;
    height: 0.55rem;
    max-width: 12rem;
    border-radius: 999px;
    background: linear-gradient(90deg, #6d8f63 0%, #6d8f63 var(--bar, 0%), rgba(109, 143, 99, 0.12) var(--bar, 0%));
}
.events-pre-event-stats__row--empty td {
    opacity: 0.45;
}
.events-pre-event-stats__row--before-publish td {
    opacity: 0.55;
    background: rgba(148, 163, 184, 0.12);
    font-style: italic;
}
.events-pre-event-stats__day-note {
    display: inline-block;
    margin-left: 0.4rem;
    font-size: 0.78rem;
    font-style: normal;
    color: #64748b;
}
.events-pre-event-stats__publish-badge {
    display: inline-block;
    margin-left: 0.35rem;
    padding: 0.1rem 0.45rem;
    border-radius: 4px;
    background: rgba(61, 107, 79, 0.12);
    color: #3d6b4f;
    font-size: 0.82rem;
}
.events-pre-event-stats__publish-sub {
    margin-top: 0.15rem;
    font-size: 0.78rem;
    color: #64748b;
}
.events-pre-event-stats__row--active td {
    background: rgba(109, 143, 99, 0.12);
}
.events-pre-event-stats__selected {
    margin: 1.5rem 0;
    padding: 1rem 1.1rem 1.25rem;
    border: 1px solid rgba(61, 107, 79, 0.18);
    border-radius: 10px;
    background: rgba(61, 107, 79, 0.04);
}
.events-pre-event-stats__events-table td:first-child {
    max-width: 22rem;
    white-space: normal;
}
.events-pre-event-stats__table th,
.events-pre-event-stats__table td {
    white-space: nowrap;
}
</style>
