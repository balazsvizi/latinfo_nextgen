<?php
/**
 * Közös favicon + Apple touch (Latinfo gradient L ikon).
 */
if (!function_exists('site_url')) {
    require_once dirname(__DIR__) . '/core/config.php';
}
if (!function_exists('h')) {
    require_once __DIR__ . '/functions.php';
}
$v = rawurlencode(function_exists('nextgen_app_version') ? nextgen_app_version() : (defined('APP_VERSION') ? APP_VERSION : '1'));
$favicon32 = nextgen_url('site/assets/images/icons/favicon-32.png') . '?v=' . $v;
$icon192 = nextgen_url('site/assets/images/icons/icon-192.png') . '?v=' . $v;
$apple = nextgen_url('site/assets/images/icons/apple-touch-icon.png') . '?v=' . $v;
?>
<link rel="icon" type="image/png" sizes="32x32" href="<?= h($favicon32) ?>">
<link rel="icon" type="image/png" sizes="192x192" href="<?= h($icon192) ?>">
<link rel="apple-touch-icon" href="<?= h($apple) ?>" sizes="180x180">
