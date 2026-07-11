<?php
declare(strict_types=1);

require_once HB_ROOT . '/lib/ActivityLog.php';

function hb_is_logged_in(): bool
{
    return !empty($_SESSION['hb_user_id']) && !empty($_SESSION['hb_subscriber_id']);
}

function hb_require_login(): void
{
    if (!hb_is_logged_in()) {
        $_SESSION['_hb_redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        hb_redirect(hb_url('login.php'));
    }
}

function hb_user_id(): int
{
    return (int) ($_SESSION['hb_user_id'] ?? 0);
}

function hb_subscriber_id(): int
{
    return (int) ($_SESSION['hb_subscriber_id'] ?? 0);
}

function hb_user_role(): string
{
    return (string) ($_SESSION['hb_user_role'] ?? '');
}

function hb_session_display_name(): string
{
    return trim((string) ($_SESSION['hb_user_name'] ?? ''));
}

function hb_is_tenant_admin(): bool
{
    return hb_user_role() === 'tenant_admin';
}

function hb_require_tenant_admin(): void
{
    hb_require_login();
    if (!hb_is_tenant_admin()) {
        hb_flash('error', hb_t('error.forbidden'));
        hb_redirect(hb_url('index.php'));
    }
}

function hb_current_property_id(): int
{
    return (int) ($_SESSION['hb_property_id'] ?? 0);
}

function hb_set_current_property_id(int $propertyId): void
{
    $_SESSION['hb_property_id'] = max(0, $propertyId);
}

/**
 * @return array<string, mixed>|null
 */
function hb_current_user(PDO $db): ?array
{
    $id = hb_user_id();
    if ($id <= 0) {
        return null;
    }

    try {
        $stmt = $db->prepare('
            SELECT u.*, s.name AS subscriber_name
            FROM hb_users u
            INNER JOIN hb_subscribers s ON s.id = u.subscriber_id
            WHERE u.id = ? AND u.active = 1 AND s.active = 1
            LIMIT 1
        ');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            hb_logout();

            return null;
        }

        return $row;
    } catch (Throwable) {
        hb_logout();

        return null;
    }
}

function hb_login(PDO $db, string $email, string $password): bool
{
    $email = trim($email);
    if ($email === '' || $password === '') {
        return false;
    }

    try {
        $stmt = $db->prepare('
            SELECT u.*, s.name AS subscriber_name
            FROM hb_users u
            INNER JOIN hb_subscribers s ON s.id = u.subscriber_id
            WHERE u.email = ? AND u.active = 1 AND s.active = 1
            LIMIT 1
        ');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user === false) {
            HbActivityLog::logLoginFailed($db, $email);

            return false;
        }

        if (!password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            HbActivityLog::logLoginFailed($db, $email, (int) $user['subscriber_id']);

            return false;
        }

        $_SESSION['hb_user_id'] = (int) $user['id'];
        $_SESSION['hb_subscriber_id'] = (int) $user['subscriber_id'];
        $_SESSION['hb_user_name'] = (string) $user['name'];
        $_SESSION['hb_user_email'] = (string) $user['email'];
        $_SESSION['hb_user_role'] = (string) $user['role'];
        $_SESSION['hb_subscriber_name'] = (string) $user['subscriber_name'];

        if (!empty($user['locale']) && in_array($user['locale'], hb_supported_locales(), true)) {
            hb_set_locale((string) $user['locale']);
        }

        session_regenerate_id(true);

        HbActivityLog::log($db, (int) $user['subscriber_id'], (int) $user['id'], 'login');

        return true;
    } catch (Throwable $ex) {
        error_log('hb_login: ' . $ex->getMessage());

        return false;
    }
}

function hb_logout(PDO $db): void
{
    $subscriberId = hb_subscriber_id();
    $userId = hb_user_id();
    if ($subscriberId > 0 && $userId > 0) {
        HbActivityLog::log($db, $subscriberId, $userId, 'logout');
    }

    unset(
        $_SESSION['hb_user_id'],
        $_SESSION['hb_subscriber_id'],
        $_SESSION['hb_user_name'],
        $_SESSION['hb_user_email'],
        $_SESSION['hb_user_role'],
        $_SESSION['hb_subscriber_name'],
        $_SESSION['hb_property_id']
    );
}

function hb_refresh_session_from_db(PDO $db): void
{
    $user = hb_current_user($db);
    if ($user === null) {
        return;
    }

    $_SESSION['hb_user_name'] = (string) $user['name'];
    $_SESSION['hb_user_email'] = (string) $user['email'];
    $_SESSION['hb_user_role'] = (string) $user['role'];
    $_SESSION['hb_subscriber_name'] = (string) ($user['subscriber_name'] ?? '');
}
