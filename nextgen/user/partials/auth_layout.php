<?php
declare(strict_types=1);

/** @var string $authTitle */
/** @var string $authSubtitle */
/** @var string $authContent */
/** @var bool $authTableReady */

$authTitle = $authTitle ?? 'Fiók';
$authSubtitle = $authSubtitle ?? '';
$authTableReady = $authTableReady ?? true;
$googleOn = function_exists('latinfo_oauth_provider_enabled') && latinfo_oauth_provider_enabled('google');
$facebookOn = function_exists('latinfo_oauth_provider_enabled') && latinfo_oauth_provider_enabled('facebook');
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($authTitle) ?> – <?= h(SITE_NAME) ?></title>
    <?php require dirname(__DIR__, 2) . '/includes/favicon_head.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= h(nextgen_url('assets/css/style.css')) ?>?v=<?= h(rawurlencode(nextgen_app_version())) ?>">
    <link rel="stylesheet" href="<?= h(user_asset_url('assets/css/account.css')) ?>?v=<?= h(rawurlencode(nextgen_app_version())) ?>">
</head>
<body class="login-page user-login-page">
    <div class="login-box">
        <a class="user-login-logo" href="<?= h(LATINFO_PUBLIC_HOME_URL) ?>" title="<?= h(SITE_NAME) ?>" aria-label="<?= h(SITE_NAME . ' kezdőoldala') ?>">
            <img
                src="<?= h(nextgen_url('events/assets/images/latinfo-logo.png')) ?>"
                alt="<?= h(SITE_NAME) ?>"
                width="240"
                height="80"
                decoding="async"
                fetchpriority="high"
            >
        </a>
        <h1 class="login-brand"><?= h($authTitle) ?></h1>
        <?php if ($authSubtitle !== ''): ?>
            <p class="login-sub"><?= h($authSubtitle) ?></p>
        <?php endif; ?>
        <?php if (!$authTableReady): ?>
            <p class="alert alert-warning">A felhasználói rendszer még nincs telepítve az adatbázisban.</p>
        <?php endif; ?>
        <?= $authContent ?>
        <?php if ($googleOn || $facebookOn): ?>
            <div class="user-oauth-divider"><span>vagy</span></div>
            <div class="user-oauth-buttons">
                <?php if ($googleOn): ?>
                    <a class="user-oauth-btn user-oauth-btn--google" href="<?= h(user_url('connect.php?provider=google')) ?>">
                        Folytatás Google-lel
                    </a>
                <?php endif; ?>
                <?php if ($facebookOn): ?>
                    <a class="user-oauth-btn user-oauth-btn--facebook" href="<?= h(user_url('connect.php?provider=facebook')) ?>">
                        Folytatás Facebookkal
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <p class="login-back-home">
            <a href="<?= h(LATINFO_PUBLIC_HOME_URL) ?>">← <?= h(SITE_NAME) ?></a>
        </p>
    </div>
    <script src="<?= h(nextgen_url('assets/js/password-toggle.js')) ?>"></script>
</body>
</html>
