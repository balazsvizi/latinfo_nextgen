<?php
declare(strict_types=1);

/**
 * HostBase konfiguráció – megosztott DB beállítások a nextgen/core-ból.
 */

require_once dirname(__DIR__, 2) . '/nextgen/core/config.php';

$hbLocalConfig = [];
$hbLocalConfigPath = dirname(__DIR__, 2) . '/nextgen/core/config.local.php';
if (is_file($hbLocalConfigPath)) {
    $hbLocalTmp = require $hbLocalConfigPath;
    if (is_array($hbLocalTmp)) {
        $hbLocalConfig = $hbLocalTmp;
    }
}

if (!defined('HB_APP_NAME')) {
    define('HB_APP_NAME', 'HostBase');
}

if (!defined('HB_ROOT')) {
    define('HB_ROOT', dirname(__DIR__));
}

if (!defined('HB_WEB')) {
    define('HB_WEB', trim((string) cfg_get('HOSTBASE_WEB', 'hostbase', $hbLocalConfig), '/'));
}

if (!function_exists('hb_url')) {
    function hb_url(string $path = ''): string
    {
        $path = ltrim($path, '/');
        $segment = HB_WEB !== '' ? HB_WEB . '/' : 'hostbase/';

        return $path === '' ? rtrim(site_url($segment), '/') . '/' : rtrim(site_url($segment), '/') . '/' . $path;
    }
}

if (!function_exists('hb_asset_url')) {
    function hb_asset_url(string $path): string
    {
        return hb_url('assets/' . ltrim($path, '/'));
    }
}
