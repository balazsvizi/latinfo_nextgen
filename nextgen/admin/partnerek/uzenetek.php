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
        if ($action === 'no_reply') {
            $msgId = (int) ($_POST['message_id'] ?? 0);
            $result = nextgen_partner_message_mark_no_reply($db, $msgId);
            if ($result['ok']) {
                flash('success', 'Megjelölve: nem kell válaszolni.');
                redirect(nextgen_url('admin/partnerek/uzenetek.php?partner_id=') . $partnerId);
            }
            $hiba = (string) ($result['error'] ?? 'Művelet sikertelen.');
        } elseif ($partnerId > 0 && $adminId > 0) {
            $result = nextgen_partner_message_send_admin($db, $partnerId, $adminId, (string) ($_POST['message'] ?? ''));
            if ($result['ok']) {
                flash('success', 'Válasz elküldve.');
                redirect(nextgen_url('admin/partnerek/uzenetek.php?partner_id=') . $partnerId);
            }
            $hiba = (string) ($result['error'] ?? 'Küldés sikertelen.');
        }
    }
}

$pageTitle = 'Partner üzenetek';
require_once dirname(__DIR__, 2) . '/partials/header.php';

$threads = nextgen_partner_messages_inbox_threads($db);
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
    <h2>Üzenetek – inbox</h2>
    <?php if ($threads === []): ?>
        <p class="help">Nincs üzenet.</p>
    <?php else: ?>
        <?php foreach ($threads as $thread): ?>
            <?php
            $tid = (int) ($thread['partner_id'] ?? 0);
            $last = $thread['last_message'] ?? [];
            $open = !empty($thread['needs_reply']);
            ?>
            <div class="partner-inbox-card<?= $open ? ' partner-inbox-card--open' : '' ?>">
                <div class="partner-inbox-card__head">
                    <div>
                        <strong>
                            <a href="<?= h(nextgen_url('admin/partnerek/uzenetek.php?partner_id=') . $tid) ?>">
                                <?php
                                $partnerListNev = (string) ($thread['partner_nev'] ?? '');
                                $partnerListKieg = (string) ($thread['partner_kieg_info'] ?? '');
                                require __DIR__ . '/partials/partner_list_name.php';
                                ?>
                            </a>
                        </strong>
                        <span class="text-muted"> – <?= h((string) ($thread['partner_email'] ?? '')) ?></span>
                    </div>
                    <span class="text-muted"><?= h((string) ($thread['last_at'] ?? '')) ?></span>
                </div>
                <p style="margin:0;"><?= h(mb_substr((string) ($last['message'] ?? ''), 0, 200)) ?></p>
                <?php if ($open): ?>
                    <p class="help" style="margin:0.35rem 0 0;">Megválaszolatlan</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($selectedPartner !== null): ?>
<div class="card partner-inbox-thread">
    <h2>Üzenetek: <?php $partner = $selectedPartner; require __DIR__ . '/partials/partner_list_name.php'; ?></h2>
    <div class="partner-messages-list">
        <?php foreach ($threadMessages as $msg): ?>
            <?php
            $isAdmin = ($msg['creator_type'] ?? '') === 'admin';
            $class = $isAdmin ? 'partner-message-item partner-message-item--admin' : 'partner-message-item partner-message-item--partner';
            $author = nextgen_partner_message_author_label($msg, (string) ($selectedPartner['név'] ?? ''));
            ?>
            <div class="<?= h($class) ?>">
                <div class="partner-message-meta"><?= h((string) ($msg['létrehozva'] ?? '')) ?> – <?= h($author) ?></div>
                <div class="partner-message-body"><?= nl2br(h((string) ($msg['message'] ?? ''))) ?></div>
                <?php if (!$isAdmin && empty($msg['nincs_valasz'])): ?>
                <form method="post" class="toolbar" style="margin-top:0.5rem;">
                    <?= csrf_input('partner_admin_messages') ?>
                    <input type="hidden" name="partner_id" value="<?= $partnerId ?>">
                    <input type="hidden" name="_action" value="no_reply">
                    <input type="hidden" name="message_id" value="<?= (int) ($msg['id'] ?? 0) ?>">
                    <button type="submit" class="btn btn-secondary btn-sm">Nem kell válaszolni</button>
                </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <form method="post" style="margin-top:1rem;">
        <?= csrf_input('partner_admin_messages') ?>
        <input type="hidden" name="partner_id" value="<?= $partnerId ?>">
        <input type="hidden" name="_action" value="send">
        <div class="form-group">
            <label for="admin_reply">Válasz a partnernek</label>
            <textarea id="admin_reply" name="message" class="partner-message-textarea" rows="5" required></textarea>
        </div>
        <p class="toolbar"><button type="submit" class="btn btn-primary">Küldés</button></p>
    </form>
</div>
<?php endif; ?>

<?php if ($partnerId > 0): ?>
<?php
$partnerActivityLogGlobal = false;
require __DIR__ . '/partials/activity_log.php';
?>
<?php endif; ?>

<?php require_once dirname(__DIR__, 2) . '/partials/footer.php'; ?>
