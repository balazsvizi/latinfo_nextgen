<?php
declare(strict_types=1);

/**
 * Partnerportál belépési pont — https://latinfo.hu/partnerportal/
 *
 * /partnerportal/ és /partnerportal/index.php ugyanide esik (DirectoryIndex),
 * ezért a session alapján döntünk: belépve dashboard, különben login.
 * (Korábban mindig login.php futott → redirect loop.)
 */
require_once dirname(__DIR__) . '/nextgen/partner/bootstrap.php';

if (partner_is_logged_in()) {
    require dirname(__DIR__) . '/nextgen/partner/index.php';
    exit;
}

require dirname(__DIR__) . '/nextgen/partner/login.php';
