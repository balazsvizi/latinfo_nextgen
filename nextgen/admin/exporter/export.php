<?php
/**
 * SQL futtatása és CSV letöltés
 */
require_once __DIR__ . '/../../../nextgen/core/config.php';
require_once __DIR__ . '/../../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../../nextgen/core/database.php';
require_once __DIR__ . '/../../../nextgen/includes/functions.php';

requireLogin();
requireSuperadmin();

/**
 * Csak egyetlen, „tiszta” SELECT — tiltott: több utasítás, INTO OUTFILE, procedure hívás, stb.
 */
function exporter_only_safe_select(string $sql): bool
{
    $sql = rtrim(trim($sql), ";");
    if ($sql === '' || str_contains($sql, ';')) {
        return false;
    }
    if (preg_match('/^\s*SELECT\s+/i', $sql) !== 1) {
        return false;
    }
    $blocked = '/\b(INTO\s+OUTFILE|INTO\s+DUMPFILE|LOAD_FILE\s*\(|SLEEP\s*\(|BENCHMARK\s*\(|FOR\s+UPDATE|INFORMATION_SCHEMA\.|PERFORMANCE_SCHEMA\.|mysql\.|sys\.)\b/i';
    if (preg_match($blocked, $sql) === 1) {
        return false;
    }

    return true;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(405);
    echo 'Csak POST engedélyezett.';
    exit;
}

if (!csrf_validate('admin_exporter_export')) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(403);
    echo 'Érvénytelen biztonsági token.';
    exit;
}

$sql = '';
if (isset($_POST['query_sql']) && is_string($_POST['query_sql'])) {
    $sql = trim($_POST['query_sql']);
} elseif (isset($_POST['id']) && (int) $_POST['id'] > 0) {
    $db = getDb();
    $stmt = $db->prepare('SELECT query_sql FROM nextgen_exporter_queries WHERE id = ?');
    $stmt->execute([(int) $_POST['id']]);
    $row = $stmt->fetch();
    if ($row) {
        $sql = trim((string) ($row['query_sql'] ?? ''));
    }
}

if ($sql === '' || !exporter_only_safe_select($sql)) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(400);
    echo $sql === '' ? 'Nincs megadva lekérdezés.' : 'Csak biztonságos SELECT lekérdezés engedélyezett.';
    exit;
}

$sql = rtrim(trim($sql), ";");

$connectionId = isset($_POST['connection_id']) && $_POST['connection_id'] !== '' ? (int) $_POST['connection_id'] : null;

if ($connectionId === null || $connectionId <= 0) {
    $db = getDb();
} else {
    require_once __DIR__ . '/../../../nextgen/includes/email.php';
    $appDb = getDb();
    $stmt = $appDb->prepare('SELECT host, port, dbname, felhasználó, jelszó_titkosított FROM nextgen_exporter_connections WHERE id = ?');
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
    error_log('exporter export: ' . $e->getMessage());
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    echo 'A lekérdezés futtatása sikertelen.';
    exit;
}

$queryName = '';
if (isset($_POST['id']) && (int) $_POST['id'] > 0) {
    $appDb = getDb();
    $nameStmt = $appDb->prepare('SELECT név FROM nextgen_exporter_queries WHERE id = ?');
    $nameStmt->execute([(int) $_POST['id']]);
    $n = $nameStmt->fetch();
    if ($n) {
        $queryName = trim($n['név'] ?? '');
    }
}
if ($queryName === '' && isset($_POST['query_name']) && is_string($_POST['query_name'])) {
    $queryName = trim($_POST['query_name']);
}
$safeName = preg_replace('/[^\p{L}\p{N}\s_-]/u', '', $queryName);
$safeName = preg_replace('/\s+/', '_', trim((string) $safeName));
$safeName = mb_substr((string) $safeName, 0, 80);
if ($safeName === '') {
    $safeName = 'export';
}
$filename = $safeName . '_' . date('Y-m-d_H-i') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

$out = fopen('php://output', 'w');
fprintf($out, "\xEF\xBB\xBF");

if (count($rows) > 0) {
    fputcsv($out, array_keys($rows[0]), ';');
    foreach ($rows as $row) {
        fputcsv($out, $row, ';');
    }
}

fclose($out);
exit;
