<?php
/**
 * Nyilvános NextGen landing – URL: /lanueva/
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

if (isLoggedIn()) {
    redirect(BASE_URL . '/index.php');
    exit;
}

require_once dirname(__DIR__) . '/landing_public.php';
