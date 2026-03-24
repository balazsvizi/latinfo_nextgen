<?php
declare(strict_types=1);
/**
 * Backoffice bootstrap – minden nextgen/*.php oldal ehhez képest tölti a közös függőségeket.
 */
$nextgenRoot = __DIR__;
require_once $nextgenRoot . '/core/config.php';
require_once $nextgenRoot . '/core/database.php';
require_once $nextgenRoot . '/includes/functions.php';
require_once $nextgenRoot . '/includes/auth.php';
