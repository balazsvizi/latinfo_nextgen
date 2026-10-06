<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (user_is_logged_in()) {
    user_redirect_after_auth_success();
}

$oauthError = trim((string) ($_GET['error'] ?? ''));
if ($oauthError !== '') {
    $msg = $oauthError === 'access_denied'
        ? 'A belépés megszakítva.'
        : 'OAuth hiba: ' . $oauthError;
    flash('error', $msg);
    redirect(user_url('login.php'));
}

$code = trim((string) ($_GET['code'] ?? ''));
$state = trim((string) ($_GET['state'] ?? ''));
$provider = strtolower(trim((string) ($_SESSION['_latinfo_oauth_provider'] ?? '')));

if ($provider === '' || $code === '' || $state === '') {
    flash('error', 'Érvénytelen OAuth visszatérés. Próbáld újra.');
    redirect(user_url('login.php'));
}

if (!rate_limit_allow(rate_limit_client_key('user_oauth_callback'), 20, 900)) {
    flash('error', 'Túl sok próbálkozás. Próbáld újra később.');
    redirect(user_url('login.php'));
}

$complete = latinfo_oauth_complete($provider, $code, $state);
if (!$complete['ok'] || empty($complete['identity'])) {
    flash('error', $complete['error'] !== '' ? $complete['error'] : 'A belépés sikertelen.');
    redirect(user_url('login.php'));
}

$db = getDb();
$result = latinfo_user_upsert_from_oauth($db, $complete['identity']);
if (!$result['ok'] || !is_array($result['user'])) {
    flash('error', $result['error'] !== '' ? $result['error'] : 'A belépés sikertelen.');
    redirect(user_url('login.php'));
}

$userId = (int) ($result['user']['id'] ?? 0);
$privacyPending = !empty($_SESSION['_latinfo_oauth_privacy_pending']);
$newsletterPending = !empty($_SESSION['_latinfo_oauth_newsletter_pending']);
unset($_SESSION['_latinfo_oauth_privacy_pending'], $_SESSION['_latinfo_oauth_newsletter_pending']);

if ($userId > 0 && $privacyPending) {
    $meta = latinfo_user_consent_request_meta();
    $meta['source'] = 'oauth_signup';
    if (!latinfo_user_has_current_privacy_consent($db, $userId)) {
        latinfo_user_consent_record(
            $db,
            $userId,
            LATINFO_CONSENT_PRIVACY,
            true,
            latinfo_privacy_policy_version(),
            $meta
        );
    }
    if ($newsletterPending && !latinfo_user_consent_is_granted($db, $userId, LATINFO_CONSENT_NEWSLETTER)) {
        latinfo_user_consent_record(
            $db,
            $userId,
            LATINFO_CONSENT_NEWSLETTER,
            true,
            '1.0',
            $meta
        );
        latinfo_mailing_subscribe_defaults($db, $userId, null, 'oauth_signup');
    }
}

user_login_from_row($result['user']);
user_redirect_after_auth_success();
