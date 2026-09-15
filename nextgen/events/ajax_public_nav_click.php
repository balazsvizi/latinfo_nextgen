<?php
declare(strict_types=1);

/**
 * Nyilvános menü / nézetváltó kattintás.
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/public_traffic.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Érvénytelen metódus.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!rate_limit_allow(rate_limit_client_key('public_nav_click'), 120, 60)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Túl sok kérés.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$navKey = events_public_traffic_normalize_nav_key($_POST['nav_key'] ?? '');
$pageKey = events_public_traffic_normalize_page_key($_POST['page_key'] ?? '');
$lang = events_public_traffic_normalize_lang($_POST['lang'] ?? 'hu');

if ($navKey === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Érvénytelen menüpont.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = getDb();
    events_public_traffic_nav_click($db, $navKey, $lang, $pageKey);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('ajax_public_nav_click: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Mentés sikertelen.'], JSON_UNESCAPED_UNICODE);
}
