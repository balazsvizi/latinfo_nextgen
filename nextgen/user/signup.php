<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$returnGet = trim((string) ($_GET['return'] ?? ''));
if ($returnGet !== '' && ($returnGet[0] ?? '') === '/') {
    $_SESSION['_user_redirect_after_login'] = user_safe_post_login_redirect($returnGet);
}

if (user_is_logged_in()) {
    user_redirect_after_auth_success();
}

$hiba = '';
$db = getDb();
$tableReady = latinfo_users_ensure_schema($db);
latinfo_user_consents_ensure_schema($db);
$policyUrl = latinfo_privacy_policy_url();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('user_signup')) {
        $hiba = 'Érvénytelen kérés. Frissítsd az oldalt, majd próbáld újra.';
    } elseif (!rate_limit_allow(rate_limit_client_key('user_signup'), 5, 900)) {
        $hiba = 'Túl sok próbálkozás. Próbáld újra később.';
    } elseif (!$tableReady) {
        $hiba = 'A felhasználói rendszer még nincs beállítva.';
    } elseif (empty($_POST['accept_privacy'])) {
        $hiba = 'Az adatkezelési tájékoztató elfogadása kötelező.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        $jelszo = (string) ($_POST['jelszo'] ?? '');
        $jelszo2 = (string) ($_POST['jelszo2'] ?? '');
        $wantNewsletter = !empty($_POST['accept_newsletter']);
        if ($jelszo !== $jelszo2) {
            $hiba = 'A két jelszó nem egyezik.';
        } else {
            $result = latinfo_user_register($db, $email, $name, $jelszo);
            if ($result['ok'] && is_array($result['user'])) {
                $userId = (int) ($result['user']['id'] ?? 0);
                $meta = latinfo_user_consent_request_meta();
                $meta['source'] = 'signup';
                latinfo_user_consent_record(
                    $db,
                    $userId,
                    LATINFO_CONSENT_PRIVACY,
                    true,
                    latinfo_privacy_policy_version(),
                    $meta
                );
                if ($wantNewsletter) {
                    latinfo_user_consent_record(
                        $db,
                        $userId,
                        LATINFO_CONSENT_NEWSLETTER,
                        true,
                        '1.0',
                        $meta
                    );
                    latinfo_mailing_subscribe_defaults($db, $userId, null, 'signup');
                }
                user_login_from_row($result['user']);
                user_redirect_after_auth_success();
            }
            $hiba = $result['error'] !== '' ? $result['error'] : 'A regisztráció sikertelen.';
        }
    }
}

$acceptPrivacyChecked = !empty($_POST['accept_privacy']);
$acceptNewsletterChecked = !empty($_POST['accept_newsletter']);

ob_start();
?>
<?php if ($hiba !== ''): ?><p class="error"><?= h($hiba) ?></p><?php endif; ?>
<form method="post" action="" id="user-signup-form" data-require-privacy="1">
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
    <label class="user-consent-check">
        <input type="checkbox" name="accept_privacy" id="accept_privacy" value="1" required<?= $acceptPrivacyChecked ? ' checked' : '' ?>>
        <span>Elolvastam és elfogadom az <a href="<?= h($policyUrl) ?>" target="_blank" rel="noopener">adatkezelési tájékoztatót</a>. <em>(kötelező)</em></span>
    </label>
    <label class="user-consent-check">
        <input type="checkbox" name="accept_newsletter" id="accept_newsletter" value="1"<?= $acceptNewsletterChecked ? ' checked' : '' ?>>
        <span>Feliratkozom a Latinfo hírekre és kedvenceim buli-értesítéseire. <em>(opcionális – a fiókban később finomhangolható)</em></span>
    </label>
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
$authRequirePrivacyForOauth = true;
require __DIR__ . '/partials/auth_layout.php';
