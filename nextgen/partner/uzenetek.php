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

$pageTitle = 'Üzenetek';
$activeNav = 'messages';
require_once __DIR__ . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>

<div class="card partner-messages-block">
    <h1 class="card-title">Üzenetek</h1>
    <p class="help">Írj a Latinfo csapatnak kérdést vagy üzenetet. A válaszok itt jelennek meg.</p>

    <form method="post">
        <?= csrf_input('partner_uzenetek') ?>
        <p class="partner-message-form-label">Új üzenet</p>
        <textarea name="message" class="partner-message-textarea" rows="5" required placeholder="Írd ide az üzeneted…"></textarea>
        <p class="toolbar" style="margin-top:0.75rem;">
            <button type="submit" class="btn btn-primary">Küldés</button>
        </p>
    </form>

    <div class="partner-messages-list">
        <?php if ($messages === []): ?>
            <p class="help">Még nincs üzenet.</p>
        <?php else: ?>
            <?php foreach ($messages as $msg): ?>
                <?php
                $isAdmin = ($msg['creator_type'] ?? '') === 'admin';
                $class = $isAdmin ? 'partner-message-item partner-message-item--admin' : 'partner-message-item partner-message-item--partner';
                $author = nextgen_partner_message_author_label($msg, $partnerName);
                ?>
                <div class="<?= h($class) ?>">
                    <div class="partner-message-meta">
                        <?= h((string) ($msg['létrehozva'] ?? '')) ?> – <?= h($author) ?>
                    </div>
                    <div class="partner-message-body"><?= nl2br(h((string) ($msg['message'] ?? ''))) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
