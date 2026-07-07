<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

redirect(partner_url('logout.php'));
