<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

user_require_login();

$db = getDb();
$user = user_current($db);
if ($user === null) {
    redirect(user_url('login.php'));
}

$providers = latinfo_user_oauth_providers($db, (int) $user['id']);
$flashError = flash('error');
$flashSuccess = flash('success');
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fiókom – <?= h(SITE_NAME) ?></title>
    <?php require dirname(__DIR__) . '/includes/favicon_head.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= h(nextgen_url('assets/css/style.css')) ?>?v=<?= h(rawurlencode(nextgen_app_version())) ?>">
    <link rel="stylesheet" href="<?= h(user_asset_url('assets/css/account.css')) ?>?v=<?= h(rawurlencode(nextgen_app_version())) ?>">
</head>
<body class="user-account-page">
    <div class="user-account-shell">
        <div class="user-account-card">
            <?php if ($flashError): ?><p class="error"><?= h($flashError) ?></p><?php endif; ?>
            <?php if ($flashSuccess): ?><p class="alert alert-success"><?= h($flashSuccess) ?></p><?php endif; ?>
            <?php if (!empty($user['avatar_url'])): ?>
                <img class="user-account-avatar" src="<?= h((string) $user['avatar_url']) ?>" alt="" width="72" height="72" loading="lazy" referrerpolicy="no-referrer">
            <?php endif; ?>
            <h1><?= h((string) ($user['name'] ?? '')) ?></h1>
            <p class="user-account-meta"><?= h((string) ($user['email'] ?? '')) ?></p>
            <?php if ($providers !== []): ?>
                <ul class="user-account-providers">
                    <?php foreach ($providers as $p): ?>
                        <li><?= h($p) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <div class="user-account-actions">
                <a class="btn btn-secondary" href="<?= h(LATINFO_PUBLIC_HOME_URL) ?>">Kezdőoldal</a>
                <a class="btn btn-primary" href="<?= h(user_url('logout.php')) ?>">Kijelentkezés</a>
            </div>
        </div>
        <?= nextgen_footer_version_markup() ?>
    </div>
</body>
</html>
