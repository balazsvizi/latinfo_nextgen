<?php
/**
 * Közös favicon + Apple touch linkek (fájlok: lanueva/assets/icons/).
 * site_url: alkönyvtáras telepítéshez is jó útvonal.
 */
if (!function_exists('site_url')) {
    require_once dirname(__DIR__) . '/core/config.php';
}
if (!function_exists('h')) {
    require_once __DIR__ . '/functions.php';
}
$favicon32 = site_url('lanueva/assets/icons/favicon-32x32.png');
$apple = site_url('lanueva/assets/icons/apple-touch-icon.png');
?>
<link rel="icon" type="image/png" sizes="32x32" href="<?= h($favicon32) ?>">
<link rel="apple-touch-icon" href="<?= h($apple) ?>" sizes="180x180">
