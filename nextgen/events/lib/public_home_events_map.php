<?php
declare(strict_types=1);

require_once __DIR__ . '/venue_request.php';
require_once __DIR__ . '/public_event_calendar.php';
require_once __DIR__ . '/event_public_lang.php';

/**
 * Szűrt események térkép-markerei (venue GPS alapján).
 *
 * @param list<array<string, mixed>> $rows
 * @param array<int, list<array<string, mixed>>> $categoriesByEventId
 * @return array{markers: list<array<string, mixed>>, skipped: int, total: int}
 */
function events_public_home_map_payload_from_rows(array $rows, array $categoriesByEventId, string $lang): array
{
    $markers = [];
    $skipped = 0;

    foreach ($rows as $ev) {
        $coords = events_venue_coordinates_from_row([
            'latitude' => $ev['venue_latitude'] ?? null,
            'longitude' => $ev['venue_longitude'] ?? null,
        ]);
        if ($coords === null) {
            $skipped++;
            continue;
        }

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
            'country' => '',
        ]);

        $markers[] = [
            'id' => $eventId,
            'lat' => $coords['lat'],
            'lng' => $coords['lng'],
            'title' => (string) ($ev['event_name'] ?? ''),
            'venue' => $venueName,
            'address' => $addressLine,
            'date' => $dateLabel,
            'url' => events_public_calendar_event_url($ev),
            'accent' => $accent,
        ];
    }

    return [
        'markers' => $markers,
        'skipped' => $skipped,
        'total' => count($rows),
    ];
}
