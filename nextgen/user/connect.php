<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (user_is_logged_in()) {
    user_redirect_after_auth_success();
}

$provider = strtolower(trim((string) ($_GET['provider'] ?? '')));
if (!in_array($provider, latinfo_oauth_providers(), true) || !latinfo_oauth_provider_enabled($provider)) {
    flash('error', 'Ez a belépési mód nincs beállítva.');
    redirect(user_url('login.php'));
}

$fromSignup = (string) ($_GET['from'] ?? '') === 'signup';
$acceptedPrivacy = (string) ($_GET['privacy'] ?? '') === '1';
if ($fromSignup && !$acceptedPrivacy) {
    flash('error', 'Az adatkezelési tájékoztató elfogadása kötelező a regisztrációhoz.');
    redirect(user_url('signup.php'));
}

$_SESSION['_latinfo_oauth_privacy_pending'] = ($fromSignup && $acceptedPrivacy) ? 1 : 0;
$_SESSION['_latinfo_oauth_newsletter_pending'] = ($fromSignup && (string) ($_GET['newsletter'] ?? '') === '1') ? 1 : 0;

if (!rate_limit_allow(rate_limit_client_key('user_oauth_connect'), 20, 900)) {
    flash('error', 'Túl sok próbálkozás. Próbáld újra később.');
    redirect(user_url('login.php'));
}

$url = latinfo_oauth_begin($provider);
if ($url === '') {
    flash('error', 'Nem sikerült elindítani a belépést.');
    redirect(user_url('login.php'));
}

redirect($url);
