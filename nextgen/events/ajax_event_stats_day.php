<?php
declare(strict_types=1);

/**
 * JSON: egy esemény egy napjának órás bontása + tételek (admin).
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/event_edit_stats.php';
require_once __DIR__ . '/lib/event_realtime_stats.php';
requireLogin();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$eventId = (int) ($_GET['id'] ?? 0);
$day = trim((string) ($_GET['day'] ?? ''));

if ($eventId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
    http_response_code(400);
    echo json_encode(
        ['ok' => false, 'error' => 'Érvénytelen esemény vagy nap.'],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

try {
    $db = getDb();
    $exists = $db->prepare('SELECT 1 FROM `events_calendar_events` WHERE `id` = ? LIMIT 1');
    $exists->execute([$eventId]);
    if ($exists->fetchColumn() === false) {
        http_response_code(404);
        echo json_encode(
            ['ok' => false, 'error' => 'Az esemény nem található.'],
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }

    $detail = events_edit_stats_day_detail_for_event($db, $eventId, $day);
    $items = [];
    foreach ($detail['items'] as $item) {
        $metric = (string) ($item['metric'] ?? '');
        $source = (string) ($item['source'] ?? '');
        $items[] = [
            'id' => (int) ($item['id'] ?? 0),
            'at' => (string) ($item['at'] ?? ''),
            'metric' => $metric,
            'metric_label' => events_realtime_metric_label($metric),
            'source' => $source,
            'source_label' => events_realtime_source_label($source),
            'is_bot' => !empty($item['is_bot']),
            'ip_short' => (string) ($item['ip_short'] ?? ''),
        ];
    }

    echo json_encode(
        [
            'ok' => true,
            'day' => $detail['day'],
            'day_label' => (new DateTimeImmutable($detail['day']))->format('Y.m.d.'),
            'table_ready' => !empty($detail['table_ready']),
            'bot_ready' => !empty($detail['bot_ready']),
            'hourly' => $detail['hourly'],
            'items' => $items,
            'items_truncated' => !empty($detail['items_truncated']),
            'totals' => $detail['totals'],
        ],
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
} catch (Throwable $ex) {
    error_log('ajax_event_stats_day: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(
        ['ok' => false, 'error' => 'A napi bontás lekérése sikertelen.'],
        JSON_UNESCAPED_UNICODE
    );
}
