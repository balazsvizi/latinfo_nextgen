<?php
declare(strict_types=1);

$nextgenRoot = dirname(__DIR__);

require_once $nextgenRoot . '/core/config.php';
require_once $nextgenRoot . '/core/database.php';
require_once $nextgenRoot . '/includes/functions.php';
require_once $nextgenRoot . '/lib/user/users.php';
require_once $nextgenRoot . '/lib/user/oauth.php';
require_once $nextgenRoot . '/lib/user/consents.php';
require_once $nextgenRoot . '/lib/user/mailing.php';
require_once $nextgenRoot . '/lib/user/dance_styles.php';

if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/includes/auth.php';

$dbBoot = getDb();
latinfo_users_ensure_schema($dbBoot);
latinfo_user_consents_ensure_schema($dbBoot);
latinfo_mailing_ensure_schema($dbBoot);
latinfo_user_dance_styles_ensure_schema($dbBoot);
