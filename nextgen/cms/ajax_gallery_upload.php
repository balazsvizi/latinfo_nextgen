<?php
declare(strict_types=1);

/**
 * CMS képgaléria feltöltés (esemény eventpics mintájára).
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';

requireLogin();

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Csak POST.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!csrf_validate('cms_gallery', 'cms_gallery_csrf')) {
    echo json_encode(['ok' => false, 'error' => 'Érvénytelen vagy lejárt munkamenet.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$file = $_FILES['file'] ?? null;
if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    echo json_encode(['ok' => false, 'error' => 'Nincs kiválasztott fájl.'], JSON_UNESCAPED_UNICODE);
    exit;
}

[$webPath, $err] = cms_uploads_handle_upload($file);
if ($err !== null || $webPath === null || $webPath === '') {
    echo json_encode(['ok' => false, 'error' => $err ?? 'Feltöltés sikertelen.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$filename = basename(str_replace('\\', '/', $webPath));

echo json_encode([
    'ok' => true,
    'filename' => $filename,
    'url' => $webPath,
    'thumb_url' => cms_absolute_url($webPath),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
exit;
