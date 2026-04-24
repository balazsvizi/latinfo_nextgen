<?php
declare(strict_types=1);

/**
 * JSON: névből egyedi venue slug (szerkesztés: exclude_id = saját rekord).
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/nextgen/includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=UTF-8');

$id = (int) ($_GET['exclude_id'] ?? $_POST['exclude_id'] ?? 0);
$name = trim((string) ($_GET['name'] ?? $_POST['name'] ?? ''));

if ($id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Hiányzó azonosító.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$db = getDb();
$st = $db->prepare('SELECT 1 FROM `events_venues` WHERE `id` = ? LIMIT 1');
$st->execute([$id]);
if (!$st->fetchColumn()) {
    echo json_encode(['ok' => false, 'error' => 'Nem található helyszín.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($name === '') {
    echo json_encode(['ok' => false, 'error' => 'A név üres.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$base = events_slugify($name);
$slug = events_ensure_unique_venue_slug($db, $base, $id);
echo json_encode(['ok' => true, 'slug' => $slug], JSON_UNESCAPED_UNICODE);
