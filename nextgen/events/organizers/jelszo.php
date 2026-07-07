<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

if (!function_exists('partner_url')) {
    function partner_url(string $path = ''): string
    {
        return nextgen_url('partner/' . ltrim($path, '/'));
    }
}

redirect(partner_url('profil.php'));
