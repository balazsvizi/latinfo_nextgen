<?php
declare(strict_types=1);

/**
 * JSON: névből egyedi DJ slug (szerkesztés: exclude_id = saját rekord; új: 0).
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/slug.php';
requireLogin();

header('Content-Type: application/json; charset=UTF-8');

$id = (int) ($_GET['exclude_id'] ?? $_POST['exclude_id'] ?? 0);
$name = trim((string) ($_GET['name'] ?? $_POST['name'] ?? ''));

if ($name === '') {
    echo json_encode(['ok' => false, 'error' => 'A név üres.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$db = getDb();
events_tags_ensure_slug_column($db);
if (!events_tags_slug_column_available($db)) {
    echo json_encode(['ok' => false, 'error' => 'A slug oszlop nem elérhető.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($id > 0) {
    $st = $db->prepare('SELECT 1 FROM `events_tags` WHERE `id` = ? LIMIT 1');
    $st->execute([$id]);
    if (!$st->fetchColumn()) {
        echo json_encode(['ok' => false, 'error' => 'Nem található DJ.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$base = events_dj_slugify($name);
$slug = events_ensure_unique_tag_slug($db, $base, $id > 0 ? $id : null);
echo json_encode(['ok' => true, 'slug' => $slug], JSON_UNESCAPED_UNICODE);
