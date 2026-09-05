<?php
require_once __DIR__ . '/../../../nextgen/core/database.php';
require_once __DIR__ . '/../../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../../nextgen/includes/functions.php';
requireSuperadmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('error', 'Érvénytelen kérés.');
    redirect(nextgen_url('admin/adminok/'));
}
csrf_require('admin_adminok_toggle', '_csrf', nextgen_url('admin/adminok/'));

$id = (int) ($_POST['id'] ?? 0);
if (!$id) {
    flash('error', 'Hiányzó azonosító.');
    redirect(nextgen_url('admin/adminok/'));
}
$db = getDb();
$db->prepare('UPDATE nextgen_admins SET aktív = 1 WHERE id = ?')->execute([$id]);
rendszer_log('admin', $id, 'Engedélyezve', null);
flash('success', 'Admin engedélyezve.');
redirect(nextgen_url('admin/adminok/'));
