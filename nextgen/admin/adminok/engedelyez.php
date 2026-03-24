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
$db->prepare('UPDATE adminok SET aktív = 1 WHERE id = ?')->execute([$id]);
rendszer_log('admin', $id, 'Engedélyezve', null);
flash('success', 'Admin engedélyezve.');
redirect(nextgen_url('admin/adminok/'));
