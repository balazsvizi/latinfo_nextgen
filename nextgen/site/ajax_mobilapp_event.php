<?php
declare(strict_types=1);

/**
 * Mobilapp / PWA esemény (beacon / fetch) – oldal, telepítő prompt, telepítés.
 */
require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/events/bootstrap.php';
require_once __DIR__ . '/lib/mobilapp_stats.php';

header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Érvénytelen metódus.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!rate_limit_allow(rate_limit_client_key('lh_mobilapp_event'), 60, 60)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Túl sok kérés.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$eventType = latinfo_mobilapp_normalize_event($_POST['event'] ?? $_POST['event_type'] ?? '');
if ($eventType === '' || $eventType === 'page_view') {
    // page_view szerveroldalon mentődik a mobilapp oldalon
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Érvénytelen esemény.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = getDb();
    $result = latinfo_mobilapp_track_event($db, $eventType);
    echo json_encode(['ok' => $result['ok'], 'recorded' => $result['recorded']], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('ajax_mobilapp_event: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Mentés sikertelen.'], JSON_UNESCAPED_UNICODE);
}
