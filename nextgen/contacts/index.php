<?php
declare(strict_types=1);

require_once __DIR__ . '/../../nextgen/core/database.php';
require_once __DIR__ . '/../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../nextgen/includes/functions.php';
require_once __DIR__ . '/../../nextgen/lib/partner/finance_billing.php';
requireLogin();

nextgen_partner_ensure_finance_contacts_migrated(getDb());
redirect(nextgen_url('admin/partnerek/'));
