<?php
declare(strict_types=1);

/**
 * Cron konfiguráció és HTTP token ellenőrzés.
 */
function cron_local_config(): array
{
    static $loaded = null;
    if ($loaded !== null) {
        return $loaded;
    }
    $loaded = [];
    if (!function_exists('cfg_get')) {
        return $loaded;
    }
    $localPath = dirname(__DIR__, 2) . '/core/config.local.php';
    if (is_file($localPath)) {
        $config = require $localPath;
        if (is_array($config)) {
            $loaded = $config;
        }
    }

    return $loaded;
}

function cron_token_from_config(): string
{
    $local = cron_local_config();

    return trim((string) cfg_get('CRON_TOKEN', '', $local));
}

function cron_is_enabled(): bool
{
    $local = cron_local_config();
    $value = cfg_get('CRON_ENABLED', true, $local);

    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

function cron_data_dir(): string
{
    return dirname(__DIR__, 2) . '/data/cron';
}

function cron_log_path(): string
{
    $local = cron_local_config();
    $custom = trim((string) cfg_get('CRON_LOG_PATH', '', $local));
    if ($custom !== '') {
        return $custom;
    }

    return cron_data_dir() . '/cron.log';
}

function cron_state_path(): string
{
    return cron_data_dir() . '/state.json';
}

function cron_lock_path(string $taskName): string
{
    $safe = preg_replace('/[^a-z0-9_-]+/i', '_', $taskName) ?? 'task';

    return cron_data_dir() . '/locks/' . $safe . '.lock';
}

function cron_http_token_valid(): bool
{
    $expected = cron_token_from_config();
    if ($expected === '') {
        return false;
    }

    $provided = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));

    return $provided !== '' && hash_equals($expected, $provided);
}

function cron_ensure_data_dirs(): void
{
    $dirs = [
        cron_data_dir(),
        cron_data_dir() . '/locks',
    ];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }
}
