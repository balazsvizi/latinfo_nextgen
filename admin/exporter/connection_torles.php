<?php
/**
 * Exporter – kapcsolat törlése
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireSuperadmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/admin/exporter/connections.php');
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Érvénytelen kapcsolat.');
    redirect(BASE_URL . '/admin/exporter/connections.php');
}

$db = getDb();
$stmt = $db->prepare('DELETE FROM exporter_connections WHERE id = ?');
$stmt->execute([$id]);
if ($stmt->rowCount()) {
    flash('success', 'Kapcsolat törölve.');
}
redirect(BASE_URL . '/admin/exporter/connections.php');
