<?php
require_once __DIR__ . '/../../../nextgen/core/database.php';
require_once __DIR__ . '/../../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../../nextgen/includes/functions.php';
requireSuperadmin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    flash('error', 'Hiányzó azonosító.');
    redirect(nextgen_url('admin/adminok/'));
}
$db = getDb();
$aktív_count = $db->query('SELECT COUNT(*) FROM adminok WHERE aktív = 1')->fetchColumn();
if ($aktív_count <= 1) {
    flash('error', 'Nem tiltható le az utolsó aktív admin.');
    redirect(nextgen_url('admin/adminok/'));
}
$db->prepare('UPDATE adminok SET aktív = 0 WHERE id = ?')->execute([$id]);
rendszer_log('admin', $id, 'Letiltva', null);
flash('success', 'Admin letiltva.');
redirect(nextgen_url('admin/adminok/'));
