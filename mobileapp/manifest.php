<?php
declare(strict_types=1);

/**
 * PWA web manifest – Latinfo mobilapp.
 */
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../nextgen/core/config.php';
}

$startUrl = site_url('/') . '?source=mobileapp';
$scope = site_url('/');
$iconSvg = site_url('mobileapp/assets/icons/icon.svg');
$iconPng = site_url('lanueva/assets/icons/apple-touch-icon.png');

$manifest = [
    'name' => SITE_NAME . ' – Mobilapp',
    'short_name' => SITE_NAME,
    'description' => 'Latin táncos események naptára – telepíthető mobilalkalmazás.',
    'lang' => 'hu',
    'dir' => 'ltr',
    'start_url' => $startUrl,
    'scope' => $scope,
    'display' => 'standalone',
    'orientation' => 'portrait-primary',
    'background_color' => '#f4f6f2',
    'theme_color' => '#6d8f63',
    'categories' => ['entertainment', 'lifestyle'],
    'icons' => [
        [
            'src' => $iconSvg,
            'sizes' => 'any',
            'type' => 'image/svg+xml',
            'purpose' => 'any',
        ],
        [
            'src' => $iconPng,
            'sizes' => '180x180',
            'type' => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src' => $iconPng,
            'sizes' => '180x180',
            'type' => 'image/png',
            'purpose' => 'maskable',
        ],
    ],
];

header('Content-Type: application/manifest+json; charset=UTF-8');
header('Cache-Control: public, max-age=3600');
echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
