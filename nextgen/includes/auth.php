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

/**
 * Bejelentkezés ellenőrzése – ha nincs, loginra küld
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        $_SESSION['_redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect(nextgen_url('login.php'));
    }
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

require_once __DIR__ . '/superadmin_php_debug_bar.php';
ng_register_superadmin_php_debug_bar_output_buffer();
