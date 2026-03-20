<?php
/**
 * Mentett lekérdezés törlése
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
requireSuperadmin();

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($id > 0) {
    $db = getDb();
    $db->prepare('DELETE FROM exporter_queries WHERE id = ?')->execute([$id]);
    flash('success', 'Lekérdezés törölve.');
}
redirect(BASE_URL . '/admin/exporter/');
