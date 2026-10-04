<?php
declare(strict_types=1);

/**
 * Oldalmegtekintés, amit a böngésző JS-e küld (GA4-hez igazítva).
 * A HTML letöltése önmagában nem számít megtekintésnek.
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/event_view_tracking.php';
require_once __DIR__ . '/lib/public_traffic.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Érvénytelen metódus.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!events_view_tracking_is_same_origin_beacon()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Érvénytelen kérés.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!rate_limit_allow(rate_limit_client_key('client_page_view'), 60, 60)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Túl sok kérés.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$type = trim((string) ($_POST['type'] ?? ''));

try {
    $db = getDb();

    if ($type === 'event') {
        $eventId = (int) ($_POST['event_id'] ?? 0);
        if ($eventId <= 0 || !events_view_tracking_is_published_event($db, $eventId)) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Nem található esemény.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $source = events_view_tracking_resolve_page_source((string) ($_POST['source'] ?? ''));
        events_track_event_view($db, $eventId, EVENTS_VIEW_METRIC_PAGE, $source);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($type === 'traffic') {
        $pageKey = events_public_traffic_normalize_page_key($_POST['page_key'] ?? '');
        if ($pageKey === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Érvénytelen oldal.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        events_public_traffic_hit(
            $db,
            $pageKey,
            events_public_traffic_normalize_lang($_POST['lang'] ?? 'hu'),
            ['view_mode' => (string) ($_POST['view_mode'] ?? '')]
        );
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($type === 'cms') {
        if (!function_exists('cms_status_publish')) {
            require_once dirname(__DIR__) . '/cms/lib/status.php';
        }
        if (!function_exists('cms_record_post_view')) {
            require_once dirname(__DIR__) . '/cms/lib/views.php';
        }
        $postId = (int) ($_POST['post_id'] ?? 0);
        if ($postId <= 0) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Nem található cikk.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $st = $db->prepare('SELECT 1 FROM `cms_posts` WHERE `id` = ? AND `status` = ? LIMIT 1');
        $st->execute([$postId, cms_status_publish()]);
        if (!$st->fetchColumn()) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Nem található cikk.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        cms_record_post_view($db, $postId, CMS_VIEW_SOURCE_DIRECT);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Érvénytelen típus.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('ajax_client_page_view: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Mentés sikertelen.'], JSON_UNESCAPED_UNICODE);
}
