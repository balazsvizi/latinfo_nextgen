<?php
/**
 * Nyilvános NextGen landing – URL: /lanueva/
 */
require_once dirname(__DIR__) . '/nextgen/core/config.php';
require_once dirname(__DIR__) . '/nextgen/includes/auth.php';

if (isLoggedIn()) {
    redirect(nextgen_url('index.php'));
    exit;
}

require_once __DIR__ . '/landing_public.php';
