<?php
declare(strict_types=1);

/**
 * Esemény szervezői értesítő e-mail küldése (szerkesztő modal POST).
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/event_notify_email.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('error', 'Érvénytelen kérés.');
    redirect(events_url('events_admin.php'));
}

$eventId = (int) ($_POST['event_id'] ?? 0);
$redirectUrl = events_url('szerkeszt.php?id=') . max(0, $eventId);

if ($eventId <= 0) {
    flash('error', 'Hiányzó esemény azonosító.');
    redirect(events_url('events_admin.php'));
}

if (!csrf_validate('events_notify_email')) {
    flash('error', 'Lejárt vagy érvénytelen munkamenet. Töltsd újra az oldalt.');
    redirect($redirectUrl);
}

$db = getDb();
events_notify_email_ensure_schema($db);

$stmt = $db->prepare('SELECT * FROM `events_calendar_events` WHERE `id` = ? LIMIT 1');
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$event) {
    flash('error', 'Esemény nem található.');
    redirect(events_url('events_admin.php'));
}

require_once __DIR__ . '/lib/event_request.php';
$organizerIds = events_load_event_organizer_ids($db, $eventId);

$toRaw = (string) ($_POST['notify_to'] ?? '');
$toEmails = [];
foreach (preg_split('/[\s,;]+/', $toRaw) ?: [] as $part) {
    $part = trim($part);
    if ($part !== '') {
        $toEmails[] = $part;
    }
}

$subject = (string) ($_POST['notify_subject'] ?? '');
$bodyHtml = (string) ($_POST['notify_html'] ?? '');
$templateId = (int) ($_POST['notify_template_id'] ?? 0);
$smtpId = (int) ($_POST['notify_smtp'] ?? 0);
$bccRaw = trim((string) ($_POST['notify_bcc'] ?? ''));

$result = events_notify_email_send(
    $db,
    $eventId,
    $event,
    $organizerIds,
    $toEmails,
    $subject,
    $bodyHtml,
    $templateId > 0 ? $templateId : null,
    $smtpId > 0 ? $smtpId : null,
    $bccRaw !== '' ? $bccRaw : null
);

if ($result['ok']) {
    flash('success', 'Az értesítő e-mail elküldve.');
} else {
    flash('error', (string) ($result['error'] ?? 'Küldési hiba.'));
}

redirect($redirectUrl . '#event-notify-email');
