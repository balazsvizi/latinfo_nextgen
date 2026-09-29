<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/init.php';
require_once dirname(__DIR__, 2) . '/lib/user/users.php';
requireLogin();

$db = getDb();
latinfo_users_ensure_schema($db);

$pageTitle = 'Userek';
require_once dirname(__DIR__, 2) . '/partials/header.php';

$kereso = trim((string) ($_GET['kereso'] ?? ''));
$order = isset($_GET['order']) && in_array((string) $_GET['order'], [
    'id', 'name', 'email', 'is_active', 'created_at', 'last_login_at',
], true) ? (string) $_GET['order'] : 'created_at';
$dirParam = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'asc' : 'desc';
if (!isset($_GET['dir']) && !isset($_GET['order'])) {
    $dirParam = 'desc';
}
$getParams = array_filter([
    'kereso' => $kereso !== '' ? $kereso : null,
    'order' => $order !== 'created_at' ? $order : null,
    'dir' => !($order === 'created_at' && $dirParam === 'desc') ? $dirParam : null,
], static fn ($v): bool => $v !== null && $v !== '');

$users = latinfo_users_list($db, $kereso !== '' ? $kereso : null, $order, $dirParam);
$tableReady = latinfo_users_table_ready($db);
$googleOn = defined('GOOGLE_LOGIN_CLIENT_ID') && trim((string) GOOGLE_LOGIN_CLIENT_ID) !== ''
    && defined('GOOGLE_LOGIN_CLIENT_SECRET') && trim((string) GOOGLE_LOGIN_CLIENT_SECRET) !== '';
$facebookOn = defined('FACEBOOK_APP_SECRET') && trim((string) FACEBOOK_APP_SECRET) !== ''
    && defined('FACEBOOK_APP_ID') && trim((string) FACEBOOK_APP_ID) !== '';
?>
<div class="card">
    <h2>Userek</h2>
    <p class="help">
        Publikus Latinfo.hu fiókok – kereshető lista. A névre kattintva a profil és a kedvencek (szívecskék) jelennek meg.
    </p>
    <?php if (!$tableReady): ?>
        <p class="alert alert-warning">A tábla még nem jött létre. Nyisd meg egyszer a <a href="<?= h(user_url()) ?>"><?= h(user_url()) ?></a> oldalt, vagy frissítsd ezt a lapot.</p>
    <?php endif; ?>
    <p class="help">
        SSO: Google <?= $googleOn ? 'bekapcsolva' : 'nincs konfigurálva' ?> ·
        Facebook <?= $facebookOn ? 'bekapcsolva' : 'nincs konfigurálva' ?>
    </p>
    <?php if ($msg = flash('success')): ?><p class="alert alert-success"><?= h($msg) ?></p><?php endif; ?>
    <?php if ($msg = flash('error')): ?><p class="alert alert-danger"><?= h($msg) ?></p><?php endif; ?>
    <form method="get" class="toolbar">
        <input type="search" name="kereso" placeholder="Név, e-mail vagy ID…" value="<?= h($kereso) ?>" autocomplete="off">
        <button type="submit" class="btn btn-primary">Keresés</button>
        <?php if ($kereso !== ''): ?>
            <a href="<?= h(nextgen_url('admin/users/')) ?>" class="btn btn-secondary">Törlés</a>
        <?php endif; ?>
    </form>
    <div class="table-wrap">
        <table class="sortable-table">
            <thead>
                <tr>
                    <th><?= sort_th('ID', 'id', $order, $dirParam, $getParams) ?></th>
                    <th><?= sort_th('Név', 'name', $order, $dirParam, $getParams) ?></th>
                    <th><?= sort_th('E-mail', 'email', $order, $dirParam, $getParams) ?></th>
                    <th>SSO</th>
                    <th>Kedvencek</th>
                    <th><?= sort_th('Státusz', 'is_active', $order, $dirParam, $getParams) ?></th>
                    <th><?= sort_th('Utolsó belépés', 'last_login_at', $order, $dirParam, $getParams) ?></th>
                    <th><?= sort_th('Létrehozva', 'created_at', $order, $dirParam, $getParams) ?></th>
                    <th>Műveletek</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($users === []): ?>
                    <tr><td colspan="9" class="text-muted">Nincs felhasználó.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <?php
                        $uid = (int) ($u['id'] ?? 0);
                        $profilUrl = nextgen_url('admin/users/profil.php?id=' . $uid);
                        $displayName = trim((string) ($u['name'] ?? ''));
                        if ($displayName === '') {
                            $displayName = (string) ($u['email'] ?? ('#' . $uid));
                        }
                        ?>
                        <tr>
                            <td><?= $uid ?></td>
                            <td>
                                <a href="<?= h($profilUrl) ?>"><strong><?= h($displayName) ?></strong></a>
                            </td>
                            <td><a href="<?= h($profilUrl) ?>"><?= h((string) ($u['email'] ?? '')) ?></a></td>
                            <td>
                                <?php
                                $providers = array_filter(array_map('trim', explode(',', (string) ($u['oauth_providers'] ?? ''))));
                                echo $providers !== [] ? h(implode(', ', $providers)) : '<span class="text-muted">—</span>';
                                ?>
                            </td>
                            <td><?= (int) ($u['favorites_count'] ?? 0) ?></td>
                            <td><?= !empty($u['is_active']) ? 'Aktív' : 'Letiltva' ?></td>
                            <td><?= h((string) ($u['last_login_at'] ?? '—')) ?></td>
                            <td><?= h((string) ($u['created_at'] ?? '')) ?></td>
                            <td class="actions">
                                <a href="<?= h($profilUrl) ?>" class="btn btn-sm btn-secondary">Profil</a>
                                <?php if (!empty($u['is_active'])): ?>
                                    <form method="post" action="<?= h(nextgen_url('admin/users/disable.php')) ?>" class="inline-form" onsubmit="return confirm('Letiltod ezt a felhasználót?');">
                                        <?= csrf_input('admin_users_toggle') ?>
                                        <input type="hidden" name="id" value="<?= $uid ?>">
                                        <button type="submit" class="btn btn-sm btn-secondary">Letilt</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="<?= h(nextgen_url('admin/users/enable.php')) ?>" class="inline-form">
                                        <?= csrf_input('admin_users_toggle') ?>
                                        <input type="hidden" name="id" value="<?= $uid ?>">
                                        <button type="submit" class="btn btn-sm btn-primary">Engedélyez</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/partials/footer.php'; ?>
