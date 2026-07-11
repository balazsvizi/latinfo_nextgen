<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (hb_is_logged_in()) {
    hb_redirect(hb_url('index.php'));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = hb_post_string('email');
    $password = (string) ($_POST['password'] ?? '');
    if ($email === '' || $password === '') {
        $error = hb_t('login.required');
    } elseif (hb_login(hb_get_db(), $email, $password)) {
        $url = $_SESSION['_hb_redirect_after_login'] ?? hb_url('index.php');
        unset($_SESSION['_hb_redirect_after_login']);
        hb_redirect($url);
    } else {
        $error = hb_t('login.error');
    }
}

$authTitle = hb_t('login.title');
require __DIR__ . '/partials/auth_layout_start.php';
?>
<div class="auth-card">
    <h1 class="auth-brand"><?= hb_h(HB_APP_NAME) ?></h1>
    <p class="auth-sub"><?= hb_h(hb_t('login.subtitle')) ?></p>

    <div class="hb-lang-switch" style="margin-bottom:1rem;">
        <a href="<?= hb_h(hb_url('set_lang.php?lang=hu')) ?>" class="hb-lang-switch__link<?= hb_current_locale() === 'hu' ? ' is-active' : '' ?>">HU</a>
        <a href="<?= hb_h(hb_url('set_lang.php?lang=en')) ?>" class="hb-lang-switch__link<?= hb_current_locale() === 'en' ? ' is-active' : '' ?>">EN</a>
    </div>

    <?php if ($error !== ''): ?>
        <p class="alert alert-error"><?= hb_h($error) ?></p>
    <?php endif; ?>

    <form method="post" action="">
        <div class="form-group">
            <label for="email"><?= hb_h(hb_t('login.email')) ?></label>
            <input type="email" id="email" name="email" value="<?= hb_h(hb_post_string('email')) ?>" required autofocus autocomplete="username">
        </div>
        <div class="form-group">
            <label for="password"><?= hb_h(hb_t('login.password')) ?></label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-primary btn-block"><?= hb_h(hb_t('login.submit')) ?></button>
    </form>
</div>
<?php
require __DIR__ . '/partials/auth_layout_end.php';
