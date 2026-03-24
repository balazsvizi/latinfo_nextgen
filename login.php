<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
redirect((BASE_URL !== '' ? rtrim(BASE_URL, '/') : '') . '/nextgen/');
