<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/events/bootstrap.php';
require_once dirname(__DIR__) . '/events/lib/tag_type.php';
require_once dirname(__DIR__) . '/lib/user/favorites.php';

user_require_login();

$db = getDb();
latinfo_favorites_ensure_schema($db);
$user = user_current($db);
if ($user === null) {
    redirect(user_url('login.php'));
}

$userId = (int) $user['id'];
$providers = latinfo_user_oauth_providers($db, $userId);
$flashError = flash('error');
$flashSuccess = flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!csrf_validate('user_account')) {
        flash('error', 'Érvénytelen kérés. Frissítsd az oldalt.');
        redirect(user_url('index.php'));
    }
    if ($action === 'save_notification_email') {
        $email = trim((string) ($_POST['notification_email'] ?? ''));
        $res = latinfo_user_save_notification_email($db, $userId, $email);
        if ($res['ok']) {
            flash('success', 'Értesítési e-mail mentve.');
            user_refresh_session_from_db($db);
        } else {
            flash('error', $res['error']);
        }
        redirect(user_url('index.php'));
    }
    if ($action === 'remove_favorite') {
        $type = latinfo_favorites_normalize_type((string) ($_POST['entity_type'] ?? ''));
        $entityId = (int) ($_POST['entity_id'] ?? 0);
        if ($type === null || $entityId <= 0) {
            flash('error', 'Érvénytelen kedvenc.');
        } else {
            $actor = latinfo_favorites_user_actor_key($userId);
            $res = latinfo_favorites_set_active($db, $type, $entityId, false, $actor);
            flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Kedvenc eltávolítva.' : $res['error']);
        }
        redirect(user_url('index.php'));
    }
}

$user = user_current($db) ?? $user;
$favorites = latinfo_favorites_list_for_user($db, $userId, 'hu');
$favoritesByType = [
    LATINFO_FAVORITE_TYPE_EVENT => [],
    LATINFO_FAVORITE_TYPE_ORGANIZER => [],
    LATINFO_FAVORITE_TYPE_VENUE => [],
    LATINFO_FAVORITE_TYPE_DJ => [],
];
foreach ($favorites as $fav) {
    $t = (string) ($fav['type'] ?? '');
    if (isset($favoritesByType[$t])) {
        $favoritesByType[$t][] = $fav;
    }
}
$typeLabels = [
    LATINFO_FAVORITE_TYPE_EVENT => 'Események',
    LATINFO_FAVORITE_TYPE_ORGANIZER => 'Szervezők',
    LATINFO_FAVORITE_TYPE_VENUE => 'Helyszínek',
    LATINFO_FAVORITE_TYPE_DJ => 'DJ-k',
];
$notificationEmail = trim((string) ($user['notification_email'] ?? ''));
$accountEmail = trim((string) ($user['email'] ?? ''));
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
    <div class="user-account-shell user-account-shell--wide">
        <div class="user-account-card">
            <?php if ($flashError): ?><p class="error"><?= h($flashError) ?></p><?php endif; ?>
            <?php if ($flashSuccess): ?><p class="alert alert-success"><?= h($flashSuccess) ?></p><?php endif; ?>
            <?php if (!empty($user['avatar_url'])): ?>
                <img class="user-account-avatar" src="<?= h((string) $user['avatar_url']) ?>" alt="" width="72" height="72" loading="lazy" referrerpolicy="no-referrer">
            <?php endif; ?>
            <h1><?= h((string) ($user['name'] ?? '')) ?></h1>
            <p class="user-account-meta"><?= h($accountEmail) ?></p>
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

        <section class="user-account-section">
            <h2>Értesítési e-mail</h2>
            <p class="user-account-help">Ide küldhet a rendszer értesítéseket. Ha üresen hagyod, a fiók e-mail címét használjuk (<?= h($accountEmail) ?>).</p>
            <form method="post" class="user-account-form">
                <?= csrf_input('user_account') ?>
                <input type="hidden" name="action" value="save_notification_email">
                <label for="notification_email">E-mail cím</label>
                <input type="email" id="notification_email" name="notification_email" maxlength="255" value="<?= h($notificationEmail) ?>" placeholder="<?= h($accountEmail) ?>" autocomplete="email">
                <button type="submit" class="btn btn-primary">Mentés</button>
            </form>
        </section>

        <section class="user-account-section">
            <h2>Kedvenceim</h2>
            <p class="user-account-help">Összesen <strong><?= count($favorites) ?></strong> kedvenc. A szívecskék száma az adott oldalon minden látogató összesített szívecskéjét mutatja.</p>
            <?php foreach ($favoritesByType as $typeKey => $items): ?>
                <div class="user-favorites-group">
                    <h3><?= h($typeLabels[$typeKey] ?? $typeKey) ?></h3>
                    <?php if ($items === []): ?>
                        <p class="user-favorites-empty">Még nincs ilyen kedvenc.</p>
                    <?php else: ?>
                        <ul class="user-favorites-list">
                            <?php foreach ($items as $item): ?>
                                <li class="user-favorites-list__item">
                                    <a class="user-favorites-list__link" href="<?= h((string) ($item['url'] ?? '#')) ?>"><?= h((string) ($item['label'] ?? '')) ?></a>
                                    <form method="post" class="user-favorites-list__remove">
                                        <?= csrf_input('user_account') ?>
                                        <input type="hidden" name="action" value="remove_favorite">
                                        <input type="hidden" name="entity_type" value="<?= h((string) ($item['type'] ?? '')) ?>">
                                        <input type="hidden" name="entity_id" value="<?= (int) ($item['id'] ?? 0) ?>">
                                        <button type="submit" class="btn btn-ghost btn-sm" aria-label="Kedvenc törlése">×</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>

        <?= nextgen_footer_version_markup() ?>
    </div>
</body>
</html>
