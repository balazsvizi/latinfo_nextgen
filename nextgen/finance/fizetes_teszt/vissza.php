<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../nextgen/core/database.php';
require_once __DIR__ . '/../../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../../nextgen/includes/functions.php';
require_once __DIR__ . '/../lib/payment_test.php';

requireLogin();

$db = getDb();
finance_payment_test_ensure_schema($db);

$paymentId = trim((string) ($_GET['paymentId'] ?? ''));
$pageUrl = nextgen_url('finance/fizetes_teszt/');

try {
    $row = finance_payment_test_sync_barion($db, $paymentId, true);
} catch (Throwable $e) {
    error_log('finance fizetes teszt vissza: ' . $e->getMessage());
    flash('error', 'A fizetés állapotát nem sikerült lekérdezni.');
    redirect($pageUrl);
}

if ($row === null) {
    flash('error', 'A Barion visszatérés nem tartozik tesztbefizetéshez.');
    redirect($pageUrl);
}

$status = (string) ($row['status'] ?? '');
if ($status === 'succeeded') {
    flash('success', 'A tesztfizetés sikeres.');
} elseif ($status === 'pending') {
    flash('error', 'A fizetés még nincs lezárva. Az állapot frissítése újra lekérdezi a Bariont.');
} else {
    flash('error', 'A tesztfizetés állapota: ' . finance_payment_test_status_label($status) . '.');
}

redirect($pageUrl . '?id=' . (int) $row['id']);
