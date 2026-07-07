<?php
declare(strict_types=1);

/**
 * Átirányítás az egységes partner portálra.
 */
require_once dirname(__DIR__, 2) . '/core/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

if (!function_exists('partner_url')) {
    function partner_url(string $path = ''): string
    {
        return nextgen_url('partner/' . ltrim($path, '/'));
    }
}

$query = $_SERVER['QUERY_STRING'] ?? '';
$target = partner_url('login.php');
if ($query !== '') {
    $target .= (str_contains($target, '?') ? '&' : '?') . $query;
}
redirect($target);
