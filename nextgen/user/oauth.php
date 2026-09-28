<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (user_is_logged_in()) {
    redirect(user_url('index.php'));
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

$result = latinfo_user_upsert_from_oauth(getDb(), $complete['identity']);
if (!$result['ok'] || !is_array($result['user'])) {
    flash('error', $result['error'] !== '' ? $result['error'] : 'A belépés sikertelen.');
    redirect(user_url('login.php'));
}

user_login_from_row($result['user']);
$url = user_safe_post_login_redirect($_SESSION['_user_redirect_after_login'] ?? null);
unset($_SESSION['_user_redirect_after_login']);
redirect($url);
