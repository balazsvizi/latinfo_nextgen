<?php
declare(strict_types=1);

require_once __DIR__ . '/partners.php';
require_once __DIR__ . '/password_reset.php';
require_once __DIR__ . '/../admin/admins.php';

function nextgen_partner_message_email_load_deps(): void
{
    if (!defined('SITE_NAME')) {
        require_once dirname(__DIR__, 2) . '/core/config.php';
    }
    if (!function_exists('h')) {
        require_once dirname(__DIR__, 2) . '/includes/functions.php';
    }
    if (!function_exists('email_kuld')) {
        require_once dirname(__DIR__, 2) . '/includes/email.php';
    }
}

function nextgen_partner_message_email_thread_html(PDO $db, int $partnerId, string $partnerName): string
{
    if ($partnerId <= 0) {
        return '';
    }

    $messages = nextgen_partner_messages_for_partner($db, $partnerId);
    if ($messages === []) {
        return '';
    }

    $messages = array_reverse($messages);
    $html = '<div style="margin:1.25em 0 0;border-top:1px solid #ddd;padding-top:1em;">'
        . '<p style="margin:0 0 0.85em;font-weight:700;color:#444;">Beszélgetés előzményei</p>';

    foreach ($messages as $msg) {
        $isAdmin = ($msg['creator_type'] ?? '') === 'admin';
        $author = nextgen_partner_message_author_label($msg, $partnerName);
        $date = (string) ($msg['létrehozva'] ?? '');
        $body = nl2br(h((string) ($msg['message'] ?? '')), false);
        $bg = $isAdmin ? '#eef4ff' : '#f9f9f9';
        $border = $isAdmin ? '#3b82f6' : '#9ca3af';

        $html .= '<div style="margin:0 0 0.75em;padding:0.75em 1em;border-left:3px solid ' . $border . ';background:' . $bg . ';">'
            . '<p style="margin:0 0 0.35em;font-size:0.875em;color:#666;">' . h($date) . ' – ' . h($author) . '</p>'
            . '<div style="white-space:pre-wrap;word-break:break-word;">' . $body . '</div>'
            . '</div>';
    }

    $html .= '</div>';

    return $html;
}

function nextgen_partner_message_notify_admins_on_partner_send(PDO $db, int $partnerId, string $message): void
{
    if ($partnerId <= 0) {
        return;
    }

    $recipients = nextgen_admin_partner_message_email_recipients($db);
    if ($recipients === []) {
        return;
    }

    $partner = nextgen_partner_by_id($db, $partnerId);
    if ($partner === null) {
        return;
    }

    nextgen_partner_message_email_load_deps();

    $partnerName = trim((string) ($partner['név'] ?? 'Partner'));
    if ($partnerName === '') {
        $partnerName = 'Partner';
    }
    $threadUrl = ng_absolute_url(nextgen_url('admin/partnerek/uzenetek.php?partner_id=' . $partnerId));
    $threadHtml = nextgen_partner_message_email_thread_html($db, $partnerId, $partnerName);
    $targy = SITE_NAME . ' – Új partner üzenet: ' . $partnerName;

    foreach ($recipients as $recipient) {
        $adminName = trim((string) ($recipient['név'] ?? ''));
        if ($adminName === '') {
            $adminName = 'Admin';
        }
        $szoveg = '<p>Kedves ' . h($adminName) . '!</p>'
            . '<p><strong>' . h($partnerName) . '</strong> új üzenetet küldött a partner portálon.</p>'
            . $threadHtml
            . '<p style="margin-top:1.25em;"><a href="' . h($threadUrl) . '">Üzenet megtekintése és válasz</a></p>';

        $result = email_kuld($recipient['email'], $targy, $szoveg, ['html' => true]);
        if (!$result['ok']) {
            error_log('nextgen_partner_message_notify_admins_on_partner_send #' . $recipient['id'] . ': ' . ($result['hiba'] ?? ''));
        }
    }
}

function nextgen_partner_message_notify_partner_on_admin_reply(PDO $db, int $partnerId, string $message, int $adminId): void
{
    if ($partnerId <= 0) {
        return;
    }

    $partner = nextgen_partner_by_id($db, $partnerId);
    if ($partner === null || empty($partner['aktív'])) {
        return;
    }

    $email = trim(mb_strtolower((string) ($partner['email'] ?? ''), 'UTF-8'));
    if (!nextgen_partner_email_is_deliverable($email)) {
        return;
    }

    nextgen_partner_message_email_load_deps();

    $partnerName = trim((string) ($partner['név'] ?? 'Partner'));
    if ($partnerName === '') {
        $partnerName = 'Partner';
    }

    $adminName = 'Admin';
    if ($adminId > 0) {
        try {
            $stmt = $db->prepare('SELECT `név` FROM `nextgen_admins` WHERE `id` = ? LIMIT 1');
            $stmt->execute([$adminId]);
            $nev = trim((string) ($stmt->fetchColumn() ?: ''));
            if ($nev !== '') {
                $adminName = $nev;
            }
        } catch (Throwable) {
            // fallback: Admin
        }
    }

    $messagesUrl = ng_absolute_url(partner_url('messages.php'));
    $threadHtml = nextgen_partner_message_email_thread_html($db, $partnerId, $partnerName);
    $targy = SITE_NAME . ' – Új válasz a partner portálon';

    $szoveg = '<p>Kedves ' . h($partnerName) . '!</p>'
        . '<p><strong>' . h($adminName) . '</strong> válaszolt az üzenetedre.</p>'
        . $threadHtml
        . '<p style="margin-top:1.25em;"><a href="' . h($messagesUrl) . '">Üzenetek megtekintése</a></p>';

    $result = email_kuld($email, $targy, $szoveg, ['html' => true]);
    if (!$result['ok']) {
        error_log('nextgen_partner_message_notify_partner_on_admin_reply partner #' . $partnerId . ': ' . ($result['hiba'] ?? ''));
    }
}
