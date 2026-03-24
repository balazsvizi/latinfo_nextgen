<?php
/**
 * Lekérdezés mentése (új vagy szerkesztés)
 */
require_once __DIR__ . '/../../../nextgen/core/config.php';
require_once __DIR__ . '/../../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../../nextgen/core/database.php';
require_once __DIR__ . '/../../../nextgen/includes/functions.php';

requireLogin();
requireSuperadmin();

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$nev = trim($_POST['nev'] ?? '');
$query_sql = trim($_POST['query_sql'] ?? '');
$connection_id = isset($_POST['connection_id']) && $_POST['connection_id'] !== '' ? (int) $_POST['connection_id'] : null;

if ($query_sql === '') {
    flash('error', 'A lekérdezés szövege kötelező.');
    redirect(nextgen_url('admin/exporter/'));
}

// Csak SELECT, egy utasítás (végén lévő ; megengedett, középen nem)
$query_sql = rtrim($query_sql, " \t\n\r\0\x0B;");
$t = preg_replace('/\s+/', ' ', $query_sql);
if (preg_match('/^\s*SELECT\s+/i', $t) !== 1 || strpos($t, ';') !== false) {
    flash('error', 'Csak SELECT lekérdezés menthető (egy utasítás, a végén lévő pontosvessző rendben).');
    redirect(nextgen_url('admin/exporter/'));
}

$db = getDb();
if ($id > 0) {
    $stmt = $db->prepare('UPDATE exporter_queries SET név = ?, query_sql = ?, connection_id = ? WHERE id = ?');
    $stmt->execute([$nev ?: 'Lekérdezés #' . $id, $query_sql, $connection_id, $id]);
    flash('success', 'Lekérdezés frissítve.');
} else {
    $stmt = $db->prepare('INSERT INTO exporter_queries (név, query_sql, connection_id) VALUES (?, ?, ?)');
    $stmt->execute([$nev ?: 'Új lekérdezés', $query_sql, $connection_id]);
    flash('success', 'Lekérdezés mentve.');
}
redirect(nextgen_url('admin/exporter/'));
