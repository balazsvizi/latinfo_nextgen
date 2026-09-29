<?php
declare(strict_types=1);

/**
 * Bejelentkezés / fiók a nyilvános fejlécben (kijelentkezés az account oldalon).
 *
 * @var string $lang
 * @var array<string, string> $N events_public_nav_strings()
 */

if (!function_exists('user_is_logged_in')) {
    require_once dirname(__DIR__, 2) . '/user/includes/auth.php';
}

if (!function_exists('events_public_append_query')) {
    require_once __DIR__ . '/../bootstrap.php';
}

$returnPath = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$fallbackHome = defined('LATINFO_PUBLIC_HOME_URL') ? (string) LATINFO_PUBLIC_HOME_URL : site_url('/');
$safeReturn = user_safe_return_path($returnPath, $fallbackHome);
$loginHref = events_public_append_query(user_url('login.php'), ['return' => $safeReturn]);
$accountHref = user_url('index.php');

$loggedIn = user_is_logged_in();
$displayName = user_session_display_name();
if ($displayName === '' && !empty($_SESSION['user_email'])) {
    $displayName = trim((string) $_SESSION['user_email']);
}
if (mb_strlen($displayName) > 28) {
    $displayName = mb_substr($displayName, 0, 25) . '…';
}

$loggedInLabel = $displayName !== ''
    ? sprintf((string) ($N['auth_logged_in_as'] ?? ''), $displayName)
    : (string) ($N['auth_account'] ?? '');
?>
<div class="event-user-auth" role="navigation" aria-label="<?= h((string) ($N['auth_nav_aria'] ?? '')) ?>">
    <?php if ($loggedIn): ?>
        <a
            class="event-user-auth__account"
            href="<?= h($accountHref) ?>"
            title="<?= h($loggedInLabel) ?>"
            aria-label="<?= h($loggedInLabel) ?>"
            data-public-nav-track="user-account"
        ><?= h($displayName !== '' ? $displayName : (string) ($N['auth_account'] ?? '')) ?></a>
    <?php else: ?>
        <a
            class="event-user-auth__login"
            href="<?= h($loginHref) ?>"
            title="<?= h((string) ($N['auth_login'] ?? '')) ?>"
            data-public-nav-track="user-login"
        ><?= h((string) ($N['auth_login'] ?? '')) ?></a>
    <?php endif; ?>
</div>
