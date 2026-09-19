<?php
declare(strict_types=1);

/**
 * Kezdőoldal modul kattintás (beacon / fetch).
 */
require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/events/bootstrap.php';
require_once __DIR__ . '/lib/site_modules.php';

header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Érvénytelen metódus.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!rate_limit_allow(rate_limit_client_key('lh_module_click'), 120, 60)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Túl sok kérés.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$moduleKey = trim((string) ($_POST['module_key'] ?? ''));
$itemKey = latinfo_home_module_normalize_item_key($_POST['item_key'] ?? '');
$itemLabel = latinfo_home_clamp((string) ($_POST['item_label'] ?? ''), 200);
$lang = strtolower(trim((string) ($_POST['lang'] ?? 'hu'))) === 'en' ? 'en' : 'hu';

if (!latinfo_home_module_is_valid($moduleKey)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Érvénytelen modul.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = getDb();
    if (!latinfo_home_modules_ensure_schema($db)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Schema hiba.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $result = latinfo_home_module_track_click($db, $moduleKey, $itemKey, $itemLabel, $lang);
    echo json_encode(['ok' => $result['ok'], 'recorded' => $result['recorded']], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('ajax_module_click: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Mentés sikertelen.'], JSON_UNESCAPED_UNICODE);
}
