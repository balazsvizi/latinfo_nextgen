#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Helyszín GPS geokódolás – cron / CLI (batch-enként, alapból 12 helyszín / futás).
 *
 * CLI példák:
 *   php nextgen/events/cron/venue_geocode.php
 *   php nextgen/events/cron/venue_geocode.php --all
 *   php nextgen/events/cron/venue_geocode.php --batches=5 --batch-size=12
 *
 * Cron (minden 5 percben 12 helyszín):
 *   */5 * * * * cd /path/to/Alatinfo && php nextgen/events/cron/venue_geocode.php >> logs/venue_geocode.log 2>&1
 *
 * HTTP cron (EVENTS_CRON_TOKEN a config.local.php-ben):
 *   curl -sf "https://latinfo.hu/nextgen/events/cron/venue_geocode.php?token=YOUR_SECRET"
 *   curl -sf "https://latinfo.hu/nextgen/events/cron/venue_geocode.php?token=YOUR_SECRET&all=1"
 */

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    header('Content-Type: application/json; charset=UTF-8');
}

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/../lib/venue_request.php';
require_once __DIR__ . '/../lib/venue_geocode_runner.php';

if (!$isCli && !events_cron_http_token_valid()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden'], JSON_UNESCAPED_UNICODE);
    exit(1);
}

$batchSize = EVENTS_VENUE_GEOCODE_DEFAULT_BATCH;
$maxBatches = 1;
$runAll = false;

if ($isCli) {
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--all') {
            $runAll = true;
            continue;
        }
        if (str_starts_with($arg, '--batch-size=')) {
            $batchSize = max(1, min(25, (int) substr($arg, 13)));
            continue;
        }
        if (str_starts_with($arg, '--batches=')) {
            $maxBatches = max(1, (int) substr($arg, 10));
            continue;
        }
        if ($arg === '--help' || $arg === '-h') {
            $help = <<<'TXT'
Helyszín GPS geokódolás (Nominatim, cím alapján).

Opciók:
  --all              Minden geokódolható helyszín (12-es batch-ekben, amíg tart)
  --batches=N        Legfeljebb N batch egy futásban (alap: 1 – cron-barát)
  --batch-size=N     Helyszínek száma batch-enként (alap: 12, max: 25)
  --help             Súgó

Alap futás (cron): egy batch = 12 helyszín.
TXT;
            echo $help . PHP_EOL;
            exit(0);
        }
    }
} else {
    if (isset($_GET['all']) && (string) $_GET['all'] === '1') {
        $runAll = true;
    }
    if (isset($_GET['batch_size']) && ctype_digit((string) $_GET['batch_size'])) {
        $batchSize = max(1, min(25, (int) $_GET['batch_size']));
    }
    if (isset($_GET['batches']) && ctype_digit((string) $_GET['batches'])) {
        $maxBatches = max(1, (int) $_GET['batches']);
    }
}

if ($runAll) {
    $maxBatches = 0;
}

$db = getDb();
$pendingBefore = events_venues_geocode_candidates_count($db);

if ($pendingBefore === 0) {
    $payload = [
        'ok' => true,
        'message' => 'Nincs geokódolandó helyszín.',
        'pending_before' => 0,
        'result' => ['ok' => 0, 'fail' => 0, 'remaining' => 0, 'batches' => 0, 'done' => true],
    ];
    if ($isCli) {
        echo $payload['message'] . PHP_EOL;
        exit(0);
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit(0);
}

$result = events_venues_geocode_run_batches($db, $batchSize, $maxBatches);
$summary = events_venues_geocode_result_summary($result);

$payload = [
    'ok' => true,
    'message' => $summary,
    'pending_before' => $pendingBefore,
    'result' => $result,
    'finished_at' => date('c'),
];

if ($isCli) {
    echo $summary . PHP_EOL;
    exit(0);
}

echo json_encode($payload, JSON_UNESCAPED_UNICODE);
