<?php
declare(strict_types=1);

/**
 * Régi útvonal: /nextgen/belepes/ → kanónikus /belepes/
 */
require_once dirname(__DIR__) . '/core/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
redirect(site_url('belepes/'));
