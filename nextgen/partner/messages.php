<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
partner_require_login();

$db = getDb();
$partnerId = partner_current_id();
$partner = partner_current($db);
if ($partner === null) {
    redirect(partner_url('login.php'));
}

$hiba = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('partner_uzenetek')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet.';
    } else {
        $result = nextgen_partner_message_send_partner($db, $partnerId, (string) ($_POST['message'] ?? ''));
        if ($result['ok']) {
            flash('success', 'Üzenet elküldve.');
            redirect(partner_url('uzenetek.php'));
        }
        $hiba = (string) ($result['error'] ?? 'Küldés sikertelen.');
    }
}

$messages = nextgen_partner_messages_for_partner($db, $partnerId);
$partnerName = (string) ($partner['név'] ?? '');
$pending = partner_portal_admin_reply_pending($db, $partnerId);

$pageTitle = 'Üzenetek';
$activeNav = 'messages';
require_once __DIR__ . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>

<div class="partner-page-head">
    <div>
        <h1 class="partner-page-title">Üzenetek</h1>
        <p class="partner-page-lead">
            Írj a Latinfo csapatnak — a teljes beszélgetés itt marad, időrendben.
            <?php if ($pending): ?><strong>Új válasz érkezett az admintól.</strong><?php endif; ?>
        </p>
    </div>
</div>

<div class="card partner-messages-block">
    <form method="post" class="partner-message-compose">
        <?= csrf_input('partner_uzenetek') ?>
        <label class="partner-message-form-label" for="partner_message">Új üzenet</label>
        <textarea id="partner_message" name="message" class="partner-message-textarea" rows="5" required placeholder="Írd ide a kérdésed vagy üzeneted…"></textarea>
        <p class="toolbar" style="margin-top:0.75rem;">
            <button type="submit" class="btn btn-primary">Küldés</button>
        </p>
    </form>

    <div class="partner-messages-thread" aria-live="polite">
        <?php if ($messages === []): ?>
            <p class="help">Még nincs üzenet. Írj bátran — pl. esemény, megjelenés, számlázás vagy technikai kérdés.</p>
        <?php else: ?>
            <?php foreach ($messages as $msg): ?>
                <?php
                $isAdmin = ($msg['creator_type'] ?? '') === 'admin';
                $class = $isAdmin
                    ? 'partner-message-item partner-message-item--admin'
                    : 'partner-message-item partner-message-item--partner';
                $author = nextgen_partner_message_author_label($msg, $partnerName);
                $noReply = !empty($msg['nincs_valasz']);
                ?>
                <article class="<?= h($class) ?>">
                    <header class="partner-message-meta">
                        <span><?= h($author) ?></span>
                        <time datetime="<?= h((string) ($msg['létrehozva'] ?? '')) ?>"><?= h((string) ($msg['létrehozva'] ?? '')) ?></time>
                        <?php if ($noReply && $isAdmin === false): ?>
                            <span class="partner-message-tag">Nem kell válasz</span>
                        <?php endif; ?>
                    </header>
                    <div class="partner-message-body"><?= nl2br(h((string) ($msg['message'] ?? ''))) ?></div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
