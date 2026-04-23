<?php
require_once __DIR__ . '/../../../nextgen/core/database.php';
require_once __DIR__ . '/../../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../../nextgen/includes/functions.php';
requireSuperadmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(nextgen_url('admin/email/'));
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Érvénytelen fiók.');
    redirect(nextgen_url('admin/email/'));
}

$db = getDb();
$stmt = $db->prepare('DELETE FROM finance_email_accounts WHERE id = ?');
$stmt->execute([$id]);
if ($stmt->rowCount()) {
    rendszer_log('email_config', $id, 'SMTP fiók törölve', null);
    flash('success', 'SMTP fiók törölve.');
}
redirect(nextgen_url('admin/email/'));
