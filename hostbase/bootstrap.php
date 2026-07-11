<?php
declare(strict_types=1);

require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/i18n.php';

if (session_status() === PHP_SESSION_NONE) {
    $cookiePath = HB_WEB !== '' ? '/' . HB_WEB . '/' : '/hostbase/';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookiePath,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

hb_load_translations();

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/lib/PropertyRepository.php';
require_once __DIR__ . '/lib/BookingService.php';
require_once __DIR__ . '/lib/CalendarService.php';
