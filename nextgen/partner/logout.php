<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
partner_logout();
redirect(partner_url('login.php'));
