<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/** @var PDO|null */
$hbPdo = null;

function hb_get_db(): PDO
{
    global $hbPdo;
    if ($hbPdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $hbPdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
    }

    return $hbPdo;
}
