<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (user_is_logged_in()) {
    redirect(user_url('index.php'));
}

$hiba = '';
$tableReady = latinfo_users_table_ready(getDb());
$flashError = flash('error');
if (is_string($flashError) && $flashError !== '') {
    $hiba = $flashError;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('user_login')) {
        $hiba = 'Érvénytelen kérés. Frissítsd az oldalt, majd próbáld újra.';
    } elseif (!rate_limit_allow(rate_limit_client_key('user_login'), 8, 900)) {
        $hiba = 'Túl sok sikertelen próbálkozás. Próbáld újra később.';
    } elseif (!$tableReady) {
        $hiba = 'A felhasználói rendszer még nincs beállítva.';
    } elseif (($email = trim((string) ($_POST['email'] ?? ''))) === '' || ($jelszo = (string) ($_POST['jelszo'] ?? '')) === '') {
        $hiba = 'Add meg az e-mail címet és a jelszót.';
    } elseif (user_login_with_password($email, $jelszo)) {
        $url = user_safe_post_login_redirect($_SESSION['_user_redirect_after_login'] ?? null);
        unset($_SESSION['_user_redirect_after_login']);
        redirect($url);
    } else {
        $hiba = 'Hibás e-mail cím vagy jelszó.';
    }
}

ob_start();
?>
<?php if ($hiba !== ''): ?><p class="error"><?= h($hiba) ?></p><?php endif; ?>
<form method="post" action="">
    <?= csrf_input('user_login') ?>
    <label for="email">E-mail cím</label>
    <input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required autofocus autocomplete="username">
    <label for="jelszo">Jelszó</label>
    <div class="password-toggle-wrap">
        <input type="password" id="jelszo" name="jelszo" required autocomplete="current-password">
        <?php
        $passwordToggleInputId = 'jelszo';
        require dirname(__DIR__) . '/partials/password_toggle_button.php';
        ?>
    </div>
    <button type="submit">Bejelentkezés</button>
</form>
<p class="user-auth-switch">
    Nincs még fiókod? <a href="<?= h(user_url('signup.php')) ?>">Regisztráció</a>
</p>
<?php
$authContent = (string) ob_get_clean();
$authTitle = 'Bejelentkezés';
$authSubtitle = 'Latinfo.hu fiók';
$authTableReady = $tableReady;
require __DIR__ . '/partials/auth_layout.php';
