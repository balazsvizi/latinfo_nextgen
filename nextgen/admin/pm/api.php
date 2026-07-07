<?php
declare(strict_types=1);

require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../lib/pm/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

if (!pm_tools_has_access()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Bejelentkezés szükséges.']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Csak POST engedélyezett.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw !== false ? $raw : '', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Érvénytelen JSON.']);
    exit;
}

if (!pm_tools_csrf_validate((string) ($data['csrf'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Biztonsági token érvénytelen.']);
    exit;
}

$action = (string) ($data['action'] ?? '');
if ($action !== 'save_page') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Ismeretlen művelet.']);
    exit;
}

$pageId = (int) ($data['page_id'] ?? 0);
if ($pageId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Hiányzó oldal azonosító.']);
    exit;
}

try {
    $pdo = pm_tools_db();
    $page = PmTools::getPageById($pdo, $pageId);
    if ($page === null) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Az oldal nem található.']);
        exit;
    }

    $displayName = trim((string) ($data['display_name'] ?? ''));
    $purpose = trim((string) ($data['purpose'] ?? ''));
    if ($displayName === '') {
        $displayName = basename((string) $page['php_path']);
    }
    if ($purpose === '') {
        $purpose = (string) $page['purpose'];
    }

    $noteRows = [];
    if (isset($data['notes']) && is_array($data['notes'])) {
        foreach ($data['notes'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $noteRows[] = [
                'note' => (string) ($row['note'] ?? ''),
                'response' => (string) ($row['response'] ?? ''),
            ];
        }
    }

    PmTools::updatePage($pdo, $pageId, $displayName, $purpose);
    PmTools::saveNotes($pdo, $pageId, $noteRows);

    echo json_encode([
        'ok' => true,
        'message' => 'Mentve.',
        'page' => [
            'id' => $pageId,
            'display_name' => $displayName,
            'purpose' => $purpose,
        ],
    ]);
} catch (Throwable $e) {
    error_log('PM Tools API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Mentés sikertelen.']);
}
