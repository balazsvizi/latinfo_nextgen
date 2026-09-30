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

$kindBadgeClass = static function (string $kind): string {
    return match ($kind) {
        'party' => 'events-rt-kind--party',
        'preview' => 'events-rt-kind--preview',
        'external' => 'events-rt-kind--external',
        'hub' => 'events-rt-kind--hub',
        'nav' => 'events-rt-kind--nav',
        'notice' => 'events-rt-kind--notice',
        'cms' => 'events-rt-kind--cms',
        'module' => 'events-rt-kind--module',
        'mobilapp' => 'events-rt-kind--mobilapp',
        'favorite' => 'events-rt-kind--favorite',
        'rating' => 'events-rt-kind--rating',
        default => 'events-rt-kind--other',
    };
};

$rtColor = static function (mixed $color): string {
    $value = (string) $color;

    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? $value : '#8b9198';
};

$whoButton = static function (array $mark) use ($rtColor): string {
    $label = (string) ($mark['label'] ?? 'Ismeretlen');
    $emoji = (string) ($mark['emoji'] ?? '•');
    $code = (string) ($mark['code'] ?? '');
    $key = (string) ($mark['key'] ?? '');
    $color = $rtColor($mark['color'] ?? '');
    $bot = !empty($mark['is_bot']);
    $registered = !empty($mark['is_registered']) || (int) ($mark['user_id'] ?? 0) > 0;
    $profileUrl = trim((string) ($mark['profile_url'] ?? ''));
    $title = $label;
    if ($code !== '') {
        $title .= ' · ' . $code;
    }
    if ($bot) {
        $title .= ' · bot';
    }
    if ($registered && $profileUrl !== '') {
        $title = $label . ' · admin adatlap';
    }

    $classes = 'events-rt-who'
        . ($bot ? ' events-rt-who--bot' : '')
        . ($registered ? ' events-rt-who--user' : '');
    $style = ' style="--rt-c:' . h($color) . '"';
    $keyAttr = $key !== '' ? ' data-visitor-key="' . h($key) . '"' : '';
    $titleAttr = ' title="' . h($title) . '"';

    if ($registered && $profileUrl !== '') {
        $html = '<a class="' . h($classes) . '" href="' . h($profileUrl) . '"'
            . $style . $titleAttr . $keyAttr . '>'
            . '<span class="events-rt-who__name">' . h($label) . '</span>';
        if ($bot) {
            $html .= '<span class="events-rt-who__bot">bot</span>';
        }
        $html .= '</a>';

        return $html;
    }

    $html = '<button type="button" class="' . h($classes) . '"'
        . $style . $titleAttr . $keyAttr
        . ' aria-pressed="false">';
    if (!$registered && $emoji !== '') {
        $html .= '<span class="events-rt-who__mark" aria-hidden="true">' . h($emoji) . '</span>';
    }
    $html .= '<span class="events-rt-who__name">' . h($label) . '</span>';
    if (!$registered && $code !== '') {
        $html .= '<span class="events-rt-who__code">' . h($code) . '</span>';
    }
    if ($bot) {
        $html .= '<span class="events-rt-who__bot">bot</span>';
    }
    $html .= '</button>';

    return $html;
};
?>
<div class="card events-admin-card events-rt-page" id="events-rt-root" data-ajax-url="<?= h($ajaxUrl) ?>" data-poll-ms="15000" data-visitor="<?= h($visitor) ?>">
    <div class="events-list-head events-cal-page__head">
        <div class="events-cal-page__head-start">
            <h2 class="events-list-title">Valós idejű áttekintés</h2>
            <p class="events-rt-subtitle">Utolsó <?= (int) EVENTS_REALTIME_WINDOW_MINUTES ?> perc · buli, menü, statikus, CMS, modul, mobilapp, kedvenc, értesítő</p>
        </div>
        <div class="events-list-actions">
            <a href="<?= h(events_url('events_statisztika.php')) ?>" class="btn btn-secondary btn-sm">Statisztikák</a>
            <a href="<?= h($publicStatUrl) ?>" class="btn btn-secondary btn-sm">Nyilvános forgalom</a>
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
        <div class="events-rt-kpi events-rt-kpi--favorite">
            <p class="events-rt-kpi__label">Kedvenc</p>
            <p class="events-rt-kpi__value" id="events-rt-favorite"><?= (int) ($snapshot['favorite_hits_30m'] ?? 0) ?></p>
            <p class="events-rt-kpi__hint">szívecske</p>
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
        <p class="events-rt-section-hint">Egyedi felhasználók, buli-, statikus oldal- és menükattintások az elmúlt <?= (int) EVENTS_REALTIME_WINDOW_MINUTES ?> percben.</p>
        <div class="events-rt-chart-canvas">
            <canvas id="events-rt-chart" aria-label="Valós idejű aktivitás grafikonja"></canvas>
        </div>
    </section>

    <div class="events-rt-split">
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
    </div>

    <section class="events-rt-panel events-rt-recent-panel" aria-labelledby="events-rt-recent-title">
        <h3 class="events-rt-section-title" id="events-rt-recent-title">Mire kattintanak most</h3>
        <p class="events-rt-section-hint">Minden mért aktivitás: buli, menü, statikus, CMS, modul, mobilapp, kedvenc, értesítő. Bejelentkezett felhasználónál a név jelenik meg.</p>
        <div class="table-wrap events-rt-table-wrap">
            <table class="events-admin-table events-rt-table events-rt-table--recent">
                <thead>
                    <tr>
                        <th scope="col">Idő</th>
                        <th scope="col">Típus</th>
                        <th scope="col">Cél</th>
                        <th scope="col">Részlet</th>
                        <th scope="col">Látogató</th>
                    </tr>
                </thead>
                <tbody id="events-rt-recent-body">
                    <?php if ($snapshot['recent'] === []): ?>
                        <tr class="events-rt-empty-row"><td colspan="5">Nincs friss aktivitás.</td></tr>
                    <?php else: ?>
                        <?php foreach ($snapshot['recent'] as $row): ?>
                            <?php
                            $kind = (string) ($row['kind'] ?? '');
                            $eventId = (int) ($row['event_id'] ?? 0);
                            $target = (string) ($row['target'] ?? $row['name'] ?? '');
                            $mark = is_array($row['visitor'] ?? null) ? $row['visitor'] : [];
                            $rowKey = (string) ($mark['key'] ?? '');
                            $rowColor = $rtColor($mark['color'] ?? '');
                            ?>
                            <tr<?= $rowKey !== '' ? ' data-visitor-key="' . h($rowKey) . '"' : '' ?> style="--rt-c: <?= h($rowColor) ?>">
                                <td class="events-rt-recent-time"><?= h((string) $row['at']) ?></td>
                                <td>
                                    <span class="events-rt-kind <?= h($kindBadgeClass($kind)) ?>"><?= h((string) ($row['kind_label'] ?? $row['metric_label'] ?? '')) ?></span>
                                </td>
                                <td>
                                    <?php if ($eventId > 0): ?>
                                        <a href="<?= h($editBase . $eventId) ?>"><?= h($target) ?></a>
                                    <?php else: ?>
                                        <?= h($target) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="events-rt-recent-detail"><?= h((string) ($row['detail'] ?? $row['source_label'] ?? '')) ?></td>
                                <td><?= $whoButton($mark) ?></td>
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
    var pollMs = parseInt(root.getAttribute('data-poll-ms') || '15000', 10) || 15000;
    var editBase = '';
    var chart = null;
    var windowMinutes = <?= (int) EVENTS_REALTIME_WINDOW_MINUTES ?>;
    var visitor = root.getAttribute('data-visitor') || 'human';
    var focusKey = '';

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

        if (chart) {
            chart.data.labels = labels;
            chart.data.datasets[0].data = users;
            chart.data.datasets[1].data = party;
            chart.data.datasets[2].data = hub;
            chart.data.datasets[3].data = nav;
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

    function renderRecent(payload) {
        var body = document.getElementById('events-rt-recent-body');
        if (!body) return;
        var rows = payload.recent || [];
        if (!rows.length) {
            body.innerHTML = '<tr class="events-rt-empty-row"><td colspan="5">Nincs friss aktivitás.</td></tr>';
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
            var mark = visitorOf(r);
            var rowAttr = mark.key ? ' data-visitor-key="' + esc(mark.key) + '"' : '';
            return '<tr' + rowAttr + ' style="--rt-c:' + mark.color + '">'
                + '<td class="events-rt-recent-time">' + esc(r.at || '') + '</td>'
                + '<td><span class="events-rt-kind ' + kindClass(kind) + '">' + esc(kindLabel) + '</span></td>'
                + '<td>' + nameHtml + '</td>'
                + '<td class="events-rt-recent-detail">' + esc(detail) + '</td>'
                + '<td>' + whoButtonHtml(mark) + '</td>'
                + '</tr>';
        }).join('');
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
        setText('events-rt-notice', Number(payload.notice_hits_30m || 0));
        setText('events-rt-bot', Number(payload.bot_hits_30m || 0));
        setText('events-rt-updated', 'Frissítve: ' + (payload.generated_at || ''));
        buildChart(payload);
        renderBarList('events-rt-pages', payload.top_pages || [], 'events-rt-source__bar--hub');
        renderBarList('events-rt-nav-list', payload.top_nav || [], 'events-rt-source__bar--nav');
        renderRecent(payload);
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

    root.addEventListener('click', function (ev) {
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
