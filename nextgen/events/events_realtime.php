<?php
declare(strict_types=1);

/**
 * Valós idejű áttekintés — GA Realtime-szerű admin dashboard.
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/event_realtime_stats.php';
requireLogin();

$db = getDb();
$snapshot = events_realtime_snapshot($db);
$generatedAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
$ajaxUrl = events_url('ajax_events_realtime.php');
$editBase = events_url('szerkeszt.php?id=');
$listaStatUrl = events_url('events_lista_stat.php');
$listUrl = events_url('events_admin.php');

$payload = array_merge(
    [
        'ok' => true,
        'generated_at' => $generatedAt,
        'edit_base' => $editBase,
    ],
    $snapshot
);
$payloadJson = json_encode(
    $payload,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Valós idejű áttekintés';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<div class="card events-admin-card events-rt-page" id="events-rt-root" data-ajax-url="<?= h($ajaxUrl) ?>" data-poll-ms="20000">
    <div class="events-list-head events-cal-page__head">
        <div class="events-cal-page__head-start">
            <h2 class="events-list-title">Valós idejű áttekintés</h2>
            <p class="events-rt-subtitle">Utolsó <?= (int) EVENTS_REALTIME_WINDOW_MINUTES ?> perc · eseményoldal és naptár előnézet</p>
        </div>
        <div class="events-list-actions">
            <a href="<?= h($listaStatUrl) ?>" class="btn btn-secondary btn-sm">Lista stat</a>
            <a href="<?= h($listUrl) ?>" class="btn btn-secondary btn-sm">Események lista</a>
        </div>
    </div>

    <div class="events-rt-hero">
        <div class="events-rt-hero__live">
            <span class="events-rt-live-dot" aria-hidden="true"></span>
            <span class="events-rt-live-label">Élő</span>
            <span class="events-rt-updated" id="events-rt-updated">Frissítve: <?= h($generatedAt) ?></span>
        </div>
        <p class="events-rt-hero__label">Felhasználók az elmúlt <?= (int) EVENTS_REALTIME_WINDOW_MINUTES ?> percben</p>
        <p class="events-rt-hero__value" id="events-rt-users"><?= (int) $snapshot['users_30m'] ?></p>
        <p class="events-rt-hero__hint">Egyedi emberi oldal-látogató (IP)</p>
    </div>

    <div class="events-rt-kpis" aria-label="Összesítők">
        <div class="events-rt-kpi events-rt-kpi--page">
            <p class="events-rt-kpi__label">Oldal</p>
            <p class="events-rt-kpi__value" id="events-rt-page"><?= (int) $snapshot['page_hits_30m'] ?></p>
            <p class="events-rt-kpi__hint">emberi megtekintés</p>
        </div>
        <div class="events-rt-kpi events-rt-kpi--preview">
            <p class="events-rt-kpi__label">Előnézet</p>
            <p class="events-rt-kpi__value" id="events-rt-preview"><?= (int) $snapshot['preview_hits_30m'] ?></p>
            <p class="events-rt-kpi__hint">naptár előnézet</p>
        </div>
        <div class="events-rt-kpi events-rt-kpi--bot">
            <p class="events-rt-kpi__label">Bot</p>
            <p class="events-rt-kpi__value" id="events-rt-bot"><?= (int) $snapshot['bot_hits_30m'] ?></p>
            <p class="events-rt-kpi__hint">összes bot hit</p>
        </div>
    </div>

    <section class="events-rt-chart-panel" aria-labelledby="events-rt-chart-title">
        <h3 class="events-rt-section-title" id="events-rt-chart-title">Percenkénti aktivitás</h3>
        <p class="events-rt-section-hint">Egyedi felhasználók, oldal- és előnézet-megnyitások az elmúlt <?= (int) EVENTS_REALTIME_WINDOW_MINUTES ?> percben.</p>
        <div class="events-rt-chart-canvas">
            <canvas id="events-rt-chart" aria-label="Valós idejű aktivitás grafikonja"></canvas>
        </div>
    </section>

    <div class="events-rt-split">
        <section class="events-rt-panel" aria-labelledby="events-rt-top-title">
            <h3 class="events-rt-section-title" id="events-rt-top-title">Top események</h3>
            <div class="table-wrap events-rt-table-wrap">
                <table class="events-admin-table events-rt-table">
                    <thead>
                        <tr>
                            <th scope="col">Esemény</th>
                            <th class="th-center" scope="col" title="Egyedi emberi oldal-látogató">Egyedi</th>
                            <th class="th-center" scope="col">Oldal</th>
                            <th class="th-center" scope="col">Előnézet</th>
                        </tr>
                    </thead>
                    <tbody id="events-rt-top-body">
                        <?php if ($snapshot['top_events'] === []): ?>
                            <tr class="events-rt-empty-row"><td colspan="4">Nincs aktivitás az elmúlt <?= (int) EVENTS_REALTIME_WINDOW_MINUTES ?> percben.</td></tr>
                        <?php else: ?>
                            <?php foreach ($snapshot['top_events'] as $ev): ?>
                                <tr>
                                    <td>
                                        <a href="<?= h($editBase . (int) $ev['id']) ?>"><?= h((string) $ev['name']) ?></a>
                                    </td>
                                    <td class="text-center"><?= (int) $ev['unique'] ?></td>
                                    <td class="text-center"><?= (int) $ev['page'] ?></td>
                                    <td class="text-center"><?= (int) $ev['preview'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="events-rt-panel" aria-labelledby="events-rt-source-title">
            <h3 class="events-rt-section-title" id="events-rt-source-title">Forrás</h3>
            <p class="events-rt-section-hint">Emberi oldalmegtekintések forrás szerint.</p>
            <ul class="events-rt-sources" id="events-rt-sources">
                <?php
                $sourceTotal = 0;
                foreach ($snapshot['by_source'] as $srcRow) {
                    $sourceTotal += (int) $srcRow['count'];
                }
                if ($snapshot['by_source'] === []):
                ?>
                    <li class="events-rt-sources__empty">Nincs adat.</li>
                <?php else: ?>
                    <?php foreach ($snapshot['by_source'] as $srcRow): ?>
                        <?php
                        $cnt = (int) $srcRow['count'];
                        $pct = $sourceTotal > 0 ? (int) round(($cnt / $sourceTotal) * 100) : 0;
                        ?>
                        <li class="events-rt-source">
                            <div class="events-rt-source__meta">
                                <span class="events-rt-source__label"><?= h((string) $srcRow['label']) ?></span>
                                <span class="events-rt-source__count"><?= $cnt ?> · <?= $pct ?>%</span>
                            </div>
                            <div class="events-rt-source__bar" aria-hidden="true">
                                <span class="events-rt-source__fill" style="width: <?= $pct ?>%"></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </section>
    </div>

    <section class="events-rt-panel events-rt-recent-panel" aria-labelledby="events-rt-recent-title">
        <h3 class="events-rt-section-title" id="events-rt-recent-title">Friss aktivitás</h3>
        <div class="table-wrap events-rt-table-wrap">
            <table class="events-admin-table events-rt-table events-rt-table--recent">
                <thead>
                    <tr>
                        <th scope="col">Idő</th>
                        <th scope="col">Esemény dátuma</th>
                        <th scope="col">Esemény</th>
                        <th scope="col">Metrika</th>
                        <th scope="col">Forrás</th>
                        <th scope="col">Típus</th>
                    </tr>
                </thead>
                <tbody id="events-rt-recent-body">
                    <?php if ($snapshot['recent'] === []): ?>
                        <tr class="events-rt-empty-row"><td colspan="6">Nincs friss aktivitás.</td></tr>
                    <?php else: ?>
                        <?php foreach ($snapshot['recent'] as $row): ?>
                            <tr>
                                <td class="events-rt-recent-time"><?= h((string) $row['at']) ?></td>
                                <td class="events-rt-recent-event-date"><?= h((string) ($row['event_date'] ?? '–')) ?></td>
                                <td>
                                    <?php if ((int) $row['event_id'] > 0): ?>
                                        <a href="<?= h($editBase . (int) $row['event_id']) ?>"><?= h((string) $row['name']) ?></a>
                                    <?php else: ?>
                                        <?= h((string) $row['name']) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= h((string) $row['metric_label']) ?></td>
                                <td><?= h((string) $row['source_label']) ?></td>
                                <td><?= !empty($row['is_bot']) ? 'Bot' : 'Ember' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script type="application/json" id="events-rt-initial"><?= $payloadJson !== false ? $payloadJson : '{}' ?></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js" crossorigin="anonymous"></script>
<script>
(function () {
    var root = document.getElementById('events-rt-root');
    var initialEl = document.getElementById('events-rt-initial');
    if (!root || !initialEl) return;

    var ajaxUrl = root.getAttribute('data-ajax-url') || '';
    var pollMs = parseInt(root.getAttribute('data-poll-ms') || '20000', 10) || 20000;
    var editBase = '';
    var chart = null;
    var windowMinutes = <?= (int) EVENTS_REALTIME_WINDOW_MINUTES ?>;

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function setText(id, value) {
        var el = document.getElementById(id);
        if (el) el.textContent = String(value);
    }

    function buildChart(payload) {
        var canvas = document.getElementById('events-rt-chart');
        if (!canvas || typeof Chart === 'undefined') return;
        var perMinute = payload.per_minute || [];
        var labels = perMinute.map(function (r) { return r.label || ''; });
        var users = perMinute.map(function (r) { return Number(r.users || 0); });
        var page = perMinute.map(function (r) { return Number(r.page || 0); });
        var preview = perMinute.map(function (r) { return Number(r.preview || 0); });

        if (chart) {
            chart.data.labels = labels;
            chart.data.datasets[0].data = users;
            chart.data.datasets[1].data = page;
            chart.data.datasets[2].data = preview;
            chart.update('none');
            return;
        }

        chart = new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Egyedi',
                        data: users,
                        borderColor: '#2f4a5c',
                        backgroundColor: 'rgba(47, 74, 92, 0.12)',
                        borderWidth: 2,
                        tension: 0.25,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        fill: true
                    },
                    {
                        label: 'Oldal',
                        data: page,
                        borderColor: '#6d8f63',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        tension: 0.25,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        fill: false
                    },
                    {
                        label: 'Előnézet',
                        data: preview,
                        borderColor: '#6b7fa8',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        tension: 0.25,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } }
                },
                scales: {
                    x: {
                        ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 10 },
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        grid: { color: 'rgba(0,0,0,0.06)' }
                    }
                }
            }
        });
    }

    function renderTop(payload) {
        var body = document.getElementById('events-rt-top-body');
        if (!body) return;
        var rows = payload.top_events || [];
        if (!rows.length) {
            body.innerHTML = '<tr class="events-rt-empty-row"><td colspan="4">Nincs aktivitás az elmúlt ' + windowMinutes + ' percben.</td></tr>';
            return;
        }
        body.innerHTML = rows.map(function (ev) {
            var href = editBase + String(ev.id || 0);
            return '<tr>'
                + '<td><a href="' + esc(href) + '">' + esc(ev.name || '') + '</a></td>'
                + '<td class="text-center">' + Number(ev.unique || 0) + '</td>'
                + '<td class="text-center">' + Number(ev.page || 0) + '</td>'
                + '<td class="text-center">' + Number(ev.preview || 0) + '</td>'
                + '</tr>';
        }).join('');
    }

    function renderSources(payload) {
        var list = document.getElementById('events-rt-sources');
        if (!list) return;
        var rows = payload.by_source || [];
        if (!rows.length) {
            list.innerHTML = '<li class="events-rt-sources__empty">Nincs adat.</li>';
            return;
        }
        var total = rows.reduce(function (sum, r) { return sum + Number(r.count || 0); }, 0);
        list.innerHTML = rows.map(function (r) {
            var cnt = Number(r.count || 0);
            var pct = total > 0 ? Math.round((cnt / total) * 100) : 0;
            return '<li class="events-rt-source">'
                + '<div class="events-rt-source__meta">'
                + '<span class="events-rt-source__label">' + esc(r.label || r.source || '') + '</span>'
                + '<span class="events-rt-source__count">' + cnt + ' · ' + pct + '%</span>'
                + '</div>'
                + '<div class="events-rt-source__bar" aria-hidden="true">'
                + '<span class="events-rt-source__fill" style="width:' + pct + '%"></span>'
                + '</div></li>';
        }).join('');
    }

    function renderRecent(payload) {
        var body = document.getElementById('events-rt-recent-body');
        if (!body) return;
        var rows = payload.recent || [];
        if (!rows.length) {
            body.innerHTML = '<tr class="events-rt-empty-row"><td colspan="6">Nincs friss aktivitás.</td></tr>';
            return;
        }
        body.innerHTML = rows.map(function (r) {
            var nameHtml;
            if (Number(r.event_id || 0) > 0) {
                nameHtml = '<a href="' + esc(editBase + String(r.event_id)) + '">' + esc(r.name || '') + '</a>';
            } else {
                nameHtml = esc(r.name || '');
            }
            return '<tr>'
                + '<td class="events-rt-recent-time">' + esc(r.at || '') + '</td>'
                + '<td class="events-rt-recent-event-date">' + esc(r.event_date || '–') + '</td>'
                + '<td>' + nameHtml + '</td>'
                + '<td>' + esc(r.metric_label || '') + '</td>'
                + '<td>' + esc(r.source_label || '') + '</td>'
                + '<td>' + (r.is_bot ? 'Bot' : 'Ember') + '</td>'
                + '</tr>';
        }).join('');
    }

    function applyPayload(payload) {
        if (!payload || !payload.ok) return;
        editBase = payload.edit_base || editBase;
        setText('events-rt-users', Number(payload.users_30m || 0));
        setText('events-rt-page', Number(payload.page_hits_30m || 0));
        setText('events-rt-preview', Number(payload.preview_hits_30m || 0));
        setText('events-rt-bot', Number(payload.bot_hits_30m || 0));
        setText('events-rt-updated', 'Frissítve: ' + (payload.generated_at || ''));
        buildChart(payload);
        renderTop(payload);
        renderSources(payload);
        renderRecent(payload);
    }

    function poll() {
        if (!ajaxUrl) return;
        fetch(ajaxUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.ok) {
                    data.edit_base = editBase;
                    applyPayload(data);
                }
            })
            .catch(function () { /* csendes retry a következő ciklusban */ });
    }

    try {
        var initial = JSON.parse(initialEl.textContent || '{}');
        editBase = initial.edit_base || '';
        applyPayload(initial);
    } catch (e) {}

    setInterval(poll, pollMs);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) poll();
    });
})();
</script>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
