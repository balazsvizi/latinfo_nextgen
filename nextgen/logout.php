<?php
require_once __DIR__ . '/init.php';
logout();
redirect(nextgen_url('login.php'));
