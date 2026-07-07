<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

function partner_auth_page_name(): string
{
    return basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '.php');
}

function partner_redirect_legacy_login_url(): void
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    if (str_contains($uri, '/nextgen/partner/login.php')) {
        redirect(partner_url('login.php'));
    }
}
