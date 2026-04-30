<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/nextgen/includes/auth.php';
require_once __DIR__ . '/lib/eventpics.php';

requireLogin();

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Csak POST.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!csrf_validate('events_eventpics', 'eventpics_csrf')) {
    echo json_encode(['ok' => false, 'error' => 'Érvénytelen vagy lejárt munkamenet.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$file = $_FILES['file'] ?? null;
if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    echo json_encode(['ok' => false, 'error' => 'Nincs kiválasztott fájl.'], JSON_UNESCAPED_UNICODE);
    exit;
}

[$webPath, $err] = events_eventpics_handle_upload($file);
if ($err !== null || $webPath === null) {
    echo json_encode(['ok' => false, 'error' => $err ?? 'Feltöltés sikertelen.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$filename = basename(str_replace('\\', '/', $webPath));

echo json_encode([
    'ok' => true,
    'filename' => $filename,
    'url' => $webPath,
    'thumb_url' => site_url(ltrim($webPath, '/')),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
exit;
