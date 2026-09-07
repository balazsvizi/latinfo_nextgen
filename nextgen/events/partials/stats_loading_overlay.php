<?php
declare(strict_types=1);
/**
 * Statisztika időszakváltás / szűrés közbeni betöltő overlay.
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
        <span class="events-stats-loading__hourglass" aria-hidden="true"></span>
        <p class="events-stats-loading__text">Statisztika betöltése…</p>
    </div>
</div>
<script>
(function () {
    if (window.EventsStatsLoadingBound) {
        return;
    }
    window.EventsStatsLoadingBound = true;

    function showLoading() {
        var el = document.getElementById('events-stats-loading');
        if (!el) {
            return;
        }
        el.hidden = false;
        el.setAttribute('aria-busy', 'true');
        document.documentElement.classList.add('events-stats-loading-active');
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
        var link = target.closest('.events-edit-stats__presets a[href]');
        if (!link) {
            return;
        }
        if (link.getAttribute('target') === '_blank') {
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
