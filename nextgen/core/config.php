<?php
/**
 * Latinfo.hu Backoffice - Konfiguráció
 *
 * Szerverfüggő értékek forrása (prioritás szerint):
 * 1) Környezeti változó (getenv)
 * 2) nextgen/core/config.local.php (giten kívüli helyi felülírás)
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

define('SITE_NAME', (string) cfg_get('SITE_NAME', 'Latinfo.hu', $localConfig));
define('BASE_PATH', dirname(__DIR__, 2));

$baseUrlConfigured = rtrim((string) cfg_get('BASE_URL', '', $localConfig), '/');
$baseUrlResolved = $baseUrlConfigured;
if ($baseUrlResolved === '' && PHP_SAPI !== 'cli') {
    $candidates = [
        (string) ($_SERVER['SCRIPT_NAME'] ?? ''),
        (string) strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?'),
    ];
    foreach ($candidates as $src) {
        if ($src === '') {
            continue;
        }
        foreach (['/nextgen/', '/lanueva/'] as $needle) {
            $p = strpos($src, $needle);
            if ($p > 0) {
                $baseUrlResolved = substr($src, 0, $p);
                break 2;
            }
        }
    }
}
define('BASE_URL', $baseUrlResolved);
/** Webes útvonal a backoffice gyökéréhez (pl. /nextgen) */
if (!defined('NEXTGEN_WEB')) {
    define('NEXTGEN_WEB', '/nextgen');
}

if (!function_exists('site_url')) {
    /**
     * Webes útvonal a domain gyökérétől, BASE_URL előtaggal (alkönyvtárban futó telepítés).
     * @param string $path pl. "lanueva/assets/css/landing.css" vagy "/lanueva/"
     */
    function site_url(string $path): string {
        $path = '/' . ltrim($path, '/');
        $base = rtrim(BASE_URL, '/');
        return $base === '' ? $path : $base . $path;
    }
}

if (!function_exists('nextgen_url')) {
    /**
     * Abszolút URL a backoffice (nextgen) alatti útvonalhoz, pl. nextgen_url('organizers/')
     */
    function nextgen_url(string $path = ''): string {
        $path = ltrim($path, '/');
        $base = (BASE_URL !== '' ? rtrim(BASE_URL, '/') : '');
        return $base . NEXTGEN_WEB . ($path !== '' ? '/' . $path : '');
    }
}
define('UPLOAD_PATH', (string) cfg_get('UPLOAD_PATH', BASE_PATH . '/nextgen/uploads/szamlak', $localConfig));
define('UPLOAD_URL', (string) cfg_get('UPLOAD_URL', site_url('nextgen/uploads/szamlak'), $localConfig));

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
