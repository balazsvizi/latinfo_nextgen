<?php
declare(strict_types=1);

const EVENTS_VIEW_METRIC_PAGE = 'page_view';
const EVENTS_VIEW_METRIC_CALENDAR_PREVIEW = 'calendar_preview';

const EVENTS_VIEW_SOURCE_DIRECT = 'direct';
const EVENTS_VIEW_SOURCE_CALENDAR = 'calendar';
const EVENTS_VIEW_SOURCE_CAL_PREVIEW = 'cal_preview';
const EVENTS_VIEW_SOURCE_LIST = 'list';

/**
 * @return list<string>
 */
function events_view_metric_types(): array
{
    return [EVENTS_VIEW_METRIC_PAGE, EVENTS_VIEW_METRIC_CALENDAR_PREVIEW];
}

function events_view_tracking_ip_hash(): ?string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    return $ip !== '' ? hash('sha256', $ip . '|' . SITE_NAME) : null;
}

function events_view_tracking_append_ref(string $url, string $ref): string
{
    $url = trim($url);
    $ref = trim($ref);
    if ($url === '' || $url === '#' || $ref === '') {
        return $url;
    }

    $separator = str_contains($url, '?') ? '&' : '?';

    return $url . $separator . 'ref=' . rawurlencode($ref);
}

function events_view_tracking_resolve_page_source(string $ref): string
{
    return match (trim($ref)) {
        EVENTS_VIEW_SOURCE_CAL_PREVIEW => EVENTS_VIEW_SOURCE_CAL_PREVIEW,
        EVENTS_VIEW_SOURCE_CALENDAR => EVENTS_VIEW_SOURCE_CALENDAR,
        EVENTS_VIEW_SOURCE_LIST => EVENTS_VIEW_SOURCE_LIST,
        default => EVENTS_VIEW_SOURCE_DIRECT,
    };
}

function events_view_tracking_is_published_event(PDO $db, int $eventId): bool
{
    if ($eventId <= 0) {
        return false;
    }

    $stmt = $db->prepare('SELECT 1 FROM `events_calendar_events` WHERE `id` = ? AND `event_status` = ? LIMIT 1');
    $stmt->execute([$eventId, events_public_post_status()]);

    return (bool) $stmt->fetchColumn();
}

/**
 * Admin munkamenetben nem rögzítünk megtekintést (saját számláló).
 */
function events_view_tracking_should_record(): bool
{
    return !(function_exists('isLoggedIn') && isLoggedIn());
}

function events_track_event_view(PDO $db, int $eventId, string $metricType, ?string $source = null): void
{
    if (!events_view_tracking_should_record()) {
        return;
    }

    if ($eventId <= 0 || !in_array($metricType, events_view_metric_types(), true)) {
        return;
    }

    if ($metricType === EVENTS_VIEW_METRIC_PAGE) {
        $source = events_view_tracking_resolve_page_source((string) $source);
    } elseif ($source === null || $source === '') {
        $source = EVENTS_VIEW_SOURCE_CALENDAR;
    }

    try {
        $stmt = $db->prepare(
            'INSERT INTO `events_calendar_event_views` (`esemény_id`, `ip_hash`, `metric_type`, `source`) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$eventId, events_view_tracking_ip_hash(), $metricType, $source]);
    } catch (Throwable) {
        // Opcionális napló – ne törjük a megjelenítést.
    }
}
