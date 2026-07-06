<?php
declare(strict_types=1);

require_once __DIR__ . '/CronAuth.php';
require_once __DIR__ . '/CronLogger.php';
require_once __DIR__ . '/CronTaskInterface.php';

final class CronRunner
{
    /** @var list<CronTaskInterface> */
    private array $tasks;

    private CronLogger $logger;

    /**
     * @param list<CronTaskInterface> $tasks
     */
    public function __construct(array $tasks, ?CronLogger $logger = null)
    {
        $this->tasks = $tasks;
        $this->logger = $logger ?? new CronLogger();
    }

    /**
     * @return list<array{name: string, label: string, interval: int, last_run: ?string, due: bool}>
     */
    public function listTasks(bool $checkDue = true): array
    {
        $state = $this->readState();
        $now = time();
        $items = [];
        foreach ($this->tasks as $task) {
            $lastRun = isset($state[$task->name()]) ? (int) $state[$task->name()] : null;
            $due = !$checkDue || $this->isDue($task, $lastRun, $now, false);
            $items[] = [
                'name' => $task->name(),
                'label' => $task->label(),
                'interval' => $task->intervalSeconds(),
                'last_run' => $lastRun !== null ? date('Y-m-d H:i:s', $lastRun) : null,
                'due' => $due,
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $options force, task, all, batch_size, batches
     * @return array{
     *   ok: bool,
     *   disabled?: bool,
     *   ran: list<string>,
     *   skipped: list<array{task: string, reason: string}>,
     *   results: array<string, array{ok: bool, message: string, data?: array<string, mixed>}>,
     *   log_path: string
     * }
     */
    public function run(array $options = []): array
    {
        if (!cron_is_enabled()) {
            $this->logger->skip('runner', 'Cron kikapcsolva (CRON_ENABLED=false).');

            return [
                'ok' => false,
                'disabled' => true,
                'ran' => [],
                'skipped' => [],
                'results' => [],
                'log_path' => $this->logger->path(),
            ];
        }

        $force = !empty($options['force']);
        $onlyTask = isset($options['task']) ? trim((string) $options['task']) : '';
        $taskOptions = $options;
        unset($taskOptions['force'], $taskOptions['task']);

        $ran = [];
        $skipped = [];
        $results = [];
        $state = $this->readState();
        $now = time();

        foreach ($this->tasks as $task) {
            $name = $task->name();
            if ($onlyTask !== '' && $name !== $onlyTask) {
                continue;
            }

            $lastRun = isset($state[$name]) ? (int) $state[$name] : null;
            if (!$this->isDue($task, $lastRun, $now, $force)) {
                $nextIn = max(0, $task->intervalSeconds() - ($now - (int) $lastRun));
                $reason = $lastRun === null
                    ? 'még nem futott, de nem force'
                    : 'nem esedékes (következő ~' . $nextIn . ' mp múlva)';
                if ($force) {
                    $reason = 'ismeretlen skip';
                }
                $this->logger->skip($name, $reason);
                $skipped[] = ['task' => $name, 'reason' => $reason];
                continue;
            }

            $lockPath = cron_lock_path($name);
            $lockHandle = @fopen($lockPath, 'c+');
            if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
                if ($lockHandle !== false) {
                    fclose($lockHandle);
                }
                $this->logger->skip($name, 'zárolt (már fut)');
                $skipped[] = ['task' => $name, 'reason' => 'locked'];
                continue;
            }

            $started = microtime(true);
            $mode = $force ? 'force' : 'scheduled';
            $this->logger->start($name, $mode . ', interval=' . $task->intervalSeconds() . 's');

            try {
                $result = $task->run($taskOptions);
                $duration = round(microtime(true) - $started, 2);
                $results[$name] = $result;
                $state[$name] = time();
                $this->writeState($state);

                if (!empty($result['ok'])) {
                    $this->logger->done($name, ($result['message'] ?? 'kész') . ' (' . $duration . 's)');
                    $ran[] = $name;
                } else {
                    $this->logger->error($name, ($result['message'] ?? 'hiba') . ' (' . $duration . 's)');
                    $ran[] = $name;
                }
            } catch (Throwable $e) {
                $duration = round(microtime(true) - $started, 2);
                $msg = 'Kivétel: ' . $e->getMessage();
                $this->logger->error($name, $msg . ' (' . $duration . 's)');
                $results[$name] = ['ok' => false, 'message' => $msg];
                $state[$name] = time();
                $this->writeState($state);
                $ran[] = $name;
            } finally {
                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
            }
        }

        if ($onlyTask !== '' && $ran === [] && $skipped === []) {
            $this->logger->error('runner', 'Ismeretlen feladat: ' . $onlyTask);

            return [
                'ok' => false,
                'ran' => [],
                'skipped' => [['task' => $onlyTask, 'reason' => 'unknown_task']],
                'results' => [],
                'log_path' => $this->logger->path(),
            ];
        }

        return [
            'ok' => true,
            'ran' => $ran,
            'skipped' => $skipped,
            'results' => $results,
            'log_path' => $this->logger->path(),
        ];
    }

    public function logger(): CronLogger
    {
        return $this->logger;
    }

    private function isDue(CronTaskInterface $task, ?int $lastRun, int $now, bool $force): bool
    {
        if ($force) {
            return true;
        }
        if ($lastRun === null) {
            return true;
        }

        return ($now - $lastRun) >= $task->intervalSeconds();
    }

    /**
     * @return array<string, int>
     */
    private function readState(): array
    {
        cron_ensure_data_dirs();
        $path = cron_state_path();
        if (!is_file($path)) {
            return [];
        }
        $json = file_get_contents($path);
        if ($json === false || trim($json) === '') {
            return [];
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }
        $state = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && is_numeric($value)) {
                $state[$key] = (int) $value;
            }
        }

        return $state;
    }

    /**
     * @param array<string, int> $state
     */
    private function writeState(array $state): void
    {
        cron_ensure_data_dirs();
        @file_put_contents(
            cron_state_path(),
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }
}
