<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_event_calendar.php';
require_once __DIR__ . '/public_event_calendar.php';
require_once __DIR__ . '/event_change.php';
require_once __DIR__ . '/event_public_styles.php';

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
    foreach (events_calendar_load_organizer_rows_by_event_id($db, $rows) as $eid => $orgRows) {
        $out[$eid] = array_values(array_map(
            static fn (array $org): string => (string) ($org['name'] ?? ''),
            $orgRows
        ));
    }

    return $out;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<int, list<array{id:int,name:string}>>
 */
function events_calendar_load_organizer_rows_by_event_id(PDO $db, array $rows): array
{
    $out = [];
    if ($rows === []) {
        return $out;
    }
    $eventIds = array_values(array_unique(array_map(static fn (array $r): int => (int) $r['id'], $rows)));
    $ph = implode(',', array_fill(0, count($eventIds), '?'));
    $stmt = $db->prepare("
        SELECT eo.`event_id`, o.`id`, o.`name`
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
        $out[$eid][] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
        ];
    }

    return $out;
}

/**
 * @param list<array<string, mixed>> $rows
 * @param list<string> $typeCodes
 * @return array<int, list<array{id:int,name:string}>>
 */
function events_calendar_load_tags_by_types_for_events(PDO $db, array $rows, array $typeCodes): array
{
    $out = [];
    if ($rows === [] || $typeCodes === []) {
        return $out;
    }
    require_once __DIR__ . '/tag_type.php';
    if (!events_tags_tables_available($db) || !events_tag_types_tables_available($db)) {
        return $out;
    }
    $typeCodes = events_tag_type_normalize_codes($typeCodes, $db);
    if ($typeCodes === []) {
        return $out;
    }
    $eventIds = array_values(array_unique(array_map(static fn (array $r): int => (int) $r['id'], $rows)));
    if ($eventIds === []) {
        return $out;
    }
    if (in_array('dj', $typeCodes, true)) {
        events_tags_ensure_dj_slugs($db);
    }
    $phEvents = implode(',', array_fill(0, count($eventIds), '?'));
    $phTypes = implode(',', array_fill(0, count($typeCodes), '?'));
    $st = $db->prepare("
        SELECT DISTINCT et.`event_id`, t.`id`, t.`name`
        FROM `events_tags` t
        INNER JOIN `events_calendar_event_tags` et ON et.`tag_id` = t.`id`
        INNER JOIN `events_tag_type_links` l ON l.`tag_id` = t.`id`
        INNER JOIN `events_tag_types` ty ON ty.`id` = l.`tag_type_id`
        WHERE et.`event_id` IN ({$phEvents}) AND ty.`code` IN ({$phTypes})
        ORDER BY t.`name` ASC, t.`id` ASC
    ");
    $st->execute(array_merge($eventIds, $typeCodes));
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $eid = (int) $row['event_id'];
        if (!isset($out[$eid])) {
            $out[$eid] = [];
        }
        $out[$eid][] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
        ];
    }

    return $out;
}

/**
 * @param list<array<string, mixed>> $rows
 * @param array<int, list<array{id: int, name: string, color: string}>> $categoriesByEventId
 * @param array<int, list<string>>|array<int, list<array{id:int,name:string}>> $organizersByEventId
 * @param array<int, list<array{id: int, name: string}>> $mainStylesByEventId
 * @param array<int, list<array{id: int, name: string}>> $supplementaryStylesByEventId
 * @param array<int, list<array{id:int,name:string}>> $djsByEventId
 * @param array<int, list<array{id:int,name:string}>> $bandsByEventId
 * @return array<int, array<string, mixed>>
 */
function events_calendar_preview_build_map(
    array $rows,
    array $categoriesByEventId,
    array $organizersByEventId,
    string $lang = 'hu',
    array $mainStylesByEventId = [],
    array $supplementaryStylesByEventId = [],
    bool $includeFavorites = false,
    array $djsByEventId = [],
    array $bandsByEventId = []
): array {
    require_once __DIR__ . '/event_public_lang.php';
    $strings = events_public_megjelenit_strings($lang);
    $favoritesLibReady = false;
    if ($includeFavorites) {
        require_once dirname(__DIR__, 2) . '/lib/user/favorites.php';
        $favoritesLibReady = latinfo_favorites_ensure_schema(getDb()) && latinfo_favorites_public_enabled(getDb());
    }
    $db = $favoritesLibReady ? getDb() : null;
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
        $organizersRaw = $organizersByEventId[$eid] ?? [];
        $organizerNames = [];
        $organizerRows = [];
        foreach ($organizersRaw as $orgItem) {
            if (is_array($orgItem)) {
                $organizerRows[] = [
                    'id' => (int) ($orgItem['id'] ?? 0),
                    'name' => (string) ($orgItem['name'] ?? ''),
                ];
                $name = trim((string) ($orgItem['name'] ?? ''));
                if ($name !== '') {
                    $organizerNames[] = $name;
                }
            } else {
                $name = trim((string) $orgItem);
                if ($name !== '') {
                    $organizerNames[] = $name;
                }
            }
        }
        $changePayload = events_event_change_preview_payload($ev, $lang, $strings);
        if ($changePayload !== null) {
            $changeStyle = events_event_change_calendar_block_style($ev);
            if (preg_match('/--events-cal-accent:([^;]+)/', $changeStyle, $m) === 1) {
                $accent = trim($m[1]);
            }
        }
        $entry = [
            'name' => (string) ($ev['event_name'] ?? ''),
            'date' => events_admin_format_datum_cell($ev),
            'time' => events_admin_calendar_event_time_label($ev),
            'venue' => events_calendar_preview_venue_line($ev),
            'organizer' => $organizerNames !== [] ? implode(', ', $organizerNames) : '',
            'categories' => array_values(array_map(
                static fn (array $cat): array => [
                    'name' => (string) ($cat['name'] ?? ''),
                    'color' => (string) ($cat['color'] ?? '#6d8f63'),
                ],
                $cats
            )),
            'mainStyles' => events_calendar_preview_style_names($mainStylesByEventId[$eid] ?? []),
            'supplementaryStyles' => events_calendar_preview_style_names($supplementaryStylesByEventId[$eid] ?? []),
            'accent' => $accent,
            'image' => events_calendar_preview_featured_image_url($ev),
            'url' => events_public_calendar_event_url($ev, EVENTS_VIEW_SOURCE_CAL_PREVIEW),
            'change' => $changePayload,
        ];
        if ($favoritesLibReady && $db instanceof PDO) {
            $venueId = (int) ($ev['venue_id'] ?? 0);
            $venueLabel = trim((string) ($ev['venue_name'] ?? ''));
            if ($venueLabel === '') {
                $venueLabel = trim((string) ($ev['venue_city'] ?? ''));
            }
            $venueForPicker = ($venueId > 0 && $venueLabel !== '')
                ? ['id' => $venueId, 'label' => $venueLabel]
                : null;
            $picker = latinfo_favorites_build_event_picker(
                $db,
                $eid,
                (string) ($ev['event_name'] ?? ''),
                $lang,
                $organizerRows,
                $venueForPicker,
                $djsByEventId[$eid] ?? [],
                $bandsByEventId[$eid] ?? []
            );
            $entry['favorite'] = [
                'enabled' => true,
                'entityType' => LATINFO_FAVORITE_TYPE_EVENT,
                'entityId' => $eid,
                'active' => $picker['active'],
                'count' => $picker['count'],
                'picker' => ['items' => $picker['items']],
            ];
        }
        $map[$eid] = $entry;
    }

    return $map;
}

/**
 * @param list<array{id?: int, name?: string}> $styles
 * @return list<string>
 */
function events_calendar_preview_style_names(array $styles): array
{
    $names = [];
    foreach ($styles as $style) {
        $name = trim((string) ($style['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $names[] = $name;
    }

    return $names;
}
