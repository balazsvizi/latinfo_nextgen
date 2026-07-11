<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

hb_require_login();

$db = hb_get_db();
hb_refresh_session_from_db($db);

$filterUserId = hb_get_int('user_id');
$filterUserId = $filterUserId > 0 ? $filterUserId : null;

$logs = HbActivityLog::listForSubscriber($db, hb_subscriber_id(), $filterUserId, 150);

$usersStmt = $db->prepare('SELECT id, name, email FROM hb_users WHERE subscriber_id = ? AND active = 1 ORDER BY name ASC');
$usersStmt->execute([hb_subscriber_id()]);
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = hb_t('logs.title');
$activeNav = 'logs';

require dirname(__DIR__) . '/partials/header.php';
?>
<div class="hb-page-head">
    <h1><?= hb_h(hb_t('logs.title')) ?></h1>
</div>

<div class="card">
    <form method="get" class="filter-bar">
        <div class="form-group">
            <label for="user_id"><?= hb_h(hb_t('logs.filter_user')) ?></label>
            <select name="user_id" id="user_id" class="hb-select">
                <option value="0"><?= hb_h(hb_t('logs.filter_all_users')) ?></option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= (int) $user['id'] ?>"<?= $filterUserId === (int) $user['id'] ? ' selected' : '' ?>>
                        <?= hb_h((string) $user['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary"><?= hb_h(hb_t('bookings.filter_apply')) ?></button>
    </form>
</div>

<div class="card">
    <h2><?= hb_h(hb_t('logs.system')) ?></h2>
    <?php if ($logs === []): ?>
        <p class="help"><?= hb_h(hb_t('logs.empty')) ?></p>
    <?php else: ?>
        <div class="log-list">
            <?php foreach ($logs as $log): ?>
                <div class="log-item">
                    <div class="log-item__date">
                        <?= hb_h(hb_format_datetime((string) $log['created_at'])) ?>
                        · <?= hb_h(HbActivityLog::actorLabel($log)) ?>
                    </div>
                    <div class="log-item__action"><?= hb_h(HbActivityLog::actionLabel((string) $log['action'])) ?></div>
                    <?php if (!empty($log['details'])): ?>
                        <div class="log-item__details"><?= nl2br(hb_h((string) $log['details'])) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php
require dirname(__DIR__) . '/partials/footer.php';
