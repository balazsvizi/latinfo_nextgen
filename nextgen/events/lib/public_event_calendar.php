<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_event_calendar.php';

/**
 * Nyilvános naptár segédek — csak közzétett események linkjei.
 */

function events_public_calendar_event_url(array $ev): string {
    $slug = trim((string) ($ev['event_slug'] ?? ''));
    if ($slug === '') {
        return '#';
    }

    return events_megjelenit_url($slug);
}

/**
 * @param array<string, string> $getParams
 */
function events_public_calendar_month_url(string $monthKey, array $getParams): string {
    return events_admin_calendar_month_url($monthKey, $getParams, events_public_home_page_script());
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
