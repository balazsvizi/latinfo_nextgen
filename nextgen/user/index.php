<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/events/bootstrap.php';
require_once dirname(__DIR__) . '/events/lib/tag_type.php';
require_once dirname(__DIR__) . '/events/lib/event_public_lang.php';
require_once dirname(__DIR__) . '/events/lib/event_public_djs.php';
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
latinfo_favorites_merge_visitor_to_user($db, $userId);
$favorites = latinfo_favorites_list_for_user($db, $userId, 'hu');
$favoritesByType = [
    LATINFO_FAVORITE_TYPE_EVENT => [],
    LATINFO_FAVORITE_TYPE_ORGANIZER => [],
    LATINFO_FAVORITE_TYPE_VENUE => [],
    LATINFO_FAVORITE_TYPE_DJ => [],
    LATINFO_FAVORITE_TYPE_ZENEKAR => [],
];
foreach ($favorites as $fav) {
    $t = (string) ($fav['type'] ?? '');
    $eid = (int) ($fav['id'] ?? 0);
    if (!isset($favoritesByType[$t]) || $eid <= 0) {
        continue;
    }
    $state = latinfo_favorites_state($db, $t, $eid);
    $fav['count'] = (int) ($state['count'] ?? 0);
    $favoritesByType[$t][] = $fav;
}
$typeLabels = [
    LATINFO_FAVORITE_TYPE_EVENT => 'Események',
    LATINFO_FAVORITE_TYPE_ORGANIZER => 'Szervezők',
    LATINFO_FAVORITE_TYPE_VENUE => 'Helyszínek',
    LATINFO_FAVORITE_TYPE_DJ => 'DJ-k',
    LATINFO_FAVORITE_TYPE_ZENEKAR => 'Zenekarok',
];
$favoritesTotal = 0;
foreach ($favoritesByType as $items) {
    $favoritesTotal += count($items);
}
$notificationEmail = trim((string) ($user['notification_email'] ?? ''));
$accountEmail = trim((string) ($user['email'] ?? ''));

$lang = 'hu';
$accountUrl = user_url('index.php');
$urlHu = $accountUrl;
$urlEn = $accountUrl;
$isEventsHome = false;
$showAdminEdit = false;
$adminEditUrl = '';
$S = [
    'lang_nav' => 'Nyelv',
    'logo_alt' => 'Latinfo.hu',
    'footer_home_link' => 'Latinfo.hu',
];
$cssUrl = events_url('assets/event_public.css') . '?v=' . rawurlencode(nextgen_app_version());
$accountCssUrl = user_asset_url('assets/css/account.css') . '?v=' . rawurlencode(nextgen_app_version());
$styleCssUrl = nextgen_url('assets/css/style.css') . '?v=' . rawurlencode(nextgen_app_version());
$favoritesAjaxUrl = events_url('ajax_favorite.php');
$accountFavoritesJsUrl = user_asset_url('assets/js/account-favorites.js') . '?v=' . rawurlencode(nextgen_app_version());

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= events_public_robots_noindex_head_markup() ?>
    <meta name="theme-color" content="#6d8f63">
    <title>Fiókom – <?= h(SITE_NAME) ?></title>
    <?= events_public_favicon_head_markup() ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= h($styleCssUrl) ?>">
    <link rel="stylesheet" href="<?= h($cssUrl) ?>">
    <link rel="stylesheet" href="<?= h($accountCssUrl) ?>">
</head>
<body class="event-public-page user-account-page" data-favorites-ajax="<?= h($favoritesAjaxUrl) ?>">
<div class="event-shell">
<article class="event-public user-account-public">
    <header class="event-public__hero">
        <?php require dirname(__DIR__) . '/events/partials/public_shell_hero_bar.php'; ?>
        <div class="event-public__hero-inner">
            <p class="event-public__eyebrow">Fiók</p>
            <h1 class="event-public__title">Fiókom</h1>
        </div>
    </header>

    <div class="user-account-shell user-account-shell--wide">
        <section class="user-account-card user-account-profile">
            <?php if ($flashError): ?><p class="error"><?= h($flashError) ?></p><?php endif; ?>
            <?php if ($flashSuccess): ?><p class="alert alert-success"><?= h($flashSuccess) ?></p><?php endif; ?>

            <div class="user-account-profile__top">
                <?php if (!empty($user['avatar_url'])): ?>
                    <img class="user-account-avatar" src="<?= h((string) $user['avatar_url']) ?>" alt="" width="48" height="48" loading="lazy" referrerpolicy="no-referrer">
                <?php endif; ?>
                <div class="user-account-profile__identity">
                    <h2 class="user-account-card__name"><?= h((string) ($user['name'] ?? '')) ?></h2>
                    <p class="user-account-meta"><?= h($accountEmail) ?></p>
                    <?php if ($providers !== []): ?>
                        <ul class="user-account-providers">
                            <?php foreach ($providers as $p): ?>
                                <li><?= h($p) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <div class="user-account-actions">
                    <a class="btn btn-primary" href="<?= h(user_url('logout.php')) ?>">Kijelentkezés</a>
                </div>
            </div>

            <div class="user-account-profile__notify">
                <div class="user-account-profile__notify-copy">
                    <h3 class="user-account-profile__notify-title">Értesítési e-mail</h3>
                    <p class="user-account-help">Ha üres, a fiók e-mailjét használjuk.</p>
                </div>
                <form method="post" class="user-account-form user-account-form--inline">
                    <?= csrf_input('user_account') ?>
                    <input type="hidden" name="action" value="save_notification_email">
                    <label class="visually-hidden" for="notification_email">Értesítési e-mail</label>
                    <input type="email" id="notification_email" name="notification_email" maxlength="255" value="<?= h($notificationEmail) ?>" placeholder="<?= h($accountEmail) ?>" autocomplete="email">
                    <button type="submit" class="btn btn-primary">Mentés</button>
                </form>
            </div>
        </section>

        <section
            class="user-account-section user-favorites"
            aria-labelledby="user-favorites-heading"
            data-empty-home="<?= h(events_public_home_page_url('hu')) ?>"
        >
            <div class="user-favorites__head">
                <div class="user-favorites__titles">
                    <h2 id="user-favorites-heading">Kedvenceim</h2>
                    <p class="user-account-help user-favorites__lead">A szívecskéid egy helyen. A szám minden látogató összesített kedvelését mutatja.</p>
                </div>
                <div class="user-favorites__total" title="Kedvenceid száma">
                    <span class="user-favorites__total-heart" aria-hidden="true">♥</span>
                    <span class="user-favorites__total-num" data-user-favorites-total><?= (int) $favoritesTotal ?></span>
                </div>
            </div>

            <?php if ($favoritesTotal === 0): ?>
                <div class="user-favorites-empty-state">
                    <span class="user-favorites-empty-state__heart" aria-hidden="true">♡</span>
                    <p class="user-favorites-empty-state__title">Még nincs kedvenced</p>
                    <p class="user-favorites-empty-state__text">Eseményeken, szervezőknél, helyszíneken és DJ-knél a ♥ gombbal mentheted ide a kedvenceidet.</p>
                    <a class="user-favorites-empty-state__cta" href="<?= h(events_public_home_page_url('hu')) ?>">Naptár böngészése</a>
                </div>
            <?php else: ?>
                <?php foreach ($favoritesByType as $typeKey => $items): ?>
                    <?php if ($items === []) {
                        continue;
                    } ?>
                    <div class="user-favorites-group" data-fav-group="<?= h((string) $typeKey) ?>">
                        <div class="user-favorites-group__head">
                            <h3 class="user-favorites-group__title">
                                <span class="user-favorites-group__badge user-favorites-group__badge--<?= h((string) $typeKey) ?>"><?= h($typeLabels[$typeKey] ?? $typeKey) ?></span>
                            </h3>
                            <span class="user-favorites-group__count" data-fav-group-count><?= count($items) ?></span>
                        </div>
                        <ul class="user-favorites-cards">
                            <?php foreach ($items as $item): ?>
                                <?php
                                $itemType = (string) ($item['type'] ?? '');
                                $itemId = (int) ($item['id'] ?? 0);
                                $itemLabel = (string) ($item['label'] ?? '');
                                $itemUrl = (string) ($item['url'] ?? '#');
                                $itemCount = (int) ($item['count'] ?? 0);
                                ?>
                                <li
                                    class="user-fav-card"
                                    data-user-fav-card
                                    data-active="1"
                                    data-entity-type="<?= h($itemType) ?>"
                                    data-entity-id="<?= $itemId ?>"
                                    data-item-label="<?= h($itemLabel) ?>"
                                >
                                    <a class="user-fav-card__body" href="<?= h($itemUrl) ?>">
                                        <span class="user-fav-card__title"><?= h($itemLabel) ?></span>
                                        <span class="user-fav-card__public-count" title="Összes szívecske">
                                            <span aria-hidden="true">♥</span>
                                            <span data-user-fav-count><?= $itemCount ?></span>
                                        </span>
                                    </a>
                                    <button
                                        type="button"
                                        class="user-fav-card__heart is-active"
                                        data-user-fav-heart
                                        aria-pressed="true"
                                        aria-label="<?= h('Kedvenc törlése: ' . $itemLabel) ?>"
                                        title="Levétel a kedvencekből"
                                    >
                                        <span class="user-fav-card__heart-icon" aria-hidden="true">♥</span>
                                    </button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>

    <footer class="event-public__footer">
        <?php require dirname(__DIR__) . '/events/partials/public_shell_footer.php'; ?>
    </footer>
</article>
</div>
<script src="<?= h($accountFavoritesJsUrl) ?>" defer></script>
</body>
</html>
