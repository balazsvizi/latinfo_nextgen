<?php
declare(strict_types=1);

/**
 * Adatkezelési tájékoztató elfogadása (hiányzó / új verzió esetén kötelező).
 */

require_once __DIR__ . '/bootstrap.php';

if (!user_is_logged_in()) {
    redirect(user_login_url());
}

$db = getDb();
$user = user_current($db);
if ($user === null) {
    redirect(user_url('login.php'));
}

$userId = (int) $user['id'];
if (latinfo_user_has_current_privacy_consent($db, $userId)) {
    $url = user_safe_post_login_redirect($_SESSION['_user_redirect_after_login'] ?? null);
    unset($_SESSION['_user_redirect_after_login']);
    redirect($url);
}

$hiba = '';
$policyUrl = latinfo_privacy_policy_url();
$policyVersion = latinfo_privacy_policy_version();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('user_privacy_accept')) {
        $hiba = 'Érvénytelen kérés. Frissítsd az oldalt, majd próbáld újra.';
    } elseif (empty($_POST['accept_privacy'])) {
        $hiba = 'Az adatkezelési tájékoztató elfogadása szükséges a fiók használatához.';
    } else {
        $meta = latinfo_user_consent_request_meta();
        $meta['source'] = 'privacy_gate';
        $ok = latinfo_user_consent_record(
            $db,
            $userId,
            LATINFO_CONSENT_PRIVACY,
            true,
            $policyVersion,
            $meta
        );
        if ($ok) {
            $url = user_safe_post_login_redirect($_SESSION['_user_redirect_after_login'] ?? null);
            unset($_SESSION['_user_redirect_after_login']);
            redirect($url);
        }
        $hiba = 'A mentés sikertelen. Próbáld újra később.';
    }
}

ob_start();
?>
<?php if ($hiba !== ''): ?><p class="error"><?= h($hiba) ?></p><?php endif; ?>
<p class="user-privacy-lead">
    A fiók használatához el kell fogadnod az aktuális
    <a href="<?= h($policyUrl) ?>" target="_blank" rel="noopener">adatkezelési tájékoztatót</a>
    (verzió <?= h($policyVersion) ?>).
</p>
<form method="post" action="">
    <?= csrf_input('user_privacy_accept') ?>
    <label class="user-consent-check">
        <input type="checkbox" name="accept_privacy" value="1" required>
        <span>Elolvastam és elfogadom az <a href="<?= h($policyUrl) ?>" target="_blank" rel="noopener">adatkezelési tájékoztatót</a>.</span>
    </label>
    <button type="submit">Elfogadom és folytatom</button>
</form>
<p class="user-auth-switch">
    <a href="<?= h(user_url('logout.php')) ?>">Kijelentkezés</a>
</p>
<?php
$authContent = (string) ob_get_clean();
$authTitle = 'Adatkezelés';
$authSubtitle = 'Hozzájárulás szükséges';
$authTableReady = true;
$authShowOauth = false;
require __DIR__ . '/partials/auth_layout.php';
