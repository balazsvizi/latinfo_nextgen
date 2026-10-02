<?php
declare(strict_types=1);

/**
 * Nyilvános eseményoldal Event JSON-LD (Google rich results).
 * Csak akkor ad vissza sémát, ha van érvényes location (Place + címrészlet).
 *
 * @param array<string, mixed> $event
 * @param array{
 *   canonical: string,
 *   description: string,
 *   lang: string,
 *   venue_name: string,
 *   venue_slug: string,
 *   venue_country: string,
 *   venue_city: string,
 *   venue_postal_code: string,
 *   venue_address: string,
 *   venue_coords: ?array{lat: float|int|string, lng: float|int|string},
 *   featured_image_url: string,
 *   change_type: ?string,
 *   ts_start: int|false,
 *   ts_end: int|false
 * } $ctx
 * @return array<string, mixed>|null
 */
function events_public_event_jsonld(array $event, array $ctx): ?array
{
    $location = events_public_event_jsonld_location($ctx);
    if ($location === null) {
        return null;
    }

    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => (string) ($event['event_name'] ?? ''),
        'url' => (string) $ctx['canonical'],
        'inLanguage' => (($ctx['lang'] ?? 'hu') === 'en') ? 'en' : 'hu',
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'location' => $location,
    ];

    $desc = trim((string) ($ctx['description'] ?? ''));
    if ($desc !== '') {
        $jsonLd['description'] = $desc;
    }

    $tsStart = $ctx['ts_start'] ?? false;
    if (!empty($event['event_start']) && $tsStart) {
        $jsonLd['startDate'] = date('c', (int) $tsStart);
    }
    $tsEnd = $ctx['ts_end'] ?? false;
    if (!empty($event['event_end']) && $tsEnd) {
        $jsonLd['endDate'] = date('c', (int) $tsEnd);
    }

    $changeType = $ctx['change_type'] ?? null;
    if (
        function_exists('events_event_change_type_cancelled')
        && $changeType === events_event_change_type_cancelled()
    ) {
        $jsonLd['eventStatus'] = 'https://schema.org/EventCancelled';
    } elseif (
        function_exists('events_event_change_type_modified')
        && $changeType === events_event_change_type_modified()
    ) {
        $jsonLd['eventStatus'] = 'https://schema.org/EventRescheduled';
    } else {
        $jsonLd['eventStatus'] = 'https://schema.org/EventScheduled';
    }

    $image = trim((string) ($ctx['featured_image_url'] ?? ''));
    if ($image !== '') {
        $jsonLd['image'] = [$image];
    }

    return $jsonLd;
}

/**
 * @param array<string, mixed> $ctx
 * @return array<string, mixed>|null
 */
function events_public_event_jsonld_location(array $ctx): ?array
{
    $name = trim((string) ($ctx['venue_name'] ?? ''));
    if ($name === '') {
        $name = trim((string) ($ctx['venue_slug'] ?? ''));
    }
    if ($name === '') {
        return null;
    }

    $address = events_public_event_jsonld_postal_address([
        'address' => (string) ($ctx['venue_address'] ?? ''),
        'city' => (string) ($ctx['venue_city'] ?? ''),
        'postal_code' => (string) ($ctx['venue_postal_code'] ?? ''),
        'country' => (string) ($ctx['venue_country'] ?? ''),
    ]);
    // Google Event rich result: location.address kötelező.
    if ($address === null) {
        return null;
    }

    $location = [
        '@type' => 'Place',
        'name' => $name,
        'address' => $address,
    ];

    $coords = $ctx['venue_coords'] ?? null;
    if (is_array($coords) && isset($coords['lat'], $coords['lng'])) {
        $location['geo'] = [
            '@type' => 'GeoCoordinates',
            'latitude' => $coords['lat'],
            'longitude' => $coords['lng'],
        ];
    }

    $slug = trim((string) ($ctx['venue_slug'] ?? ''));
    if ($slug !== '' && function_exists('events_helyszin_megjelenit_url') && function_exists('events_absolute_url')) {
        $location['url'] = events_absolute_url(events_helyszin_megjelenit_url($slug));
    }

    return $location;
}

/**
 * @param array{address?: string, city?: string, postal_code?: string, country?: string} $venue
 * @return array<string, mixed>|null
 */
function events_public_event_jsonld_postal_address(array $venue): ?array
{
    $street = trim((string) ($venue['address'] ?? ''));
    $city = trim((string) ($venue['city'] ?? ''));
    $postal = trim((string) ($venue['postal_code'] ?? ''));
    $country = trim((string) ($venue['country'] ?? ''));

    // Legalább település vagy utca kell a Google location.address elvárásához.
    if ($street === '' && $city === '') {
        return null;
    }

    $addr = ['@type' => 'PostalAddress'];
    if ($street !== '') {
        $addr['streetAddress'] = $street;
    }
    if ($city !== '') {
        $addr['addressLocality'] = $city;
    }
    if ($postal !== '') {
        $addr['postalCode'] = $postal;
    }

    if ($country !== '') {
        $addr['addressCountry'] = $country;
    } elseif (function_exists('events_venue_default_country')) {
        $addr['addressCountry'] = events_venue_default_country();
    }

    return $addr;
}
