<?php
declare(strict_types=1);

/**
 * Publikus user OAuth (Google + Facebook) – nem az admin Drive backup OAuth.
 */

/** @return list<string> */
function latinfo_oauth_providers(): array
{
    return ['google', 'facebook'];
}

function latinfo_oauth_provider_enabled(string $provider): bool
{
    $provider = strtolower(trim($provider));
    if ($provider === 'google') {
        return latinfo_oauth_google_client_id() !== '' && latinfo_oauth_google_client_secret() !== '';
    }
    if ($provider === 'facebook') {
        return latinfo_oauth_facebook_app_id() !== '' && latinfo_oauth_facebook_app_secret() !== '';
    }

    return false;
}

function latinfo_oauth_google_client_id(): string
{
    return trim((string) (defined('GOOGLE_LOGIN_CLIENT_ID') ? GOOGLE_LOGIN_CLIENT_ID : ''));
}

function latinfo_oauth_google_client_secret(): string
{
    return trim((string) (defined('GOOGLE_LOGIN_CLIENT_SECRET') ? GOOGLE_LOGIN_CLIENT_SECRET : ''));
}

function latinfo_oauth_facebook_app_id(): string
{
    return preg_replace('/\D+/', '', (string) (defined('FACEBOOK_APP_ID') ? FACEBOOK_APP_ID : '')) ?? '';
}

function latinfo_oauth_facebook_app_secret(): string
{
    return trim((string) (defined('FACEBOOK_APP_SECRET') ? FACEBOOK_APP_SECRET : ''));
}

function latinfo_oauth_callback_url(): string
{
    return ng_absolute_url(user_url('oauth.php'));
}

/**
 * @return array{ok:bool,error:string,body:string,http_code:int}
 */
function latinfo_oauth_http_post(string $url, array $fields): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'cURL nincs engedélyezve.', 'body' => '', 'http_code' => 0];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_TIMEOUT => 30,
    ]);
    $body = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if (!is_string($body) || $body === '') {
        return ['ok' => false, 'error' => $err !== '' ? $err : 'Üres válasz.', 'body' => '', 'http_code' => $httpCode];
    }

    return ['ok' => $httpCode >= 200 && $httpCode < 300, 'error' => '', 'body' => $body, 'http_code' => $httpCode];
}

/**
 * @return array{ok:bool,error:string,body:string,http_code:int}
 */
function latinfo_oauth_http_get(string $url, array $headers = []): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'cURL nincs engedélyezve.', 'body' => '', 'http_code' => 0];
    }
    $ch = curl_init($url);
    $hdrs = array_merge(['Accept: application/json'], $headers);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $hdrs,
        CURLOPT_TIMEOUT => 30,
    ]);
    $body = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if (!is_string($body) || $body === '') {
        return ['ok' => false, 'error' => $err !== '' ? $err : 'Üres válasz.', 'body' => '', 'http_code' => $httpCode];
    }

    return ['ok' => $httpCode >= 200 && $httpCode < 300, 'error' => '', 'body' => $body, 'http_code' => $httpCode];
}

function latinfo_oauth_begin(string $provider): string
{
    $provider = strtolower(trim($provider));
    if (!in_array($provider, latinfo_oauth_providers(), true) || !latinfo_oauth_provider_enabled($provider)) {
        return '';
    }

    $state = bin2hex(random_bytes(16));
    $_SESSION['_latinfo_oauth_state'] = $state;
    $_SESSION['_latinfo_oauth_provider'] = $provider;

    $redirectUri = latinfo_oauth_callback_url();

    if ($provider === 'google') {
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => latinfo_oauth_google_client_id(),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ]);
    }

    return 'https://www.facebook.com/v21.0/dialog/oauth?' . http_build_query([
        'client_id' => latinfo_oauth_facebook_app_id(),
        'redirect_uri' => $redirectUri,
        'state' => $state,
        'scope' => 'email,public_profile',
        'response_type' => 'code',
    ]);
}

/**
 * @return array{ok:bool,identity?:array{provider:string,provider_user_id:string,email:?string,name:?string,avatar_url:?string},error:string}
 */
function latinfo_oauth_complete(string $provider, string $code, string $state): array
{
    $provider = strtolower(trim($provider));
    $expectedState = (string) ($_SESSION['_latinfo_oauth_state'] ?? '');
    $expectedProvider = (string) ($_SESSION['_latinfo_oauth_provider'] ?? '');
    unset($_SESSION['_latinfo_oauth_state'], $_SESSION['_latinfo_oauth_provider']);

    if ($expectedState === '' || !hash_equals($expectedState, $state)) {
        return ['ok' => false, 'error' => 'Érvénytelen OAuth állapot (CSRF). Próbáld újra.'];
    }
    if ($expectedProvider !== $provider) {
        return ['ok' => false, 'error' => 'Érvénytelen OAuth szolgáltató.'];
    }
    if ($code === '') {
        return ['ok' => false, 'error' => 'Hiányzó engedélyezési kód.'];
    }
    if (!latinfo_oauth_provider_enabled($provider)) {
        return ['ok' => false, 'error' => 'Ez a belépési mód nincs beállítva.'];
    }

    if ($provider === 'google') {
        return latinfo_oauth_complete_google($code);
    }

    return latinfo_oauth_complete_facebook($code);
}

/**
 * @return array{ok:bool,identity?:array{provider:string,provider_user_id:string,email:?string,name:?string,avatar_url:?string},error:string}
 */
function latinfo_oauth_complete_google(string $code): array
{
    $tokenRes = latinfo_oauth_http_post('https://oauth2.googleapis.com/token', [
        'code' => $code,
        'client_id' => latinfo_oauth_google_client_id(),
        'client_secret' => latinfo_oauth_google_client_secret(),
        'redirect_uri' => latinfo_oauth_callback_url(),
        'grant_type' => 'authorization_code',
    ]);
    if (!$tokenRes['ok']) {
        return ['ok' => false, 'error' => 'Google token csere sikertelen.'];
    }
    $tokenJson = json_decode($tokenRes['body'], true);
    if (!is_array($tokenJson) || empty($tokenJson['access_token'])) {
        return ['ok' => false, 'error' => 'Google nem adott access tokent.'];
    }
    $accessToken = (string) $tokenJson['access_token'];
    $infoRes = latinfo_oauth_http_get(
        'https://www.googleapis.com/oauth2/v3/userinfo',
        ['Authorization: Bearer ' . $accessToken]
    );
    if (!$infoRes['ok']) {
        return ['ok' => false, 'error' => 'Google profil lekérés sikertelen.'];
    }
    $info = json_decode($infoRes['body'], true);
    if (!is_array($info) || empty($info['sub'])) {
        return ['ok' => false, 'error' => 'Érvénytelen Google profil válasz.'];
    }
    $emailVerified = !empty($info['email_verified']);
    $email = trim((string) ($info['email'] ?? ''));
    if ($email !== '' && !$emailVerified) {
        return ['ok' => false, 'error' => 'A Google e-mail cím nincs megerősítve.'];
    }

    return [
        'ok' => true,
        'error' => '',
        'identity' => [
            'provider' => 'google',
            'provider_user_id' => (string) $info['sub'],
            'email' => $email !== '' ? $email : null,
            'name' => trim((string) ($info['name'] ?? '')),
            'avatar_url' => trim((string) ($info['picture'] ?? '')),
        ],
    ];
}

/**
 * @return array{ok:bool,identity?:array{provider:string,provider_user_id:string,email:?string,name:?string,avatar_url:?string},error:string}
 */
function latinfo_oauth_complete_facebook(string $code): array
{
    $tokenUrl = 'https://graph.facebook.com/v21.0/oauth/access_token?' . http_build_query([
        'client_id' => latinfo_oauth_facebook_app_id(),
        'client_secret' => latinfo_oauth_facebook_app_secret(),
        'redirect_uri' => latinfo_oauth_callback_url(),
        'code' => $code,
    ]);
    $tokenRes = latinfo_oauth_http_get($tokenUrl);
    if (!$tokenRes['ok']) {
        return ['ok' => false, 'error' => 'Facebook token csere sikertelen.'];
    }
    $tokenJson = json_decode($tokenRes['body'], true);
    if (!is_array($tokenJson) || empty($tokenJson['access_token'])) {
        return ['ok' => false, 'error' => 'Facebook nem adott access tokent.'];
    }
    $accessToken = (string) $tokenJson['access_token'];
    $meUrl = 'https://graph.facebook.com/v21.0/me?' . http_build_query([
        'fields' => 'id,name,email,picture.type(large)',
        'access_token' => $accessToken,
    ]);
    $meRes = latinfo_oauth_http_get($meUrl);
    if (!$meRes['ok']) {
        return ['ok' => false, 'error' => 'Facebook profil lekérés sikertelen.'];
    }
    $me = json_decode($meRes['body'], true);
    if (!is_array($me) || empty($me['id'])) {
        return ['ok' => false, 'error' => 'Érvénytelen Facebook profil válasz.'];
    }
    $avatar = '';
    if (isset($me['picture']['data']['url']) && is_string($me['picture']['data']['url'])) {
        $avatar = $me['picture']['data']['url'];
    }

    return [
        'ok' => true,
        'error' => '',
        'identity' => [
            'provider' => 'facebook',
            'provider_user_id' => (string) $me['id'],
            'email' => isset($me['email']) ? trim((string) $me['email']) : null,
            'name' => trim((string) ($me['name'] ?? '')),
            'avatar_url' => $avatar,
        ],
    ];
}
