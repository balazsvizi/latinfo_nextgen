<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

organizers_portal_logout();
redirect(organizers_portal_url('login.php'));
