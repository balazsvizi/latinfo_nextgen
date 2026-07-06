<?php
declare(strict_types=1);

require_once __DIR__ . '/venue_request.php';
require_once __DIR__ . '/public_event_calendar.php';
require_once __DIR__ . '/event_public_lang.php';

/**
 * @param array<string, mixed> $ev
 * @param array<int, list<array<string, mixed>>> $categoriesByEventId
 * @return array<string, mixed>
 */
function events_map_marker_meta_from_row(
    array $ev,
    array $categoriesByEventId,
    string $lang,
    callable $eventUrlFn
): array {
    $eventId = (int) ($ev['id'] ?? 0);
    $accent = '#6d8f63';
    $cats = $categoriesByEventId[$eventId] ?? [];
    if ($cats !== []) {
        $rawColor = trim((string) ($cats[0]['color'] ?? ''));
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $rawColor) === 1) {
            $accent = $rawColor;
        }
    }

    $allday = !empty($ev['event_allday']);
    $tsStart = !empty($ev['event_start']) ? strtotime((string) $ev['event_start']) : false;
    $dateLabel = events_public_event_start_date_time_display($allday, $tsStart, $lang);

    $venueName = trim((string) ($ev['venue_name'] ?? ''));
    $addressLine = events_venue_address_summary([
        'postal_code' => (string) ($ev['venue_postal_code'] ?? ''),
        'city' => (string) ($ev['venue_city'] ?? ''),
        'address' => (string) ($ev['venue_address'] ?? ''),
        'country' => (string) ($ev['venue_country'] ?? ''),
    ]);

    return [
        'id' => $eventId,
        'title' => (string) ($ev['event_name'] ?? ''),
        'venue' => $venueName,
        'address' => $addressLine,
        'date' => $dateLabel,
        'url' => $eventUrlFn($ev),
        'accent' => $accent,
    ];
}

/**
 * Szűrt események térkép-markerei (venue GPS, vagy cím alapú geokódolás a kliensen).
 *
 * @param callable(array<string, mixed>): string $eventUrlFn
 * @return array{
 *     markers: list<array<string, mixed>>,
 *     geocode_jobs: list<array{query: string, country_code: string, markers: list<array<string, mixed>>}>,
 *     skipped: int,
 *     pending: int,
 *     total: int
 * }
 */
function events_map_payload_from_rows(
    array $rows,
    array $categoriesByEventId,
    string $lang,
    callable $eventUrlFn
): array {
    $markers = [];
    $geocodeJobsByKey = [];
    $skipped = 0;
    $pending = 0;

    foreach ($rows as $ev) {
        $meta = events_map_marker_meta_from_row($ev, $categoriesByEventId, $lang, $eventUrlFn);
        $coords = events_venue_coordinates_from_row([
            'latitude' => $ev['venue_latitude'] ?? null,
            'longitude' => $ev['venue_longitude'] ?? null,
        ]);

        if ($coords !== null) {
            $markers[] = array_merge($meta, [
                'lat' => $coords['lat'],
                'lng' => $coords['lng'],
            ]);
            continue;
        }

        $venueFields = [
            'address' => (string) ($ev['venue_address'] ?? ''),
            'city' => (string) ($ev['venue_city'] ?? ''),
            'postal_code' => (string) ($ev['venue_postal_code'] ?? ''),
            'country' => (string) ($ev['venue_country'] ?? ''),
        ];
        $query = events_venue_geocode_query($venueFields);
        if ($query === '') {
            $skipped++;
            continue;
        }

        $venueId = (int) ($ev['venue_id'] ?? 0);
        $jobKey = $venueId > 0 ? 'v' . $venueId : 'q' . md5($query);
        if (!isset($geocodeJobsByKey[$jobKey])) {
            $geocodeJobsByKey[$jobKey] = [
                'query' => $query,
                'country_code' => events_venue_country_nominatim_code($venueFields['country']),
                'markers' => [],
            ];
        }
        $geocodeJobsByKey[$jobKey]['markers'][] = $meta;
        $pending++;
    }

    return [
        'markers' => $markers,
        'geocode_jobs' => array_values($geocodeJobsByKey),
        'skipped' => $skipped,
        'pending' => $pending,
        'total' => count($rows),
    ];
}

function events_public_home_map_payload_from_rows(array $rows, array $categoriesByEventId, string $lang): array
{
    return events_map_payload_from_rows(
        $rows,
        $categoriesByEventId,
        $lang,
        static fn (array $ev): string => events_public_calendar_event_url($ev)
    );
}

function events_admin_map_payload_from_rows(array $rows, array $categoriesByEventId): array
{
    $editBase = events_url('szerkeszt.php?id=');

    return events_map_payload_from_rows(
        $rows,
        $categoriesByEventId,
        'hu',
        static fn (array $ev): string => $editBase . (int) ($ev['id'] ?? 0)
    );
}
