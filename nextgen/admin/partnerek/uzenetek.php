<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/init.php';
require_once dirname(__DIR__, 2) . '/lib/partner/partners.php';
require_once dirname(__DIR__, 2) . '/lib/partner/messages.php';
require_once dirname(__DIR__, 2) . '/lib/partner/activity_log.php';
requireLogin();

$db = getDb();
$adminId = (int) ($_SESSION['admin_id'] ?? 0);
$partnerId = (int) ($_GET['partner_id'] ?? $_POST['partner_id'] ?? 0);
$hiba = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('partner_admin_messages')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet.';
    } else {
        $action = (string) ($_POST['_action'] ?? 'send');
        $redirectPartnerId = $partnerId > 0 ? $partnerId : (int) ($_POST['partner_id'] ?? 0);
        $redirectUrl = nextgen_url('admin/partnerek/uzenetek.php')
            . ($redirectPartnerId > 0 ? '?partner_id=' . $redirectPartnerId : '');

        if ($action === 'no_reply') {
            $msgId = (int) ($_POST['message_id'] ?? 0);
            $result = nextgen_partner_message_mark_no_reply($db, $msgId, 'admin', $adminId);
            if ($result['ok']) {
                flash('success', 'Megjelölve: nem kell válaszolni.');
                redirect($redirectUrl);
            }
            $hiba = (string) ($result['error'] ?? 'Művelet sikertelen.');
        } elseif ($partnerId > 0 && $adminId > 0) {
            $result = nextgen_partner_message_send_admin($db, $partnerId, $adminId, (string) ($_POST['message'] ?? ''));
            if ($result['ok']) {
                flash('success', 'Válasz elküldve.');
                redirect($redirectUrl);
            }
            $hiba = (string) ($result['error'] ?? 'Küldés sikertelen.');
        }
    }
}

$pageTitle = 'Partner üzenetek';
require_once dirname(__DIR__, 2) . '/partials/header.php';

$threads = nextgen_partner_messages_inbox_threads($db);
$unreadCount = nextgen_partner_unread_reply_count($db);
$selectedPartner = $partnerId > 0 ? nextgen_partner_by_id($db, $partnerId) : null;
$threadMessages = $partnerId > 0 ? nextgen_partner_messages_for_partner($db, $partnerId) : [];
$partnerActivityLog = $partnerId > 0 ? nextgen_partner_activity_log_for_partner($db, $partnerId) : [];
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>

<p class="toolbar">
    <a href="<?= h(nextgen_url('admin/partnerek/')) ?>" class="btn btn-secondary btn-sm">← Partnerek</a>
</p>

<div class="card">
    <h2>
        Üzenetek – inbox
        <?php if ($unreadCount > 0): ?>
            <span class="partner-inbox-open-count"><?= (int) $unreadCount ?> megválaszolatlan</span>
        <?php endif; ?>
    </h2>
    <?php if ($threads === []): ?>
        <p class="help">Nincs üzenet.</p>
    <?php else: ?>
        <div class="partner-inbox-list">
        <?php foreach ($threads as $thread): ?>
            <?php
            $tid = (int) ($thread['partner_id'] ?? 0);
            $last = $thread['last_message'] ?? [];
            $open = !empty($thread['needs_reply'])
                && ($last['creator_type'] ?? '') === 'partner'
                && empty($last['nincs_valasz']);
            $threadUrl = nextgen_url('admin/partnerek/uzenetek.php?partner_id=') . $tid;
            $replyUrl = $threadUrl . '#admin_reply';
            $lastMsgId = (int) ($last['id'] ?? 0);
            ?>
            <div class="partner-inbox-card<?= $open ? ' partner-inbox-card--open' : ' partner-inbox-card--done' ?>">
                <div class="partner-inbox-card__head">
                    <div class="partner-inbox-card__title-wrap">
                        <?php if ($open): ?>
                            <span class="partner-inbox-card__badge">Megválaszolatlan</span>
                        <?php endif; ?>
                        <strong>
                            <a href="<?= h($threadUrl) ?>">
                                <?php
                                $partnerListNev = (string) ($thread['partner_nev'] ?? '');
                                $partnerListKieg = (string) ($thread['partner_kieg_info'] ?? '');
                                require __DIR__ . '/partials/partner_list_name.php';
                                ?>
                            </a>
                        </strong>
                        <span class="text-muted"> – <?= h((string) ($thread['partner_email'] ?? '')) ?></span>
                    </div>
                    <span class="text-muted partner-inbox-card__date"><?= h((string) ($thread['last_at'] ?? '')) ?></span>
                </div>
                <p class="partner-inbox-card__preview"><?= h(mb_substr((string) ($last['message'] ?? ''), 0, 200)) ?></p>
                <div class="partner-inbox-card__actions toolbar">
                    <?php if ($open && $lastMsgId > 0): ?>
                        <a href="<?= h($replyUrl) ?>" class="btn btn-primary btn-sm">Válaszolok</a>
                        <form method="post" class="partner-inbox-card__action-form">
                            <?= csrf_input('partner_admin_messages') ?>
                            <input type="hidden" name="partner_id" value="<?= $tid ?>">
                            <input type="hidden" name="_action" value="no_reply">
                            <input type="hidden" name="message_id" value="<?= $lastMsgId ?>">
                            <button type="submit" class="btn btn-secondary btn-sm">Nem kell megválaszolni</button>
                        </form>
                    <?php else: ?>
                        <a href="<?= h($threadUrl) ?>" class="btn btn-secondary btn-sm">Megtekintés</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($selectedPartner !== null): ?>
<div class="card partner-inbox-thread" id="partner-thread">
    <h2>Üzenetek: <?php $partner = $selectedPartner; require __DIR__ . '/partials/partner_list_name.php'; ?></h2>

    <form method="post" class="partner-inbox-compose" id="admin_reply">
        <?= csrf_input('partner_admin_messages') ?>
        <input type="hidden" name="partner_id" value="<?= $partnerId ?>">
        <input type="hidden" name="_action" value="send">
        <div class="form-group">
            <label for="admin_reply_text">Válasz a partnernek</label>
            <textarea id="admin_reply_text" name="message" class="partner-message-textarea partner-message-textarea--admin-compose" rows="12" required></textarea>
        </div>
        <p class="toolbar"><button type="submit" class="btn btn-primary">Küldés</button></p>
    </form>

    <div class="partner-messages-list partner-inbox-thread__history">
        <?php foreach ($threadMessages as $msg): ?>
            <?php
            $isAdmin = ($msg['creator_type'] ?? '') === 'admin';
            $needsReply = !$isAdmin && nextgen_partner_message_needs_admin_reply($msg, $threadMessages);
            $class = $isAdmin ? 'partner-message-item partner-message-item--admin' : 'partner-message-item partner-message-item--partner';
            if ($needsReply) {
                $class .= ' partner-message-item--pending';
            }
            $author = nextgen_partner_message_author_label($msg, (string) ($selectedPartner['név'] ?? ''));
            $noReply = !empty($msg['nincs_valasz']);
            ?>
            <div class="<?= h($class) ?>">
                <div class="partner-message-meta">
                    <?= h((string) ($msg['létrehozva'] ?? '')) ?> – <?= h($author) ?>
                    <?php if ($needsReply): ?>
                        <span class="partner-message-tag partner-message-tag--pending">Megválaszolatlan</span>
                    <?php elseif ($noReply): ?>
                        <span class="partner-message-tag">Nem kell válasz</span>
                    <?php endif; ?>
                </div>
                <div class="partner-message-body"><?= nl2br(h((string) ($msg['message'] ?? ''))) ?></div>
                <?php if ($needsReply): ?>
                <div class="partner-inbox-card__actions toolbar" style="margin-top:0.5rem;">
                    <a href="#admin_reply" class="btn btn-primary btn-sm">Válaszolok</a>
                    <form method="post" class="partner-inbox-card__action-form">
                        <?= csrf_input('partner_admin_messages') ?>
                        <input type="hidden" name="partner_id" value="<?= $partnerId ?>">
                        <input type="hidden" name="_action" value="no_reply">
                        <input type="hidden" name="message_id" value="<?= (int) ($msg['id'] ?? 0) ?>">
                        <button type="submit" class="btn btn-secondary btn-sm">Nem kell megválaszolni</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($partnerId > 0): ?>
<?php
$partnerActivityLogGlobal = false;
require __DIR__ . '/partials/activity_log.php';
?>
<?php endif; ?>

<?php require_once dirname(__DIR__, 2) . '/partials/footer.php'; ?>
