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

function nextgen_partner_message_email_thread_html(PDO $db, int $partnerId, string $partnerName, ?int $highlightMessageId = null): string
{
    if ($partnerId <= 0) {
        return '';
    }

    $messages = nextgen_partner_messages_for_partner($db, $partnerId);
    if ($messages === []) {
        return '';
    }

    $html = '<div style="margin:1.25em 0 0;border-top:1px solid #ddd;padding-top:1em;">'
        . '<p style="margin:0 0 0.85em;font-weight:700;color:#444;">Beszélgetés</p>';

    foreach ($messages as $msg) {
        $isAdmin = ($msg['creator_type'] ?? '') === 'admin';
        $author = nextgen_partner_message_author_label($msg, $partnerName);
        $date = (string) ($msg['létrehozva'] ?? '');
        $body = nl2br(h((string) ($msg['message'] ?? '')), false);
        $isNew = $highlightMessageId !== null && (int) ($msg['id'] ?? 0) === $highlightMessageId;

        if ($isNew) {
            $bg = $isAdmin ? '#dbeafe' : '#ecfdf5';
            $border = $isAdmin ? '#2563eb' : '#059669';
            $borderWidth = '4px';
            $boxShadow = '0 0 0 1px ' . $border . ', 0 4px 12px rgba(0,0,0,0.08)';
        } else {
            $bg = $isAdmin ? '#eef4ff' : '#f9f9f9';
            $border = $isAdmin ? '#3b82f6' : '#9ca3af';
            $borderWidth = '3px';
            $boxShadow = 'none';
        }

        $newLabel = $isNew
            ? ' <span style="display:inline-block;margin-left:0.35em;padding:0.05rem 0.45rem;border-radius:999px;background:#fef3c7;color:#b45309;font-size:0.75em;font-weight:700;">Új üzenet</span>'
            : '';
        $noReplyLabel = !empty($msg['nincs_valasz'])
            ? ' <span style="display:inline-block;margin-left:0.35em;padding:0.05rem 0.45rem;border-radius:999px;background:#fff7ed;color:#b45309;font-size:0.75em;font-weight:700;">Nem kell válasz</span>'
            : '';

        $html .= '<div style="margin:0 0 0.75em;padding:0.75em 1em;border-left:' . $borderWidth . ' solid ' . $border . ';background:' . $bg . ';box-shadow:' . $boxShadow . ';">'
            . '<p style="margin:0 0 0.35em;font-size:0.875em;color:#666;">' . h($date) . ' – ' . h($author) . $newLabel . $noReplyLabel . '</p>'
            . '<div style="white-space:pre-wrap;word-break:break-word;">' . $body . '</div>'
            . '</div>';
    }

    $html .= '</div>';

    return $html;
}

function nextgen_partner_message_notify_admins_on_partner_send(PDO $db, int $partnerId, string $message, int $messageId): void
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
    $threadHtml = nextgen_partner_message_email_thread_html($db, $partnerId, $partnerName, $messageId);
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

function nextgen_partner_message_notify_partner_on_admin_reply(PDO $db, int $partnerId, string $message, int $adminId, int $messageId): void
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
    $threadHtml = nextgen_partner_message_email_thread_html($db, $partnerId, $partnerName, $messageId);
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
