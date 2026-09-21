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
$iconJpg = site_url('mobileapp/assets/icons/latinfo-app-logo.jpg');

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
    'background_color' => '#f4f1ea',
    'theme_color' => '#6d8f63',
    'categories' => ['entertainment', 'lifestyle'],
    'icons' => [
        [
            'src' => $iconJpg,
            'sizes' => '1024x1024',
            'type' => 'image/jpeg',
            'purpose' => 'any',
        ],
        [
            'src' => $iconJpg,
            'sizes' => '1024x1024',
            'type' => 'image/jpeg',
            'purpose' => 'maskable',
        ],
    ],
];

header('Content-Type: application/manifest+json; charset=UTF-8');
header('Cache-Control: public, max-age=3600');
echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
