<?php
declare(strict_types=1);

/**
 * Front controller: /account/* → nextgen/user/*
 */

$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/account/router.php'));
$base = rtrim(dirname($scriptName), '/');
if ($base === '' || $base === '.') {
    $base = '/account';
}

$uriPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$uriPath = is_string($uriPath) ? str_replace('\\', '/', $uriPath) : '/';

$rel = $uriPath;
if (str_starts_with($rel, $base . '/')) {
    $rel = substr($rel, strlen($base) + 1);
} elseif ($rel === $base || $rel === $base . '/') {
    $rel = '';
} else {
    $marker = '/account/';
    $pos = strpos($rel, $marker);
    $rel = $pos !== false ? substr($rel, $pos + strlen($marker)) : ltrim($rel, '/');
}

$rel = trim($rel, '/');

if ($rel === '' || $rel === 'router.php') {
    require __DIR__ . '/index.php';
    exit;
}

if ($rel === 'index.php') {
    require dirname(__DIR__) . '/nextgen/user/index.php';
    exit;
}

if (str_contains($rel, '..')) {
    http_response_code(404);
    echo 'Not Found';
    exit;
}

$allowed = [
    'login.php',
    'signup.php',
    'logout.php',
    'oauth.php',
    'connect.php',
    'index.php',
];
$baseName = basename($rel);
if (!in_array($baseName, $allowed, true) || $baseName !== $rel) {
    http_response_code(404);
    echo 'Not Found';
    exit;
}

$target = dirname(__DIR__) . '/nextgen/user/' . $rel;
if (is_file($target) && str_ends_with(strtolower($target), '.php')) {
    require $target;
    exit;
}

http_response_code(404);
echo 'Not Found';
