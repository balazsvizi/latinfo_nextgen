<?php
declare(strict_types=1);

/**
 * Átirányítás az egységes partner portálra (/partners/).
 */
require_once dirname(__DIR__, 2) . '/core/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

$query = $_SERVER['QUERY_STRING'] ?? '';
$target = partner_url('');
if ($query !== '') {
    $target .= (str_contains($target, '?') ? '&' : '?') . $query;
}
redirect($target);
