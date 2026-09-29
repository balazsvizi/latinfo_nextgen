<?php
declare(strict_types=1);

function user_is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function user_login_url(): string
{
    return user_url('login.php');
}

function user_require_login(): void
{
    if (!user_is_logged_in()) {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if ($uri !== '' && $uri[0] === '/' && !str_contains($uri, '://')) {
            $_SESSION['_user_redirect_after_login'] = $uri;
        }
        redirect(user_login_url());
    }
}

function user_current_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function user_session_display_name(): string
{
    return trim((string) ($_SESSION['user_nev'] ?? ''));
}

function user_establish_session(array $user): void
{
    $_SESSION['user_id'] = (int) ($user['id'] ?? 0);
    $_SESSION['user_nev'] = (string) ($user['name'] ?? '');
    $_SESSION['user_email'] = (string) ($user['email'] ?? '');
    session_regenerate_id(true);
    if (!function_exists('latinfo_favorites_merge_visitor_to_user')) {
        $favLib = dirname(__DIR__, 2) . '/lib/user/favorites.php';
        if (is_file($favLib)) {
            require_once $favLib;
        }
    }
    if (function_exists('latinfo_favorites_merge_visitor_to_user')) {
        latinfo_favorites_merge_visitor_to_user(getDb(), (int) ($user['id'] ?? 0));
    }
}

function user_login_with_password(string $email, string $password): bool
{
    $db = getDb();
    if (!latinfo_users_table_ready($db)) {
        return false;
    }
    $user = latinfo_user_by_email($db, $email);
    if ($user === null || empty($user['is_active'])) {
        return false;
    }
    $hash = (string) ($user['password_hash'] ?? '');
    if ($hash === '' || !password_verify($password, $hash)) {
        return false;
    }

    user_establish_session($user);
    latinfo_user_touch_login($db, (int) $user['id']);

    return true;
}

function user_login_from_row(array $user): bool
{
    if (empty($user['id']) || empty($user['is_active'])) {
        return false;
    }
    user_establish_session($user);
    latinfo_user_touch_login(getDb(), (int) $user['id']);

    return true;
}

function user_logout(): void
{
    unset(
        $_SESSION['user_id'],
        $_SESSION['user_nev'],
        $_SESSION['user_email'],
        $_SESSION['_user_redirect_after_login'],
        $_SESSION['_latinfo_oauth_state'],
        $_SESSION['_latinfo_oauth_provider']
    );
}

/**
 * @return array<string, mixed>|null
 */
function user_current(PDO $db): ?array
{
    $id = user_current_id();
    if ($id <= 0) {
        return null;
    }
    $row = latinfo_user_by_id($db, $id);
    if ($row === null || empty($row['is_active'])) {
        user_logout();

        return null;
    }

    return $row;
}

function user_refresh_session_from_db(PDO $db): void
{
    $user = user_current($db);
    if ($user === null) {
        return;
    }
    $_SESSION['user_nev'] = (string) ($user['name'] ?? '');
    $_SESSION['user_email'] = (string) ($user['email'] ?? '');
}

function user_safe_post_login_redirect(?string $url): string
{
    $url = trim((string) $url);
    if ($url === '' || str_contains($url, '://') || str_starts_with($url, '//') || ($url[0] ?? '') !== '/') {
        return user_url('index.php');
    }
    $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?? ''));
    $blocked = ['/login.php', '/signup.php', '/logout.php', '/oauth.php', '/connect.php'];
    foreach ($blocked as $suffix) {
        if ($path === $suffix || str_ends_with($path, $suffix)) {
            return user_url('index.php');
        }
    }

    return $url;
}

/**
 * Biztonságos relatív URL kijelentkezés / visszalépés után (nem auth oldalak).
 */
function user_safe_return_path(?string $url, string $fallback): string
{
    $url = trim((string) $url);
    if ($url === '' || str_contains($url, '://') || str_starts_with($url, '//') || ($url[0] ?? '') !== '/') {
        return $fallback;
    }
    $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?? ''));
    $accountSeg = defined('USERS_PATH') && USERS_PATH !== '' ? USERS_PATH : 'account';
    $accountBase = '/' . trim($accountSeg, '/') . '/';
    $blocked = [
        '/login.php',
        '/signup.php',
        '/logout.php',
        '/oauth.php',
        '/connect.php',
        $accountBase,
        rtrim($accountBase, '/') . '/logout.php',
        rtrim($accountBase, '/') . '/oauth.php',
        rtrim($accountBase, '/') . '/connect.php',
        rtrim($accountBase, '/') . '/signup.php',
    ];
    foreach ($blocked as $suffix) {
        if ($path === $suffix || str_ends_with($path, $suffix)) {
            return $fallback;
        }
    }

    return $url;
}
