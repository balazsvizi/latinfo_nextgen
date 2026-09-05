<?php
declare(strict_types=1);

/**
 * Nyilvános esemény metrika (naptár előnézet, további információ kattintás).
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/event_view_tracking.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

if (!rate_limit_allow(rate_limit_client_key('event_metric'), 120, 60)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Túl sok kérés.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$eventId = (int) ($_POST['event_id'] ?? $_GET['event_id'] ?? 0);
$metric = trim((string) ($_POST['metric'] ?? $_GET['metric'] ?? ''));

if ($eventId <= 0 || $metric === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Hiányzó paraméter.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!in_array($metric, events_view_metric_types_ajax(), true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Érvénytelen metrika.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = getDb();
    if (!events_view_tracking_is_published_event($db, $eventId)) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Nem található esemény.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $source = $metric === EVENTS_VIEW_METRIC_EXTERNAL_INFO
        ? EVENTS_VIEW_SOURCE_DIRECT
        : EVENTS_VIEW_SOURCE_CALENDAR;

    events_track_event_view($db, $eventId, $metric, $source);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('ajax_event_metric: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Mentés sikertelen.'], JSON_UNESCAPED_UNICODE);
}
