<?php
declare(strict_types=1);

/**
 * Publikus fiók belépési pont — /account/
 */
require_once dirname(__DIR__) . '/nextgen/user/bootstrap.php';

if (user_is_logged_in()) {
    require dirname(__DIR__) . '/nextgen/user/index.php';
    exit;
}

require dirname(__DIR__) . '/nextgen/user/login.php';
