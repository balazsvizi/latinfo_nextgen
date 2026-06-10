<?php
declare(strict_types=1);

require_once __DIR__ . '/event_public_lang.php';

function events_ical_calendar_name(): string
{
    if (defined('SITE_NAME')) {
        $name = trim((string) SITE_NAME);
        if ($name !== '') {
            return $name;
        }
    }

    return 'Latinfo.hu';
}

function events_ical_fold_line(string $line): string
{
    if (strlen($line) <= 75) {
        return $line;
    }

    $out = substr($line, 0, 75);
    $rest = substr($line, 75);
    while ($rest !== '') {
        $out .= "\r\n " . substr($rest, 0, 74);
        $rest = substr($rest, 74);
    }

    return $out;
}

function events_ical_escape_text(string $text): string
{
    $text = str_replace('\\', '\\\\', $text);
    $text = str_replace(';', '\\;', $text);
    $text = str_replace(',', '\\,', $text);
    $text = str_replace(["\r\n", "\n", "\r"], '\\n', $text);

    return $text;
}

function events_ical_format_utc(DateTimeImmutable $dt): string
{
    return $dt->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
}

/**
 * @return array{0: string, 1: string}|null [dtstart, dtend]
 */
function events_ical_event_datetimes(array $row): ?array
{
    $startRaw = trim((string) ($row['event_start'] ?? ''));
    if ($startRaw === '') {
        return null;
    }

    try {
        $start = new DateTimeImmutable($startRaw);
    } catch (Throwable) {
        return null;
    }

    $allday = !empty($row['event_allday']);
    $endRaw = trim((string) ($row['event_end'] ?? ''));

    if ($allday) {
        $dtStart = 'DTSTART;VALUE=DATE:' . $start->format('Ymd');
        if ($endRaw !== '') {
            try {
                $end = new DateTimeImmutable($endRaw);
                $endExclusive = $end->modify('+1 day');
            } catch (Throwable) {
                $endExclusive = $start->modify('+1 day');
            }
        } else {
            $endExclusive = $start->modify('+1 day');
        }

        return [$dtStart, 'DTEND;VALUE=DATE:' . $endExclusive->format('Ymd')];
    }

    $dtStart = 'DTSTART:' . events_ical_format_utc($start);
    if ($endRaw !== '') {
        try {
            $end = new DateTimeImmutable($endRaw);
        } catch (Throwable) {
            $end = $start->modify('+2 hours');
        }
    } else {
        $end = $start->modify('+2 hours');
    }
    if ($end <= $start) {
        $end = $start->modify('+1 hour');
    }

    return [$dtStart, 'DTEND:' . events_ical_format_utc($end)];
}

function events_ical_event_location(array $row): string
{
    $parts = [];
    $venue = trim((string) ($row['venue_name'] ?? ''));
    if ($venue !== '') {
        $parts[] = $venue;
    }
    $city = trim((string) ($row['venue_city'] ?? ''));
    if ($city !== '') {
        $parts[] = $city;
    }

    return implode(', ', $parts);
}

function events_ical_plain_description(array $row, string $lang): string
{
    $parts = [];
    $content = trim(strip_tags(html_entity_decode((string) ($row['event_content'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if ($content !== '') {
        if (function_exists('mb_substr')) {
            $content = mb_substr($content, 0, 1200, 'UTF-8');
        } else {
            $content = substr($content, 0, 1200);
        }
        $parts[] = $content;
    }

    $slug = trim((string) ($row['event_slug'] ?? ''));
    if ($slug !== '') {
        $parts[] = events_absolute_url(events_public_event_page_url($slug, $lang));
    }

    return implode("\n\n", $parts);
}

/**
 * @param list<array<string, mixed>> $events
 */
function events_ical_build_calendar(array $events, ?string $calendarName, string $lang, bool $outlook = false): string
{
    $calendarName = trim((string) ($calendarName ?? ''));
    if ($calendarName === '') {
        $calendarName = events_ical_calendar_name();
    }

    $eol = $outlook ? "\r\n" : "\r\n";
    $now = events_ical_format_utc(new DateTimeImmutable('now', new DateTimeZone('UTC')));
    $escapedName = events_ical_escape_text($calendarName);
    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Latinfo.hu//Events Calendar//HU',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'NAME:' . $escapedName,
        'X-WR-CALNAME:' . $escapedName,
    ];
    if ($outlook) {
        $lines[] = 'X-MS-OLK-FORCEINSPECTOROPEN:TRUE';
    }

    foreach ($events as $row) {
        $dates = events_ical_event_datetimes($row);
        if ($dates === null) {
            continue;
        }

        $eventId = (int) ($row['id'] ?? 0);
        $summary = trim((string) ($row['event_name'] ?? ''));
        if ($summary === '') {
            continue;
        }

        $slug = trim((string) ($row['event_slug'] ?? ''));
        $eventUrl = $slug !== '' ? events_absolute_url(events_public_event_page_url($slug, $lang)) : '';
        $location = events_ical_event_location($row);
        $description = events_ical_plain_description($row, $lang);

        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:latinfo-event-' . $eventId . '@latinfo.hu';
        $lines[] = 'DTSTAMP:' . $now;
        $lines[] = $dates[0];
        $lines[] = $dates[1];
        $lines[] = 'SUMMARY:' . events_ical_escape_text($summary);
        if ($description !== '') {
            $lines[] = 'DESCRIPTION:' . events_ical_escape_text($description);
        }
        if ($location !== '') {
            $lines[] = 'LOCATION:' . events_ical_escape_text($location);
        }
        if ($eventUrl !== '') {
            $lines[] = 'URL:' . events_ical_escape_text($eventUrl);
        }
        $lines[] = 'END:VEVENT';
    }

    $lines[] = 'END:VCALENDAR';

    $folded = array_map('events_ical_fold_line', $lines);

    return implode($eol, $folded) . $eol;
}

/**
 * @param array<string, string|int> $queryParams
 */
function events_ical_feed_url(array $queryParams, ?string $variant = null): string
{
    $params = $queryParams;
    unset($params['month'], $params['view']);
    if ($variant === 'outlook') {
        $params['outlook'] = '1';
    }
    if ($variant === 'download') {
        $params['download'] = '1';
    }
    if ($variant === 'outlook_download') {
        $params['outlook'] = '1';
        $params['download'] = '1';
    }

    $qs = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    return events_absolute_url(events_url('ical_feed.php' . ($qs !== '' ? '?' . $qs : '')));
}

/**
 * @param array<string, string|int> $queryParams
 */
function events_ical_subscribe_links(array $queryParams, ?string $calendarName = null, ?string $lang = null): array
{
    $calendarName = trim((string) ($calendarName ?? ''));
    if ($calendarName === '') {
        $calendarName = events_ical_calendar_name();
    }

    $feedHttps = events_ical_feed_url($queryParams);
    $feedWebcal = preg_replace('#^https?://#i', 'webcal://', $feedHttps) ?? $feedHttps;
    $encodedWebcal = rawurlencode($feedWebcal);
    $encodedHttps = rawurlencode($feedHttps);
    $encodedName = rawurlencode($calendarName);

    return [
        'feed' => $feedHttps,
        'google' => 'https://www.google.com/calendar/render?cid=' . $encodedWebcal,
        'ical' => $feedWebcal,
        'outlook365' => 'https://outlook.office.com/calendar/0/addfromweb?url=' . $encodedHttps . '&name=' . $encodedName,
        'outlook_live' => 'https://outlook.live.com/calendar/0/addfromweb?url=' . $encodedHttps . '&name=' . $encodedName,
        'download' => events_ical_feed_url($queryParams, 'download'),
        'download_outlook' => events_ical_feed_url($queryParams, 'outlook_download'),
    ];
}
