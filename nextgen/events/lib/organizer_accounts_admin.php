<?php
declare(strict_types=1);

require_once __DIR__ . '/organizer_accounts.php';

if (!function_exists('organizers_portal_url')) {
    function organizers_portal_url(string $path = ''): string
    {
        return events_url('organizers/' . ltrim($path, '/'));
    }
}

/**
 * Portál fiók POST műveletek (admin szervező szerkesztő).
 *
 * @return string Hibaüzenet, vagy üres ha nincs hiba / redirect történt.
 */
function events_organizer_portal_account_handle_post(PDO $db, int $organizerId): string
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $organizerId <= 0) {
        return '';
    }

    $action = (string) ($_POST['_portal_action'] ?? '');
    if ($action === '') {
        return '';
    }

    if (!csrf_validate('organizer_portal_account')) {
        return 'Lejárt vagy érvénytelen munkamenet.';
    }

    if ($action === 'create_account') {
        $email = trim((string) ($_POST['portal_email'] ?? ''));
        $nev = trim((string) ($_POST['portal_nev'] ?? ''));
        $jelszo = (string) ($_POST['portal_jelszo'] ?? '');
        $result = events_organizer_account_create($db, $organizerId, $email, $jelszo, $nev !== '' ? $nev : null);
        if ($result['ok']) {
            rendszer_log('szervező', $organizerId, 'Portál fiók létrehozva', $email);
            flash('success', 'Szervezői portál fiók létrehozva.');
            redirect(events_url('organizer_szerkeszt.php?id=') . $organizerId);
        }

        return (string) ($result['error'] ?? 'Fiók létrehozása sikertelen.');
    }

    $portalAccount = events_organizer_account_by_organizer_id($db, $organizerId);
    if ($portalAccount === null) {
        return 'Nincs portál fiók ehhez a szervezőhöz.';
    }

    if ($action === 'reset_password') {
        $jelszo = (string) ($_POST['portal_jelszo'] ?? '');
        $result = events_organizer_account_update_password($db, (int) $portalAccount['id'], $jelszo);
        if ($result['ok']) {
            rendszer_log('szervező', $organizerId, 'Portál jelszó újraállítva', (string) $portalAccount['email']);
            flash('success', 'Portál jelszó frissítve.');
            redirect(events_url('organizer_szerkeszt.php?id=') . $organizerId);
        }

        return (string) ($result['error'] ?? 'Jelszó mentése sikertelen.');
    }

    if ($action === 'toggle_active') {
        $active = !empty($portalAccount['aktív']);
        $result = events_organizer_account_set_active($db, (int) $portalAccount['id'], !$active);
        if ($result['ok']) {
            $label = $active ? 'Portál fiók deaktiválva' : 'Portál fiók aktiválva';
            rendszer_log('szervező', $organizerId, $label, (string) $portalAccount['email']);
            flash('success', $label . '.');
            redirect(events_url('organizer_szerkeszt.php?id=') . $organizerId);
        }

        return (string) ($result['error'] ?? 'Státusz mentése sikertelen.');
    }

    return '';
}
