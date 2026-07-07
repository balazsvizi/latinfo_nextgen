<?php
declare(strict_types=1);

/**
 * Szervezői portál bootstrap – elkülönülten a backoffice-tól (nincs admin requireLogin).
 */
$nextgenRoot = dirname(__DIR__, 2);
$eventsRoot = dirname(__DIR__);

require_once $nextgenRoot . '/core/config.php';
require_once $nextgenRoot . '/core/database.php';
require_once $nextgenRoot . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(0, '/');
    session_start();
}

require_once __DIR__ . '/includes/auth.php';
require_once $eventsRoot . '/lib/event_status.php';
require_once $eventsRoot . '/lib/event_edit_stats.php';
require_once $eventsRoot . '/lib/admin_event_filters.php';
require_once $eventsRoot . '/lib/organizers_admin.php';
require_once $eventsRoot . '/lib/organizer_accounts.php';

if (!function_exists('events_url')) {
    function events_url(string $path = ''): string
    {
        return nextgen_url('events/' . ltrim($path, '/'));
    }
}

if (!function_exists('organizers_portal_url')) {
    function organizers_portal_url(string $path = ''): string
    {
        return events_url('organizers/' . ltrim($path, '/'));
    }
}

/** @deprecated organizers_portal_url() használata */
if (!function_exists('szervezo_url')) {
    function szervezo_url(string $path = ''): string
    {
        return organizers_portal_url($path);
    }
}

if (!function_exists('events_public_home_path')) {
    function events_public_home_path(): string
    {
        $seg = defined('EVENTS_HOME_PATH') ? EVENTS_HOME_PATH : 'events';

        return rtrim(site_url($seg . '/'), '/') . '/';
    }
}

if (!function_exists('events_public_canonical_url')) {
    function events_public_canonical_url(string $slug): string
    {
        $seg = defined('EVENTS_PUBLIC_PATH') ? EVENTS_PUBLIC_PATH : 'event';

        return rtrim(site_url($seg . '/' . rawurlencode($slug)), '/') . '/';
    }
}

/**
 * @return array<string, mixed>|null
 */
function organizers_portal_organizer_summary(PDO $db, int $organizerId): ?array
{
    if ($organizerId <= 0) {
        return null;
    }
    $statsSql = events_organizers_admin_stats_subquery_sql();
    try {
        $stmt = $db->prepare("
            SELECT
                o.`id`,
                o.`name`,
                COALESCE(st.`event_count`, 0) AS `event_count`,
                COALESCE(st.`published_count`, 0) AS `published_count`,
                COALESCE(st.`upcoming_count`, 0) AS `upcoming_count`,
                st.`last_event_at`,
                st.`next_event_at`
            FROM `events_organizers` o
            LEFT JOIN ({$statsSql}) st ON st.`organizer_id` = o.`id`
            WHERE o.`id` = ?
            LIMIT 1
        ");
        $stmt->execute([$organizerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (Throwable) {
        return null;
    }
}

/** @deprecated organizers_portal_organizer_summary() használata */
function szervezo_organizer_summary(PDO $db, int $organizerId): ?array
{
    return organizers_portal_organizer_summary($db, $organizerId);
}
