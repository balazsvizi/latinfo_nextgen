<?php
declare(strict_types=1);

/**
 * DJ fotó feltöltő (fájl + AJAX előnézet).
 *
 * @var string $djPhotoUrl aktuális photo_url
 * @var string $djPhotoPick djpics fájlnév (ha van)
 */

require_once __DIR__ . '/../lib/djpics.php';

$djPhotoUrl = trim((string) ($djPhotoUrl ?? ''));
$djPhotoPick = trim((string) ($djPhotoPick ?? ''));
if ($djPhotoPick === '' && $djPhotoUrl !== '') {
    $djPhotoPick = events_djpics_extract_selected_from_photo($djPhotoUrl);
}
$previewSrc = '';
if ($djPhotoPick !== '') {
    $previewSrc = site_url(ltrim(events_djpics_build_web_path($djPhotoPick), '/'));
} elseif ($djPhotoUrl !== '') {
    $previewSrc = events_absolute_url($djPhotoUrl);
}
$uploadUrl = events_url('ajax_djpic_upload.php');
?>
<div class="events-dj-photo" id="dj-photo-panel" data-upload-url="<?= h($uploadUrl) ?>">
    <h3 class="events-edit-panel__title">Fotó</h3>
    <div class="events-dj-photo__preview-wrap">
        <?php if ($previewSrc !== ''): ?>
            <img class="events-dj-photo__preview" id="dj-photo-preview" src="<?= h($previewSrc) ?>" alt="">
        <?php else: ?>
            <img class="events-dj-photo__preview" id="dj-photo-preview" src="" alt="" hidden>
            <div class="events-dj-photo__placeholder" id="dj-photo-placeholder">Nincs fotó</div>
        <?php endif; ?>
    </div>
    <input type="hidden" name="dj_photo_pick" id="dj_photo_pick" value="<?= h($djPhotoPick) ?>">
    <div class="events-dj-photo__actions">
        <label class="btn btn-secondary events-dj-photo__upload-btn" for="dj_photo_file">
            Kép feltöltése
            <input type="file" id="dj_photo_file" name="dj_photo_upload" class="visually-hidden" accept="image/jpeg,image/png,image/webp,image/gif">
        </label>
        <label class="events-dj-photo__clear">
            <input type="checkbox" name="dj_photo_clear" id="dj_photo_clear" value="1">
            Fotó törlése mentéskor
        </label>
    </div>
    <p class="help" id="dj-photo-status">JPG, PNG, WEBP vagy GIF, max. 8 MB. Mentés előtt feltöltheted (azonnal), vagy a Mentés gombbal együtt.</p>
    <?= csrf_input('events_djpics', 'djpics_csrf') ?>
</div>
<script>
(function () {
    var panel = document.getElementById('dj-photo-panel');
    if (!panel) return;
    var fileInp = document.getElementById('dj_photo_file');
    var pickInp = document.getElementById('dj_photo_pick');
    var preview = document.getElementById('dj-photo-preview');
    var placeholder = document.getElementById('dj-photo-placeholder');
    var statusEl = document.getElementById('dj-photo-status');
    var clearCb = document.getElementById('dj_photo_clear');
    var uploadUrl = panel.getAttribute('data-upload-url') || '';
    var csrfInp = panel.querySelector('input[name="djpics_csrf"]');

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

    if (fileInp) {
        fileInp.addEventListener('change', function () {
            var file = fileInp.files && fileInp.files[0];
            if (!file || !uploadUrl || !csrfInp) return;
            if (clearCb) clearCb.checked = false;
            setStatus('Feltöltés…');
            var fd = new FormData();
            fd.append('file', file);
            fd.append('djpics_csrf', csrfInp.value);
            fetch(uploadUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.ok) {
                        setStatus((data && data.error) ? data.error : 'Feltöltés sikertelen.', true);
                        return;
                    }
                    if (pickInp) pickInp.value = data.filename || '';
                    setPreview(data.thumb_url || data.url || '');
                    // Ne küldjük újra a fájlt mentéskor, ha AJAX már feltöltötte.
                    try { fileInp.value = ''; } catch (e) {}
                    setStatus('Fotó feltöltve. Mentéskor mentjük az adatlaphoz.');
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
                setStatus('A fotó mentéskor törlődik.');
            }
        });
    }
})();
</script>
