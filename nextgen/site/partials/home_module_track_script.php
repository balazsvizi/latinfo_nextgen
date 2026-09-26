<?php
declare(strict_types=1);

/**
 * Kezdőoldal modul kattintás-követő script.
 *
 * @var string $lang
 * @var bool $lhModuleTrackAllowed
 * @var string $homeSurface
 */
$lhModuleTrackAllowed = !empty($lhModuleTrackAllowed);
$lang = (($lang ?? 'hu') === 'en') ? 'en' : 'hu';
$homeSurface = latinfo_home_normalize_surface($homeSurface ?? 'web');
if (!$lhModuleTrackAllowed) {
    return;
}
$trackUrl = nextgen_url('site/ajax_module_click.php');
?>
<script>
(function () {
    var trackUrl = <?= json_encode($trackUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var lang = <?= json_encode($lang, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var surface = <?= json_encode($homeSurface, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    if (!trackUrl) return;

    function track(moduleKey, itemKey, itemLabel) {
        if (!moduleKey) return;
        var body = new FormData();
        body.append('module_key', moduleKey);
        body.append('lang', lang);
        body.append('surface', surface || 'web');
        if (itemKey) body.append('item_key', itemKey);
        if (itemLabel) body.append('item_label', itemLabel);
        if (navigator.sendBeacon) {
            navigator.sendBeacon(trackUrl, body);
            return;
        }
        fetch(trackUrl, { method: 'POST', body: body, keepalive: true }).catch(function () {});
    }

    document.addEventListener('click', function (e) {
        var target = e.target;
        if (!target || typeof target.closest !== 'function') return;
        var el = target.closest('[data-lh-module-track]');
        if (!el) return;
        track(
            el.getAttribute('data-lh-module-track') || '',
            el.getAttribute('data-lh-item-key') || '',
            el.getAttribute('data-lh-item-label') || ''
        );
    });
})();
</script>
