<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/routing.php';

partner_redirect_legacy_login_url();

if (partner_is_logged_in()) {
    $partner = partner_current(getDb());
    if ($partner !== null && nextgen_partner_must_change_password($partner)) {
        redirect(partner_url('jelszo_kotelezo.php'));
    }
    redirect(partner_url('index.php'));
}

$hiba = '';
$uzenet = '';
$tableReady = nextgen_partners_table_ready(getDb());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $jelszo = (string) ($_POST['jelszo'] ?? '');
    if (!$tableReady) {
        $hiba = 'A partner portál még nincs beállítva. Kérjük, vedd fel a kapcsolatot az üzemeltetővel.';
    } elseif ($email === '' || $jelszo === '') {
        $hiba = 'Kérjük, add meg az e-mail címet és a jelszót.';
    } elseif (partner_login($email, $jelszo)) {
        $partner = partner_current(getDb());
        if ($partner !== null && nextgen_partner_must_change_password($partner)) {
            redirect(partner_url('jelszo_kotelezo.php'));
        }
        $url = $_SESSION['_partner_redirect_after_login'] ?? partner_url('index.php');
        unset($_SESSION['_partner_redirect_after_login']);
        if ($url !== '' && $url[0] !== '/') {
            $url = rtrim(BASE_URL, '/') . '/' . ltrim($url, '/');
        }
        redirect($url);
    } else {
        $hiba = 'Hibás e-mail cím vagy jelszó.';
    }
}

ob_start();
?>
<?php if ($hiba !== ''): ?><p class="error"><?= h($hiba) ?></p><?php endif; ?>
<?php if ($uzenet !== ''): ?><p class="alert alert-success"><?= h($uzenet) ?></p><?php endif; ?>
<form method="post" action="">
    <label for="email">E-mail cím</label>
    <input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required autofocus autocomplete="username">
    <label for="jelszo">Jelszó</label>
    <input type="password" id="jelszo" name="jelszo" required autocomplete="current-password">
    <button type="submit">Bejelentkezés</button>
</form>
<p class="help" style="margin-top:1rem;text-align:center;">
    <a href="<?= h(partner_url('jelszo_emlekezteto.php')) ?>">Elfelejtett jelszó / új jelszó beállítása</a>
</p>
<?php
$authContent = (string) ob_get_clean();
$authTitle = 'Partner bejelentkezés';
$authSubtitle = 'Partner portál – szervezők, DJ-k, üzenetek';
$authTableReady = $tableReady;
$authHideLoginLink = true;
require __DIR__ . '/partials/auth_layout.php';
