<?php
declare(strict_types=1);

/**
 * TinyMCE / CMS HTML szerkesztő kép feltöltés (eventpics tár).
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/eventpics.php';

requireLogin();

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => ['message' => 'Csak POST.']], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!csrf_validate('events_cms_image', '_csrf')) {
    http_response_code(403);
    echo json_encode(['error' => ['message' => 'Érvénytelen vagy lejárt munkamenet.']], JSON_UNESCAPED_UNICODE);
    exit;
}

$file = $_FILES['file'] ?? null;
if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => 'Nincs kiválasztott fájl.']], JSON_UNESCAPED_UNICODE);
    exit;
}

[$webPath, $err] = events_eventpics_handle_upload($file);
if ($err !== null || $webPath === null || $webPath === '') {
    http_response_code(400);
    echo json_encode(['error' => ['message' => $err ?? 'Feltöltés sikertelen.']], JSON_UNESCAPED_UNICODE);
    exit;
}

$location = events_absolute_url($webPath);

echo json_encode(
    ['location' => $location],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
exit;
