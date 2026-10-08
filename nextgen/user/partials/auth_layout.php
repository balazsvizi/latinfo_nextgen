<?php
declare(strict_types=1);

/** @var string $authTitle */
/** @var string $authSubtitle */
/** @var string $authContent */
/** @var bool $authTableReady */
/** @var bool $authShowOauth */
/** @var bool $authRequirePrivacyForOauth */

$authTitle = $authTitle ?? 'Fiók';
$authSubtitle = $authSubtitle ?? '';
$authTableReady = $authTableReady ?? true;
$authShowOauth = $authShowOauth ?? true;
$authRequirePrivacyForOauth = $authRequirePrivacyForOauth ?? false;
$googleOn = $authShowOauth && function_exists('latinfo_oauth_provider_enabled') && latinfo_oauth_provider_enabled('google');
$facebookOn = $authShowOauth && function_exists('latinfo_oauth_provider_enabled') && latinfo_oauth_provider_enabled('facebook');
$policyUrl = function_exists('latinfo_privacy_policy_url') ? latinfo_privacy_policy_url() : site_url('adatkezeles/');
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
            <?php if ($authRequirePrivacyForOauth): ?>
                <p class="user-oauth-privacy-hint">
                    A közösségi belépéshez pipáld be az adatkezelési tájékoztató elfogadását fent.
                </p>
            <?php endif; ?>
            <div class="user-oauth-buttons"<?= $authRequirePrivacyForOauth ? ' data-oauth-require-privacy="1"' : '' ?>>
                <?php if ($googleOn): ?>
                    <a
                        class="user-oauth-btn user-oauth-btn--google"
                        href="<?= h(user_url('connect.php?provider=google' . ($authRequirePrivacyForOauth ? '&from=signup' : ''))) ?>"
                        data-oauth-provider="google"
                    >
                        <svg class="user-oauth-btn__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        </svg>
                        Folytatás Google-lel
                    </a>
                <?php endif; ?>
                <?php if ($facebookOn): ?>
                    <a
                        class="user-oauth-btn user-oauth-btn--facebook"
                        href="<?= h(user_url('connect.php?provider=facebook' . ($authRequirePrivacyForOauth ? '&from=signup' : ''))) ?>"
                        data-oauth-provider="facebook"
                    >
                        <svg class="user-oauth-btn__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path fill="currentColor" d="M24 12.07C24 5.41 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.09 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.7 4.53-4.7 1.31 0 2.68.24 2.68.24v2.96h-1.51c-1.49 0-1.95.93-1.95 1.89v2.27h3.32l-.53 3.49h-2.79V24C19.61 23.09 24 18.1 24 12.07z"/>
                        </svg>
                        Folytatás Facebookkal
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <p class="login-back-home">
            <a href="<?= h(LATINFO_PUBLIC_HOME_URL) ?>">← <?= h(SITE_NAME) ?></a>
            <span aria-hidden="true"> · </span>
            <a href="<?= h($policyUrl) ?>">Adatkezelés</a>
        </p>
    </div>
    <script src="<?= h(nextgen_url('assets/js/password-toggle.js')) ?>"></script>
    <?php if ($authRequirePrivacyForOauth): ?>
    <script>
    (function () {
        var wrap = document.querySelector('[data-oauth-require-privacy]');
        if (!wrap) return;
        wrap.addEventListener('click', function (e) {
            var link = e.target.closest('a[data-oauth-provider]');
            if (!link) return;
            var privacy = document.getElementById('accept_privacy');
            if (!privacy || !privacy.checked) {
                e.preventDefault();
                alert('Az adatkezelési tájékoztató elfogadása kötelező a regisztrációhoz.');
                if (privacy) privacy.focus();
                return;
            }
            e.preventDefault();
            var url = new URL(link.href, window.location.origin);
            url.searchParams.set('from', 'signup');
            url.searchParams.set('privacy', '1');
            var newsletter = document.getElementById('accept_newsletter');
            if (newsletter && newsletter.checked) {
                url.searchParams.set('newsletter', '1');
            }
            window.location.href = url.toString();
        });
    })();
    </script>
    <?php endif; ?>
</body>
</html>
