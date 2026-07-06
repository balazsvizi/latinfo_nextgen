<?php
declare(strict_types=1);

require_once __DIR__ . '/venue_request.php';

const EVENTS_VENUE_GEOCODE_DEFAULT_BATCH = 12;

/**
 * @return array{ok: int, fail: int, failed_ids: list<int>, remaining: int, batches: int, done: bool}
 */
function events_venues_geocode_run_batches(
    PDO $db,
    int $batchSize = EVENTS_VENUE_GEOCODE_DEFAULT_BATCH,
    int $maxBatches = 1,
    array $excludeIds = []
): array {
    $batchSize = max(1, min(25, $batchSize));
    $totalOk = 0;
    $totalFail = 0;
    $failedIds = array_values(array_unique(array_filter(array_map('intval', $excludeIds), static fn (int $id): bool => $id > 0)));
    $batchesRun = 0;
    $remaining = events_venues_geocode_candidates_count($db);

    $limit = $maxBatches <= 0 ? PHP_INT_MAX : $maxBatches;
    for ($i = 0; $i < $limit; $i++) {
        $batch = events_venues_geocode_batch($db, $batchSize, $failedIds);
        if ($batch['ok'] === 0 && $batch['fail'] === 0) {
            $remaining = (int) $batch['remaining'];
            break;
        }

        $totalOk += (int) $batch['ok'];
        $totalFail += (int) $batch['fail'];
        $failedIds = array_values(array_unique(array_merge($failedIds, $batch['failed_ids'])));
        $remaining = (int) $batch['remaining'];
        $batchesRun++;

        $stillPending = events_venues_fetch_geocode_candidates($db, 1, $failedIds);
        if ($stillPending === []) {
            break;
        }
    }

    $stillPending = events_venues_fetch_geocode_candidates($db, 1, $failedIds);

    return [
        'ok' => $totalOk,
        'fail' => $totalFail,
        'failed_ids' => $failedIds,
        'remaining' => $remaining,
        'batches' => $batchesRun,
        'done' => $remaining === 0 || $stillPending === [],
    ];
}

function events_venues_geocode_result_summary(array $result): string
{
    $parts = [];
    if (($result['ok'] ?? 0) > 0) {
        $parts[] = (int) $result['ok'] . ' sikeres';
    }
    if (($result['fail'] ?? 0) > 0) {
        $parts[] = (int) $result['fail'] . ' sikertelen';
    }
    if (($result['batches'] ?? 0) > 0) {
        $parts[] = (int) $result['batches'] . ' batch';
    }
    $parts[] = (int) ($result['remaining'] ?? 0) . ' hátralévő';

    return implode(', ', $parts);
}
