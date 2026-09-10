<?php
declare(strict_types=1);

/**
 * DJ média feltöltő (fotó vagy logó).
 *
 * @var string $djMediaKind 'photo'|'logo'
 * @var string $djMediaUrl aktuális URL
 * @var string $djMediaPick djpics fájlnév (ha van)
 */

require_once __DIR__ . '/../lib/djpics.php';

$djMediaKind = (string) ($djMediaKind ?? 'photo');
if ($djMediaKind !== 'logo') {
    $djMediaKind = 'photo';
}
$djMediaUrl = trim((string) ($djMediaUrl ?? ''));
$djMediaPick = trim((string) ($djMediaPick ?? ''));
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
$uploadUrl = events_url('ajax_djpic_upload.php');
$csrfName = $isLogo ? 'djpics_logo_csrf' : 'djpics_csrf';
$fitClass = $isLogo ? ' events-dj-media__preview--contain' : '';
?>
<div class="events-dj-media events-dj-media--<?= h($djMediaKind) ?>" id="<?= h($panelId) ?>" data-upload-url="<?= h($uploadUrl) ?>" data-ok-msg="<?= h($okMsg) ?>" data-clear-msg="<?= h($clearMsg) ?>">
    <h3 class="events-edit-panel__title"><?= h($title) ?></h3>
    <div class="events-dj-media__preview-wrap<?= $isLogo ? ' events-dj-media__preview-wrap--logo' : '' ?>">
        <img
            class="events-dj-media__preview<?= h($fitClass) ?>"
            id="<?= h($prefix) ?>-preview"
            <?= $previewSrc !== '' ? 'src="' . h($previewSrc) . '"' : '' ?>
            alt=""
            <?= $previewSrc !== '' ? '' : 'hidden' ?>
        >
        <div
            class="events-dj-media__placeholder"
            id="<?= h($prefix) ?>-placeholder"
            <?= $previewSrc !== '' ? 'hidden' : '' ?>
        ><?= h($placeholder) ?></div>
    </div>
    <input type="hidden" name="<?= h($prefix) ?>_pick" id="<?= h($prefix) ?>_pick" value="<?= h($djMediaPick) ?>">
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
    var fileInp = document.getElementById(prefix + '_file');
    var pickInp = document.getElementById(prefix + '_pick');
    var preview = document.getElementById(prefix + '-preview');
    var placeholder = document.getElementById(prefix + '-placeholder');
    var statusEl = document.getElementById(prefix + '-status');
    var clearCb = document.getElementById(prefix + '_clear');
    var uploadUrl = panel.getAttribute('data-upload-url') || '';
    var csrfInp = panel.querySelector('input[name="<?= h($csrfName) ?>"]');
    var okMsg = panel.getAttribute('data-ok-msg') || '';
    var clearMsg = panel.getAttribute('data-clear-msg') || '';

    function setPreview(url) {
        if (!preview) return;
        if (url) {
            preview.src = url;
            preview.hidden = false;
            if (placeholder) placeholder.hidden = true;
        } else {
            preview.removeAttribute('src');
            preview.hidden = true;
            if (placeholder) placeholder.hidden = false;
        }
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
})();
</script>
