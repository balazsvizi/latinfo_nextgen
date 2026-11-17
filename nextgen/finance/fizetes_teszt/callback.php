<?php
declare(strict_types=1);

/**
 * Barion sandbox állapotjelzés. Bejelentkezés nélkül hívható.
 * A jelzés csak annyit mond, hogy változott a fizetés; az állapotot a GetPaymentState adja.
 */

require_once __DIR__ . '/../../../nextgen/core/database.php';
require_once __DIR__ . '/../lib/payment_test.php';

$paymentId = trim((string) ($_POST['paymentId'] ?? $_GET['paymentId'] ?? ''));

try {
    $db = getDb();
    finance_payment_test_ensure_schema($db);
    if ($paymentId !== '') {
        finance_payment_test_sync_barion($db, $paymentId, false);
    }
    http_response_code(200);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'OK';
} catch (Throwable $e) {
    error_log('finance fizetes teszt callback: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'ERROR';
}
