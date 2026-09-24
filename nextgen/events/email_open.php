<?php
declare(strict_types=1);

/**
 * E-mail megnyitás tracking pixel (1×1 GIF).
 * Nyilvános végpont – nincs login.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/event_notify_email.php';

$token = trim((string) ($_GET['t'] ?? ''));
if ($token !== '') {
    try {
        events_notify_email_record_open(getDb(), $token);
    } catch (Throwable $ex) {
        error_log('email_open: ' . $ex->getMessage());
    }
}

header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
echo hex2bin('47494638396101000100800000ffffff00000021f90401000000002c00000000010001000002024401003b');
exit;
