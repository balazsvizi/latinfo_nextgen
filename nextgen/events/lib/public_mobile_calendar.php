<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_event_calendar.php';
require_once __DIR__ . '/public_event_calendar.php';
require_once __DIR__ . '/event_public_lang.php';
require_once __DIR__ . '/event_view_tracking.php';

/**
 * Új mobil naptár (mcal) — segédek.
 */

/**
 * @return list<string>
 */
function events_public_mobile_calendar_weekday_letters(string $lang): array
{
    if ($lang === 'en') {
        return ['M', 'T', 'W', 'T', 'F', 'S', 'S'];
    }

    return ['H', 'K', 'S', 'C', 'P', 'S', 'V'];
}

function events_public_mobile_calendar_tz_abbr(DateTimeImmutable $dt): string
{
    $abbr = $dt->setTimezone(new DateTimeZone('Europe/Budapest'))->format('T');

    return $abbr !== '' ? $abbr : 'CEST';
}

/**
 * Esemény meta sor: „2026. július 15. @ 18:00 – 20:00 CEST”
 */
function events_public_mobile_calendar_event_meta(array $ev, string $lang): string
{
    $startRaw = $ev['event_start'] ?? null;
    if ($startRaw === null || $startRaw === '') {
        return '';
    }

    try {
        $tz = new DateTimeZone('Europe/Budapest');
        $start = new DateTimeImmutable((string) $startRaw, $tz);
    } catch (Throwable) {
        return '';
    }

    $dayLabel = events_public_format_event_day((int) $start->format('U'), $lang);
    if (!empty($ev['event_allday'])) {
        return $dayLabel;
    }

    $timePart = $start->format('H:i');
    $endRaw = $ev['event_end'] ?? null;
    if ($endRaw !== null && $endRaw !== '') {
        try {
            $end = new DateTimeImmutable((string) $endRaw, $tz);
            $endTime = $end->format('H:i');
            if ($endTime !== '00:00' && $endTime !== $timePart) {
                $timePart .= ' – ' . $endTime;
            }
        } catch (Throwable) {
            // keep start only
        }
    }

    $tzAbbr = events_public_mobile_calendar_tz_abbr($start);

    return $dayLabel . ' @ ' . $timePart . ' ' . $tzAbbr;
}

/**
 * @param array<int, list<array{color?: string}>> $categoriesByEventId
 */
function events_public_mobile_calendar_event_accent(array $ev, array $categoriesByEventId): string
{
    $eid = (int) ($ev['id'] ?? 0);
    $cats = $categoriesByEventId[$eid] ?? [];
    $accent = '#6d8f63';
    if ($cats !== []) {
        $candidate = trim((string) ($cats[0]['color'] ?? '#6d8f63'));
        if ($candidate !== '' && preg_match('/^#[0-9A-Fa-f]{6}$/', $candidate) === 1) {
            $accent = $candidate;
        }
    }
    if (function_exists('events_event_change_active') && events_event_change_active($ev)) {
        $changeStyle = events_event_change_calendar_block_style($ev);
        if (preg_match('/--events-cal-accent:([^;]+)/', $changeStyle, $m) === 1) {
            $accent = trim($m[1]);
        }
    }

    return $accent;
}

/**
 * Alapértelmezett kiválasztott nap a hónapban.
 *
 * @param array<string, list<array<string, mixed>>> $byDay
 */
function events_public_mobile_calendar_resolve_selected_day(
    DateTimeImmutable $monthFirst,
    array $byDay,
    string $dayParam = ''
): string {
    $monthKey = $monthFirst->format('Y-m');
    $dayParam = trim($dayParam);
    if ($dayParam !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dayParam) === 1 && str_starts_with($dayParam, $monthKey)) {
        return $dayParam;
    }

    $today = events_admin_calendar_effective_today()->format('Y-m-d');
    if (str_starts_with($today, $monthKey)) {
        return $today;
    }

    $keys = array_keys($byDay);
    sort($keys);
    foreach ($keys as $key) {
        if (str_starts_with((string) $key, $monthKey) && $byDay[$key] !== []) {
            return (string) $key;
        }
    }

    return $monthFirst->format('Y-m-d');
}

/**
 * @param list<array<string, mixed>> $dayEvents
 * @param array<int, list<array{color?: string}>> $categoriesByEventId
 * @return list<array{id: int, name: string, url: string, accent: string, meta: string}>
 */
function events_public_mobile_calendar_day_event_payload(
    array $dayEvents,
    array $categoriesByEventId,
    string $lang
): array {
    $out = [];
    foreach ($dayEvents as $ev) {
        $eid = (int) ($ev['id'] ?? 0);
        if ($eid <= 0) {
            continue;
        }
        $out[] = [
            'id' => $eid,
            'name' => (string) ($ev['event_name'] ?? ''),
            'url' => events_public_calendar_event_url($ev, EVENTS_VIEW_SOURCE_CALENDAR),
            'accent' => events_public_mobile_calendar_event_accent($ev, $categoriesByEventId),
            'meta' => events_public_mobile_calendar_event_meta($ev, $lang),
        ];
    }

    return $out;
}

/**
 * @param array<string, list<array<string, mixed>>> $byDay
 * @param array<int, list<array{color?: string}>> $categoriesByEventId
 * @return array<string, list<array{id: int, name: string, url: string, accent: string, meta: string}>>
 */
function events_public_mobile_calendar_events_by_day_payload(
    array $byDay,
    array $categoriesByEventId,
    string $lang
): array {
    $out = [];
    foreach ($byDay as $dayKey => $dayEvents) {
        $out[(string) $dayKey] = events_public_mobile_calendar_day_event_payload($dayEvents, $categoriesByEventId, $lang);
    }

    return $out;
}

/**
 * Nap fejléc a listához: „2026. július 15.”
 */
function events_public_mobile_calendar_day_heading(string $dayKey, string $lang): string
{
    try {
        $dt = new DateTimeImmutable($dayKey . ' 12:00:00', new DateTimeZone('Europe/Budapest'));
    } catch (Throwable) {
        return $dayKey;
    }

    return events_public_format_event_day((int) $dt->format('U'), $lang);
}
