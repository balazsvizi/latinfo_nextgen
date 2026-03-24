<?php
/**
 * Exporter – kapcsolat törlése
 */
require_once __DIR__ . '/../../../nextgen/core/config.php';
require_once __DIR__ . '/../../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../../nextgen/core/database.php';
require_once __DIR__ . '/../../../nextgen/includes/functions.php';

requireLogin();
requireSuperadmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(nextgen_url('admin/exporter/connections.php'));
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Érvénytelen kapcsolat.');
    redirect(nextgen_url('admin/exporter/connections.php'));
}

$db = getDb();
$stmt = $db->prepare('DELETE FROM exporter_connections WHERE id = ?');
$stmt->execute([$id]);
if ($stmt->rowCount()) {
    flash('success', 'Kapcsolat törölve.');
}
redirect(nextgen_url('admin/exporter/connections.php'));
