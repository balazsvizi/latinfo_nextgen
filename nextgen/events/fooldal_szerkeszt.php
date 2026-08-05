<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/public_home_content.php';
requireLogin();

$db = getDb();
$tableOk = events_public_home_table_available($db);
$hiba = '';
$noticePresets = events_public_home_notice_color_presets();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('events_fooldal')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet. Töltsd újra az oldalt.';
    } elseif (!$tableOk) {
        $hiba = 'Hiányzik az events_public_home tábla. Futtasd: events/sql/migration_public_home.sql';
    } else {
        $top = (string) ($_POST['content_top'] ?? '');
        $bottom = (string) ($_POST['content_bottom'] ?? '');
        $notice = [
            'notice_text' => (string) ($_POST['notice_text'] ?? ''),
            'notice_text_en' => (string) ($_POST['notice_text_en'] ?? ''),
            'notice_url' => (string) ($_POST['notice_url'] ?? ''),
            'notice_color_scheme' => (string) ($_POST['notice_color_scheme'] ?? 'neon_green'),
            'notice_custom_color' => (string) ($_POST['notice_custom_color'] ?? '#39FF14'),
        ];
        try {
            events_public_home_save($db, $top, $bottom, $notice);
            flash('success', 'A főoldal szövegei mentve.');
            redirect(events_url('fooldal_szerkeszt.php'));
        } catch (InvalidArgumentException $e) {
            $hiba = $e->getMessage();
        } catch (Throwable $e) {
            error_log('events fooldal_szerkeszt save: ' . $e->getMessage());
            $hiba = 'A mentés nem sikerült.';
        }
    }
}

$content = events_public_home_load($db);
$noticeScheme = (string) ($content['notice_color_scheme'] ?? 'neon_green');
$noticeCustom = (string) ($content['notice_custom_color'] ?? '#39FF14');
$previewStyle = events_public_home_notice_css_vars_style($noticeScheme, $noticeCustom);

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
            <a href="<?= h(events_public_home_url('hu')) ?>" class="btn btn-secondary btn-sm" target="_blank" rel="noopener">Előnézet</a>
        </div>
    </div>

    <?php if (!$tableOk): ?>
        <p class="alert alert-error">Hiányzik az <code>events_public_home</code> tábla. Futtasd: <code>events/sql/migration_public_home.sql</code></p>
    <?php else: ?>
        <p class="text-muted" style="margin-top:0">A szövegek a nyilvános esemény főoldalon jelennek meg a naptár felett és alatt. Csak közzétett események látszanak a naptárban.</p>
        <?php if (empty($content['notice_schema_ok'])): ?>
            <p class="alert alert-warning">A tip mezőkhöz futtasd: <code>events/sql/migration_public_home_notice.sql</code> (vagy mentsd el az űrlapot, ha az auto-migráció engedélyezett).</p>
        <?php endif; ?>
        <form method="post" action="<?= h(events_url('fooldal_szerkeszt.php')) ?>" class="events-admin-form" id="fooldal-szerkeszt-form">
            <?= csrf_input('events_fooldal') ?>

            <fieldset class="events-fooldal-notice">
                <legend class="events-fooldal-notice__legend">Fejléc tip (logó mellett)</legend>
                <p class="help" style="margin-top:0">Üres magyar és angol szöveg esetén a tip nem jelenik meg. A színminták a jelenlegi neon stílus változatai; saját színt a „Egyedi” opcióval adhatsz meg.</p>

                <div class="form-group">
                    <label for="notice_text">Tip szöveg (magyar)</label>
                    <input type="text" id="notice_text" name="notice_text" maxlength="500" value="<?= h((string) $content['notice_text']) ?>">
                </div>

                <div class="form-group">
                    <label for="notice_text_en">Tip szöveg (angol)</label>
                    <input type="text" id="notice_text_en" name="notice_text_en" maxlength="500" value="<?= h((string) $content['notice_text_en']) ?>">
                </div>

                <div class="form-group">
                    <label for="notice_url">Átkattintás URL</label>
                    <input type="text" id="notice_url" name="notice_url" maxlength="500" value="<?= h((string) $content['notice_url']) ?>" placeholder="/lanueva/ vagy https://…">
                    <p class="help">Relatív útvonal (<code>/lanueva/</code>) vagy teljes http(s) URL.</p>
                </div>

                <div class="form-group">
                    <span class="events-fooldal-notice__color-label">Színséma</span>
                    <div class="events-fooldal-notice__swatches" role="radiogroup" aria-label="Tip színséma">
                        <?php foreach ($noticePresets as $key => $preset): ?>
                            <?php
                            $accent = (string) $preset['accent'];
                            $checked = $noticeScheme === $key;
                            ?>
                            <label class="events-fooldal-notice__swatch<?= $checked ? ' is-selected' : '' ?>">
                                <input type="radio" name="notice_color_scheme" value="<?= h($key) ?>" <?= $checked ? 'checked' : '' ?>>
                                <span class="events-fooldal-notice__swatch-chip" style="--swatch-accent: <?= h($accent) ?>; --swatch-bg-from: <?= h((string) $preset['bg_from']) ?>; --swatch-bg-to: <?= h((string) $preset['bg_to']) ?>;" aria-hidden="true"></span>
                                <span class="events-fooldal-notice__swatch-name"><?= h((string) $preset['label']) ?></span>
                            </label>
                        <?php endforeach; ?>
                        <?php $customChecked = $noticeScheme === 'custom'; ?>
                        <label class="events-fooldal-notice__swatch events-fooldal-notice__swatch--custom<?= $customChecked ? ' is-selected' : '' ?>">
                            <input type="radio" name="notice_color_scheme" value="custom" <?= $customChecked ? 'checked' : '' ?>>
                            <span class="events-fooldal-notice__swatch-chip events-fooldal-notice__swatch-chip--custom" style="--swatch-accent: <?= h($noticeCustom) ?>;" aria-hidden="true"></span>
                            <span class="events-fooldal-notice__swatch-name">Egyedi</span>
                        </label>
                    </div>
                </div>

                <div class="form-group" id="notice-custom-color-group"<?= $noticeScheme === 'custom' ? '' : ' hidden' ?>>
                    <label for="notice_custom_color">Egyedi szín (hex)</label>
                    <div class="events-category-color-input-row">
                        <input type="color" id="notice_custom_color_picker" value="<?= h($noticeCustom) ?>" aria-label="Egyedi tip szín">
                        <input type="text" id="notice_custom_color" name="notice_custom_color" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$" value="<?= h($noticeCustom) ?>" placeholder="#39FF14">
                    </div>
                </div>

                <div class="events-fooldal-notice__preview" aria-live="polite">
                    <span class="events-fooldal-notice__preview-label">Előnézet</span>
                    <span class="home-public__renewal-notice-link events-fooldal-notice__preview-link" id="notice-preview" style="<?= h($previewStyle) ?>"><?= h((string) ($content['notice_text'] !== '' ? $content['notice_text'] : 'Tip előnézet')) ?></span>
                </div>
            </fieldset>

            <div class="form-group">
                <label for="content_top">Szöveg felül (HTML)</label>
                <textarea class="js-tinymce" id="content_top" name="content_top" rows="14"><?= h($content['content_top']) ?></textarea>
            </div>

            <div class="form-group">
                <label for="content_bottom">Szöveg alul (HTML)</label>
                <textarea class="js-tinymce" id="content_bottom" name="content_bottom" rows="14"><?= h($content['content_bottom']) ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Mentés</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php if ($tableOk): ?>
<script>
(function () {
    var form = document.getElementById('fooldal-szerkeszt-form');
    if (!form) return;

    var presets = <?= json_encode(
        array_map(static fn (array $p): array => [
            'accent' => $p['accent'],
            'bg_from' => $p['bg_from'],
            'bg_to' => $p['bg_to'],
        ], $noticePresets),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>;

    var picker = document.getElementById('notice_custom_color_picker');
    var text = document.getElementById('notice_custom_color');
    var customGroup = document.getElementById('notice-custom-color-group');
    var preview = document.getElementById('notice-preview');
    var noticeText = document.getElementById('notice_text');
    var radios = form.querySelectorAll('input[name="notice_color_scheme"]');

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

    function selectedScheme() {
        var checked = form.querySelector('input[name="notice_color_scheme"]:checked');
        return checked ? checked.value : 'neon_green';
    }

    function resolveTheme() {
        var scheme = selectedScheme();
        if (scheme === 'custom') {
            var c = ((text && text.value) || '#39FF14').trim().toUpperCase();
            if (c.charAt(0) !== '#') c = '#' + c;
            if (!/^#[0-9A-F]{6}$/.test(c)) c = '#39FF14';
            return { accent: c, bg_from: '#0A0E27', bg_to: '#141829' };
        }
        return presets[scheme] || presets.neon_green;
    }

    function applyPreview() {
        if (!preview) return;
        var theme = resolveTheme();
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
        if (noticeText) {
            preview.textContent = (noticeText.value || '').trim() || 'Tip előnézet';
        }
    }

    function syncCustomVisibility() {
        var isCustom = selectedScheme() === 'custom';
        if (customGroup) customGroup.hidden = !isCustom;
        form.querySelectorAll('.events-fooldal-notice__swatch').forEach(function (el) {
            el.classList.toggle('is-selected', !!(el.querySelector('input') || {}).checked);
        });
        applyPreview();
    }

    radios.forEach(function (r) {
        r.addEventListener('change', syncCustomVisibility);
    });
    if (noticeText) noticeText.addEventListener('input', applyPreview);

    if (picker && text) {
        picker.addEventListener('input', function () {
            text.value = (picker.value || '').toUpperCase();
            var chip = form.querySelector('.events-fooldal-notice__swatch-chip--custom');
            if (chip) chip.style.setProperty('--swatch-accent', text.value);
            applyPreview();
        });
        text.addEventListener('input', function () {
            var v = (text.value || '').trim();
            if (/^#[0-9A-Fa-f]{6}$/.test(v)) {
                picker.value = v;
                var chip = form.querySelector('.events-fooldal-notice__swatch-chip--custom');
                if (chip) chip.style.setProperty('--swatch-accent', v.toUpperCase());
                applyPreview();
            }
        });
    }

    syncCustomVisibility();
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/partials/tinymce_script.php'; ?>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
