<?php
declare(strict_types=1);

/**
 * CMS kiemelt kép: galéria + feltöltés (eventpics UI mintára, cms/uploads tartalommal).
 *
 * @var array<string, mixed> $p
 */
$cmsPicFiles = cms_uploads_list_files();
$cmsPicsPick = cms_uploads_extract_selected_from_featured((string) ($p['featured_image_url'] ?? ''));
$cmsCoverPreview = cms_featured_image_preview_meta((string) ($p['featured_image_url'] ?? ''), $cmsPicsPick);
$cmsCoverCaption = $cmsCoverPreview['source'] === 'url'
    ? 'Előnézet a „Kiemelt kép URL” mező alapján.'
    : ($cmsCoverPreview['source'] === 'pick'
        ? 'Előnézet a CMS képtárból.'
        : '');
$cmsUploadsBase = nextgen_url('cms/uploads/');
?>
<div class="form-group eventpics-media-block cms-featured-block">
    <label>Kiemelt kép</label>
    <p class="help">A galériában az összes CMS feltöltés látszik (<code>cms/uploads</code>). Feltöltés vagy választás az eventpics-től elkülönülten.</p>

    <div class="events-edit-sidebar-cover cms-featured-cover" id="cmspics-summary-preview">
        <div class="events-edit-sidebar-cover__media">
            <div class="events-edit-sidebar-cover__frame">
                <button type="button" class="events-edit-sidebar-cover__trigger" id="cmspics-summary-trigger" aria-label="Teljes kép megnyitása"<?= $cmsCoverPreview['src'] === '' ? ' disabled' : '' ?>>
                    <img
                        id="cmspics-summary-img"
                        class="events-edit-sidebar-cover__img"
                        src="<?= $cmsCoverPreview['src'] !== '' ? h($cmsCoverPreview['src']) : '' ?>"
                        alt="Kiemelt kép előnézet"
                        decoding="async"
                        <?= $cmsCoverPreview['src'] === '' ? 'hidden' : '' ?>
                    >
                </button>
                <div class="events-edit-sidebar-cover__placeholder" id="cmspics-cover-placeholder"<?= $cmsCoverPreview['source'] !== 'none' ? ' hidden' : '' ?>>Nincs kiemelt kép</div>
                <div class="events-edit-sidebar-cover__toolbar">
                    <button type="button" class="events-edit-sidebar-cover__tool" id="cmspics-cover-gallery" title="Galéria" aria-label="Kép választása a galériában">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                    </button>
                    <button type="button" class="events-edit-sidebar-cover__tool" id="cmspics-cover-upload" title="Feltöltés" aria-label="Kép feltöltése és beállítása">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 8l4-4 4 4"/><path stroke-linecap="round" d="M4 20h16"/></svg>
                    </button>
                </div>
            </div>
        </div>
        <span id="cmspics-summary-name" class="visually-hidden"><?= $cmsCoverPreview['label'] !== '' ? h($cmsCoverPreview['label']) : '' ?></span>
        <span id="cmspics-summary-source" class="visually-hidden"><?= $cmsCoverCaption !== '' ? h($cmsCoverCaption) : '' ?></span>
    </div>

    <div class="form-group" style="margin-top:1rem;margin-bottom:0;">
        <label for="cms-featured">Kiemelt kép URL</label>
        <input type="text" id="cms-featured" name="featured_image_url" maxlength="500" value="<?= h((string) ($p['featured_image_url'] ?? '')) ?>" placeholder="https://… vagy /nextgen/cms/uploads/…" spellcheck="false" autocomplete="off">
        <p class="help">Külső URL, vagy a galériából választott CMS kép útvonala.</p>
    </div>

    <input type="hidden" name="featured_image_pick" id="cms_featured_image_pick" value="<?= h($cmsPicsPick) ?>">
    <div class="eventpics-form-summary" id="cmspics-form-summary" data-base="<?= h($cmsUploadsBase) ?>">
        <div class="eventpics-form-summary__inner eventpics-form-summary__inner--toolbar">
            <p class="eventpics-form-summary__empty help" id="cmspics-summary-empty"<?= $cmsCoverPreview['source'] !== 'none' ? ' hidden' : '' ?>>Nincs kiemelt kép (adj meg URL-t vagy válassz a galériából).</p>
            <div class="eventpics-form-summary__actions">
                <button type="button" class="btn btn-secondary" id="cmspics-clear-main">Kiemelt kép törlése</button>
            </div>
        </div>
    </div>
</div>

<dialog class="event-edit-cover-lightbox" id="cms-edit-cover-lightbox" aria-label="Kiemelt kép">
    <button type="button" class="event-edit-cover-lightbox__close" id="cms-edit-cover-lightbox-close" aria-label="Bezárás">×</button>
    <div class="event-edit-cover-lightbox__stage">
        <img
            class="event-edit-cover-lightbox__img"
            id="cms-edit-cover-lightbox-img"
            src="<?= $cmsCoverPreview['src'] !== '' ? h($cmsCoverPreview['src']) : '' ?>"
            alt="Kiemelt kép"
            decoding="async"
        >
    </div>
</dialog>

<dialog class="eventpics-modal" id="cmspics-modal" aria-labelledby="cmspics-modal-title">
    <div class="eventpics-modal__inner">
        <header class="eventpics-modal__header">
            <h2 class="eventpics-modal__title" id="cmspics-modal-title">CMS képek (összes feltöltés)</h2>
            <button type="button" class="eventpics-modal__x" id="cmspics-modal-x" aria-label="Bezárás">×</button>
        </header>
        <div
            class="eventpics-browser"
            id="cmspics-browser"
            data-upload-url="<?= h(cms_url('ajax_gallery_upload.php')) ?>"
            data-csrf="<?= h(csrf_token('cms_gallery')) ?>"
        >
            <div class="eventpics-toolbar">
                <button type="button" class="btn btn-primary" id="cmspics-btn-upload">Kép feltöltése</button>
                <button type="button" class="btn btn-secondary" id="cmspics-btn-clear">Nincs kiválasztva</button>
                <input type="search" class="eventpics-filter" id="cmspics-filter" placeholder="Szűrés fájlnév szerint…" autocomplete="off" spellcheck="false">
                <span class="eventpics-msg" id="cmspics-msg" role="status"></span>
            </div>
            <div class="eventpics-dropzone" id="cmspics-dropzone" tabindex="-1">
                <div class="eventpics-grid" id="cmspics-grid">
                    <?php if ($cmsPicFiles === []): ?>
                        <p class="help" id="cmspics-grid-empty">Még nincs feltöltött kép. Tölts fel egyet.</p>
                    <?php else: ?>
                        <?php foreach ($cmsPicFiles as $picFile): ?>
                            <button
                                type="button"
                                class="eventpics-item<?= $picFile === $cmsPicsPick ? ' is-selected' : '' ?>"
                                data-filename="<?= h($picFile) ?>"
                                title="<?= h($picFile) ?>"
                            >
                                <span class="eventpics-item-check" aria-hidden="true"></span>
                                <img src="<?= h(cms_uploads_build_web_path($picFile)) ?>" alt="" loading="lazy" width="150" height="150">
                            </button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <input type="file" id="cmspics-file-input" class="visually-hidden" accept="image/jpeg,image/png,image/webp,image/gif">
        </div>
        <footer class="eventpics-modal__footer">
            <button type="button" class="btn btn-secondary" id="cmspics-modal-cancel">Mégse</button>
            <button type="button" class="btn btn-primary" id="cmspics-modal-ok">Kiválasztás</button>
        </footer>
    </div>
</dialog>
<script>
(function () {
    var dialog = document.getElementById('cmspics-modal');
    var root = document.getElementById('cmspics-browser');
    var hidden = document.getElementById('cms_featured_image_pick');
    var grid = document.getElementById('cmspics-grid');
    var btnUp = document.getElementById('cmspics-btn-upload');
    var btnClear = document.getElementById('cmspics-btn-clear');
    var fileInp = document.getElementById('cmspics-file-input');
    var drop = document.getElementById('cmspics-dropzone');
    var msg = document.getElementById('cmspics-msg');
    var filter = document.getElementById('cmspics-filter');
    var btnOpen = document.getElementById('cmspics-cover-gallery');
    var btnCoverUpload = document.getElementById('cmspics-cover-upload');
    var btnClearMain = document.getElementById('cmspics-clear-main');
    var btnOk = document.getElementById('cmspics-modal-ok');
    var btnCancel = document.getElementById('cmspics-modal-cancel');
    var btnX = document.getElementById('cmspics-modal-x');
    var summary = document.getElementById('cmspics-form-summary');
    var sumPreview = document.getElementById('cmspics-summary-preview');
    var sumImg = document.getElementById('cmspics-summary-img');
    var sumName = document.getElementById('cmspics-summary-name');
    var sumSource = document.getElementById('cmspics-summary-source');
    var sumEmpty = document.getElementById('cmspics-summary-empty');
    var urlInp = document.getElementById('cms-featured');
    var coverTrigger = document.getElementById('cmspics-summary-trigger');
    var coverLightbox = document.getElementById('cms-edit-cover-lightbox');
    var coverLightboxImg = document.getElementById('cms-edit-cover-lightbox-img');
    var coverLightboxClose = document.getElementById('cms-edit-cover-lightbox-close');
    var coverPlaceholder = document.getElementById('cmspics-cover-placeholder');
    if (!dialog || !root || !hidden || !grid || !btnUp || !btnClear || !fileInp || !drop || !btnOpen || !btnCoverUpload || !btnOk || !btnCancel) return;

    var pendingPick = (hidden.value || '').trim();
    var directUploadApply = false;

    function setMsg(t, isErr) {
        if (!msg) return;
        msg.textContent = t || '';
        msg.classList.toggle('eventpics-msg--error', !!isErr);
    }

    function syncModalSelection() {
        var cur = (pendingPick || '').trim();
        grid.querySelectorAll('.eventpics-item').forEach(function (b) {
            b.classList.toggle('is-selected', cur !== '' && b.getAttribute('data-filename') === cur);
        });
    }

    function pickInModal(name) {
        var fn = (name || '').trim();
        if (fn && pendingPick === fn) {
            pendingPick = '';
        } else {
            pendingPick = fn;
        }
        syncModalSelection();
    }

    function summaryBase() {
        var b = (summary && summary.getAttribute('data-base')) || '';
        if (b && b.slice(-1) !== '/') return b + '/';
        return b;
    }

    function resolveCoverImgSrcFromUrlField(urlTrim) {
        var t = (urlTrim || '').trim();
        if (!t) return '';
        if (/^https?:\/\//i.test(t)) return t;
        if (t.indexOf('//') === 0) return (window.location.protocol || 'https:') + t;
        if (t.charAt(0) === '/') {
            try { return new URL(t, window.location.origin).href; } catch (e1) { return t; }
        }
        return t;
    }

    function truncateLabel(s, maxLen) {
        var t = (s || '').trim();
        if (!t) return '';
        if (t.length <= maxLen) return t;
        return t.slice(0, Math.max(0, maxLen - 1)) + '…';
    }

    function isCmsUploadsUrl(val) {
        var t = (val || '').trim();
        if (!t) return false;
        try {
            var path = t.indexOf('://') !== -1 ? (new URL(t, window.location.origin)).pathname : t;
            return /\/nextgen\/cms\/uploads\//i.test(path);
        } catch (e0) {
            return /\/nextgen\/cms\/uploads\//i.test(t);
        }
    }

    function syncMainSummary() {
        var urlTrim = urlInp ? (urlInp.value || '').trim() : '';
        var fn = (hidden.value || '').trim();
        if (!sumPreview || !sumImg || !sumName || !sumEmpty) return;
        var src = '';
        var nameText = '';
        if (urlTrim) {
            src = resolveCoverImgSrcFromUrlField(urlTrim);
            nameText = truncateLabel(urlTrim, 52);
        } else if (fn) {
            src = summaryBase() + encodeURIComponent(fn);
            nameText = fn;
        }
        if (src === '') {
            sumEmpty.hidden = false;
            sumImg.removeAttribute('src');
            sumImg.hidden = true;
            if (coverPlaceholder) coverPlaceholder.hidden = false;
            sumName.textContent = '';
            if (sumSource) sumSource.textContent = '';
            if (coverTrigger) coverTrigger.disabled = true;
            if (coverLightboxImg) {
                coverLightboxImg.removeAttribute('src');
                coverLightboxImg.alt = 'Kiemelt kép';
            }
            return;
        }
        sumEmpty.hidden = true;
        sumImg.hidden = false;
        if (coverPlaceholder) coverPlaceholder.hidden = true;
        sumImg.src = src;
        sumName.textContent = nameText;
        if (sumSource) {
            sumSource.textContent = urlTrim
                ? 'Előnézet a „Kiemelt kép URL” mező alapján.'
                : 'Előnézet a CMS képtárból.';
        }
        if (coverTrigger) coverTrigger.disabled = false;
        if (coverLightboxImg) {
            coverLightboxImg.src = src;
            coverLightboxImg.alt = nameText || 'Kiemelt kép';
        }
    }

    function openCoverLightbox() {
        if (!coverLightbox || !sumImg || !sumImg.getAttribute('src')) return;
        if (coverLightboxImg && sumImg.src) {
            coverLightboxImg.src = sumImg.src;
            coverLightboxImg.alt = sumName ? (sumName.textContent || 'Kiemelt kép') : 'Kiemelt kép';
        }
        if (typeof coverLightbox.showModal === 'function') coverLightbox.showModal();
        else coverLightbox.setAttribute('open', 'open');
        document.body.classList.add('event-edit-cover-lightbox-open');
    }

    function closeCoverLightbox() {
        if (!coverLightbox) return;
        if (typeof coverLightbox.close === 'function') coverLightbox.close();
        else coverLightbox.removeAttribute('open');
        document.body.classList.remove('event-edit-cover-lightbox-open');
    }

    if (coverTrigger) coverTrigger.addEventListener('click', openCoverLightbox);
    if (coverLightboxClose) coverLightboxClose.addEventListener('click', closeCoverLightbox);
    if (coverLightbox) {
        coverLightbox.addEventListener('click', function (e) {
            if (e.target === coverLightbox) closeCoverLightbox();
        });
        coverLightbox.addEventListener('close', function () {
            document.body.classList.remove('event-edit-cover-lightbox-open');
        });
        coverLightbox.addEventListener('cancel', function (e) {
            e.preventDefault();
            closeCoverLightbox();
        });
    }

    function hasThumb(filename) {
        return Array.prototype.some.call(grid.querySelectorAll('.eventpics-item'), function (b) {
            return (b.getAttribute('data-filename') || '') === filename;
        });
    }

    function addThumb(filename, imgUrl) {
        if (hasThumb(filename)) return;
        var emptyHint = document.getElementById('cmspics-grid-empty');
        if (emptyHint) emptyHint.remove();
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'eventpics-item';
        btn.setAttribute('data-filename', filename);
        btn.title = filename;
        var chk = document.createElement('span');
        chk.className = 'eventpics-item-check';
        chk.setAttribute('aria-hidden', 'true');
        var img = document.createElement('img');
        img.src = imgUrl;
        img.alt = '';
        img.loading = 'lazy';
        img.width = 150;
        img.height = 150;
        btn.appendChild(chk);
        btn.appendChild(img);
        grid.insertBefore(btn, grid.firstChild);
        bindItem(btn);
    }

    function bindItem(btn) {
        btn.addEventListener('click', function () {
            var fn = btn.getAttribute('data-filename') || '';
            if (!fn) return;
            pickInModal(fn);
        });
    }
    grid.querySelectorAll('.eventpics-item').forEach(bindItem);

    btnClear.addEventListener('click', function () {
        pendingPick = '';
        syncModalSelection();
        setMsg('');
    });

    function applyPickToForm(filename) {
        var fn = (filename || '').trim();
        hidden.value = fn;
        pendingPick = fn;
        if (urlInp) {
            var urlTrim = (urlInp.value || '').trim();
            if (fn !== '' || isCmsUploadsUrl(urlTrim)) {
                urlInp.value = fn ? (summaryBase() + encodeURIComponent(fn)) : '';
            }
        }
        syncModalSelection();
        syncMainSummary();
    }

    btnUp.addEventListener('click', function () {
        directUploadApply = false;
        fileInp.click();
    });
    btnCoverUpload.addEventListener('click', function () {
        directUploadApply = true;
        fileInp.click();
    });

    if (filter) {
        filter.addEventListener('input', function () {
            var q = (filter.value || '').trim().toLowerCase();
            grid.querySelectorAll('.eventpics-item').forEach(function (b) {
                var fn = (b.getAttribute('data-filename') || '').toLowerCase();
                b.style.display = !q || fn.indexOf(q) !== -1 ? '' : 'none';
            });
        });
    }

    function uploadFiles(files) {
        var list = files ? Array.prototype.slice.call(files) : [];
        if (!list.length) return;
        if (list.length > 1) {
            setMsg('Egyszerre egy kép tölthető fel; az első kerül feldolgozásra.', false);
            list = [list[0]];
        }
        var url = root.getAttribute('data-upload-url') || '';
        var csrf = root.getAttribute('data-csrf') || '';
        if (!url || !csrf) {
            setMsg('Hiányzik a feltöltési beállítás.', true);
            return;
        }
        setMsg('Feltöltés…', false);
        var f = list[0];
        var fd = new FormData();
        fd.append('file', f, f.name);
        fd.append('cms_gallery_csrf', csrf);
        fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok) {
                    directUploadApply = false;
                    setMsg((data && data.error) ? data.error : 'Feltöltés sikertelen.', true);
                    return;
                }
                addThumb(data.filename, data.thumb_url || data.url);
                pendingPick = data.filename;
                syncModalSelection();
                fileInp.value = '';
                if (filter) {
                    filter.value = '';
                    filter.dispatchEvent(new Event('input', { bubbles: true }));
                }
                if (directUploadApply) {
                    directUploadApply = false;
                    applyPickToForm(data.filename);
                    setMsg('');
                    return;
                }
                setMsg('Feltöltve. Nyomd meg a „Kiválasztás” gombot a mentéshez.', false);
            })
            .catch(function () {
                directUploadApply = false;
                setMsg('Hálózati hiba a feltöltéskor.', true);
            });
    }

    fileInp.addEventListener('change', function () {
        if (!fileInp.files || !fileInp.files.length) {
            directUploadApply = false;
            return;
        }
        uploadFiles(fileInp.files);
    });

    ;['dragenter', 'dragover'].forEach(function (ev) {
        drop.addEventListener(ev, function (e) {
            e.preventDefault();
            e.stopPropagation();
            drop.classList.add('eventpics-dropzone--active');
        });
    });
    ;['dragleave', 'drop'].forEach(function (ev) {
        drop.addEventListener(ev, function (e) {
            e.preventDefault();
            e.stopPropagation();
            drop.classList.remove('eventpics-dropzone--active');
        });
    });
    drop.addEventListener('drop', function (e) {
        var dt = e.dataTransfer;
        if (!dt || !dt.files || !dt.files.length) return;
        uploadFiles([dt.files[0]]);
    });

    function openModal() {
        pendingPick = (hidden.value || '').trim();
        syncModalSelection();
        setMsg('');
        if (filter) {
            filter.value = '';
            filter.dispatchEvent(new Event('input', { bubbles: true }));
        }
        if (typeof dialog.showModal === 'function') dialog.showModal();
        else dialog.setAttribute('open', 'open');
        try { filter.focus(); } catch (e2) {}
    }

    function closeModal() {
        if (typeof dialog.close === 'function') dialog.close();
        else dialog.removeAttribute('open');
    }

    btnOpen.addEventListener('click', openModal);
    if (btnX) btnX.addEventListener('click', closeModal);
    btnCancel.addEventListener('click', closeModal);
    btnOk.addEventListener('click', function () {
        applyPickToForm(pendingPick);
        closeModal();
    });
    if (btnClearMain) {
        btnClearMain.addEventListener('click', function () {
            hidden.value = '';
            pendingPick = '';
            if (urlInp) urlInp.value = '';
            syncModalSelection();
            syncMainSummary();
        });
    }

    if (urlInp) {
        urlInp.addEventListener('input', function () {
            var urlTrim = (urlInp.value || '').trim();
            if (urlTrim && !isCmsUploadsUrl(urlTrim)) {
                hidden.value = '';
                pendingPick = '';
                syncModalSelection();
            } else if (isCmsUploadsUrl(urlTrim)) {
                // keep pick in sync if user pastes cms path
            }
            syncMainSummary();
        });
        urlInp.addEventListener('change', syncMainSummary);
    }

    syncMainSummary();
})();
</script>
