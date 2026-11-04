<?php
declare(strict_types=1);

require_once __DIR__ . '/../../nextgen/core/database.php';
require_once __DIR__ . '/../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../nextgen/includes/functions.php';
requireLogin();

$szervezoId = (int) ($_POST['szervezo_id'] ?? $_GET['szervezo_id'] ?? 0);
flash('success', 'A partner leválasztása a partner szerkesztőjén, a Finance szervezők részen történik.');
if ($szervezoId > 0) {
    redirect(nextgen_url('organizers/megtekint.php?id=') . $szervezoId . '#kontaktok');
}
redirect(nextgen_url('admin/partnerek/'));
