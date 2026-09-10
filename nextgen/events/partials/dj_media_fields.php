<?php
declare(strict_types=1);

/**
 * DJ média feltöltő (fotó vagy logó) + illesztés / fókusz / zoom.
 *
 * @var string $djMediaKind 'photo'|'logo'
 * @var string $djMediaUrl aktuális URL
 * @var string $djMediaPick djpics fájlnév (ha van)
 * @var array<string, string>|null $djMediaProfile
 */

require_once __DIR__ . '/../lib/djpics.php';
require_once __DIR__ . '/../lib/tag_profile.php';

$djMediaKind = (string) ($djMediaKind ?? 'photo');
if ($djMediaKind !== 'logo') {
    $djMediaKind = 'photo';
}
$djMediaUrl = trim((string) ($djMediaUrl ?? ''));
$djMediaPick = trim((string) ($djMediaPick ?? ''));
$djMediaProfile = is_array($djMediaProfile ?? null) ? $djMediaProfile : [];
if ($djMediaPick === '' && $djMediaUrl !== '') {
    $djMediaPick = events_djpics_extract_selected_from_photo($djMediaUrl);
}
$previewSrc = '';
if ($djMediaPick !== '') {
    $previewSrc = site_url(ltrim(events_djpics_build_web_path($djMediaPick), '/'));
} elseif ($djMediaUrl !== '') {
    $previewSrc = events_absolute_url($djMediaUrl);
}

$isLogo = $djMediaKind === 'logo';
$panelId = $isLogo ? 'dj-logo-panel' : 'dj-photo-panel';
$title = $isLogo ? 'Logó' : 'Fotó';
$placeholder = $isLogo ? 'Nincs logó' : 'Nincs fotó';
$clearLabel = $isLogo ? 'Logó törlése mentéskor' : 'Fotó törlése mentéskor';
$okMsg = $isLogo
    ? 'Logó feltöltve. Mentéskor mentjük az adatlaphoz.'
    : 'Fotó feltöltve. Mentéskor mentjük az adatlaphoz.';
$clearMsg = $isLogo ? 'A logó mentéskor törlődik.' : 'A fotó mentéskor törlődik.';
$prefix = $isLogo ? 'dj_logo' : 'dj_photo';
$fieldPrefix = $isLogo ? 'tag_logo' : 'tag_photo';
$uploadUrl = events_url('ajax_djpic_upload.php');
$csrfName = $isLogo ? 'djpics_logo_csrf' : 'djpics_csrf';

$defaultFit = $isLogo ? 'contain' : 'cover';
$fit = events_dj_media_fit_normalize((string) ($djMediaProfile[$djMediaKind . '_fit'] ?? ''), $defaultFit);
$focusX = events_dj_media_pct_normalize($djMediaProfile[$djMediaKind . '_focus_x'] ?? 50, 50);
$focusY = events_dj_media_pct_normalize($djMediaProfile[$djMediaKind . '_focus_y'] ?? 50, 50);
$zoom = events_dj_media_zoom_normalize($djMediaProfile[$djMediaKind . '_zoom'] ?? 100, 100);
$previewStyle = events_dj_media_img_style([
    $djMediaKind . '_fit' => $fit,
    $djMediaKind . '_focus_x' => (string) $focusX,
    $djMediaKind . '_focus_y' => (string) $focusY,
    $djMediaKind . '_zoom' => (string) $zoom,
], $djMediaKind);
?>
<div class="events-dj-media events-dj-media--<?= h($djMediaKind) ?>" id="<?= h($panelId) ?>" data-upload-url="<?= h($uploadUrl) ?>" data-ok-msg="<?= h($okMsg) ?>" data-clear-msg="<?= h($clearMsg) ?>" data-default-fit="<?= h($defaultFit) ?>">
    <h3 class="events-edit-panel__title"><?= h($title) ?></h3>
    <div class="events-dj-media__preview-wrap<?= $isLogo ? ' events-dj-media__preview-wrap--logo' : '' ?>" id="<?= h($prefix) ?>-preview-wrap" title="Kattints a fókuszpontra">
        <img
            class="events-dj-media__preview<?= $fit === 'contain' ? ' events-dj-media__preview--contain' : '' ?>"
            id="<?= h($prefix) ?>-preview"
            <?= $previewSrc !== '' ? 'src="' . h($previewSrc) . '"' : '' ?>
            alt=""
            style="<?= h($previewStyle) ?>"
            <?= $previewSrc !== '' ? '' : 'hidden' ?>
        >
        <div class="events-dj-media__focus-mark" id="<?= h($prefix) ?>-focus-mark" style="left:<?= (int) $focusX ?>%;top:<?= (int) $focusY ?>%;" <?= $previewSrc !== '' ? '' : 'hidden' ?> aria-hidden="true"></div>
        <div
            class="events-dj-media__placeholder"
            id="<?= h($prefix) ?>-placeholder"
            <?= $previewSrc !== '' ? 'hidden' : '' ?>
        ><?= h($placeholder) ?></div>
    </div>
    <input type="hidden" name="<?= h($prefix) ?>_pick" id="<?= h($prefix) ?>_pick" value="<?= h($djMediaPick) ?>">
    <input type="hidden" name="<?= h($fieldPrefix) ?>_focus_x" id="<?= h($fieldPrefix) ?>_focus_x" value="<?= (int) $focusX ?>">
    <input type="hidden" name="<?= h($fieldPrefix) ?>_focus_y" id="<?= h($fieldPrefix) ?>_focus_y" value="<?= (int) $focusY ?>">

    <div class="events-dj-media__display" id="<?= h($prefix) ?>-display">
        <div class="events-dj-media__display-row">
            <span class="events-dj-media__display-label">Illesztés</span>
            <div class="events-dj-media__fit" role="group" aria-label="Kép illesztése">
                <label class="events-dj-media__fit-opt">
                    <input type="radio" name="<?= h($fieldPrefix) ?>_fit" value="cover" <?= $fit === 'cover' ? 'checked' : '' ?>>
                    Kitöltés
                </label>
                <label class="events-dj-media__fit-opt">
                    <input type="radio" name="<?= h($fieldPrefix) ?>_fit" value="contain" <?= $fit === 'contain' ? 'checked' : '' ?>>
                    Teljes kép
                </label>
            </div>
        </div>
        <div class="events-dj-media__display-row">
            <label class="events-dj-media__display-label" for="<?= h($fieldPrefix) ?>_zoom">Nagyítás</label>
            <div class="events-dj-media__zoom">
                <input type="range" id="<?= h($fieldPrefix) ?>_zoom" name="<?= h($fieldPrefix) ?>_zoom" min="100" max="200" step="5" value="<?= (int) $zoom ?>">
                <span class="events-dj-media__zoom-val" id="<?= h($prefix) ?>-zoom-val"><?= (int) $zoom ?>%</span>
            </div>
        </div>
        <p class="help events-dj-media__focus-help">A fókuszponthoz kattints a képre (arc / fontos rész középre).</p>
    </div>

    <div class="events-dj-media__actions">
        <label class="btn btn-secondary events-dj-media__upload-btn" for="<?= h($prefix) ?>_file">
            Kép feltöltése
            <input type="file" id="<?= h($prefix) ?>_file" name="<?= h($prefix) ?>_upload" class="visually-hidden" accept="image/jpeg,image/png,image/webp,image/gif">
        </label>
        <label class="events-dj-media__clear">
            <input type="checkbox" name="<?= h($prefix) ?>_clear" id="<?= h($prefix) ?>_clear" value="1">
            <?= h($clearLabel) ?>
        </label>
    </div>
    <p class="help" id="<?= h($prefix) ?>-status">JPG, PNG, WEBP vagy GIF, max. 8 MB. Mentés előtt feltöltheted (azonnal), vagy a Mentés gombbal együtt.</p>
    <?= csrf_input('events_djpics', $csrfName) ?>
</div>
<script>
(function () {
    var panel = document.getElementById(<?= json_encode($panelId, JSON_UNESCAPED_UNICODE) ?>);
    if (!panel || panel.getAttribute('data-bound') === '1') return;
    panel.setAttribute('data-bound', '1');
    var prefix = <?= json_encode($prefix, JSON_UNESCAPED_UNICODE) ?>;
    var fieldPrefix = <?= json_encode($fieldPrefix, JSON_UNESCAPED_UNICODE) ?>;
    var fileInp = document.getElementById(prefix + '_file');
    var pickInp = document.getElementById(prefix + '_pick');
    var preview = document.getElementById(prefix + '-preview');
    var previewWrap = document.getElementById(prefix + '-preview-wrap');
    var placeholder = document.getElementById(prefix + '-placeholder');
    var focusMark = document.getElementById(prefix + '-focus-mark');
    var statusEl = document.getElementById(prefix + '-status');
    var clearCb = document.getElementById(prefix + '_clear');
    var focusXInp = document.getElementById(fieldPrefix + '_focus_x');
    var focusYInp = document.getElementById(fieldPrefix + '_focus_y');
    var zoomInp = document.getElementById(fieldPrefix + '_zoom');
    var zoomVal = document.getElementById(prefix + '-zoom-val');
    var fitInputs = panel.querySelectorAll('input[name="' + fieldPrefix + '_fit"]');
    var uploadUrl = panel.getAttribute('data-upload-url') || '';
    var csrfInp = panel.querySelector('input[name="<?= h($csrfName) ?>"]');
    var okMsg = panel.getAttribute('data-ok-msg') || '';
    var clearMsg = panel.getAttribute('data-clear-msg') || '';

    function currentFit() {
        for (var i = 0; i < fitInputs.length; i++) {
            if (fitInputs[i].checked) return fitInputs[i].value;
        }
        return panel.getAttribute('data-default-fit') || 'cover';
    }

    function applyPreviewStyle() {
        if (!preview) return;
        var fit = currentFit();
        var x = focusXInp ? parseInt(focusXInp.value, 10) : 50;
        var y = focusYInp ? parseInt(focusYInp.value, 10) : 50;
        var zoom = zoomInp ? parseInt(zoomInp.value, 10) : 100;
        if (isNaN(x)) x = 50;
        if (isNaN(y)) y = 50;
        if (isNaN(zoom)) zoom = 100;
        preview.classList.toggle('events-dj-media__preview--contain', fit === 'contain');
        var style = 'object-fit:' + fit + ';object-position:' + x + '% ' + y + '%;';
        if (zoom !== 100) {
            style += 'transform:scale(' + (zoom / 100).toFixed(2) + ');transform-origin:' + x + '% ' + y + '%;';
        } else {
            style += 'transform:none;';
        }
        preview.style.cssText = style;
        if (focusMark) {
            focusMark.style.left = x + '%';
            focusMark.style.top = y + '%';
        }
        if (zoomVal) zoomVal.textContent = zoom + '%';
    }

    function setPreview(url) {
        if (!preview) return;
        if (url) {
            preview.src = url;
            preview.hidden = false;
            if (placeholder) placeholder.hidden = true;
            if (focusMark) focusMark.hidden = false;
        } else {
            preview.removeAttribute('src');
            preview.hidden = true;
            if (placeholder) placeholder.hidden = false;
            if (focusMark) focusMark.hidden = true;
        }
        applyPreviewStyle();
    }

    function setStatus(msg, isError) {
        if (!statusEl) return;
        statusEl.textContent = msg;
        statusEl.classList.toggle('alert-error', !!isError);
    }

    if (preview) {
        preview.addEventListener('error', function () {
            if (!preview.getAttribute('src')) {
                return;
            }
            setPreview('');
            setStatus('A kép nem tölthető be.', true);
        });
    }

    if (previewWrap) {
        previewWrap.addEventListener('click', function (e) {
            if (!preview || preview.hidden || !focusXInp || !focusYInp) return;
            var rect = previewWrap.getBoundingClientRect();
            if (rect.width <= 0 || rect.height <= 0) return;
            var x = Math.round(((e.clientX - rect.left) / rect.width) * 100);
            var y = Math.round(((e.clientY - rect.top) / rect.height) * 100);
            if (x < 0) x = 0;
            if (x > 100) x = 100;
            if (y < 0) y = 0;
            if (y > 100) y = 100;
            focusXInp.value = String(x);
            focusYInp.value = String(y);
            applyPreviewStyle();
        });
    }

    for (var i = 0; i < fitInputs.length; i++) {
        fitInputs[i].addEventListener('change', applyPreviewStyle);
    }
    if (zoomInp) {
        zoomInp.addEventListener('input', applyPreviewStyle);
        zoomInp.addEventListener('change', applyPreviewStyle);
    }

    if (fileInp) {
        fileInp.addEventListener('change', function () {
            var file = fileInp.files && fileInp.files[0];
            if (!file || !uploadUrl || !csrfInp) return;
            if (clearCb) clearCb.checked = false;
            setStatus('Feltöltés…');
            var fd = new FormData();
            fd.append('file', file);
            fd.append(csrfInp.name, csrfInp.value);
            fetch(uploadUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.ok) {
                        setStatus((data && data.error) ? data.error : 'Feltöltés sikertelen.', true);
                        return;
                    }
                    if (pickInp) pickInp.value = data.filename || '';
                    setPreview(data.thumb_url || data.url || '');
                    try { fileInp.value = ''; } catch (e) {}
                    setStatus(okMsg);
                })
                .catch(function () {
                    setStatus('Hálózati hiba a feltöltésnél.', true);
                });
        });
    }

    if (clearCb) {
        clearCb.addEventListener('change', function () {
            if (clearCb.checked) {
                if (pickInp) pickInp.value = '';
                setPreview('');
                setStatus(clearMsg);
            }
        });
    }

    applyPreviewStyle();
})();
</script>
