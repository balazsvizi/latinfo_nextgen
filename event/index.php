<?php
declare(strict_types=1);

/**
 * Fizikai /event/{slug}/ belépési pont.
 * Apache-on a rewrite nem mindig fut — ez biztosítja a működést.
 */

$uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$uri = is_string($uri) ? $uri : '/';

$prefix = '/event/';
$slug = '';
if (str_starts_with($uri, $prefix)) {
    $slug = trim(substr($uri, strlen($prefix)), '/');
}

if ($slug === '' || $slug === 'index.php') {
    header('Location: /events/', true, 302);
    exit;
}

if (str_contains($slug, '..') || str_contains($slug, '/')) {
    http_response_code(404);
    echo 'Not Found';
    exit;
}

$_GET['slug'] = rawurldecode($slug);
$_REQUEST['slug'] = $_GET['slug'];

require dirname(__DIR__) . '/nextgen/events/megjelenit.php';
