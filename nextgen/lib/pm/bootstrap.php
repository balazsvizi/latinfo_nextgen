<?php
declare(strict_types=1);

require_once __DIR__ . '/PmTools.php';

function pm_tools_db(): PDO
{
    return getDb();
}

function pm_tools_asset_url(string $file): string
{
    $path = 'assets/' . ltrim(str_replace('\\', '/', $file), '/');

    return nextgen_url($path);
}

function pm_tools_api_url(): string
{
    return nextgen_url('admin/pm/api.php');
}

function pm_tools_index_url(): string
{
    return nextgen_url('admin/pm/');
}

function pm_tools_page_url(string $phpPath): string
{
    $phpPath = PmTools::normalizePhpPath($phpPath);

    return site_url(ltrim($phpPath, '/'));
}

function pm_tools_has_access(): bool
{
    return isLoggedIn() && isSuperadmin();
}

function pm_tools_require_access(): void
{
    requireSuperadmin();
}

function pm_tools_csrf_token(): string
{
    return csrf_token('pmtools');
}

function pm_tools_csrf_validate(string $token): bool
{
    $expected = (string) ($_SESSION['_csrf']['pmtools'] ?? '');

    return $token !== '' && $expected !== '' && hash_equals($expected, $token);
}

function pm_tools_current_php_path(): string
{
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if ($script === '') {
        return '/';
    }

    return PmTools::normalizePhpPath($script);
}

function pm_tools_unanswered_badge_count(): int
{
    if (!pm_tools_has_access()) {
        return 0;
    }
    try {
        return PmTools::countTotalUnansweredPages(pm_tools_db());
    } catch (Throwable) {
        return 0;
    }
}
