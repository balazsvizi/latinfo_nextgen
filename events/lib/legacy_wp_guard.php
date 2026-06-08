<?php
declare(strict_types=1);

/**
 * Régi WordPress / The Events Calendar /events URL-ek felismerése.
 */
function events_is_legacy_wp_events_request(): bool {
    foreach ($_GET as $key => $value) {
        $key = strtolower((string) $key);
        $value = strtolower((string) $value);
        if ($key === 'post_type' && (str_contains($value, 'tribe') || $value === 'event')) {
            return true;
        }
        if (str_starts_with($key, 'tribe_')) {
            return true;
        }
        if (in_array($key, ['eventdisplay', 'ical', 'download', 'action', 'feed'], true)) {
            return true;
        }
    }

    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    if (preg_match('#/events/(feed|rss|list|month|today|day|week)(/|$|\?)#i', $uri) === 1) {
        return true;
    }

    return false;
}

/**
 * /events/ gyökér — nem publikus; régi WP tartalom helyett átirányítás.
 */
function events_handle_events_root_request(): void {
    if (isLoggedIn()) {
        redirect(events_url('events_admin.php'), 302);
    }

    $target = defined('LATINFO_PUBLIC_HOME_URL') ? LATINFO_PUBLIC_HOME_URL : '/';
    redirect($target, 302);
}
