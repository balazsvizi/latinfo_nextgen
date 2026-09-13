<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/public_home_content.php';
require_once __DIR__ . '/lib/public_home_notices.php';
require_once __DIR__ . '/lib/event_edit_stats.php';
require_once __DIR__ . '/lib/public_home_notice_stats.php';
requireLogin();

$db = getDb();
$tableOk = events_public_home_table_available($db);
$hiba = '';
$noticePresets = events_public_home_notice_color_presets();
$selfUrl = events_url('fooldal_szerkeszt.php');
$noticesOk = $tableOk && events_public_home_notices_ensure_schema($db);

$noticeEditorUrl = static function (int $id) use ($selfUrl): string {
    return $id > 0 ? $selfUrl . '?open=' . $id . '#fooldal-notice-' . $id : $selfUrl . '#fooldal-notices';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('events_fooldal')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet. Töltsd újra az oldalt.';
    } elseif (!$tableOk) {
        $hiba = 'Hiányzik az events_public_home tábla. Futtasd: events/sql/migration_public_home.sql';
    } else {
        $action = (string) ($_POST['notice_action'] ?? '');
        $noticeId = filter_var($_POST['notice_id'] ?? 0, FILTER_VALIDATE_INT);
        $noticeId = ($noticeId === false || $noticeId < 0) ? 0 : (int) $noticeId;

        try {
            if ($action === 'create') {
                if (!$noticesOk) {
                    throw new RuntimeException('A tip tábla nem érhető el.');
                }
                $newId = events_public_home_notices_create($db);
                if (function_exists('rendszer_log')) {
                    rendszer_log('fooldal_tip', $newId, 'Létrehozva', '');
                }
                flash('success', 'Új tip létrehozva. Töltsd ki, majd mentsd — üres szöveggel nem jelenik meg.');
                redirect($noticeEditorUrl($newId));
            } elseif ($action === 'save') {
                if (!$noticesOk) {
                    throw new RuntimeException('A tip tábla nem érhető el.');
                }
                events_public_home_notices_save($db, $noticeId, [
                    'is_active' => events_public_home_notice_new_tab_enabled($_POST['is_active'] ?? '0'),
                    'notice_text' => (string) ($_POST['notice_text'] ?? ''),
                    'notice_text_en' => (string) ($_POST['notice_text_en'] ?? ''),
                    'notice_url' => (string) ($_POST['notice_url'] ?? ''),
                    'notice_url_new_tab' => events_public_home_notice_new_tab_enabled($_POST['notice_url_new_tab'] ?? '0'),
                    'notice_color_scheme' => (string) ($_POST['notice_color_scheme'] ?? 'neon_green'),
                    'notice_custom_color' => (string) ($_POST['notice_custom_color'] ?? '#39FF14'),
                ]);
                if (function_exists('rendszer_log')) {
                    rendszer_log('fooldal_tip', $noticeId, 'Mentve', trim((string) ($_POST['notice_text'] ?? '')));
                }
                flash('success', 'A tip mentve.');
                redirect($noticeEditorUrl($noticeId));
            } elseif ($action === 'toggle') {
                if (!$noticesOk) {
                    throw new RuntimeException('A tip tábla nem érhető el.');
                }
                $active = events_public_home_notice_new_tab_enabled($_POST['is_active'] ?? '0');
                events_public_home_notices_set_active($db, $noticeId, $active);
                flash('success', $active ? 'A tip bekapcsolva, a látogatóknak kiosztható.' : 'A tip kikapcsolva, nem jelenik meg.');
                redirect($selfUrl . '#fooldal-notices');
            } elseif ($action === 'delete') {
                if (!$noticesOk) {
                    throw new RuntimeException('A tip tábla nem érhető el.');
                }
                events_public_home_notices_delete($db, $noticeId);
                if (function_exists('rendszer_log')) {
                    rendszer_log('fooldal_tip', $noticeId, 'Törölve', '');
                }
                flash('success', 'A tip törölve. A korábbi átkattintások a statisztikában megmaradnak.');
                redirect($selfUrl . '#fooldal-notices');
            } else {
                $top = (string) ($_POST['content_top'] ?? '');
                $bottom = (string) ($_POST['content_bottom'] ?? '');
                events_public_home_save($db, $top, $bottom);
                flash('success', 'A főoldal szövegei mentve.');
                redirect($selfUrl);
            }
        } catch (InvalidArgumentException $e) {
            $hiba = $e->getMessage();
        } catch (Throwable $e) {
            error_log('events fooldal_szerkeszt save: ' . $e->getMessage());
            $hiba = 'A mentés nem sikerült.';
        }
    }
}

$content = events_public_home_load($db);
$homeNotices = $noticesOk ? events_public_home_notices_all($db) : [];
$openRaw = (string) ($_GET['open'] ?? '');
$openNoticeId = ctype_digit($openRaw) ? (int) $openRaw : 0;
$activeNoticeCount = 0;
foreach ($homeNotices as $homeNotice) {
    if (!empty($homeNotice['is_active']) && events_public_home_notices_has_displayable_text($homeNotice)) {
        $activeNoticeCount++;
    }
}

$noticeStatsFormAction = $selfUrl;
$noticeStatsParams = events_public_home_notice_stats_params_from_request($_GET);
$noticeStatsData = $tableOk
    ? events_public_home_notice_stats($db, $noticeStatsParams)
    : [
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
$noticeVersionOptions = $tableOk ? events_public_home_notice_list_for_filter($db) : [];
$noticeStatsAllFrom = $tableOk ? events_public_home_notice_earliest_click_date($db) : null;
$noticeStatsActivePreset = events_edit_stats_detect_preset($noticeStatsParams, $noticeStatsAllFrom);
$noticeStatsPresetLinks = [];
$noticeStatsExtraQuery = array_filter([
    'notice_tip' => ($noticeStatsParams['notice_id'] ?? 0) > 0 ? $noticeStatsParams['notice_id'] : null,
    'notice_lang' => $noticeStatsParams['lang'] !== 'all' ? $noticeStatsParams['lang'] : null,
    'notice_visitor' => $noticeStatsParams['visitor'] !== 'all' ? $noticeStatsParams['visitor'] : null,
], static fn ($v): bool => $v !== null && $v !== '');
foreach (events_edit_stats_presets() as $preset) {
    $presetId = (string) $preset['id'];
    $noticeStatsPresetLinks[] = [
        'id' => $presetId,
        'label' => (string) $preset['label'],
        'url' => events_edit_stats_filter_url(
            $noticeStatsFormAction,
            events_edit_stats_range_for_preset($presetId, $noticeStatsAllFrom),
            $noticeStatsExtraQuery
        ) . '#notice-click-stats',
        'active' => $noticeStatsActivePreset === $presetId,
    ];
}

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Publikus főoldal szövegei';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>

<div class="card">
    <div class="events-list-head">
        <h2 class="events-list-title">Publikus főoldal szövegei</h2>
        <div class="events-list-actions">
            <a href="#notice-click-stats" class="btn btn-secondary btn-sm">Átkattintások</a>
            <a href="<?= h(events_public_home_url('hu')) ?>" class="btn btn-secondary btn-sm" target="_blank" rel="noopener">Előnézet</a>
        </div>
    </div>

    <?php if (!$tableOk): ?>
        <p class="alert alert-error">Hiányzik az <code>events_public_home</code> tábla. Futtasd: <code>events/sql/migration_public_home.sql</code></p>
    <?php else: ?>
        <p class="text-muted" style="margin-top:0">A szövegek a nyilvános esemény főoldalon jelennek meg a naptár felett és alatt. Csak közzétett események látszanak a naptárban.</p>

        <section class="events-fooldal-notice" id="fooldal-notices">
            <h3 class="events-fooldal-notice__legend">Fejléc tip (logó mellett)</h3>
            <p class="help" style="margin-top:0">
                Több tipet is felvehetsz; a látogatók a <strong>használatban</strong> lévőket kapják.
                Tiszta főoldal-betöltéskor (pl. /events/) lehetőleg olyat kapnak, amit még nem láttak; ha már mindet látták, véletlenszerűen választunk.
                Hónap-/szűrőváltáskor ugyanaz a tip marad.
                Üres magyar és angol szöveg esetén a tip nem jelenik meg. Az átkattintásokat a <a href="#notice-click-stats">lap alján</a> tipenként követheted.
            </p>
            <p class="help">Most <?= (int) $activeNoticeCount ?> tip van kiosztásban, összesen <?= count($homeNotices) ?>.</p>

            <?php if (!$noticesOk): ?>
                <p class="alert alert-warning">A tip táblához futtasd: <code>events/sql/migration_public_home_notices.sql</code> (vagy töltsd újra az oldalt, ha az auto-migráció engedélyezett).</p>
            <?php else: ?>
                <form method="post" action="<?= h($selfUrl) ?>" class="events-fooldal-notice__create">
                    <?= csrf_input('events_fooldal') ?>
                    <input type="hidden" name="notice_action" value="create">
                    <button type="submit" class="btn btn-primary btn-sm">+ Új tip</button>
                </form>

                <?php if ($homeNotices === []): ?>
                    <p class="alert alert-warning">Még nincs tip. Hozz létre egyet a fenti gombbal.</p>
                <?php else: ?>
                    <div class="events-fooldal-notice-list" id="fooldal-notice-list">
                        <?php foreach ($homeNotices as $noticeRow): ?>
                            <?php
                            $nid = (int) $noticeRow['id'];
                            $isOpen = $openNoticeId === $nid;
                            $isActive = !empty($noticeRow['is_active']);
                            $scheme = (string) $noticeRow['notice_color_scheme'];
                            $customColor = (string) $noticeRow['notice_custom_color'];
                            $previewStyle = events_public_home_notice_css_vars_style($scheme, $customColor);
                            $summary = trim((string) $noticeRow['notice_text']);
                            if ($summary === '') {
                                $summary = trim((string) $noticeRow['notice_text_en']);
                            }
                            if ($summary === '') {
                                $summary = '(üres tip)';
                            }
                            $displayable = events_public_home_notices_has_displayable_text($noticeRow);
                            ?>
                            <article
                                class="events-fooldal-notice-card<?= $isActive ? '' : ' is-inactive' ?>"
                                id="fooldal-notice-<?= $nid ?>"
                                data-notice-card="<?= $nid ?>"
                            >
                                <header class="events-fooldal-notice-card__bar">
                                    <span class="events-fooldal-notice-card__summary"><?= h($summary) ?></span>
                                    <?php if (!$isActive): ?>
                                        <span class="events-fooldal-notice-stats__badge">Kikapcsolva</span>
                                    <?php elseif (!$displayable): ?>
                                        <span class="events-fooldal-notice-stats__badge">Üres — nem jelenik meg</span>
                                    <?php else: ?>
                                        <span class="events-fooldal-notice-stats__badge">Használatban</span>
                                    <?php endif; ?>
                                    <div class="events-fooldal-notice-card__bar-actions">
                                        <form method="post" class="events-fooldal-notice-card__mini-form">
                                            <?= csrf_input('events_fooldal') ?>
                                            <input type="hidden" name="notice_action" value="toggle">
                                            <input type="hidden" name="notice_id" value="<?= $nid ?>">
                                            <label class="events-fooldal-notice__newtab" for="notice_active_bar_<?= $nid ?>">
                                                <input
                                                    class="events-fooldal-notice__newtab-input js-notice-toggle"
                                                    type="checkbox"
                                                    id="notice_active_bar_<?= $nid ?>"
                                                    name="is_active"
                                                    value="1"
                                                    role="switch"
                                                    <?= $isActive ? 'checked' : '' ?>
                                                >
                                                <span class="events-fooldal-notice__newtab-text">Használatban</span>
                                            </label>
                                        </form>
                                        <button
                                            type="button"
                                            class="btn btn-secondary btn-sm"
                                            data-notice-toggle="<?= $nid ?>"
                                            aria-expanded="<?= $isOpen ? 'true' : 'false' ?>"
                                            aria-controls="fooldal-notice-body-<?= $nid ?>"
                                        ><?= $isOpen ? 'Bezárás' : 'Szerkesztés' ?></button>
                                    </div>
                                </header>

                                <div class="events-fooldal-notice-card__body" id="fooldal-notice-body-<?= $nid ?>"<?= $isOpen ? '' : ' hidden' ?>>
                                    <form method="post" action="<?= h($selfUrl) ?>" class="events-admin-form js-notice-editor">
                                        <?= csrf_input('events_fooldal') ?>
                                        <input type="hidden" name="notice_action" value="save">
                                        <input type="hidden" name="notice_id" value="<?= $nid ?>">

                                        <label class="events-fooldal-notice__newtab events-fooldal-notice-card__active" for="notice_active_<?= $nid ?>">
                                            <input
                                                class="events-fooldal-notice__newtab-input"
                                                type="checkbox"
                                                id="notice_active_<?= $nid ?>"
                                                name="is_active"
                                                value="1"
                                                role="switch"
                                                <?= $isActive ? 'checked' : '' ?>
                                            >
                                            <span class="events-fooldal-notice__newtab-text">Használatban (kiosztásra kerül)</span>
                                        </label>

                                        <div class="form-group">
                                            <label for="notice_text_<?= $nid ?>">Tip szöveg (magyar)</label>
                                            <input type="text" id="notice_text_<?= $nid ?>" name="notice_text" maxlength="500" value="<?= h((string) $noticeRow['notice_text']) ?>" class="js-notice-text">
                                        </div>

                                        <div class="form-group">
                                            <label for="notice_text_en_<?= $nid ?>">Tip szöveg (angol)</label>
                                            <input type="text" id="notice_text_en_<?= $nid ?>" name="notice_text_en" maxlength="500" value="<?= h((string) $noticeRow['notice_text_en']) ?>">
                                        </div>

                                        <div class="form-group">
                                            <label for="notice_url_<?= $nid ?>">Átkattintás URL</label>
                                            <div class="events-fooldal-notice__url-row">
                                                <input type="text" id="notice_url_<?= $nid ?>" name="notice_url" maxlength="500" value="<?= h((string) $noticeRow['notice_url']) ?>" placeholder="/lanueva/ vagy https://…">
                                                <?php $newTabOn = !empty($noticeRow['notice_url_new_tab']); ?>
                                                <label class="events-fooldal-notice__newtab" for="notice_url_new_tab_<?= $nid ?>">
                                                    <input
                                                        class="events-fooldal-notice__newtab-input"
                                                        type="checkbox"
                                                        id="notice_url_new_tab_<?= $nid ?>"
                                                        name="notice_url_new_tab"
                                                        value="1"
                                                        role="switch"
                                                        <?= $newTabOn ? 'checked' : '' ?>
                                                    >
                                                    <span class="events-fooldal-notice__newtab-text">Új ablak</span>
                                                </label>
                                            </div>
                                            <p class="help">Relatív útvonal (<code>/lanueva/</code>) vagy teljes http(s) URL. A kapcsolóval új lapon nyílik a link.</p>
                                        </div>

                                        <div class="form-group">
                                            <span class="events-fooldal-notice__color-label">Színséma</span>
                                            <div class="events-fooldal-notice__swatches" role="radiogroup" aria-label="Tip színséma">
                                                <?php foreach ($noticePresets as $key => $preset): ?>
                                                    <?php
                                                    $accent = (string) $preset['accent'];
                                                    $checked = $scheme === $key;
                                                    ?>
                                                    <label class="events-fooldal-notice__swatch<?= $checked ? ' is-selected' : '' ?>">
                                                        <input type="radio" name="notice_color_scheme" value="<?= h($key) ?>" <?= $checked ? 'checked' : '' ?>>
                                                        <span class="events-fooldal-notice__swatch-chip" style="--swatch-accent: <?= h($accent) ?>; --swatch-bg-from: <?= h((string) $preset['bg_from']) ?>; --swatch-bg-to: <?= h((string) $preset['bg_to']) ?>;" aria-hidden="true"></span>
                                                        <span class="events-fooldal-notice__swatch-name"><?= h((string) $preset['label']) ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                                <?php $customChecked = $scheme === 'custom'; ?>
                                                <label class="events-fooldal-notice__swatch events-fooldal-notice__swatch--custom<?= $customChecked ? ' is-selected' : '' ?>">
                                                    <input type="radio" name="notice_color_scheme" value="custom" <?= $customChecked ? 'checked' : '' ?>>
                                                    <span class="events-fooldal-notice__swatch-chip events-fooldal-notice__swatch-chip--custom" style="--swatch-accent: <?= h($customColor) ?>;" aria-hidden="true"></span>
                                                    <span class="events-fooldal-notice__swatch-name">Egyedi</span>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="form-group js-notice-custom-color"<?= $scheme === 'custom' ? '' : ' hidden' ?>>
                                            <label for="notice_custom_color_<?= $nid ?>">Egyedi szín (hex)</label>
                                            <div class="events-category-color-input-row">
                                                <input type="color" class="js-notice-color-picker" value="<?= h($customColor) ?>" aria-label="Egyedi tip szín">
                                                <input type="text" id="notice_custom_color_<?= $nid ?>" name="notice_custom_color" class="js-notice-color-text" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$" value="<?= h($customColor) ?>" placeholder="#39FF14">
                                            </div>
                                        </div>

                                        <div class="events-fooldal-notice__preview" aria-live="polite">
                                            <span class="events-fooldal-notice__preview-label">Előnézet</span>
                                            <span class="home-public__renewal-notice-link events-fooldal-notice__preview-link js-notice-preview" style="<?= h($previewStyle) ?>"><?= h($summary !== '(üres tip)' ? $summary : 'Tip előnézet') ?></span>
                                        </div>

                                        <div class="form-actions">
                                            <button type="submit" class="btn btn-primary">Tip mentése</button>
                                        </div>
                                    </form>

                                    <form method="post" class="events-fooldal-notice-card__delete" onsubmit="return confirm('Biztosan törlöd ezt a tipet? A statisztikák megmaradnak.');">
                                        <?= csrf_input('events_fooldal') ?>
                                        <input type="hidden" name="notice_action" value="delete">
                                        <input type="hidden" name="notice_id" value="<?= $nid ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm">Tip törlése</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <form method="post" action="<?= h($selfUrl) ?>" class="events-admin-form" id="fooldal-szerkeszt-form">
            <?= csrf_input('events_fooldal') ?>

            <div class="form-group">
                <label for="content_top">Szöveg felül (HTML)</label>
                <textarea class="js-tinymce" id="content_top" name="content_top" rows="14"><?= h($content['content_top']) ?></textarea>
            </div>

            <div class="form-group">
                <label for="content_bottom">Szöveg alul (HTML)</label>
                <textarea class="js-tinymce" id="content_bottom" name="content_bottom" rows="14"><?= h($content['content_bottom']) ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Szövegek mentése</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php if ($tableOk): ?>
    <?php require __DIR__ . '/partials/fooldal_notice_stats.php'; ?>
<?php endif; ?>

<?php if ($tableOk && $noticesOk): ?>
<script>
(function () {
    var list = document.getElementById('fooldal-notice-list');
    if (!list) return;

    var presets = <?= json_encode(
        array_map(static fn (array $p): array => [
            'accent' => $p['accent'],
            'bg_from' => $p['bg_from'],
            'bg_to' => $p['bg_to'],
        ], $noticePresets),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>;

    function lightenHex(hex, amount) {
        hex = (hex || '').replace(/^#/, '');
        if (!/^[0-9A-Fa-f]{6}$/.test(hex)) return '#7DFF5C';
        var r = parseInt(hex.slice(0, 2), 16);
        var g = parseInt(hex.slice(2, 4), 16);
        var b = parseInt(hex.slice(4, 6), 16);
        var t = Math.max(0, Math.min(1, amount));
        r = Math.round(r + (255 - r) * t);
        g = Math.round(g + (255 - g) * t);
        b = Math.round(b + (255 - b) * t);
        return '#' + [r, g, b].map(function (n) {
            return n.toString(16).padStart(2, '0');
        }).join('').toUpperCase();
    }

    function hexToRgba(hex, alpha) {
        hex = (hex || '').replace(/^#/, '');
        var r = parseInt(hex.slice(0, 2), 16);
        var g = parseInt(hex.slice(2, 4), 16);
        var b = parseInt(hex.slice(4, 6), 16);
        return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha.toFixed(2) + ')';
    }

    function selectedScheme(card) {
        var checked = card.querySelector('input[name="notice_color_scheme"]:checked');
        return checked ? checked.value : 'neon_green';
    }

    function resolveTheme(card) {
        var scheme = selectedScheme(card);
        if (scheme === 'custom') {
            var text = card.querySelector('.js-notice-color-text');
            var c = ((text && text.value) || '#39FF14').trim().toUpperCase();
            if (c.charAt(0) !== '#') c = '#' + c;
            if (!/^#[0-9A-F]{6}$/.test(c)) c = '#39FF14';
            return { accent: c, bg_from: '#0A0E27', bg_to: '#141829' };
        }
        return presets[scheme] || presets.neon_green;
    }

    function applyPreview(card) {
        var preview = card.querySelector('.js-notice-preview');
        if (!preview) return;
        var theme = resolveTheme(card);
        var accent = theme.accent;
        var hover = lightenHex(accent, 0.28);
        preview.style.setProperty('--rn-accent', accent);
        preview.style.setProperty('--rn-accent-hover', hover);
        preview.style.setProperty('--rn-border', hexToRgba(accent, 0.35));
        preview.style.setProperty('--rn-border-hover', hexToRgba(hover, 0.55));
        preview.style.setProperty('--rn-shadow', hexToRgba(accent, 0.15));
        preview.style.setProperty('--rn-shadow-hover', hexToRgba(accent, 0.28));
        preview.style.setProperty('--rn-glow', hexToRgba(accent, 0.55));
        preview.style.setProperty('--rn-glow-soft', hexToRgba(accent, 0.25));
        preview.style.setProperty('--rn-bg-from', theme.bg_from);
        preview.style.setProperty('--rn-bg-to', theme.bg_to);
        var noticeText = card.querySelector('.js-notice-text');
        if (noticeText) {
            preview.textContent = (noticeText.value || '').trim() || 'Tip előnézet';
        }
    }

    function syncCard(card) {
        var isCustom = selectedScheme(card) === 'custom';
        var customGroup = card.querySelector('.js-notice-custom-color');
        if (customGroup) customGroup.hidden = !isCustom;
        card.querySelectorAll('.events-fooldal-notice__swatch').forEach(function (el) {
            el.classList.toggle('is-selected', !!(el.querySelector('input') || {}).checked);
        });
        applyPreview(card);
    }

    list.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-notice-toggle]');
        if (!btn || !list.contains(btn)) return;
        var id = btn.getAttribute('data-notice-toggle');
        var body = document.getElementById('fooldal-notice-body-' + id);
        if (!body) return;
        var open = body.hidden;
        body.hidden = !open;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        btn.textContent = open ? 'Bezárás' : 'Szerkesztés';
    });

    list.addEventListener('change', function (e) {
        var toggle = e.target.closest('.js-notice-toggle');
        if (toggle && toggle.form) {
            toggle.form.submit();
            return;
        }
        var card = e.target.closest('[data-notice-card]');
        if (card) syncCard(card);
    });

    list.addEventListener('input', function (e) {
        var card = e.target.closest('[data-notice-card]');
        if (!card) return;
        if (e.target.classList.contains('js-notice-color-picker')) {
            var text = card.querySelector('.js-notice-color-text');
            if (text) text.value = (e.target.value || '').toUpperCase();
            var chip = card.querySelector('.events-fooldal-notice__swatch-chip--custom');
            if (chip && text) chip.style.setProperty('--swatch-accent', text.value);
        }
        if (e.target.classList.contains('js-notice-color-text')) {
            var v = (e.target.value || '').trim();
            if (/^#[0-9A-Fa-f]{6}$/.test(v)) {
                var picker = card.querySelector('.js-notice-color-picker');
                if (picker) picker.value = v;
                var chip = card.querySelector('.events-fooldal-notice__swatch-chip--custom');
                if (chip) chip.style.setProperty('--swatch-accent', v.toUpperCase());
            }
        }
        applyPreview(card);
    });

    list.querySelectorAll('[data-notice-card]').forEach(syncCard);
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/partials/tinymce_script.php'; ?>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
