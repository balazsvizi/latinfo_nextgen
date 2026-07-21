<?php
declare(strict_types=1);

/**
 * JSON: valós idejű esemény-megtekintés snapshot (admin).
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/event_realtime_stats.php';
requireLogin();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

try {
    $db = getDb();
    $snapshot = events_realtime_snapshot($db);
    echo json_encode(
        array_merge(
            [
                'ok' => true,
                'generated_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            ],
            $snapshot
        ),
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
} catch (Throwable $ex) {
    error_log('ajax_events_realtime: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(
        ['ok' => false, 'error' => 'A valós idejű adatok lekérése sikertelen.'],
        JSON_UNESCAPED_UNICODE
    );
}
