<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireSuperadmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/admin/email/');
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Érvénytelen fiók.');
    redirect(BASE_URL . '/admin/email/');
}

$db = getDb();
$stmt = $db->prepare('DELETE FROM email_config WHERE id = ?');
$stmt->execute([$id]);
if ($stmt->rowCount()) {
    rendszer_log('email_config', $id, 'SMTP fiók törölve', null);
    flash('success', 'SMTP fiók törölve.');
}
redirect(BASE_URL . '/admin/email/');
