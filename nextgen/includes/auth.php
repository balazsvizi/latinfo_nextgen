<?php
/**
 * Admin hitelesítés
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(0, '/');
    session_start();
}

require_once dirname(__DIR__, 2) . '/nextgen/core/database.php';
require_once __DIR__ . '/functions.php';

$scriptName = strtolower(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')));
$skipPmBootstrap = str_ends_with($scriptName, '/login.php')
    || str_ends_with($scriptName, '/logout.php')
    || str_ends_with($scriptName, '/admin/backup/step.php')
    || str_ends_with($scriptName, '/admin/backup/poll.php')
    || str_ends_with($scriptName, '/admin/backup/cancel.php')
    || str_ends_with($scriptName, '/admin/pm/api.php');

if (!$skipPmBootstrap) {
    require_once __DIR__ . '/../lib/pm/bootstrap.php';
    pm_tools_register_footer_output_buffer();
}
/**
 * Bejelentkezés ellenőrzése – ha nincs, loginra küld
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if ($uri !== '' && alatinfo_is_safe_post_login_redirect($uri)) {
            $_SESSION['_redirect_after_login'] = $uri;
        } else {
            unset($_SESSION['_redirect_after_login']);
        }
        redirect(nextgen_url('login.php'));
    }
}

/**
 * Csak normál oldalakra engedjük a login utáni visszairányítást (nem AJAX/API).
 */
function alatinfo_is_safe_post_login_redirect(string $url): bool
{
    $url = trim($url);
    if ($url === '' || str_contains($url, '://') || str_starts_with($url, '//')) {
        return false;
    }
    if ($url[0] !== '/') {
        return false;
    }
    $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?? ''));
    if ($path === '') {
        return false;
    }
    $blocked = [
        '/login.php',
        '/logout.php',
        '/admin/backup/step.php',
        '/admin/backup/poll.php',
        '/admin/backup/cancel.php',
        '/admin/backup/oauth.php',
        '/admin/pm/api.php',
    ];
    foreach ($blocked as $suffix) {
        if ($path === $suffix || str_ends_with($path, $suffix)) {
            return false;
        }
    }

    return true;
}

function isLoggedIn(): bool {
    return !empty($_SESSION['admin_id']) && !empty($_SESSION['admin_nev']);
}

/**
 * Admin szint a sessionből, szükség esetén DB-ből frissítve (régi sessionök).
 */
function ng_admin_szint_resolved(): ?string
{
    static $resolved = null;
    if ($resolved !== null) {
        return $resolved === '' ? null : $resolved;
    }
    $resolved = '';
    if (!isLoggedIn()) {
        return null;
    }
    $szint = (string) ($_SESSION['admin_szint'] ?? '');
    if ($szint === 'superadmin' || $szint === 'admin') {
        $resolved = $szint;

        return $szint;
    }
    try {
        $db = getDb();
        $stmt = $db->prepare('SELECT szint FROM nextgen_admins WHERE id = ? AND aktív = 1 LIMIT 1');
        $stmt->execute([(int) $_SESSION['admin_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $szint = isset($row['szint']) && $row['szint'] === 'superadmin' ? 'superadmin' : 'admin';
            $_SESSION['admin_szint'] = $szint;
            $resolved = $szint;

            return $szint;
        }
    } catch (Throwable) {
        $_SESSION['admin_szint'] = 'admin';
        $resolved = 'admin';

        return 'admin';
    }

    return null;
}

/**
 * Superadmin-e a bejelentkezett felhasználó (Admin menü, admin kezelés)
 */
function isSuperadmin(): bool {
    return ng_admin_szint_resolved() === 'superadmin';
}

/**
 * Csak superadmin látja – különben átirányít
 */
function requireSuperadmin(): void {
    requireLogin();
    if (!isSuperadmin()) {
        $_SESSION['_flash']['error'] = 'Nincs jogosultságod ehhez a laphoz.';
        redirect(nextgen_url('apps.php'));
    }
}

/**
 * Bejelentkeztetés
 */
function login(string $felhasznalonev, string $jelszo): bool {
    $db = getDb();
    try {
        $stmt = $db->prepare('SELECT id, név, felhasználónév, email, jelszó_hash, szint FROM nextgen_admins WHERE felhasználónév = ? AND aktív = 1');
        $stmt->execute([$felhasznalonev]);
        $admin = $stmt->fetch();
    } catch (Throwable $e) {
        $stmt = $db->prepare('SELECT id, név, felhasználónév, jelszó_hash, szint FROM nextgen_admins WHERE felhasználónév = ? AND aktív = 1');
        $stmt->execute([$felhasznalonev]);
        $admin = $stmt->fetch();
        if ($admin) {
            $admin['email'] = null;
        }
    }
    if (!$admin || !password_verify($jelszo, $admin['jelszó_hash'])) {
        return false;
    }
    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['admin_nev'] = $admin['név'];
    $_SESSION['admin_felhasznalonev'] = (string)($admin['felhasználónév'] ?? $felhasznalonev);
    $_SESSION['admin_email'] = $admin['email'] ?? null;
    $_SESSION['admin_szint'] = isset($admin['szint']) && $admin['szint'] === 'superadmin' ? 'superadmin' : 'admin';
    session_regenerate_id(true);
    return true;
}

/**
 * Kijelentkezés
 */
function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
