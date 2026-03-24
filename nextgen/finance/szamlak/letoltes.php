<?php
require_once __DIR__ . '/../../../nextgen/core/config.php';
require_once __DIR__ . '/../../../nextgen/core/database.php';
require_once __DIR__ . '/../../../nextgen/includes/auth.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('HTTP/1.0 404 Not Found');
    exit;
}
$db = getDb();
$f = $db->prepare('SELECT * FROM számla_fájlok WHERE id = ?');
$f->execute([$id]);
$f = $f->fetch();
if (!$f) {
    header('HTTP/1.0 404 Not Found');
    exit;
}
$path = UPLOAD_PATH . '/' . $f['fájl_útvonal'];
if (!is_file($path)) {
    header('HTTP/1.0 404 Not Found');
    exit;
}
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($f['eredeti_név']) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
