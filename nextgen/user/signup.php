<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (user_is_logged_in()) {
    redirect(user_url('index.php'));
}

$hiba = '';
$tableReady = latinfo_users_ensure_schema(getDb());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('user_signup')) {
        $hiba = 'Érvénytelen kérés. Frissítsd az oldalt, majd próbáld újra.';
    } elseif (!rate_limit_allow(rate_limit_client_key('user_signup'), 5, 900)) {
        $hiba = 'Túl sok próbálkozás. Próbáld újra később.';
    } elseif (!$tableReady) {
        $hiba = 'A felhasználói rendszer még nincs beállítva.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        $jelszo = (string) ($_POST['jelszo'] ?? '');
        $jelszo2 = (string) ($_POST['jelszo2'] ?? '');
        if ($jelszo !== $jelszo2) {
            $hiba = 'A két jelszó nem egyezik.';
        } else {
            $result = latinfo_user_register(getDb(), $email, $name, $jelszo);
            if ($result['ok'] && is_array($result['user'])) {
                user_login_from_row($result['user']);
                redirect(user_url('index.php'));
            }
            $hiba = $result['error'] !== '' ? $result['error'] : 'A regisztráció sikertelen.';
        }
    }
}

ob_start();
?>
<?php if ($hiba !== ''): ?><p class="error"><?= h($hiba) ?></p><?php endif; ?>
<form method="post" action="">
    <?= csrf_input('user_signup') ?>
    <label for="name">Név</label>
    <input type="text" id="name" name="name" value="<?= h($_POST['name'] ?? '') ?>" required maxlength="160" autocomplete="name">
    <label for="email">E-mail cím</label>
    <input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required autocomplete="email">
    <label for="jelszo">Jelszó (min. 8 karakter)</label>
    <div class="password-toggle-wrap">
        <input type="password" id="jelszo" name="jelszo" required minlength="8" autocomplete="new-password">
        <?php
        $passwordToggleInputId = 'jelszo';
        require dirname(__DIR__) . '/partials/password_toggle_button.php';
        ?>
    </div>
    <label for="jelszo2">Jelszó mégegyszer</label>
    <input type="password" id="jelszo2" name="jelszo2" required minlength="8" autocomplete="new-password">
    <button type="submit">Regisztráció</button>
</form>
<p class="user-auth-switch">
    Van már fiókod? <a href="<?= h(user_url('login.php')) ?>">Bejelentkezés</a>
</p>
<?php
$authContent = (string) ob_get_clean();
$authTitle = 'Regisztráció';
$authSubtitle = 'Latinfo.hu fiók létrehozása';
$authTableReady = $tableReady;
require __DIR__ . '/partials/auth_layout.php';
