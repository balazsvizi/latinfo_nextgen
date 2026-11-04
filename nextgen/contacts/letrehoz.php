<?php
declare(strict_types=1);

require_once __DIR__ . '/../../nextgen/core/database.php';
require_once __DIR__ . '/../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../nextgen/includes/functions.php';
requireLogin();

redirect(nextgen_url('admin/partnerek/letrehoz.php'));
