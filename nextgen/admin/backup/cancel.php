<?php
declare(strict_types=1);

require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../includes/google_drive_backup.php';
require_once __DIR__ . '/../../includes/google_drive_backup_step.php';

requireSuperadmin();

alatinfo_backup_google_drive_handle_cancel();
