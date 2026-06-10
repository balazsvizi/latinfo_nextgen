<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_event_calendar.php';
require_once __DIR__ . '/public_event_calendar.php';

/**
 * Naptár esemény előnézet popup — adatok és segédek (nyilvános naptár).
 */

function events_calendar_preview_featured_image_url(array $ev): string
{
    $featRaw = trim(html_entity_decode(trim((string) ($ev['event_featured_image_url'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $featRaw = preg_replace('/^\x{FEFF}|\x{200B}/u', '', $featRaw) ?? $featRaw;
    if ($featRaw === '') {
        return '';
    }

    return events_absolute_url($featRaw);
}

function events_calendar_preview_venue_line(array $ev): string
{
    $venueName = trim((string) ($ev['venue_name'] ?? ''));
    $venueCity = trim((string) ($ev['venue_city'] ?? ''));
    if ($venueName === '' && $venueCity === '') {
        return '';
    }
    if ($venueName !== '' && $venueCity !== '') {
        return $venueName . ', ' . $venueCity;
    }

    return $venueName !== '' ? $venueName : $venueCity;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<int, list<string>>
 */
function events_calendar_load_organizers_by_event_id(PDO $db, array $rows): array
{
    $out = [];
    if ($rows === []) {
        return $out;
    }
    $eventIds = array_values(array_unique(array_map(static fn (array $r): int => (int) $r['id'], $rows)));
    $ph = implode(',', array_fill(0, count($eventIds), '?'));
    $stmt = $db->prepare("
        SELECT eo.`event_id`, o.`name`
        FROM `events_calendar_event_organizers` eo
        INNER JOIN `events_organizers` o ON o.`id` = eo.`organizer_id`
        WHERE eo.`event_id` IN ({$ph})
        ORDER BY eo.`sort_order` ASC, o.`name` ASC, o.`id` ASC
    ");
    $stmt->execute($eventIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $eid = (int) $row['event_id'];
        if (!isset($out[$eid])) {
            $out[$eid] = [];
        }
        $out[$eid][] = (string) $row['name'];
    }

    return $out;
}

/**
 * @param list<array<string, mixed>> $rows
 * @param array<int, list<array{id: int, name: string, color: string}>> $categoriesByEventId
 * @param array<int, list<string>> $organizersByEventId
 * @return array<int, array<string, mixed>>
 */
function events_calendar_preview_build_map(array $rows, array $categoriesByEventId, array $organizersByEventId): array
{
    $map = [];
    foreach ($rows as $ev) {
        $eid = (int) ($ev['id'] ?? 0);
        if ($eid <= 0) {
            continue;
        }
        $cats = $categoriesByEventId[$eid] ?? [];
        $accent = '#6d8f63';
        if ($cats !== []) {
            $candidate = trim((string) ($cats[0]['color'] ?? '#6d8f63'));
            if ($candidate !== '' && preg_match('/^#[0-9A-Fa-f]{6}$/', $candidate) === 1) {
                $accent = $candidate;
            }
        }
        $organizers = $organizersByEventId[$eid] ?? [];
        $map[$eid] = [
            'name' => (string) ($ev['event_name'] ?? ''),
            'date' => events_admin_format_datum_cell($ev),
            'time' => events_admin_calendar_event_time_label($ev),
            'venue' => events_calendar_preview_venue_line($ev),
            'organizer' => $organizers !== [] ? implode(', ', $organizers) : '',
            'categories' => array_values(array_map(
                static fn (array $cat): array => [
                    'name' => (string) ($cat['name'] ?? ''),
                    'color' => (string) ($cat['color'] ?? '#6d8f63'),
                ],
                $cats
            )),
            'accent' => $accent,
            'image' => events_calendar_preview_featured_image_url($ev),
            'url' => events_public_calendar_event_url($ev, EVENTS_VIEW_SOURCE_CAL_PREVIEW),
        ];
    }

    return $map;
}
