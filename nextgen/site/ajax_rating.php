<?php
declare(strict_types=1);

/**
 * Latinfo.hu kezdőoldal 5 csillagos értékelés.
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

if (!rate_limit_allow(rate_limit_client_key('lh_rating'), 30, 60)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Túl sok kérés.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stars = filter_var($_POST['stars'] ?? 0, FILTER_VALIDATE_INT);
$stars = ($stars === false) ? 0 : (int) $stars;
$lang = strtolower(trim((string) ($_POST['lang'] ?? 'hu'))) === 'en' ? 'en' : 'hu';

try {
    $db = getDb();
    if (!latinfo_home_modules_ensure_schema($db)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Schema hiba.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $result = latinfo_home_rating_submit($db, $stars, $lang);
    $status = $result['ok'] ? 200 : 400;
    http_response_code($status);
    echo json_encode([
        'ok' => $result['ok'],
        'recorded' => $result['recorded'],
        'average' => $result['average'],
        'count' => $result['count'],
        'message' => $result['message'],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('ajax_rating: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Mentés sikertelen.'], JSON_UNESCAPED_UNICODE);
}
