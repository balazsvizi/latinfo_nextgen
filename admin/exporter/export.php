<?php
/**
 * SQL futtatása és CSV letöltés
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

requireLogin();
requireSuperadmin();

// Csak SELECT engedélyezett, egy utasítás (végén lévő ; elfogadott)
function onlySelect(string $sql): bool {
    $sql = rtrim(trim($sql), ";");
    $t = preg_replace('/\s+/', ' ', $sql);
    return preg_match('/^\s*SELECT\s+/i', $t) === 1 && strpos($t, ';') === false;
}

$sql = '';
if (isset($_POST['query_sql']) && is_string($_POST['query_sql'])) {
    $sql = trim($_POST['query_sql']);
} elseif (isset($_GET['id']) && (int) $_GET['id'] > 0) {
    $db = getDb();
    $stmt = $db->prepare('SELECT query_sql FROM exporter_queries WHERE id = ?');
    $stmt->execute([(int) $_GET['id']]);
    $row = $stmt->fetch();
    if ($row) {
        $sql = trim($row['query_sql']);
    }
} elseif (isset($_GET['sql']) && is_string($_GET['sql'])) {
    $sql = trim($_GET['sql']);
}

if ($sql === '' || !onlySelect($sql)) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(400);
    echo $sql === '' ? 'Nincs megadva lekérdezés.' : 'Csak SELECT lekérdezés engedélyezett.';
    exit;
}

$sql = rtrim(trim($sql), ";");

$connectionId = isset($_POST['connection_id']) && $_POST['connection_id'] !== '' ? (int) $_POST['connection_id'] : null;

// PDO: alapértelmezett config vagy mentett kapcsolat
if ($connectionId === null || $connectionId <= 0) {
    $db = getDb();
} else {
    require_once __DIR__ . '/../../includes/email.php';
    $appDb = getDb();
    $stmt = $appDb->prepare('SELECT host, port, dbname, felhasználó, jelszó_titkosított FROM exporter_connections WHERE id = ?');
    $stmt->execute([$connectionId]);
    $row = $stmt->fetch();
    if (!$row) {
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(500);
        echo 'Kapcsolat nem található.';
        exit;
    }
    $jelszo = !empty($row['jelszó_titkosított']) ? email_jelszo_visszafejt($row['jelszó_titkosított']) : '';
    $dsn = 'mysql:host=' . $row['host'] . ';port=' . (int) $row['port'] . ';dbname=' . $row['dbname'] . ';charset=utf8mb4';
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $db = new PDO($dsn, $row['felhasználó'], $jelszo, $opts);
}
try {
    $stmt = $db->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    echo 'Hiba: ' . $e->getMessage();
    exit;
}

// Fájlnév: lekérdezés neve + timestamp (üres/érvénytelen név esetén "export")
$queryName = '';
if (isset($_GET['id']) && (int) $_GET['id'] > 0) {
    $appDb = getDb();
    $nameStmt = $appDb->prepare('SELECT név FROM exporter_queries WHERE id = ?');
    $nameStmt->execute([(int) $_GET['id']]);
    $n = $nameStmt->fetch();
    if ($n) {
        $queryName = trim($n['név'] ?? '');
    }
}
if ($queryName === '' && isset($_POST['query_name']) && is_string($_POST['query_name'])) {
    $queryName = trim($_POST['query_name']);
}
$safeName = preg_replace('/[^\p{L}\p{N}\s_-]/u', '', $queryName);
$safeName = preg_replace('/\s+/', '_', trim($safeName));
$safeName = mb_substr($safeName, 0, 80);
if ($safeName === '') {
    $safeName = 'export';
}
$filename = $safeName . '_' . date('Y-m-d_H-i') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

$out = fopen('php://output', 'w');
fprintf($out, "\xEF\xBB\xBF"); // UTF-8 BOM

if (count($rows) > 0) {
    fputcsv($out, array_keys($rows[0]), ';');
    foreach ($rows as $row) {
        fputcsv($out, $row, ';');
    }
}

fclose($out);
exit;
