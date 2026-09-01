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

if (!nextgen_partner_must_change_password($partner)) {
    redirect(partner_url('index.php'));
}

$hiba = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('partner_jelszo_kotelezo')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet.';
    } else {
        $jelszo = (string) ($_POST['jelszo'] ?? '');
        $jelszo2 = (string) ($_POST['jelszo2'] ?? '');
        if ($jelszo === '' || $jelszo2 === '') {
            $hiba = 'Mindkét jelszó mező kitöltése kötelező.';
        } elseif ($jelszo !== $jelszo2) {
            $hiba = 'A két jelszó nem egyezik.';
        } else {
            $result = nextgen_partner_update_password($db, $partnerId, $jelszo, false);
            if ($result['ok']) {
                unset($_SESSION['partner_must_change_password']);
                flash('success', 'Új jelszó beállítva.');
                redirect(partner_url('index.php'));
            }
            $hiba = (string) ($result['error'] ?? 'Jelszó mentése sikertelen.');
        }
    }
}

ob_start();
?>
<?php if ($hiba !== ''): ?><p class="error"><?= h($hiba) ?></p><?php endif; ?>
<p class="help">Biztonsági okokból új jelszót kell beállítanod, mielőtt folytathatnád.</p>
<form method="post" action="">
    <?= csrf_input('partner_jelszo_kotelezo') ?>
    <label for="jelszo">Új jelszó</label>
    <input type="password" id="jelszo" name="jelszo" minlength="8" required autocomplete="new-password">
    <label for="jelszo2">Új jelszó újra</label>
    <input type="password" id="jelszo2" name="jelszo2" minlength="8" required autocomplete="new-password">
    <button type="submit">Jelszó mentése és tovább</button>
</form>
<p class="help" style="margin-top:1rem;text-align:center;">
    <a href="<?= h(partner_url('logout.php')) ?>">Kijelentkezés</a>
</p>
<?php
$authContent = (string) ob_get_clean();
$authTitle = 'Kötelező jelszócsere';
$authSubtitle = 'Állíts be új jelszót a folytatáshoz';
$authTableReady = true;
require __DIR__ . '/partials/auth_layout.php';
