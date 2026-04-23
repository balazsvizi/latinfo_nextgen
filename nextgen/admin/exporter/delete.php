<?php
/**
 * Mentett lekérdezés törlése
 */
require_once __DIR__ . '/../../../nextgen/core/config.php';
require_once __DIR__ . '/../../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../../nextgen/core/database.php';
require_once __DIR__ . '/../../../nextgen/includes/functions.php';

requireLogin();
requireSuperadmin();

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($id > 0) {
    $db = getDb();
    $db->prepare('DELETE FROM nextgen_exporter_queries WHERE id = ?')->execute([$id]);
    flash('success', 'Lekérdezés törölve.');
}
redirect(nextgen_url('admin/exporter/'));
