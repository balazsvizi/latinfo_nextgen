<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/nextgen/includes/auth.php';
require_once __DIR__ . '/lib/entity_quick_create.php';

requireLogin();

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Csak POST.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!csrf_validate('events_entity_create', 'entity_csrf')) {
    echo json_encode(['ok' => false, 'error' => 'Érvénytelen vagy lejárt munkamenet.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$type = trim((string) ($_POST['entity_type'] ?? ''));
$name = trim((string) ($_POST['name'] ?? ''));

if ($name === '') {
    echo json_encode(['ok' => false, 'error' => 'A név kötelező.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (mb_strlen($name, 'UTF-8') > 255) {
    echo json_encode(['ok' => false, 'error' => 'A név legfeljebb 255 karakter lehet.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = getDb();
    $id = events_entity_quick_create_by_name($db, $type, $name);
    echo json_encode([
        'ok' => true,
        'id' => $id,
        'name' => $name,
        'entity_type' => $type,
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $ex) {
    echo json_encode(['ok' => false, 'error' => $ex->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $ex) {
    error_log('events ajax_entity_quick_create: ' . $ex->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Létrehozási hiba történt.'], JSON_UNESCAPED_UNICODE);
}
