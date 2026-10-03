<?php
declare(strict_types=1);

/**
 * Valós idejű áttekintés — mire kattintanak a látogatók (menü, oldalak, bulik).
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/event_realtime_stats.php';
requireLogin();

$db = getDb();
$visitor = events_realtime_normalize_visitor($_GET['visitor'] ?? 'human');
$snapshot = events_realtime_snapshot($db, $visitor);
$generatedAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
$ajaxUrl = events_url('ajax_events_realtime.php');
$editBase = events_url('szerkeszt.php?id=');
$listaStatUrl = events_url('events_lista_stat.php');
$listUrl = events_url('events_admin.php');
$publicStatUrl = events_url('events_public_stat.php');
$favoritesStatUrl = events_url('events_kedvencek_stat.php');

$payload = array_merge(
    [
        'ok' => true,
        'generated_at' => $generatedAt,
        'edit_base' => $editBase,
        'visitor' => $visitor,
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
<div class="card events-admin-card events-rt-page" id="events-rt-root" data-ajax-url="<?= h($ajaxUrl) ?>" data-poll-ms="15000" data-visitor="<?= h($visitor) ?>">
    <div class="events-list-head events-cal-page__head">
        <div class="events-cal-page__head-start">
            <h2 class="events-list-title">Valós idejű áttekintés</h2>
            <p class="events-rt-subtitle">Utolsó <?= (int) EVENTS_REALTIME_WINDOW_MINUTES ?> perc · buli, menü, statikus, CMS, modul, mobilapp, kedvencnek jelölés, értékelés, értesítő</p>
        </div>
        <div class="events-list-actions">
            <a href="<?= h(events_url('events_statisztika.php')) ?>" class="btn btn-secondary btn-sm">Statisztikák</a>
            <a href="<?= h($publicStatUrl) ?>" class="btn btn-secondary btn-sm">Nyilvános forgalom</a>
            <a href="<?= h($favoritesStatUrl) ?>" class="btn btn-secondary btn-sm">Kedvencek</a>
            <a href="<?= h($listaStatUrl) ?>" class="btn btn-secondary btn-sm">Lista stat</a>
            <a href="<?= h($listUrl) ?>" class="btn btn-secondary btn-sm">Események lista</a>
        </div>
    </div>

    <div class="events-rt-hero">
        <div class="events-rt-hero__live">
            <span class="events-rt-live-dot" aria-hidden="true"></span>
            <span class="events-rt-live-label">Élő</span>
            <div class="events-rt-visitor" role="group" aria-label="Látogató szűrő">
                <?php
                $visitorOptions = [
                    'human' => 'Ember',
                    'all' => 'Mind',
                    'bot' => 'Bot',
                ];
                foreach ($visitorOptions as $optKey => $optLabel):
                    $isActive = $visitor === $optKey;
                ?>
                    <button
                        type="button"
                        class="events-rt-visitor__btn<?= $isActive ? ' is-active' : '' ?>"
                        data-visitor="<?= h($optKey) ?>"
                        aria-pressed="<?= $isActive ? 'true' : 'false' ?>"
                    ><?= h($optLabel) ?></button>
                <?php endforeach; ?>
            </div>
            <span class="events-rt-updated" id="events-rt-updated">Frissítve: <?= h($generatedAt) ?></span>
        </div>
        <p class="events-rt-hero__label" id="events-rt-users-label">
            <?php
            echo match ($visitor) {
                'bot' => 'Botok az elmúlt ' . EVENTS_REALTIME_WINDOW_MINUTES . ' percben',
                'all' => 'Látogatók az elmúlt ' . EVENTS_REALTIME_WINDOW_MINUTES . ' percben',
                default => 'Felhasználók az elmúlt ' . EVENTS_REALTIME_WINDOW_MINUTES . ' percben',
            };
            ?>
        </p>
        <p class="events-rt-hero__value" id="events-rt-users"><?= (int) $snapshot['users_30m'] ?></p>
        <p class="events-rt-hero__hint" id="events-rt-users-hint">
            <?php
            echo match ($visitor) {
                'bot' => 'Egyedi bot (IP) — buli és nyilvános oldalak együtt',
                'all' => 'Egyedi látogató (IP) — ember és bot együtt',
                default => 'Egyedi emberi látogató (IP) — buli és nyilvános oldalak együtt',
            };
            ?>
        </p>
    </div>

    <div class="events-rt-kpis" aria-label="Összesítők">
        <div class="events-rt-kpi events-rt-kpi--party">
            <p class="events-rt-kpi__label">Bulik</p>
            <p class="events-rt-kpi__value" id="events-rt-party"><?= (int) ($snapshot['party_hits_30m'] ?? $snapshot['page_hits_30m'] ?? 0) ?></p>
            <p class="events-rt-kpi__hint">esemény oldal</p>
        </div>
        <div class="events-rt-kpi events-rt-kpi--hub">
            <p class="events-rt-kpi__label">Statikus</p>
            <p class="events-rt-kpi__value" id="events-rt-hub"><?= (int) ($snapshot['hub_hits_30m'] ?? 0) ?></p>
            <p class="events-rt-kpi__hint">főoldal, naptár…</p>
        </div>
        <div class="events-rt-kpi events-rt-kpi--nav">
            <p class="events-rt-kpi__label">Menü</p>
            <p class="events-rt-kpi__value" id="events-rt-nav"><?= (int) ($snapshot['nav_hits_30m'] ?? 0) ?></p>
            <p class="events-rt-kpi__hint">menükattintás</p>
        </div>
        <div class="events-rt-kpi events-rt-kpi--preview">
            <p class="events-rt-kpi__label">Előnézet</p>
            <p class="events-rt-kpi__value" id="events-rt-preview"><?= (int) $snapshot['preview_hits_30m'] ?></p>
            <p class="events-rt-kpi__hint">naptár előnézet</p>
        </div>
        <div class="events-rt-kpi events-rt-kpi--external">
            <p class="events-rt-kpi__label">További info</p>
            <p class="events-rt-kpi__value" id="events-rt-external"><?= (int) ($snapshot['external_hits_30m'] ?? 0) ?></p>
            <p class="events-rt-kpi__hint">CTA kattintás</p>
        </div>
        <div class="events-rt-kpi events-rt-kpi--cms">
            <p class="events-rt-kpi__label">CMS</p>
            <p class="events-rt-kpi__value" id="events-rt-cms"><?= (int) ($snapshot['cms_hits_30m'] ?? 0) ?></p>
            <p class="events-rt-kpi__hint">cikk megtekintés</p>
        </div>
        <div class="events-rt-kpi events-rt-kpi--module">
            <p class="events-rt-kpi__label">Modul</p>
            <p class="events-rt-kpi__value" id="events-rt-module"><?= (int) ($snapshot['module_hits_30m'] ?? 0) ?></p>
            <p class="events-rt-kpi__hint">kezdőlap modul</p>
        </div>
        <div class="events-rt-kpi events-rt-kpi--mobilapp">
            <p class="events-rt-kpi__label">Mobilapp</p>
            <p class="events-rt-kpi__value" id="events-rt-mobilapp"><?= (int) ($snapshot['mobilapp_hits_30m'] ?? 0) ?></p>
            <p class="events-rt-kpi__hint">PWA esemény</p>
        </div>
        <div class="events-rt-kpi events-rt-kpi--favorite" data-kind-filter="favorite" title="Szűrés: kedvencnek jelölés">
            <p class="events-rt-kpi__label">Kedvencnek jelölés</p>
            <p class="events-rt-kpi__value" id="events-rt-favorite"><?= (int) ($snapshot['favorite_hits_30m'] ?? 0) ?></p>
            <p class="events-rt-kpi__hint">szívecske</p>
        </div>
        <div class="events-rt-kpi events-rt-kpi--rating" data-kind-filter="rating" title="Szűrés: értékelés">
            <p class="events-rt-kpi__label">Értékelés</p>
            <p class="events-rt-kpi__value" id="events-rt-rating"><?= (int) ($snapshot['rating_hits_30m'] ?? 0) ?></p>
            <p class="events-rt-kpi__hint">kezdőlap csillag</p>
        </div>
        <div class="events-rt-kpi events-rt-kpi--notice">
            <p class="events-rt-kpi__label">Értesítő</p>
            <p class="events-rt-kpi__value" id="events-rt-notice"><?= (int) ($snapshot['notice_hits_30m'] ?? 0) ?></p>
            <p class="events-rt-kpi__hint">tipp kattintás</p>
        </div>
        <div class="events-rt-kpi events-rt-kpi--bot">
            <p class="events-rt-kpi__label">Bot</p>
            <p class="events-rt-kpi__value" id="events-rt-bot"><?= (int) $snapshot['bot_hits_30m'] ?></p>
            <p class="events-rt-kpi__hint">összes bot hit</p>
        </div>
    </div>

    <section class="events-rt-chart-panel" aria-labelledby="events-rt-chart-title">
        <h3 class="events-rt-section-title" id="events-rt-chart-title">Percenkénti aktivitás</h3>
        <p class="events-rt-section-hint">Egyedi felhasználók, buli-, statikus oldal-, menü- és kedvencnek jelölések az elmúlt <?= (int) EVENTS_REALTIME_WINDOW_MINUTES ?> percben.</p>
        <div class="events-rt-chart-canvas">
            <canvas id="events-rt-chart" aria-label="Valós idejű aktivitás grafikonja"></canvas>
        </div>
    </section>

    <div class="events-rt-split events-rt-split--triple">
        <section class="events-rt-panel" aria-labelledby="events-rt-pages-title">
            <h3 class="events-rt-section-title" id="events-rt-pages-title">Top statikus oldalak</h3>
            <p class="events-rt-section-hint">Emberi oldalmegtekintések.</p>
            <ul class="events-rt-sources" id="events-rt-pages">
                <?php
                $pageTotal = 0;
                foreach ($snapshot['top_pages'] as $pageRow) {
                    $pageTotal += (int) $pageRow['count'];
                }
                if ($snapshot['top_pages'] === []):
                ?>
                    <li class="events-rt-sources__empty">Nincs adat.</li>
                <?php else: ?>
                    <?php foreach ($snapshot['top_pages'] as $pageRow): ?>
                        <?php
                        $cnt = (int) $pageRow['count'];
                        $pct = $pageTotal > 0 ? (int) round(($cnt / $pageTotal) * 100) : 0;
                        ?>
                        <li class="events-rt-source">
                            <div class="events-rt-source__meta">
                                <span class="events-rt-source__label"><?= h((string) $pageRow['label']) ?></span>
                                <span class="events-rt-source__count"><?= $cnt ?> · <?= $pct ?>%</span>
                            </div>
                            <div class="events-rt-source__bar events-rt-source__bar--hub" aria-hidden="true">
                                <span class="events-rt-source__fill" style="width: <?= $pct ?>%"></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </section>

        <section class="events-rt-panel" aria-labelledby="events-rt-nav-title">
            <h3 class="events-rt-section-title" id="events-rt-nav-title">Top menükattintások</h3>
            <p class="events-rt-section-hint">Fejléc, nézetváltó, nyelv, logó.</p>
            <ul class="events-rt-sources" id="events-rt-nav-list">
                <?php
                $navTotal = 0;
                foreach ($snapshot['top_nav'] as $navRow) {
                    $navTotal += (int) $navRow['count'];
                }
                if ($snapshot['top_nav'] === []):
                ?>
                    <li class="events-rt-sources__empty">Nincs adat.</li>
                <?php else: ?>
                    <?php foreach ($snapshot['top_nav'] as $navRow): ?>
                        <?php
                        $cnt = (int) $navRow['count'];
                        $pct = $navTotal > 0 ? (int) round(($cnt / $navTotal) * 100) : 0;
                        ?>
                        <li class="events-rt-source">
                            <div class="events-rt-source__meta">
                                <span class="events-rt-source__label"><?= h((string) $navRow['label']) ?></span>
                                <span class="events-rt-source__count"><?= $cnt ?> · <?= $pct ?>%</span>
                            </div>
                            <div class="events-rt-source__bar events-rt-source__bar--nav" aria-hidden="true">
                                <span class="events-rt-source__fill" style="width: <?= $pct ?>%"></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </section>

        <section class="events-rt-panel" aria-labelledby="events-rt-fav-title">
            <h3 class="events-rt-section-title" id="events-rt-fav-title">Top kedvencnek jelölés</h3>
            <p class="events-rt-section-hint">Szívecskével megjelölt elemek.</p>
            <ul class="events-rt-sources" id="events-rt-favorites">
                <?php
                $favTop = is_array($snapshot['top_favorites'] ?? null) ? $snapshot['top_favorites'] : [];
                $favTotal = 0;
                foreach ($favTop as $favRow) {
                    $favTotal += (int) ($favRow['count'] ?? 0);
                }
                if ($favTop === []):
                ?>
                    <li class="events-rt-sources__empty">Nincs kedvencnek jelölés.</li>
                <?php else: ?>
                    <?php foreach ($favTop as $favRow): ?>
                        <?php
                        $cnt = (int) ($favRow['count'] ?? 0);
                        $pct = $favTotal > 0 ? (int) round(($cnt / $favTotal) * 100) : 0;
                        ?>
                        <li class="events-rt-source">
                            <div class="events-rt-source__meta">
                                <span class="events-rt-source__label"><?= h((string) ($favRow['label'] ?? '')) ?></span>
                                <span class="events-rt-source__count"><?= $cnt ?> · <?= $pct ?>%</span>
                            </div>
                            <div class="events-rt-source__bar events-rt-source__bar--favorite" aria-hidden="true">
                                <span class="events-rt-source__fill" style="width: <?= $pct ?>%"></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </section>
    </div>

    <?php
    $kindFilterOptions = [
        '' => 'Összes típus',
        'party' => 'Buli',
        'preview' => 'Előnézet',
        'external' => 'További info',
        'hub' => 'Statikus oldal',
        'nav' => 'Menü',
        'notice' => 'Értesítő',
        'cms' => 'CMS',
        'module' => 'Modul',
        'mobilapp' => 'Mobilapp',
        'favorite' => 'Kedvencnek jelölés',
        'rating' => 'Értékelés',
    ];
    ?>
    <section class="events-rt-panel events-rt-recent-panel" aria-labelledby="events-rt-recent-title">
        <h3 class="events-rt-section-title" id="events-rt-recent-title">Mire kattintanak most</h3>
        <p class="events-rt-section-hint">Minden mért aktivitás: buli, menü, statikus, CMS, modul, mobilapp, kedvencnek jelölés, értékelés, értesítő. Bejelentkezett felhasználónál a név jelenik meg.</p>

        <div class="events-rt-recent-controls" id="events-rt-recent-controls">
            <div class="events-rt-recent-filters">
                <div class="form-group">
                    <label class="events-filter-label" for="events_rt_filter_search">Keresés</label>
                    <input class="events-filter-input" type="search" id="events_rt_filter_search" placeholder="Cél, részlet, látogató…" autocomplete="off">
                </div>
                <div class="form-group">
                    <label class="events-filter-label" for="events_rt_filter_kind">Típus</label>
                    <select class="events-filter-input" id="events_rt_filter_kind">
                        <?php foreach ($kindFilterOptions as $kindKey => $kindLabel): ?>
                            <option value="<?= h($kindKey) ?>"><?= h($kindLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group events-rt-recent-filters__actions">
                    <button type="button" class="btn btn-secondary btn-sm" id="events_rt_filter_clear">Szűrés törlése</button>
                </div>
            </div>
            <p class="events-rt-recent-count" aria-live="polite">
                <strong><span id="events-rt-recent-visible"><?= count($snapshot['recent']) ?></span></strong>
                / <span id="events-rt-recent-total"><?= count($snapshot['recent']) ?></span> sor
            </p>
        </div>

        <div class="table-wrap events-rt-table-wrap">
            <table class="sortable-table events-admin-table events-rt-table events-rt-table--recent" id="events-rt-recent-table">
                <thead>
                    <tr>
                        <th scope="col">
                            <button type="button" class="th-sort is-active" data-sort="at" aria-pressed="true">Idő ↓</button>
                        </th>
                        <th scope="col">
                            <button type="button" class="th-sort" data-sort="kind" aria-pressed="false">Típus</button>
                        </th>
                        <th scope="col">
                            <button type="button" class="th-sort" data-sort="target" aria-pressed="false">Cél</button>
                        </th>
                        <th scope="col">
                            <button type="button" class="th-sort" data-sort="event_date" aria-pressed="false">Dátum</button>
                        </th>
                        <th scope="col">
                            <button type="button" class="th-sort" data-sort="detail" aria-pressed="false">Részlet</button>
                        </th>
                        <th scope="col">
                            <button type="button" class="th-sort" data-sort="visitor" aria-pressed="false">Látogató</button>
                        </th>
                    </tr>
                </thead>
                <tbody id="events-rt-recent-body">
                    <tr class="events-rt-empty-row"><td colspan="6">Betöltés…</td></tr>
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
    var pollMs = parseInt(root.getAttribute('data-poll-ms') || '15000', 10) || 15000;
    var editBase = '';
    var chart = null;
    var windowMinutes = <?= (int) EVENTS_REALTIME_WINDOW_MINUTES ?>;
    var visitor = root.getAttribute('data-visitor') || 'human';
    var focusKey = '';
    var recentRows = [];
    var recentSortKey = 'at';
    var recentSortDir = 'desc';
    var recentSearchTimer = null;

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

    function visitorLabels(v) {
        if (v === 'bot') {
            return {
                label: 'Botok az elmúlt ' + windowMinutes + ' percben',
                hint: 'Egyedi bot (IP) — buli és nyilvános oldalak együtt'
            };
        }
        if (v === 'all') {
            return {
                label: 'Látogatók az elmúlt ' + windowMinutes + ' percben',
                hint: 'Egyedi látogató (IP) — ember és bot együtt'
            };
        }
        return {
            label: 'Felhasználók az elmúlt ' + windowMinutes + ' percben',
            hint: 'Egyedi emberi látogató (IP) — buli és nyilvános oldalak együtt'
        };
    }

    function syncVisitorButtons(v) {
        var buttons = root.querySelectorAll('.events-rt-visitor__btn');
        buttons.forEach(function (btn) {
            var active = btn.getAttribute('data-visitor') === v;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function setVisitor(next) {
        if (next !== 'human' && next !== 'all' && next !== 'bot') {
            next = 'human';
        }
        if (next === visitor) {
            poll();
            return;
        }
        visitor = next;
        root.setAttribute('data-visitor', visitor);
        syncVisitorButtons(visitor);
        try {
            var url = new URL(window.location.href);
            if (visitor === 'human') {
                url.searchParams.delete('visitor');
            } else {
                url.searchParams.set('visitor', visitor);
            }
            window.history.replaceState({}, '', url.toString());
        } catch (e) {}
        poll();
    }

    function safeColor(color) {
        return /^#[0-9a-fA-F]{6}$/.test(String(color || '')) ? String(color) : '#8b9198';
    }

    function visitorOf(row) {
        var mark = (row && row.visitor) ? row.visitor : (row || {});
        return {
            key: String(mark.key || ''),
            label: String(mark.label || (row && row.is_bot ? 'Ismeretlen bot' : 'Ismeretlen')),
            color: safeColor(mark.color),
            emoji: String(mark.emoji || '•'),
            code: String(mark.code || ''),
            is_bot: !!(mark.is_bot || (row && row.is_bot)),
            is_registered: !!(mark.is_registered || (mark.user_id && Number(mark.user_id) > 0)),
            profile_url: String(mark.profile_url || ''),
            user_id: Number(mark.user_id || 0)
        };
    }

    function whoButtonHtml(mark) {
        var registered = !!mark.is_registered && !!mark.profile_url;
        var title = mark.label;
        if (registered) {
            title = mark.label + ' · admin adatlap';
        } else {
            if (mark.code) title += ' · ' + mark.code;
            if (mark.is_bot) title += ' · bot';
        }
        var keyAttr = mark.key ? ' data-visitor-key="' + esc(mark.key) + '"' : '';
        var classes = 'events-rt-who'
            + (mark.is_bot ? ' events-rt-who--bot' : '')
            + (registered ? ' events-rt-who--user' : '');
        if (registered) {
            return '<a class="' + classes + '" href="' + esc(mark.profile_url) + '"'
                + ' style="--rt-c:' + mark.color + '"'
                + ' title="' + esc(title) + '"'
                + keyAttr + '>'
                + '<span class="events-rt-who__name">' + esc(mark.label) + '</span>'
                + (mark.is_bot ? '<span class="events-rt-who__bot">bot</span>' : '')
                + '</a>';
        }
        return '<button type="button" class="' + classes + '"'
            + ' style="--rt-c:' + mark.color + '"'
            + ' title="' + esc(title) + '"'
            + keyAttr
            + ' aria-pressed="false">'
            + '<span class="events-rt-who__mark" aria-hidden="true">' + esc(mark.emoji) + '</span>'
            + '<span class="events-rt-who__name">' + esc(mark.label) + '</span>'
            + (mark.code ? '<span class="events-rt-who__code">' + esc(mark.code) + '</span>' : '')
            + (mark.is_bot ? '<span class="events-rt-who__bot">bot</span>' : '')
            + '</button>';
    }

    function applyFocus() {
        var on = focusKey !== '';
        root.classList.toggle('is-visitor-focus', on);
        root.querySelectorAll('tr[data-visitor-key], .events-rt-who[data-visitor-key]').forEach(function (el) {
            var match = on && el.getAttribute('data-visitor-key') === focusKey;
            el.classList.toggle('is-person-match', match);
            if (el.tagName === 'BUTTON') {
                el.setAttribute('aria-pressed', match ? 'true' : 'false');
            }
        });
    }

    function kindClass(kind) {
        var map = {
            party: 'events-rt-kind--party',
            preview: 'events-rt-kind--preview',
            external: 'events-rt-kind--external',
            hub: 'events-rt-kind--hub',
            nav: 'events-rt-kind--nav',
            notice: 'events-rt-kind--notice',
            cms: 'events-rt-kind--cms',
            module: 'events-rt-kind--module',
            mobilapp: 'events-rt-kind--mobilapp',
            favorite: 'events-rt-kind--favorite',
            rating: 'events-rt-kind--rating'
        };
        return map[kind] || 'events-rt-kind--other';
    }

    function renderBarList(listId, rows, barMod) {
        var list = document.getElementById(listId);
        if (!list) return;
        rows = rows || [];
        if (!rows.length) {
            list.innerHTML = '<li class="events-rt-sources__empty">Nincs adat.</li>';
            return;
        }
        var total = rows.reduce(function (sum, r) { return sum + Number(r.count || 0); }, 0);
        var barClass = 'events-rt-source__bar' + (barMod ? ' ' + barMod : '');
        list.innerHTML = rows.map(function (r) {
            var cnt = Number(r.count || 0);
            var pct = total > 0 ? Math.round((cnt / total) * 100) : 0;
            return '<li class="events-rt-source">'
                + '<div class="events-rt-source__meta">'
                + '<span class="events-rt-source__label">' + esc(r.label || r.key || '') + '</span>'
                + '<span class="events-rt-source__count">' + cnt + ' · ' + pct + '%</span>'
                + '</div>'
                + '<div class="' + barClass + '" aria-hidden="true">'
                + '<span class="events-rt-source__fill" style="width:' + pct + '%"></span>'
                + '</div></li>';
        }).join('');
    }

    function buildChart(payload) {
        var canvas = document.getElementById('events-rt-chart');
        if (!canvas || typeof Chart === 'undefined') return;
        var perMinute = payload.per_minute || [];
        var labels = perMinute.map(function (r) { return r.label || ''; });
        var users = perMinute.map(function (r) { return Number(r.users || 0); });
        var party = perMinute.map(function (r) { return Number(r.party != null ? r.party : r.page || 0); });
        var hub = perMinute.map(function (r) { return Number(r.hub || 0); });
        var nav = perMinute.map(function (r) { return Number(r.nav || 0); });
        var favorite = perMinute.map(function (r) { return Number(r.favorite || 0); });

        if (chart) {
            chart.data.labels = labels;
            chart.data.datasets[0].data = users;
            chart.data.datasets[1].data = party;
            chart.data.datasets[2].data = hub;
            chart.data.datasets[3].data = nav;
            chart.data.datasets[4].data = favorite;
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
                        label: 'Bulik',
                        data: party,
                        borderColor: '#6d8f63',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        tension: 0.25,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        fill: false
                    },
                    {
                        label: 'Statikus',
                        data: hub,
                        borderColor: '#2f6f8f',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        tension: 0.25,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        fill: false
                    },
                    {
                        label: 'Menü',
                        data: nav,
                        borderColor: '#a8784a',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        tension: 0.25,
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        fill: false
                    },
                    {
                        label: 'Kedvenc',
                        data: favorite,
                        borderColor: '#c4477a',
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

    function recentSearchValue() {
        var input = document.getElementById('events_rt_filter_search');
        return input ? input.value.trim().toLowerCase() : '';
    }

    function recentKindValue() {
        var select = document.getElementById('events_rt_filter_kind');
        return select ? String(select.value || '') : '';
    }

    function recentRowSearchBlob(r) {
        var mark = visitorOf(r);
        return [
            r.at || '',
            r.kind || '',
            r.kind_label || r.metric_label || '',
            r.target || r.name || '',
            r.event_date || '',
            r.detail || r.source_label || '',
            mark.label || '',
            mark.code || '',
            mark.is_bot ? 'bot' : ''
        ].join(' ').toLowerCase();
    }

    function recentSortValue(r, key) {
        var mark = visitorOf(r);
        if (key === 'at') return String(r.at || '');
        if (key === 'kind') return String(r.kind_label || r.kind || '');
        if (key === 'target') return String(r.target || r.name || '');
        if (key === 'event_date') {
            if (r.event_days != null && r.event_days !== '') {
                return Number(r.event_days);
            }
            return String(r.event_date || '');
        }
        if (key === 'detail') return String(r.detail || r.source_label || '');
        if (key === 'visitor') return String(mark.label || '');
        return '';
    }

    function updateRecentSortHeaders() {
        var table = document.getElementById('events-rt-recent-table');
        if (!table) return;
        table.querySelectorAll('thead .th-sort[data-sort]').forEach(function (btn) {
            var key = btn.getAttribute('data-sort') || '';
            var active = key === recentSortKey;
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            btn.classList.toggle('is-active', active);
            var label = (btn.getAttribute('data-label') || btn.textContent || '').replace(/\s*[↑↓]\s*$/, '').trim();
            btn.setAttribute('data-label', label);
            btn.textContent = active ? (label + (recentSortDir === 'asc' ? ' ↑' : ' ↓')) : label;
        });
    }

    function filteredRecentRows() {
        var search = recentSearchValue();
        var kind = recentKindValue();
        var rows = recentRows.slice();
        if (kind !== '') {
            rows = rows.filter(function (r) { return String(r.kind || '') === kind; });
        }
        if (search !== '') {
            rows = rows.filter(function (r) {
                return recentRowSearchBlob(r).indexOf(search) !== -1;
            });
        }
        rows.sort(function (a, b) {
            var va = recentSortValue(a, recentSortKey);
            var vb = recentSortValue(b, recentSortKey);
            var cmp;
            if (typeof va === 'number' && typeof vb === 'number') {
                cmp = va - vb;
            } else if (typeof va === 'number') {
                cmp = -1;
            } else if (typeof vb === 'number') {
                cmp = 1;
            } else {
                cmp = String(va).localeCompare(String(vb), 'hu', { sensitivity: 'base', numeric: true });
            }
            if (cmp === 0) {
                cmp = String(a.at || '').localeCompare(String(b.at || ''), 'hu');
            }
            return recentSortDir === 'asc' ? cmp : -cmp;
        });
        return rows;
    }

    function paintRecent() {
        var body = document.getElementById('events-rt-recent-body');
        if (!body) return;
        setText('events-rt-recent-total', recentRows.length);
        updateRecentSortHeaders();

        if (!recentRows.length) {
            body.innerHTML = '<tr class="events-rt-empty-row"><td colspan="6">Nincs friss aktivitás.</td></tr>';
            setText('events-rt-recent-visible', 0);
            return;
        }

        var rows = filteredRecentRows();
        setText('events-rt-recent-visible', rows.length);
        if (!rows.length) {
            body.innerHTML = '<tr class="events-rt-empty-row"><td colspan="6">Nincs találat a szűrésre.</td></tr>';
            return;
        }

        body.innerHTML = rows.map(function (r) {
            var eventId = Number(r.event_id || 0);
            var target = r.target || r.name || '';
            var nameHtml;
            if (eventId > 0) {
                nameHtml = '<a href="' + esc(editBase + String(eventId)) + '">' + esc(target) + '</a>';
            } else {
                nameHtml = esc(target);
            }
            var kind = r.kind || '';
            var kindLabel = r.kind_label || r.metric_label || '';
            var detail = r.detail || r.source_label || '';
            var eventDate = r.event_date || '–';
            var days = r.event_days;
            var daysNum = (days != null && days !== '') ? Number(days) : null;
            var dateHtml;
            if (daysNum === 0) {
                dateHtml = '<span class="events-rt-date-chip events-rt-date-chip--today" title="Ma lesz kiemelten">'
                    + esc(eventDate) + '</span>';
            } else if (daysNum != null && daysNum > 0) {
                dateHtml = '<span class="events-rt-date-chip events-rt-date-chip--upcoming" title="'
                    + esc(String(daysNum) + ' nap múlva lesz kiemelten') + '">'
                    + esc(eventDate) + '</span>';
            } else if (daysNum != null && daysNum < 0) {
                dateHtml = '<span class="events-rt-recent-event-date events-rt-recent-event-date--past">'
                    + esc(eventDate) + '</span>';
            } else {
                dateHtml = '<span class="events-rt-recent-event-date">' + esc(eventDate) + '</span>';
            }
            var mark = visitorOf(r);
            var rowAttr = mark.key ? ' data-visitor-key="' + esc(mark.key) + '"' : '';
            return '<tr' + rowAttr + ' style="--rt-c:' + mark.color + '">'
                + '<td class="events-rt-recent-time">' + esc(r.at || '') + '</td>'
                + '<td><span class="events-rt-kind ' + kindClass(kind) + '">' + esc(kindLabel) + '</span></td>'
                + '<td>' + nameHtml + '</td>'
                + '<td>' + dateHtml + '</td>'
                + '<td class="events-rt-recent-detail">' + esc(detail) + '</td>'
                + '<td>' + whoButtonHtml(mark) + '</td>'
                + '</tr>';
        }).join('');
    }

    function setRecentRows(rows) {
        recentRows = Array.isArray(rows) ? rows.slice() : [];
        paintRecent();
    }

    function applyPayload(payload) {
        if (!payload || !payload.ok) return;
        editBase = payload.edit_base || editBase;
        if (payload.visitor) {
            visitor = payload.visitor;
            root.setAttribute('data-visitor', visitor);
            syncVisitorButtons(visitor);
        }
        var labels = visitorLabels(visitor);
        setText('events-rt-users-label', labels.label);
        setText('events-rt-users-hint', labels.hint);
        setText('events-rt-users', Number(payload.users_30m || 0));
        setText('events-rt-party', Number(payload.party_hits_30m != null ? payload.party_hits_30m : payload.page_hits_30m || 0));
        setText('events-rt-hub', Number(payload.hub_hits_30m || 0));
        setText('events-rt-nav', Number(payload.nav_hits_30m || 0));
        setText('events-rt-preview', Number(payload.preview_hits_30m || 0));
        setText('events-rt-external', Number(payload.external_hits_30m || 0));
        setText('events-rt-cms', Number(payload.cms_hits_30m || 0));
        setText('events-rt-module', Number(payload.module_hits_30m || 0));
        setText('events-rt-mobilapp', Number(payload.mobilapp_hits_30m || 0));
        setText('events-rt-favorite', Number(payload.favorite_hits_30m || 0));
        setText('events-rt-rating', Number(payload.rating_hits_30m || 0));
        setText('events-rt-notice', Number(payload.notice_hits_30m || 0));
        setText('events-rt-bot', Number(payload.bot_hits_30m || 0));
        setText('events-rt-updated', 'Frissítve: ' + (payload.generated_at || ''));
        buildChart(payload);
        renderBarList('events-rt-pages', payload.top_pages || [], 'events-rt-source__bar--hub');
        renderBarList('events-rt-nav-list', payload.top_nav || [], 'events-rt-source__bar--nav');
        renderBarList('events-rt-favorites', payload.top_favorites || [], 'events-rt-source__bar--favorite');
        setRecentRows(payload.recent || []);
        applyFocus();
    }

    function poll() {
        if (!ajaxUrl) return;
        var url = ajaxUrl + (ajaxUrl.indexOf('?') >= 0 ? '&' : '?') + 'visitor=' + encodeURIComponent(visitor);
        fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
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
        if (initial.visitor) {
            visitor = initial.visitor;
        }
        applyPayload(initial);
    } catch (e) {}

    root.querySelectorAll('.events-rt-visitor__btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setVisitor(btn.getAttribute('data-visitor') || 'human');
        });
    });

    var searchInput = document.getElementById('events_rt_filter_search');
    var kindSelect = document.getElementById('events_rt_filter_kind');
    var clearBtn = document.getElementById('events_rt_filter_clear');
    var recentTable = document.getElementById('events-rt-recent-table');

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(recentSearchTimer);
            recentSearchTimer = setTimeout(paintRecent, 120);
        });
    }
    if (kindSelect) {
        kindSelect.addEventListener('change', paintRecent);
    }
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            if (searchInput) searchInput.value = '';
            if (kindSelect) kindSelect.value = '';
            paintRecent();
            if (searchInput) searchInput.focus();
        });
    }
    if (recentTable) {
        recentTable.addEventListener('click', function (e) {
            var btn = e.target.closest('.th-sort[data-sort]');
            if (!btn || !recentTable.contains(btn)) return;
            var key = btn.getAttribute('data-sort') || '';
            if (key === '') return;
            if (recentSortKey === key) {
                recentSortDir = recentSortDir === 'asc' ? 'desc' : 'asc';
            } else {
                recentSortKey = key;
                recentSortDir = key === 'at' ? 'desc' : 'asc';
            }
            paintRecent();
        });
    }

    root.addEventListener('click', function (ev) {
        var kindCard = ev.target.closest('[data-kind-filter]');
        if (kindCard && root.contains(kindCard)) {
            var kind = kindCard.getAttribute('data-kind-filter') || '';
            var kindSelectEl = document.getElementById('events_rt_filter_kind');
            if (kindSelectEl && kind !== '') {
                kindSelectEl.value = kindSelectEl.value === kind ? '' : kind;
                paintRecent();
                var recentPanel = document.getElementById('events-rt-recent-title');
                if (recentPanel) {
                    recentPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
            return;
        }
        var link = ev.target.closest('a.events-rt-who--user');
        if (link && root.contains(link)) {
            return;
        }
        var btn = ev.target.closest('.events-rt-who');
        if (!btn || !root.contains(btn)) return;
        var key = btn.getAttribute('data-visitor-key') || '';
        if (!key) return;
        focusKey = focusKey === key ? '' : key;
        applyFocus();
    });

    setInterval(poll, pollMs);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) poll();
    });
})();
</script>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
