<?php
declare(strict_types=1);

function organizers_portal_is_logged_in(): bool
{
    return !empty($_SESSION['organizers_portal_account_id'])
        && !empty($_SESSION['organizers_portal_organizer_id']);
}

function organizers_portal_require_login(): void
{
    if (!organizers_portal_is_logged_in()) {
        $_SESSION['_organizers_portal_redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect(organizers_portal_url('login.php'));
    }
}

function organizers_portal_current_account_id(): int
{
    return (int) ($_SESSION['organizers_portal_account_id'] ?? 0);
}

function organizers_portal_current_organizer_id(): int
{
    return (int) ($_SESSION['organizers_portal_organizer_id'] ?? 0);
}

function organizers_portal_session_display_name(): string
{
    $nev = trim((string) ($_SESSION['organizers_portal_nev'] ?? ''));
    if ($nev !== '') {
        return $nev;
    }

    return trim((string) ($_SESSION['organizers_portal_organizer_name'] ?? ''));
}

function organizers_portal_login(string $email, string $password): bool
{
    if (!events_organizer_accounts_table_ready(getDb())) {
        return false;
    }

    $account = events_organizer_account_by_email(getDb(), $email);
    if ($account === null || empty($account['aktív'])) {
        return false;
    }
    if (!password_verify($password, (string) ($account['jelszó_hash'] ?? ''))) {
        return false;
    }

    $_SESSION['organizers_portal_account_id'] = (int) $account['id'];
    $_SESSION['organizers_portal_organizer_id'] = (int) $account['organizer_id'];
    $_SESSION['organizers_portal_email'] = (string) $account['email'];
    $_SESSION['organizers_portal_nev'] = (string) ($account['név'] ?? '');
    $_SESSION['organizers_portal_organizer_name'] = (string) ($account['organizer_name'] ?? '');
    session_regenerate_id(true);

    return true;
}

function organizers_portal_logout(): void
{
    unset(
        $_SESSION['organizers_portal_account_id'],
        $_SESSION['organizers_portal_organizer_id'],
        $_SESSION['organizers_portal_email'],
        $_SESSION['organizers_portal_nev'],
        $_SESSION['organizers_portal_organizer_name']
    );
}

/**
 * @return array<string, mixed>|null
 */
function organizers_portal_current_account(PDO $db): ?array
{
    $accountId = organizers_portal_current_account_id();
    if ($accountId <= 0) {
        return null;
    }
    try {
        $stmt = $db->prepare('
            SELECT a.*, o.`name` AS organizer_name
            FROM `events_organizer_accounts` a
            INNER JOIN `events_organizers` o ON o.`id` = a.`organizer_id`
            WHERE a.`id` = ? AND a.`aktív` = 1
            LIMIT 1
        ');
        $stmt->execute([$accountId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (Throwable) {
        return null;
    }
}

function organizers_portal_refresh_session_from_db(PDO $db): void
{
    $account = organizers_portal_current_account($db);
    if ($account === null) {
        organizers_portal_logout();

        return;
    }
    $_SESSION['organizers_portal_email'] = (string) $account['email'];
    $_SESSION['organizers_portal_nev'] = (string) ($account['név'] ?? '');
    $_SESSION['organizers_portal_organizer_name'] = (string) ($account['organizer_name'] ?? '');
}

/** @deprecated organizers_portal_* használata */
function szervezo_is_logged_in(): bool { return organizers_portal_is_logged_in(); }
function szervezo_require_login(): void { organizers_portal_require_login(); }
function szervezo_current_account_id(): int { return organizers_portal_current_account_id(); }
function szervezo_current_organizer_id(): int { return organizers_portal_current_organizer_id(); }
function szervezo_session_display_name(): string { return organizers_portal_session_display_name(); }
function szervezo_login(string $email, string $password): bool { return organizers_portal_login($email, $password); }
function szervezo_logout(): void { organizers_portal_logout(); }
function szervezo_current_account(PDO $db): ?array { return organizers_portal_current_account($db); }
function szervezo_refresh_session_from_db(PDO $db): void { organizers_portal_refresh_session_from_db($db); }
