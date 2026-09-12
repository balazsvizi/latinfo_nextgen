<?php
declare(strict_types=1);
/** @var array<int, array<string, mixed>> $calendarPreviewById */
/** @var array<string, string> $D */
?>
<dialog class="events-cal-preview" id="events-cal-preview" aria-labelledby="events-cal-preview-title">
    <div class="events-cal-preview__sheet" role="document">
        <button type="button" class="events-cal-preview__close" id="events-cal-preview-close" aria-label="<?= h((string) ($D['cal_preview_close'] ?? 'Bezárás')) ?>">×</button>
        <div class="events-cal-preview__handle" aria-hidden="true"></div>
        <div class="events-cal-preview__media" id="events-cal-preview-media" hidden>
            <a class="events-cal-preview__media-link" id="events-cal-preview-media-link" href="#" aria-label="<?= h((string) ($D['cal_preview_details'] ?? 'Részletek')) ?>">
                <img class="events-cal-preview__img" id="events-cal-preview-img" src="" alt="" decoding="async">
            </a>
        </div>
        <div class="events-cal-preview__body">
            <div class="events-cal-preview__topline" id="events-cal-preview-topline" hidden>
                <p class="events-cal-preview__meta" id="events-cal-preview-meta"></p>
                <div class="events-cal-preview__cats" id="events-cal-preview-cats" hidden></div>
            </div>
            <div class="events-cal-preview__change" id="events-cal-preview-change" hidden>
                <div class="events-cal-preview__change-inner">
                    <span class="events-cal-preview__change-icon" id="events-cal-preview-change-icon" aria-hidden="true"></span>
                    <div class="events-cal-preview__change-copy">
                        <span class="events-cal-preview__change-badge" id="events-cal-preview-change-badge" hidden></span>
                        <p class="events-cal-preview__change-title" id="events-cal-preview-change-title"></p>
                        <p class="events-cal-preview__change-note" id="events-cal-preview-change-note"></p>
                    </div>
                </div>
            </div>
            <h2 class="events-cal-preview__title" id="events-cal-preview-title">
                <a class="events-cal-preview__title-link" id="events-cal-preview-title-link" href="#"></a>
            </h2>
            <dl class="events-cal-preview__facts">
                <div class="events-cal-preview__fact" id="events-cal-preview-venue-wrap" hidden>
                    <dt><?= h((string) ($D['cal_preview_venue'] ?? 'Helyszín')) ?></dt>
                    <dd id="events-cal-preview-venue"></dd>
                </div>
                <div class="events-cal-preview__fact" id="events-cal-preview-organizer-wrap" hidden>
                    <dt><?= h((string) ($D['cal_preview_organizer'] ?? 'Szervező')) ?></dt>
                    <dd id="events-cal-preview-organizer"></dd>
                </div>
            </dl>
            <div class="events-cal-preview__style-chips" id="events-cal-preview-styles" hidden></div>
            <a class="events-cal-preview__cta" id="events-cal-preview-cta" href="#"><?= h((string) ($D['cal_preview_details'] ?? 'Részletek')) ?></a>
        </div>
    </div>
</dialog>
<script type="application/json" id="events-cal-preview-data"><?= json_encode($calendarPreviewById, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script>
(function () {
    var trackUrl = <?= json_encode(events_url('ajax_event_metric.php'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var dialog = document.getElementById('events-cal-preview');
    var dataEl = document.getElementById('events-cal-preview-data');
    if (!dialog || !dataEl) return;

    var previewMap = {};
    try {
        previewMap = JSON.parse(dataEl.textContent || '{}');
    } catch (e) {
        return;
    }

    var titleEl = document.getElementById('events-cal-preview-title-link');
    var mediaLinkEl = document.getElementById('events-cal-preview-media-link');
    var toplineEl = document.getElementById('events-cal-preview-topline');
    var metaEl = document.getElementById('events-cal-preview-meta');
    var changeWrap = document.getElementById('events-cal-preview-change');
    var changeIconEl = document.getElementById('events-cal-preview-change-icon');
    var changeBadgeEl = document.getElementById('events-cal-preview-change-badge');
    var changeTitleEl = document.getElementById('events-cal-preview-change-title');
    var changeNoteEl = document.getElementById('events-cal-preview-change-note');
    var mediaEl = document.getElementById('events-cal-preview-media');
    var imgEl = document.getElementById('events-cal-preview-img');
    var venueWrap = document.getElementById('events-cal-preview-venue-wrap');
    var venueEl = document.getElementById('events-cal-preview-venue');
    var orgWrap = document.getElementById('events-cal-preview-organizer-wrap');
    var orgEl = document.getElementById('events-cal-preview-organizer');
    var stylesEl = document.getElementById('events-cal-preview-styles');
    var catsEl = document.getElementById('events-cal-preview-cats');
    var ctaEl = document.getElementById('events-cal-preview-cta');
    var closeBtn = document.getElementById('events-cal-preview-close');
    if (!titleEl || !mediaLinkEl || !toplineEl || !metaEl || !mediaEl || !imgEl || !venueWrap || !venueEl || !orgWrap || !orgEl || !stylesEl || !catsEl || !ctaEl) return;

    function setVisible(wrap, el, text) {
        var t = (text || '').trim();
        if (t === '') {
            wrap.hidden = true;
            el.textContent = '';
            return;
        }
        wrap.hidden = false;
        el.textContent = t;
    }

    function appendStyleChips(el, names, modifier) {
        var list = Array.isArray(names) ? names : [];
        var shown = 0;
        list.forEach(function (name) {
            var label = String(name || '').trim();
            if (label === '') return;
            var span = document.createElement('span');
            span.className = 'events-cal-preview__style events-cal-preview__style--' + modifier;
            span.textContent = label;
            el.appendChild(span);
            shown += 1;
        });
        return shown;
    }

    function fillStyles(el, mainNames, suppNames) {
        el.innerHTML = '';
        var shown = 0;
        shown += appendStyleChips(el, mainNames, 'main');
        shown += appendStyleChips(el, suppNames, 'supplementary');
        el.hidden = shown === 0;
    }

    function fillCategories(el, cats) {
        el.innerHTML = '';
        var list = Array.isArray(cats) ? cats : [];
        var shown = 0;
        list.forEach(function (cat) {
            var name = String((cat && cat.name) || '').trim();
            if (name === '') return;
            var span = document.createElement('span');
            span.className = 'events-cal-preview__cat';
            var color = String((cat && cat.color) || '#6d8f63').trim();
            if (/^#[0-9A-Fa-f]{6}$/.test(color)) {
                span.style.setProperty('--cal-preview-cat', color);
            }
            span.textContent = name;
            el.appendChild(span);
            shown += 1;
        });
        el.hidden = shown === 0;
        return shown;
    }

    function trackPreviewOpen(id) {
        if (!trackUrl || !id) return;
        var body = new FormData();
        body.append('event_id', String(id));
        body.append('metric', 'calendar_preview');
        if (navigator.sendBeacon) {
            navigator.sendBeacon(trackUrl, body);
            return;
        }
        fetch(trackUrl, { method: 'POST', body: body, keepalive: true }).catch(function () {});
    }

    function openPreview(id) {
        var data = previewMap[String(id)] || previewMap[id];
        if (!data) return;

        titleEl.textContent = data.name || '';
        var metaParts = [];
        if (data.date) metaParts.push(data.date);
        else if (data.time) metaParts.push(data.time);
        var metaText = metaParts.join(' · ');
        metaEl.textContent = metaText;
        metaEl.hidden = metaText === '';
        var catCount = fillCategories(catsEl, data.categories);
        toplineEl.hidden = metaText === '' && catCount === 0;

        if (changeWrap && changeTitleEl && changeNoteEl) {
            var change = data.change || null;
            if (change && (change.typeLabel || change.note || change.badge)) {
                changeWrap.hidden = false;
                changeWrap.className = 'events-cal-preview__change';
                if (change.type === 'cancelled') {
                    changeWrap.classList.add('events-cal-preview__change--cancelled');
                    if (changeIconEl) changeIconEl.textContent = '✕';
                } else if (change.type === 'modified') {
                    changeWrap.classList.add('events-cal-preview__change--modified');
                    if (changeIconEl) changeIconEl.textContent = '⚠';
                } else if (changeIconEl) {
                    changeIconEl.textContent = '⚠';
                }
                var badge = (change.badge || '').trim();
                if (changeBadgeEl) {
                    changeBadgeEl.textContent = badge;
                    changeBadgeEl.hidden = badge === '';
                }
                changeTitleEl.textContent = change.typeLabel || '';
                changeTitleEl.hidden = !change.typeLabel;
                var note = (change.note || '').trim();
                changeNoteEl.textContent = note;
                changeNoteEl.hidden = note === '';
            } else {
                changeWrap.hidden = true;
                if (changeIconEl) changeIconEl.textContent = '';
                if (changeBadgeEl) {
                    changeBadgeEl.textContent = '';
                    changeBadgeEl.hidden = true;
                }
                changeTitleEl.textContent = '';
                changeNoteEl.textContent = '';
            }
        }

        if (data.image) {
            imgEl.src = data.image;
            imgEl.alt = data.name || '';
            mediaEl.hidden = false;
            if (typeof window.eventsApplyImageOrientation === 'function') {
                if (imgEl.complete && imgEl.naturalWidth) {
                    window.eventsApplyImageOrientation(imgEl);
                } else {
                    imgEl.onload = function () {
                        window.eventsApplyImageOrientation(imgEl);
                    };
                }
            }
        } else {
            imgEl.removeAttribute('src');
            imgEl.alt = '';
            mediaEl.hidden = true;
        }

        setVisible(venueWrap, venueEl, data.venue || '');
        setVisible(orgWrap, orgEl, data.organizer || '');
        fillStyles(stylesEl, data.mainStyles, data.supplementaryStyles);

        var detailsUrl = data.url || '#';
        ctaEl.href = detailsUrl;
        titleEl.href = detailsUrl;
        mediaLinkEl.href = detailsUrl;
        if (data.accent && /^#[0-9A-Fa-f]{6}$/.test(data.accent)) {
            dialog.style.setProperty('--cal-preview-accent', data.accent);
        } else {
            dialog.style.removeProperty('--cal-preview-accent');
        }

        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', 'open');
        }
        document.body.classList.add('events-cal-preview-open');
        trackPreviewOpen(id);
    }

    function closePreview() {
        if (typeof dialog.close === 'function') {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
        }
        document.body.classList.remove('events-cal-preview-open');
    }

    document.addEventListener('click', function (e) {
        var link = e.target && e.target.closest ? e.target.closest('.js-cal-event-preview') : null;
        if (!link) return;
        var rawId = link.getAttribute('data-preview-id');
        if (!rawId || !previewMap[rawId] && !previewMap[String(parseInt(rawId, 10))]) return;
        e.preventDefault();
        openPreview(rawId);
    });

    if (closeBtn) closeBtn.addEventListener('click', closePreview);
    dialog.addEventListener('click', function (e) {
        if (e.target === dialog) closePreview();
    });
    dialog.addEventListener('close', function () {
        document.body.classList.remove('events-cal-preview-open');
    });
    dialog.addEventListener('cancel', function (e) {
        e.preventDefault();
        closePreview();
    });
})();
</script>
