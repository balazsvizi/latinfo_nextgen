<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$fallback = defined('LATINFO_PUBLIC_HOME_URL') ? (string) LATINFO_PUBLIC_HOME_URL : site_url('/');
$return = user_safe_return_path($_GET['return'] ?? null, $fallback);
user_logout();
redirect($return);
