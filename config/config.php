<?php
/**
 * Latinfo.hu Backoffice - Konfiguráció
 *
 * Szerverfüggő értékek forrása (prioritás szerint):
 * 1) Környezeti változó (getenv)
 * 2) config/config.local.php (giten kívüli helyi felülírás)
 * 3) Biztonságos alapértelmezés ebben a fájlban
 */

$localConfig = [];
$localConfigPath = __DIR__ . '/config.local.php';
if (is_file($localConfigPath)) {
    $tmp = require $localConfigPath;
    if (is_array($tmp)) {
        $localConfig = $tmp;
    }
}

if (!function_exists('cfg_get')) {
    function cfg_get(string $key, $default = null, array $localConfig = []) {
        $env = getenv($key);
        if ($env !== false && $env !== '') {
            return $env;
        }
        if (array_key_exists($key, $localConfig)) {
            return $localConfig[$key];
        }
        return $default;
    }
}

define('DB_HOST', (string) cfg_get('DB_HOST', 'localhost', $localConfig));
define('DB_NAME', (string) cfg_get('DB_NAME', 'alatinfo', $localConfig));
define('DB_USER', (string) cfg_get('DB_USER', 'root', $localConfig));
define('DB_PASS', (string) cfg_get('DB_PASS', '', $localConfig));
define('DB_CHARSET', (string) cfg_get('DB_CHARSET', 'utf8mb4', $localConfig));

define('SITE_NAME', (string) cfg_get('SITE_NAME', 'Latinfo.hu Pénzügy', $localConfig));
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', rtrim((string) cfg_get('BASE_URL', '', $localConfig), '/'));
define('UPLOAD_PATH', (string) cfg_get('UPLOAD_PATH', BASE_PATH . '/uploads/szamlak', $localConfig));
define('UPLOAD_URL', (string) cfg_get('UPLOAD_URL', (BASE_URL !== '' ? BASE_URL : '') . '/uploads/szamlak', $localConfig));

// Session
define('SESSION_LIFETIME', (int) cfg_get('SESSION_LIFETIME', 3600 * 8, $localConfig)); // 8 óra

// Alapértelmezett időzóna
date_default_timezone_set((string) cfg_get('APP_TIMEZONE', 'Europe/Budapest', $localConfig));

// E-mail: titkosítási kulcs az adatbázisban tárolt SMTP jelszavakhoz
if (!defined('EMAIL_ENCRYPT_KEY')) {
    define('EMAIL_ENCRYPT_KEY', (string) cfg_get('EMAIL_ENCRYPT_KEY', 'change-this-key-in-local-config', $localConfig));
}

// Hibajelentés (fejlesztés: E_ALL, éles: 0)
$displayErrors = (string) cfg_get('APP_DISPLAY_ERRORS', '1', $localConfig);
if ($displayErrors === '0') {
    error_reporting(0);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}
