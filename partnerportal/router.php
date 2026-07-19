<?php
declare(strict_types=1);

/**
 * Front controller: /partnerportal/* → nextgen/partner/*
 * (Apache gyakran nem engedi a rewrite-ot a szülő mappába.)
 */

$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/partnerportal/router.php'));
$base = rtrim(dirname($scriptName), '/');
if ($base === '' || $base === '.') {
    $base = '/partnerportal';
}

$uriPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$uriPath = is_string($uriPath) ? str_replace('\\', '/', $uriPath) : '/';

$rel = $uriPath;
if (str_starts_with($rel, $base . '/')) {
    $rel = substr($rel, strlen($base) + 1);
} elseif ($rel === $base || $rel === $base . '/') {
    $rel = '';
} else {
    // Fallback: utolsó szegmens utáni rész
    $marker = '/partnerportal/';
    $pos = strpos($rel, $marker);
    $rel = $pos !== false ? substr($rel, $pos + strlen($marker)) : ltrim($rel, '/');
}

$rel = trim($rel, '/');

if ($rel === '' || $rel === 'index.php' || $rel === 'router.php') {
    require dirname(__DIR__) . '/nextgen/partner/login.php';
    exit;
}

if (str_contains($rel, '..')) {
    http_response_code(404);
    echo 'Not Found';
    exit;
}

$target = dirname(__DIR__) . '/nextgen/partner/' . $rel;

if (is_file($target) && str_ends_with(strtolower($target), '.php')) {
    require $target;
    exit;
}

if (is_file($target)) {
    $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
    $types = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
    ];
    if (isset($types[$ext])) {
        header('Content-Type: ' . $types[$ext]);
    }
    header('Content-Length: ' . (string) filesize($target));
    readfile($target);
    exit;
}

http_response_code(404);
echo 'Not Found';
