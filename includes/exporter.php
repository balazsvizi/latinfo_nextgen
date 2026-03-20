<?php
/**
 * Exporter – adatbázis kapcsolat segédfüggvények
 */

if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/../config/config.php';
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/email.php';

/**
 * PDO példány a megadott kapcsolathoz.
 * $connectionId null, 0 vagy '' esetén az alkalmazás alapértelmezett DB-je (config).
 */
function getExporterPdo(?int $connectionId = null): PDO {
    if ($connectionId === null || $connectionId <= 0) {
        return getDb();
    }
    $db = getDb();
    $stmt = $db->prepare('SELECT host, port, dbname, felhasználó, jelszó_titkosított FROM exporter_connections WHERE id = ?');
    $stmt->execute([$connectionId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Kapcsolat nem található.');
    }
    $jelszo = !empty($row['jelszó_titkosított']) ? email_jelszo_visszafejt($row['jelszó_titkosított']) : '';
    $dsn = 'mysql:host=' . $row['host'] . ';port=' . (int) $row['port'] . ';dbname=' . $row['dbname'] . ';charset=utf8mb4';
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    return new PDO($dsn, $row['felhasználó'], $jelszo, $opts);
}
