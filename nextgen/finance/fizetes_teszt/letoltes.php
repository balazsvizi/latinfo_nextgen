<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../nextgen/core/database.php';
require_once __DIR__ . '/../../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../../nextgen/includes/functions.php';
require_once __DIR__ . '/../lib/payment_test.php';

requireLogin();

$id = (int) ($_GET['id'] ?? 0);
if ($id < 1) {
    http_response_code(404);
    exit;
}

$db = getDb();
finance_payment_test_ensure_schema($db);
$row = finance_payment_test_find($db, $id);
$path = $row === null ? null : finance_payment_test_pdf_file($id);
if ($row === null || $path === null) {
    http_response_code(404);
    exit;
}

$downloadName = trim((string) ($row['invoice_number'] ?? ''));
if ($downloadName === '') {
    $downloadName = 'szamla-elonezet-' . $id;
}
$downloadName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $downloadName) ?? ('szamla-' . $id);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $downloadName . '.pdf"');
header('Content-Length: ' . (string) filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
