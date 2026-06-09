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
            <img class="events-cal-preview__img" id="events-cal-preview-img" src="" alt="" width="640" height="360" decoding="async">
        </div>
        <div class="events-cal-preview__body">
            <p class="events-cal-preview__meta" id="events-cal-preview-meta"></p>
            <h2 class="events-cal-preview__title" id="events-cal-preview-title"></h2>
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
            <div class="events-cal-preview__cats" id="events-cal-preview-cats" hidden></div>
            <a class="events-cal-preview__cta" id="events-cal-preview-cta" href="#"><?= h((string) ($D['cal_preview_details'] ?? 'Részletek')) ?></a>
        </div>
    </div>
</dialog>
<script type="application/json" id="events-cal-preview-data"><?= json_encode($calendarPreviewById, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script>
(function () {
    var dialog = document.getElementById('events-cal-preview');
    var dataEl = document.getElementById('events-cal-preview-data');
    if (!dialog || !dataEl) return;

    var previewMap = {};
    try {
        previewMap = JSON.parse(dataEl.textContent || '{}');
    } catch (e) {
        return;
    }

    var titleEl = document.getElementById('events-cal-preview-title');
    var metaEl = document.getElementById('events-cal-preview-meta');
    var mediaEl = document.getElementById('events-cal-preview-media');
    var imgEl = document.getElementById('events-cal-preview-img');
    var venueWrap = document.getElementById('events-cal-preview-venue-wrap');
    var venueEl = document.getElementById('events-cal-preview-venue');
    var orgWrap = document.getElementById('events-cal-preview-organizer-wrap');
    var orgEl = document.getElementById('events-cal-preview-organizer');
    var catsEl = document.getElementById('events-cal-preview-cats');
    var ctaEl = document.getElementById('events-cal-preview-cta');
    var closeBtn = document.getElementById('events-cal-preview-close');
    if (!titleEl || !metaEl || !mediaEl || !imgEl || !venueWrap || !venueEl || !orgWrap || !orgEl || !catsEl || !ctaEl) return;

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

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

    function openPreview(id) {
        var data = previewMap[String(id)] || previewMap[id];
        if (!data) return;

        titleEl.textContent = data.name || '';
        var metaParts = [];
        if (data.date) metaParts.push(data.date);
        else if (data.time) metaParts.push(data.time);
        metaEl.textContent = metaParts.join(' · ');
        metaEl.hidden = metaParts.length === 0;

        if (data.image) {
            imgEl.src = data.image;
            imgEl.alt = data.name || '';
            mediaEl.hidden = false;
        } else {
            imgEl.removeAttribute('src');
            imgEl.alt = '';
            mediaEl.hidden = true;
        }

        setVisible(venueWrap, venueEl, data.venue || '');
        setVisible(orgWrap, orgEl, data.organizer || '');

        catsEl.innerHTML = '';
        var cats = Array.isArray(data.categories) ? data.categories : [];
        if (cats.length > 0) {
            catsEl.hidden = false;
            cats.forEach(function (cat) {
                var span = document.createElement('span');
                span.className = 'events-cal-preview__cat';
                var color = (cat.color || '#6d8f63').trim();
                if (/^#[0-9A-Fa-f]{6}$/.test(color)) {
                    span.style.setProperty('--cal-preview-cat', color);
                }
                span.textContent = cat.name || '';
                catsEl.appendChild(span);
            });
        } else {
            catsEl.hidden = true;
        }

        ctaEl.href = data.url || '#';
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
