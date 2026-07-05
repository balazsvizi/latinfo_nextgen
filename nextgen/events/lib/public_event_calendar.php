<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_event_calendar.php';
require_once __DIR__ . '/event_view_tracking.php';

/**
 * Nyilvános naptár segédek — csak közzétett események linkjei.
 */

function events_public_calendar_event_url(array $ev, ?string $ref = null): string {
    $slug = trim((string) ($ev['event_slug'] ?? ''));
    if ($slug === '') {
        return '#';
    }

    $url = events_megjelenit_url($slug);
    if ($ref !== null && $ref !== '') {
        return events_view_tracking_append_ref($url, $ref);
    }

    return $url;
}

/**
 * @param array<string, string> $getParams
 */
function events_public_calendar_month_url(string $monthKey, array $getParams): string {
    $lang = (($getParams['lang'] ?? 'hu') === 'en') ? 'en' : 'hu';

    return events_public_home_url($lang, array_merge($getParams, ['month' => $monthKey]));
}

function events_public_calendar_month_label(DateTimeImmutable $monthFirst, string $lang): string {
    if ($lang === 'en') {
        return $monthFirst->format('F Y');
    }

    return events_admin_calendar_month_label($monthFirst);
}

/**
 * @return list<string>
 */
function events_public_calendar_weekday_headers(string $lang): array {
    if ($lang === 'en') {
        return ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    }

    return ['Hétfő', 'Kedd', 'Szerda', 'Csütörtök', 'Péntek', 'Szombat', 'Vasárnap'];
}
