<?php
declare(strict_types=1);

function partner_is_logged_in(): bool
{
    return !empty($_SESSION['partner_id']);
}

function partner_login_url(): string
{
    return partner_url('login.php');
}

function partner_require_login(): void
{
    if (!partner_is_logged_in()) {
        $_SESSION['_partner_redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect(partner_login_url());
    }
    partner_require_password_change_if_needed();
}

function partner_require_password_change_if_needed(): void
{
    if (empty($_SESSION['partner_id'])) {
        return;
    }

    $allowed = ['jelszo_kotelezo.php', 'logout.php'];
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (in_array($script, $allowed, true)) {
        return;
    }

    $db = getDb();
    $partner = partner_current($db);
    if ($partner !== null && nextgen_partner_must_change_password($partner)) {
        redirect(partner_url('jelszo_kotelezo.php'));
    }
}

function partner_current_id(): int
{
    return (int) ($_SESSION['partner_id'] ?? 0);
}

function partner_session_display_name(): string
{
    return trim((string) ($_SESSION['partner_nev'] ?? ''));
}

function partner_login(string $email, string $password): bool
{
    if (!nextgen_partners_table_ready(getDb())) {
        return false;
    }
    $partner = nextgen_partner_by_email(getDb(), $email);
    if ($partner === null || empty($partner['aktív'])) {
        return false;
    }
    if (!password_verify($password, (string) ($partner['jelszó_hash'] ?? ''))) {
        return false;
    }

    $_SESSION['partner_id'] = (int) $partner['id'];
    $_SESSION['partner_nev'] = (string) ($partner['név'] ?? '');
    $_SESSION['partner_email'] = (string) ($partner['email'] ?? '');
    $_SESSION['partner_must_change_password'] = nextgen_partner_must_change_password($partner);
    session_regenerate_id(true);

    nextgen_partner_log(getDb(), (int) $partner['id'], 'Bejelentkezés');

    return true;
}

function partner_logout(): void
{
    $partnerId = (int) ($_SESSION['partner_id'] ?? 0);
    if ($partnerId > 0 && function_exists('nextgen_partner_log')) {
        nextgen_partner_log(getDb(), $partnerId, 'Kijelentkezés');
    }
    unset(
        $_SESSION['partner_id'],
        $_SESSION['partner_nev'],
        $_SESSION['partner_email'],
        $_SESSION['partner_must_change_password']
    );
}

/**
 * @return array<string, mixed>|null
 */
function partner_current(PDO $db): ?array
{
    $id = partner_current_id();
    if ($id <= 0) {
        return null;
    }
    $row = nextgen_partner_by_id($db, $id);
    if ($row === null || empty($row['aktív'])) {
        partner_logout();

        return null;
    }

    return $row;
}

function partner_refresh_session_from_db(PDO $db): void
{
    $partner = partner_current($db);
    if ($partner === null) {
        return;
    }
    $_SESSION['partner_nev'] = (string) ($partner['név'] ?? '');
    $_SESSION['partner_email'] = (string) ($partner['email'] ?? '');
    $_SESSION['partner_must_change_password'] = nextgen_partner_must_change_password($partner);
}

function partner_require_organizer_access(PDO $db, int $organizerId): void
{
    partner_require_login();
    if (!nextgen_partner_can_access_organizer($db, partner_current_id(), $organizerId)) {
        flash('error', 'Nincs hozzáférésed ehhez a szervezőhöz.');
        redirect(partner_url('szervezok.php'));
    }
}

function partner_require_event_access(PDO $db, int $eventId): void
{
    partner_require_login();
    if (!partner_portal_can_access_event($db, partner_current_id(), $eventId)) {
        flash('error', 'Nincs hozzáférésed ehhez az eseményhez.');
        redirect(partner_url('esemenyek.php'));
    }
}
