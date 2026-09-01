<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (partner_is_logged_in()) {
    redirect(partner_url('index.php'));
}

$hiba = '';
$uzenet = '';
$tableReady = nextgen_partners_table_ready(getDb());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('partner_jelszo_emlekezteto')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet.';
    } else {
        $result = nextgen_partner_password_reset_request(getDb(), (string) ($_POST['email'] ?? ''));
        if ($result['ok']) {
            $uzenet = (string) ($result['message'] ?? 'Ha az e-mail cím regisztrálva van, hamarosan kapsz egy linket.');
        } else {
            $hiba = (string) ($result['error'] ?? 'A kérés sikertelen.');
        }
    }
}

ob_start();
?>
<?php if ($hiba !== ''): ?><p class="error"><?= h($hiba) ?></p><?php endif; ?>
<?php if ($uzenet !== ''): ?><p class="alert alert-success"><?= h($uzenet) ?></p><?php endif; ?>
<form method="post" action="">
    <?= csrf_input('partner_jelszo_emlekezteto') ?>
    <label for="email">E-mail cím</label>
    <input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required autofocus autocomplete="username">
    <p class="help">Ha a fiókodhoz tartozik érvényes e-mail cím, küldünk egy linket az új jelszó beállításához.</p>
    <button type="submit">Link küldése</button>
</form>
<?php
$authContent = (string) ob_get_clean();
$authTitle = 'Jelszó emlékeztető';
$authSubtitle = 'Új jelszó beállítása e-mailben kapott linkkel';
$authTableReady = $tableReady;
require __DIR__ . '/partials/auth_layout.php';
