<?php
declare(strict_types=1);

require_once __DIR__ . '/../../nextgen/core/database.php';
require_once __DIR__ . '/../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../nextgen/includes/functions.php';
requireLogin();

flash('success', 'A partnert a partner szerkesztőjén lehet a finance szervezőhöz rendelni.');
redirect(nextgen_url('admin/partnerek/'));
