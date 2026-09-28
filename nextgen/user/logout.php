<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

user_logout();
redirect(user_url('login.php'));
