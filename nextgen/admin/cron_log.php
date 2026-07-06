<?php
declare(strict_types=1);

$pageTitle = 'Cron log';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../lib/cron/CronAuth.php';
require_once __DIR__ . '/../lib/cron/CronLogger.php';
require_once __DIR__ . '/../lib/cron/CronRunner.php';

requireSuperadmin();

/** @var list<CronTaskInterface> $tasks */
$tasks = require dirname(__DIR__) . '/cron/tasks.php';
$runner = new CronRunner($tasks);
$logger = $runner->logger();

$lines = isset($_GET['lines']) && ctype_digit((string) $_GET['lines'])
    ? max(50, min(1000, (int) $_GET['lines']))
    : 200;
$logLines = $logger->tail($lines);
$taskList = $runner->listTasks(false);
$logPath = $logger->path();
$cronUrl = nextgen_url('cron/run.php');

require_once __DIR__ . '/../partials/header.php';
?>
<div class="card">
    <h2>Cron log</h2>
    <p>Ütemezett háttérfeladatok futási naplója. A központi futtató: <code>nextgen/cron/run.php</code>.</p>

    <div class="toolbar">
        <a href="<?= h(nextgen_url('admin/cron_log.php')) ?>" class="btn btn-secondary">Frissítés</a>
        <a href="<?= h(nextgen_url('admin/cron_log.php?lines=500')) ?>" class="btn btn-secondary">Utolsó 500 sor</a>
    </div>

    <h3>Regisztrált feladatok</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Azonosító</th>
                    <th>Név</th>
                    <th>Intervallum</th>
                    <th>Utolsó futás</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($taskList as $task): ?>
                <tr>
                    <td><code><?= h($task['name']) ?></code></td>
                    <td><?= h($task['label']) ?></td>
                    <td><?= (int) $task['interval'] ?> mp</td>
                    <td><?= h($task['last_run'] ?? '–') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h3>Log (utolsó <?= (int) $lines ?> sor)</h3>
    <p class="help">Fájl: <code><?= h($logPath) ?></code></p>
    <?php if ($logLines === []): ?>
        <p class="help muted">Még nincs log bejegyzés. Állíts be crontab-ot vagy futtasd CLI-ből: <code>php nextgen/cron/run.php --task=venue_geocode --force</code></p>
    <?php else: ?>
        <pre class="venues-geocode-run__code cron-log-view"><?= h(implode("\n", $logLines)) ?></pre>
    <?php endif; ?>

    <h3>Beállítás</h3>
    <ul class="help">
        <li><code>config.local.php</code> → <code>CRON_TOKEN</code> (HTTP cronhoz, min. 32 karakter)</li>
        <li>Crontab: <code>* * * * * cd /path/to/Alatinfo &amp;&amp; php nextgen/cron/run.php</code></li>
        <li>HTTP: <code>curl -sf "<?= h($cronUrl) ?>?token=…"</code></li>
        <li>Egy feladat kényszerítve: <code>php nextgen/cron/run.php --task=venue_geocode --force</code></li>
    </ul>
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
