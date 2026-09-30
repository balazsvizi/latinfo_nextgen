<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/init.php';
require_once dirname(__DIR__, 2) . '/events/bootstrap.php';
require_once dirname(__DIR__, 2) . '/events/lib/tag_type.php';
require_once dirname(__DIR__, 2) . '/events/lib/event_public_lang.php';
require_once dirname(__DIR__, 2) . '/events/lib/event_public_djs.php';
require_once dirname(__DIR__, 2) . '/lib/user/users.php';
require_once dirname(__DIR__, 2) . '/lib/user/favorites.php';
requireLogin();

$db = getDb();
latinfo_users_ensure_schema($db);
latinfo_favorites_ensure_schema($db);

$userId = (int) ($_GET['id'] ?? 0);
if ($userId <= 0) {
    flash('error', 'Hiányzó felhasználó azonosító.');
    redirect(nextgen_url('admin/users/'));
}

$user = latinfo_user_by_id($db, $userId);
if ($user === null) {
    flash('error', 'Felhasználó nem található.');
    redirect(nextgen_url('admin/users/'));
}

$providers = latinfo_user_oauth_providers($db, $userId);
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
    LATINFO_FAVORITE_TYPE_ZENEKAR => 'Előadók',
];
$favoritesTotal = 0;
foreach ($favoritesByType as $items) {
    $favoritesTotal += count($items);
}

$displayName = trim((string) ($user['name'] ?? ''));
$accountEmail = trim((string) ($user['email'] ?? ''));
$notificationEmail = trim((string) ($user['notification_email'] ?? ''));
if ($displayName === '') {
    $displayName = $accountEmail !== '' ? $accountEmail : ('#' . $userId);
}
$isActive = !empty($user['is_active']);

$pageTitle = 'User: ' . $displayName;
require_once dirname(__DIR__, 2) . '/partials/header.php';
?>
<div class="card">
    <p class="help" style="margin-top:0;">
        <a href="<?= h(nextgen_url('admin/users/')) ?>">← User lista</a>
    </p>
    <?php if ($msg = flash('success')): ?><p class="alert alert-success"><?= h($msg) ?></p><?php endif; ?>
    <?php if ($msg = flash('error')): ?><p class="alert alert-danger"><?= h($msg) ?></p><?php endif; ?>
    <div class="admin-user-profile">
        <?php if (!empty($user['avatar_url'])): ?>
            <img
                class="admin-user-profile__avatar"
                src="<?= h((string) $user['avatar_url']) ?>"
                alt=""
                width="64"
                height="64"
                loading="lazy"
                referrerpolicy="no-referrer"
            >
        <?php endif; ?>
        <div class="admin-user-profile__main">
            <h2 style="margin:0 0 0.35rem;"><?= h($displayName) ?></h2>
            <p class="help" style="margin:0 0 0.5rem;"><?= h($accountEmail) ?></p>
            <p class="help" style="margin:0;">
                ID: <?= $userId ?> ·
                Státusz: <strong><?= $isActive ? 'Aktív' : 'Letiltva' ?></strong>
                <?php if ($providers !== []): ?>
                    · SSO: <?= h(implode(', ', $providers)) ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="admin-user-profile__actions">
            <?php if ($isActive): ?>
                <form method="post" action="<?= h(nextgen_url('admin/users/disable.php')) ?>" class="inline-form" onsubmit="return confirm('Letiltod ezt a felhasználót?');">
                    <?= csrf_input('admin_users_toggle') ?>
                    <input type="hidden" name="id" value="<?= $userId ?>">
                    <input type="hidden" name="return" value="profil">
                    <button type="submit" class="btn btn-secondary">Letilt</button>
                </form>
            <?php else: ?>
                <form method="post" action="<?= h(nextgen_url('admin/users/enable.php')) ?>" class="inline-form">
                    <?= csrf_input('admin_users_toggle') ?>
                    <input type="hidden" name="id" value="<?= $userId ?>">
                    <input type="hidden" name="return" value="profil">
                    <button type="submit" class="btn btn-primary">Engedélyez</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <dl class="admin-user-meta">
        <div>
            <dt>Értesítési e-mail</dt>
            <dd><?= $notificationEmail !== '' ? h($notificationEmail) : '<span class="text-muted">— (fiók e-mail)</span>' ?></dd>
        </div>
        <div>
            <dt>Utolsó belépés</dt>
            <dd><?= h((string) ($user['last_login_at'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt>Regisztráció</dt>
            <dd><?= h((string) ($user['created_at'] ?? '—')) ?></dd>
        </div>
        <div>
            <dt>Kedvencek</dt>
            <dd><?= (int) $favoritesTotal ?></dd>
        </div>
    </dl>
</div>

<div class="card">
    <h2>Kedvencek (szívecskék)</h2>
    <?php if ($favoritesTotal === 0): ?>
        <p class="text-muted">Ennek a felhasználónak még nincs kedvence.</p>
    <?php else: ?>
        <?php foreach ($favoritesByType as $typeKey => $items): ?>
            <?php if ($items === []) {
                continue;
            } ?>
            <h3 style="margin:1.1rem 0 0.45rem;font-size:1rem;"><?= h($typeLabels[$typeKey] ?? $typeKey) ?> <span class="text-muted">(<?= count($items) ?>)</span></h3>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Név</th>
                            <th>Összes ♥</th>
                            <th>Hozzáadva</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <?php
                                    $url = trim((string) ($item['url'] ?? ''));
                                    $label = (string) ($item['label'] ?? '');
                                    if ($url !== '' && $url !== '#'):
                                        ?>
                                        <a href="<?= h($url) ?>" target="_blank" rel="noopener"><?= h($label) ?></a>
                                    <?php else: ?>
                                        <?= h($label) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int) ($item['count'] ?? 0) ?></td>
                                <td><?= h((string) ($item['created_at'] ?? '—')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<style>
.admin-user-profile {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.85rem 1.1rem;
}
.admin-user-profile__avatar {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--border, #d4e0cf);
}
.admin-user-profile__main {
    flex: 1 1 14rem;
    min-width: 0;
}
.admin-user-profile__actions {
    margin-left: auto;
}
.admin-user-meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));
    gap: 0.75rem 1.25rem;
    margin: 1.15rem 0 0;
    padding-top: 1rem;
    border-top: 1px solid var(--border, #d4e0cf);
}
.admin-user-meta dt {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--text-muted, #7a8574);
    margin: 0 0 0.15rem;
}
.admin-user-meta dd {
    margin: 0;
    font-size: 0.95rem;
}
</style>
<?php require_once dirname(__DIR__, 2) . '/partials/footer.php'; ?>
