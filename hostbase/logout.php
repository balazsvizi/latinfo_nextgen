<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (hb_is_logged_in()) {
    hb_logout(hb_get_db());
}

hb_redirect(hb_url('login.php'));
