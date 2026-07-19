<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
partner_require_login();

$db = getDb();
partner_refresh_session_from_db($db);
$partner = partner_current($db);
if ($partner === null) {
    redirect(partner_url('login.php'));
}

$pageTitle = 'Kezdőlap';
$activeNav = 'home';
require_once __DIR__ . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="card partner-dashboard-card">
    <h1 class="card-title">Szia!</h1>
    <p class="help">Üdvözöllek a Partnerportálon<?= partner_session_display_name() !== '' ? ', ' . h(partner_session_display_name()) : '' ?>.</p>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
