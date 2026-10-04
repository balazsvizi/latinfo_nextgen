<?php
declare(strict_types=1);

/**
 * Oldalmegtekintés beacon. Csak akkor fut, ha a böngésző végrehajtja a JS-t
 * (előtöltés / prerender addig nem számít, amíg a lap tényleg megnyílik).
 *
 * @var array{type?: string, event_id?: int, source?: string, page_key?: string, lang?: string, view_mode?: string, post_id?: int}|null $clientPageView
 */
if (!isset($clientPageView) || !is_array($clientPageView)) {
    $trafficKey = '';
    if (isset($eventsPublicTrafficPageKey) && is_string($eventsPublicTrafficPageKey) && $eventsPublicTrafficPageKey !== '') {
        if (!function_exists('events_public_traffic_normalize_page_key')) {
            require_once __DIR__ . '/../lib/public_traffic.php';
        }
        $trafficKey = events_public_traffic_normalize_page_key($eventsPublicTrafficPageKey);
    }
    if ($trafficKey !== '') {
        $viewMode = isset($eventsPublicTrafficViewMode) && is_string($eventsPublicTrafficViewMode)
            ? $eventsPublicTrafficViewMode
            : '';
        $clientPageView = [
            'type' => 'traffic',
            'page_key' => $trafficKey,
            'lang' => (isset($lang) && $lang === 'en') ? 'en' : 'hu',
            'view_mode' => $viewMode,
        ];
    }
}

$clientPageViewAllowed = isset($clientPageView) && is_array($clientPageView)
    && function_exists('events_public_visitor_metrics_allowed')
    && events_public_visitor_metrics_allowed();

if ($clientPageViewAllowed):
    $clientPageViewUrl = events_url('ajax_client_page_view.php');
    $clientPageViewJson = json_encode(
        $clientPageView,
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
?>
<script>
(function () {
    var trackUrl = <?= json_encode($clientPageViewUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var payload = <?= $clientPageViewJson !== false ? $clientPageViewJson : '{}' ?>;
    if (!trackUrl || !payload || !payload.type) return;

    function send() {
        var body = new FormData();
        Object.keys(payload).forEach(function (key) {
            if (payload[key] == null || payload[key] === '') return;
            body.append(key, String(payload[key]));
        });
        if (navigator.sendBeacon) {
            navigator.sendBeacon(trackUrl, body);
            return;
        }
        fetch(trackUrl, { method: 'POST', body: body, credentials: 'same-origin', keepalive: true }).catch(function () {});
    }

    if (document.prerendering) {
        document.addEventListener('prerenderingchange', function () {
            if (!document.prerendering) send();
        }, { once: true });
        return;
    }
    send();
})();
</script>
<?php
endif;
unset($clientPageViewAllowed, $clientPageViewUrl, $clientPageViewJson);
