#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Központi cron / ütemezett feladat futtató.
 *
 * Crontab (percenként – a feladatok saját intervallumuk szerint futnak):
 *   * * * * * cd /path/to/Alatinfo && php nextgen/cron/run.php >> nextgen/data/cron/runner.out.log 2>&1
 *
 * CLI:
 *   php nextgen/cron/run.php
 *   php nextgen/cron/run.php --task=venue_geocode --force
 *   php nextgen/cron/run.php --task=venue_geocode --all
 *   php nextgen/cron/run.php --list
 *
 * HTTP (CRON_TOKEN a config.local.php-ben):
 *   curl -sf "https://latinfo.hu/nextgen/cron/run.php?token=SECRET"
 *   curl -sf "https://latinfo.hu/nextgen/cron/run.php?token=SECRET&task=venue_geocode&force=1"
 */

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    header('Content-Type: application/json; charset=UTF-8');
}

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/lib/cron/CronAuth.php';
require_once dirname(__DIR__) . '/lib/cron/CronRunner.php';

if (!$isCli && !cron_http_token_valid()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden'], JSON_UNESCAPED_UNICODE);
    exit(1);
}

/** @var list<CronTaskInterface> $tasks */
$tasks = require __DIR__ . '/tasks.php';
$runner = new CronRunner($tasks);

$options = [
    'force' => false,
    'all' => false,
];
$onlyTask = '';
$listOnly = false;

if ($isCli) {
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--force') {
            $options['force'] = true;
            continue;
        }
        if ($arg === '--all') {
            $options['all'] = true;
            continue;
        }
        if ($arg === '--list') {
            $listOnly = true;
            continue;
        }
        if (str_starts_with($arg, '--task=')) {
            $onlyTask = trim(substr($arg, 7));
            continue;
        }
        if (str_starts_with($arg, '--batch-size=')) {
            $options['batch_size'] = max(1, min(25, (int) substr($arg, 13)));
            continue;
        }
        if (str_starts_with($arg, '--batches=')) {
            $options['batches'] = max(1, (int) substr($arg, 10));
            continue;
        }
        if ($arg === '--help' || $arg === '-h') {
            echo <<<'TXT'
Latinfo cron futtató – regisztrált feladatok ütemezése.

Opciók:
  --list             Feladatok listája (intervallum, utolsó futás, esedékes)
  --task=NÉV         Csak egy feladat (pl. venue_geocode)
  --force            Esedékesség figyelmen kívül hagyása
  --all              Teljes populáció (venue_geocode: minden batch)
  --batches=N        Legfeljebb N batch (venue_geocode)
  --batch-size=N     Batch méret (venue_geocode, max 25)
  --help             Súgó

Beállítás: nextgen/core/config.local.php → CRON_TOKEN (HTTP-hez)
Log: nextgen/data/cron/cron.log
TXT;
            exit(0);
        }
    }
} else {
    if (isset($_GET['force']) && (string) $_GET['force'] === '1') {
        $options['force'] = true;
    }
    if (isset($_GET['all']) && (string) $_GET['all'] === '1') {
        $options['all'] = true;
    }
    if (isset($_GET['list']) && (string) $_GET['list'] === '1') {
        $listOnly = true;
    }
    if (isset($_GET['task'])) {
        $onlyTask = trim((string) $_GET['task']);
    }
    if (isset($_GET['batch_size']) && ctype_digit((string) $_GET['batch_size'])) {
        $options['batch_size'] = max(1, min(25, (int) $_GET['batch_size']));
    }
    if (isset($_GET['batches']) && ctype_digit((string) $_GET['batches'])) {
        $options['batches'] = max(1, (int) $_GET['batches']);
    }
}

if ($listOnly) {
    $payload = [
        'ok' => true,
        'tasks' => $runner->listTasks(!$options['force']),
        'log_path' => cron_log_path(),
    ];
    if ($isCli) {
        foreach ($payload['tasks'] as $task) {
            $due = !empty($task['due']) ? 'due' : 'wait';
            echo sprintf(
                "%-18s %-30s interval=%ds last=%s [%s]\n",
                $task['name'],
                $task['label'],
                $task['interval'],
                $task['last_run'] ?? '-',
                $due
            );
        }
        exit(0);
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit(0);
}

if ($onlyTask !== '') {
    $options['task'] = $onlyTask;
}

$result = $runner->run($options);

if ($isCli) {
    if (!empty($result['disabled'])) {
        echo "Cron kikapcsolva (CRON_ENABLED=false).\n";
        exit(1);
    }
    if ($result['ran'] !== []) {
        echo 'Lefutott: ' . implode(', ', $result['ran']) . PHP_EOL;
        foreach ($result['results'] as $name => $taskResult) {
            echo '  ' . $name . ': ' . ($taskResult['message'] ?? '') . PHP_EOL;
        }
    }
    if ($result['skipped'] !== []) {
        foreach ($result['skipped'] as $skip) {
            echo 'Kihagyva: ' . $skip['task'] . ' – ' . $skip['reason'] . PHP_EOL;
        }
    }
    if ($result['ran'] === [] && $result['skipped'] === []) {
        echo "Nincs futtatandó feladat.\n";
    }
    echo 'Log: ' . ($result['log_path'] ?? cron_log_path()) . PHP_EOL;
    exit(!empty($result['ok']) ? 0 : 1);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
