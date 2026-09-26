<?php
declare(strict_types=1);

/**
 * JSON: esemény szervezői értesítő default címzettjei (partner kapcsolók figyelembevételével).
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/event_notify_email.php';
require_once __DIR__ . '/lib/event_request.php';
requireLogin();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$eventId = (int) ($_GET['event_id'] ?? $_POST['event_id'] ?? 0);
if ($eventId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Hiányzó esemény azonosító.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$db = getDb();
$st = $db->prepare('SELECT `id` FROM `events_calendar_events` WHERE `id` = ? LIMIT 1');
$st->execute([$eventId]);
if (!$st->fetchColumn()) {
    echo json_encode(['ok' => false, 'error' => 'Esemény nem található.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$organizerIds = events_load_event_organizer_ids($db, $eventId);
$emails = events_notify_email_recipient_emails($db, $organizerIds);

echo json_encode([
    'ok' => true,
    'emails' => $emails,
    'to' => implode(', ', $emails),
], JSON_UNESCAPED_UNICODE);
