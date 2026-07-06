<?php
declare(strict_types=1);

require_once __DIR__ . '/CronAuth.php';

final class CronLogger
{
    private string $logPath;

    public function __construct(?string $logPath = null)
    {
        $this->logPath = $logPath ?? cron_log_path();
        cron_ensure_data_dirs();
    }

    public function info(string $task, string $message): void
    {
        $this->write('INFO', $task, $message);
    }

    public function skip(string $task, string $message): void
    {
        $this->write('SKIP', $task, $message);
    }

    public function error(string $task, string $message): void
    {
        $this->write('ERROR', $task, $message);
    }

    public function start(string $task, string $message): void
    {
        $this->write('START', $task, $message);
    }

    public function done(string $task, string $message): void
    {
        $this->write('DONE', $task, $message);
    }

    /**
     * Utolsó N sor olvasása (admin megjelenítéshez).
     *
     * @return list<string>
     */
    public function tail(int $lines = 200): array
    {
        if (!is_file($this->logPath)) {
            return [];
        }

        $content = @file_get_contents($this->logPath);
        if ($content === false || $content === '') {
            return [];
        }

        $all = preg_split('/\R/', rtrim($content, "\r\n")) ?: [];
        if (count($all) <= $lines) {
            return $all;
        }

        return array_slice($all, -$lines);
    }

    public function path(): string
    {
        return $this->logPath;
    }

    private function write(string $level, string $task, string $message): void
    {
        cron_ensure_data_dirs();
        $line = sprintf(
            '[%s] %-5s %-22s %s',
            date('Y-m-d H:i:s'),
            $level,
            $task,
            $message
        );
        @file_put_contents($this->logPath, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
