<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (partner_is_logged_in()) {
    redirect(partner_url('index.php'));
}

$hiba = '';
$uzenet = '';
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$tableReady = nextgen_partners_table_ready(getDb());
$tokenValid = false;

if ($token !== '') {
    $validation = nextgen_partner_password_reset_validate_token(getDb(), $token);
    $tokenValid = $validation['ok'];
    if (!$tokenValid && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $hiba = (string) ($validation['error'] ?? 'Érvénytelen vagy lejárt link.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token !== '') {
    if (!csrf_validate('partner_jelszo_uj')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet.';
    } else {
        $jelszo = (string) ($_POST['jelszo'] ?? '');
        $jelszo2 = (string) ($_POST['jelszo2'] ?? '');
        if ($jelszo === '' || $jelszo2 === '') {
            $hiba = 'Mindkét jelszó mező kitöltése kötelező.';
        } elseif ($jelszo !== $jelszo2) {
            $hiba = 'A két jelszó nem egyezik.';
        } else {
            $result = nextgen_partner_password_reset_complete(getDb(), $token, $jelszo);
            if ($result['ok']) {
                $uzenet = 'Az új jelszó elmentve. Most már bejelentkezhetsz.';
                $tokenValid = false;
            } else {
                $hiba = (string) ($result['error'] ?? 'Jelszó mentése sikertelen.');
            }
        }
    }
}

ob_start();
?>
<?php if ($hiba !== ''): ?><p class="error"><?= h($hiba) ?></p><?php endif; ?>
<?php if ($uzenet !== ''): ?><p class="alert alert-success"><?= h($uzenet) ?></p><?php endif; ?>
<?php if ($tokenValid): ?>
<form method="post" action="">
    <?= csrf_input('partner_jelszo_uj') ?>
    <input type="hidden" name="token" value="<?= h($token) ?>">
    <label for="jelszo">Új jelszó</label>
    <input type="password" id="jelszo" name="jelszo" minlength="8" required autocomplete="new-password">
    <label for="jelszo2">Új jelszó újra</label>
    <input type="password" id="jelszo2" name="jelszo2" minlength="8" required autocomplete="new-password">
    <button type="submit">Jelszó mentése</button>
</form>
<?php elseif ($uzenet === ''): ?>
    <p class="help">Kérj új linket a <a href="<?= h(partner_url('jelszo_emlekezteto.php')) ?>">jelszó emlékeztető</a> oldalon.</p>
<?php endif; ?>
<?php
$authContent = (string) ob_get_clean();
$authTitle = 'Új jelszó beállítása';
$authSubtitle = 'Add meg az új jelszavad';
$authTableReady = $tableReady;
require __DIR__ . '/partials/auth_layout.php';
