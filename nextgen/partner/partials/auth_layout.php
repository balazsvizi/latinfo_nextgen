<?php
declare(strict_types=1);
/** @var string $authTitle */
/** @var string $authSubtitle */
/** @var string $authContent */
/** @var bool $authTableReady */
$authTitle = $authTitle ?? 'Partner';
$authSubtitle = $authSubtitle ?? '';
/** @var bool $authHideLoginLink */
$authHideLoginLink = $authHideLoginLink ?? false;
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($authTitle) ?> – <?= h(SITE_NAME) ?></title>
    <?php require dirname(__DIR__, 2) . '/includes/favicon_head.php'; ?>
    <link rel="stylesheet" href="<?= h(nextgen_url('assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= h(partner_asset_url('assets/css/portal.css')) ?>">
</head>
<body class="login-page partner-login-page">
    <div class="login-box">
        <h1 class="login-brand"><span class="logo-site"><?= h(SITE_NAME) ?></span> <span class="logo-area">Partner</span></h1>
        <?php if ($authSubtitle !== ''): ?>
            <p class="login-sub"><?= h($authSubtitle) ?></p>
        <?php endif; ?>
        <?php if (!$authTableReady): ?>
            <p class="alert alert-warning">A partner rendszer még nincs telepítve az adatbázisban.</p>
        <?php endif; ?>
        <?= $authContent ?>
        <p class="login-back-home">
            <?php if (!$authHideLoginLink): ?><a href="<?= h(partner_url('login.php')) ?>">← Bejelentkezés</a><?php if (function_exists('events_public_home_path')): ?> · <?php endif; ?><?php endif; ?>
            <?php if (function_exists('events_public_home_path')): ?><a href="<?= h(events_public_home_path()) ?>">← Naptár</a><?php endif; ?>
        </p>
    </div>
</body>
</html>
