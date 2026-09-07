<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/init.php';
requireLogin();
header('Location: ' . nextgen_url('admin/partnerek/mediaertek.php'), true, 301);
exit;
