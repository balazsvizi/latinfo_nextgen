<?php
declare(strict_types=1);

/**
 * /events/ gyökér — egyelőre nem publikus főoldal.
 * A naptáras főoldal: public_home.php (később átirányítható ide).
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/nextgen/includes/auth.php';

if (isLoggedIn()) {
    redirect(events_url('events_admin.php'));
}

http_response_code(404);
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>404 – <?= h(SITE_NAME) ?></title>
</head>
<body>
    <p>Az oldal nem található.</p>
</body>
</html>
