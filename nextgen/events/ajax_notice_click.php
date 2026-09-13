<?php
declare(strict_types=1);

/**
 * Nyilvános főoldal fejléc-tip átkattintás.
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/event_view_tracking.php';
require_once __DIR__ . '/lib/public_home_notice_stats.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Érvénytelen metódus.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!rate_limit_allow(rate_limit_client_key('notice_click'), 60, 60)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Túl sok kérés.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$versionId = filter_var($_POST['version_id'] ?? 0, FILTER_VALIDATE_INT);
$lang = strtolower(trim((string) ($_POST['lang'] ?? 'hu')));
if ($versionId === false || $versionId < 0) {
    $versionId = 0;
}
if ($lang !== 'en') {
    $lang = 'hu';
}

try {
    $db = getDb();
    $ok = events_public_home_notice_track_click($db, (int) $versionId, $lang);
    echo json_encode(['ok' => $ok], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('ajax_notice_click: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Mentés sikertelen.'], JSON_UNESCAPED_UNICODE);
}
