<?php
declare(strict_types=1);
/**
 * Statisztika navigáció / időszakváltás közbeni betöltő overlay.
 * Egyszer jelenjen meg az oldalon (idempotens JS).
 */
?>
<div
    id="events-stats-loading"
    class="events-stats-loading"
    hidden
    aria-live="assertive"
    aria-busy="false"
>
    <div class="events-stats-loading__panel" role="status">
        <span class="events-stats-loading__spinner" aria-hidden="true"></span>
        <p class="events-stats-loading__text">Statisztika betöltése…</p>
    </div>
</div>
<script>
(function () {
    if (window.EventsStatsLoadingBound) {
        return;
    }
    window.EventsStatsLoadingBound = true;

    var STATS_PATH_RE = /\/events\/(?:events_statisztika|events_szervezok_statisztika|events_lista_stat|events_event_statisztika|events_realtime|events_stat)\.php(?:[?#]|$)/i;

    function showLoading() {
        var el = document.getElementById('events-stats-loading');
        if (!el) {
            return;
        }
        el.hidden = false;
        el.setAttribute('aria-busy', 'true');
        document.documentElement.classList.add('events-stats-loading-active');
    }

    function linkLooksLikeStatsNav(link) {
        if (!link || link.getAttribute('target') === '_blank') {
            return false;
        }
        if (link.closest('.events-edit-stats__presets')) {
            return true;
        }
        if (link.closest('#submenu-events-stat')) {
            return true;
        }
        if (link.classList.contains('nav-parent-link')) {
            var parentHref = link.getAttribute('href') || '';
            if (STATS_PATH_RE.test(parentHref) || /events_statisztika\.php/i.test(parentHref)) {
                return true;
            }
        }
        var href = link.getAttribute('href') || '';
        if (!href || href.charAt(0) === '#') {
            return false;
        }
        try {
            var url = new URL(href, window.location.href);
            return STATS_PATH_RE.test(url.pathname);
        } catch (e) {
            return STATS_PATH_RE.test(href);
        }
    }

    document.addEventListener('click', function (ev) {
        if (ev.defaultPrevented || ev.button !== 0) {
            return;
        }
        if (ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) {
            return;
        }
        var target = ev.target;
        if (!target || typeof target.closest !== 'function') {
            return;
        }
        var link = target.closest('a[href]');
        if (!linkLooksLikeStatsNav(link)) {
            return;
        }
        showLoading();
    }, true);

    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (!form || !form.classList || !form.classList.contains('events-edit-stats__filters')) {
            return;
        }
        showLoading();
    }, true);
})();
</script>
