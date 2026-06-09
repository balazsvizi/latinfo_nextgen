<?php
declare(strict_types=1);

function events_venue_default_country(): string {
    return 'Magyarország';
}

function events_leaflet_css_url(): string {
    return 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css';
}

function events_leaflet_js_url(): string {
    return 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js';
}

/**
 * @return array{error: string, lat: null, lng: null}|array{error: null, lat: ?float, lng: ?float}
 */
function events_venue_parse_coordinates(string $latRaw, string $lngRaw): array {
    $latT = trim(str_replace(',', '.', $latRaw));
    $lngT = trim(str_replace(',', '.', $lngRaw));
    if ($latT === '' && $lngT === '') {
        return ['error' => null, 'lat' => null, 'lng' => null];
    }
    if ($latT === '' || $lngT === '') {
        return ['error' => 'A szélesség és hosszúság együtt adható meg, vagy mindkettő üres legyen.', 'lat' => null, 'lng' => null];
    }
    if (!is_numeric($latT) || !is_numeric($lngT)) {
        return ['error' => 'A GPS koordináták számok legyenek (pl. 47.4979 és 19.0402).', 'lat' => null, 'lng' => null];
    }
    $lat = (float) $latT;
    $lng = (float) $lngT;
    if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
        return ['error' => 'A GPS koordináták tartományon kívül esnek.', 'lat' => null, 'lng' => null];
    }

    return ['error' => null, 'lat' => round($lat, 7), 'lng' => round($lng, 7)];
}

function events_venue_format_coord_for_form(mixed $val): string {
    if ($val === null || $val === '') {
        return '';
    }
    if (!is_numeric($val)) {
        return '';
    }
    $s = rtrim(rtrim(sprintf('%.7F', (float) $val), '0'), '.');

    return $s === '' ? '0' : $s;
}

/**
 * @param array<string, mixed> $r
 * @return array{lat: float, lng: float}|null
 */
function events_venue_coordinates_from_row(array $r): ?array {
    $latRaw = $r['latitude'] ?? null;
    $lngRaw = $r['longitude'] ?? null;
    if ($latRaw === null || $lngRaw === null || $latRaw === '' || $lngRaw === '') {
        return null;
    }
    if (!is_numeric((string) $latRaw) || !is_numeric((string) $lngRaw)) {
        return null;
    }
    $lat = (float) $latRaw;
    $lng = (float) $lngRaw;
    if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
        return null;
    }

    return ['lat' => $lat, 'lng' => $lng];
}

function events_venue_country_nominatim_code(string $country): string {
    $c = mb_strtolower(trim($country), 'UTF-8');
    if ($c === '' || $c === 'magyarország' || $c === 'hungary' || $c === 'hu') {
        return 'hu';
    }
    if ($c === 'österreich' || $c === 'austria' || $c === 'at') {
        return 'at';
    }
    if ($c === 'slovensko' || $c === 'slovakia' || $c === 'szlovákia' || $c === 'sk') {
        return 'sk';
    }
    if ($c === 'românia' || $c === 'romania' || $c === 'románia' || $c === 'ro') {
        return 'ro';
    }
    if ($c === 'deutschland' || $c === 'germany' || $c === 'németország' || $c === 'de') {
        return 'de';
    }

    return '';
}

/**
 * Egy soros összefoglaló listához (IRSZ település, utca; opcionálisan ország, ha nem HU).
 *
 * @param array<string, mixed> $r
 */
function events_venue_address_summary(array $r): string {
    $pc = trim((string) ($r['postal_code'] ?? ''));
    $city = trim((string) ($r['city'] ?? ''));
    $street = trim((string) ($r['address'] ?? ''));
    $country = trim((string) ($r['country'] ?? ''));

    $head = trim($pc . ' ' . $city);
    $parts = [];
    if ($head !== '') {
        $parts[] = $head;
    }
    if ($street !== '') {
        $parts[] = $street;
    }
    $out = implode(', ', $parts);
    $def = events_venue_default_country();
    if ($country !== '' && mb_strtolower($country, 'UTF-8') !== mb_strtolower($def, 'UTF-8')) {
        $out = $out === '' ? $country : $out . ', ' . $country;
    }
    return $out;
}

/**
 * @return array<int, string> id => név
 */
function events_load_venue_options(PDO $db): array {
    $rows = $db->query('SELECT `id`, `name` FROM `events_venues` ORDER BY `name` ASC, `id` ASC')->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $r) {
        $out[(int) $r['id']] = (string) $r['name'];
    }
    return $out;
}

/**
 * Kapcsolt helyszín választóhoz: összes venue, opcionálisan egy ID kihagyása (szerkesztés: ne önmagára mutasson).
 *
 * @return array<int, string> id => név
 */
function events_load_venue_options_excluding(PDO $db, ?int $excludeId): array {
    $out = events_load_venue_options($db);
    if ($excludeId !== null && $excludeId > 0) {
        unset($out[$excludeId]);
    }
    return $out;
}

/**
 * Hány naptár-esemény hivatkozik erre a helyszínre (venue_id).
 */
function events_venue_calendar_event_count(PDO $db, int $venueId): int {
    if ($venueId <= 0) {
        return 0;
    }
    $st = $db->prepare('SELECT COUNT(*) FROM `events_calendar_events` WHERE `venue_id` = ?');
    $st->execute([$venueId]);
    return (int) $st->fetchColumn();
}

function events_normalize_venue_id(PDO $db, ?int $id): ?int {
    if ($id === null || $id <= 0) {
        return null;
    }
    $st = $db->prepare('SELECT 1 FROM `events_venues` WHERE `id` = ? LIMIT 1');
    $st->execute([$id]);
    return $st->fetchColumn() ? $id : null;
}

/**
 * @param array<string, mixed> $defaults name, slug, description, country, city, postal_code, address, linked_venue_id
 * @return array{0: array<string, mixed>, 1: ?string}
 */
function events_venue_row_from_post(PDO $db, array $defaults, ?int $excludeIdForSlug): array {
    $row = $defaults;
    $row['name'] = trim((string) ($_POST['name'] ?? ''));
    $slugInput = trim((string) ($_POST['slug'] ?? ''));
    $row['slug'] = $slugInput !== '' ? events_slugify($slugInput) : '';
    $row['description'] = events_sanitize_html_fragment((string) ($_POST['description'] ?? ''));
    $row['country'] = trim((string) ($_POST['country'] ?? ''));
    if ($row['country'] === '') {
        $row['country'] = events_venue_default_country();
    }
    $row['city'] = trim((string) ($_POST['city'] ?? ''));
    $row['postal_code'] = trim((string) ($_POST['postal_code'] ?? ''));
    $row['address'] = trim((string) ($_POST['address'] ?? ''));

    $coord = events_venue_parse_coordinates(
        (string) ($_POST['latitude'] ?? ''),
        (string) ($_POST['longitude'] ?? '')
    );
    if ($coord['error'] !== null) {
        return [$row, $coord['error']];
    }
    $row['latitude'] = $coord['lat'];
    $row['longitude'] = $coord['lng'];

    $linkRaw = trim((string) ($_POST['linked_venue_id'] ?? ''));
    $row['linked_venue_id'] = $linkRaw === '' ? null : (int) $linkRaw;
    if ($row['linked_venue_id'] !== null && $row['linked_venue_id'] <= 0) {
        $row['linked_venue_id'] = null;
    }
    if ($row['linked_venue_id'] !== null) {
        $norm = events_normalize_venue_id($db, $row['linked_venue_id']);
        if ($norm === null) {
            return [$row, 'A kapcsolt helyszín nem található.'];
        }
        if ($excludeIdForSlug !== null && $norm === $excludeIdForSlug) {
            return [$row, 'A kapcsolt helyszín nem lehet ugyanaz a rekord, amit szerkesztesz.'];
        }
        $row['linked_venue_id'] = $norm;
    }

    if ($row['name'] === '') {
        return [$row, 'A név kötelező.'];
    }

    $baseSlug = $row['slug'] !== '' ? $row['slug'] : events_slugify($row['name']);
    $row['slug'] = events_ensure_unique_venue_slug($db, $baseSlug, $excludeIdForSlug);

    return [$row, null];
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function events_venue_row_for_form(array $row): array {
    $e = $row;
    foreach (['name', 'slug', 'description', 'country', 'city', 'postal_code', 'address'] as $k) {
        $e[$k] = isset($e[$k]) && $e[$k] !== null ? (string) $e[$k] : '';
    }
    $e['latitude'] = events_venue_format_coord_for_form($e['latitude'] ?? null);
    $e['longitude'] = events_venue_format_coord_for_form($e['longitude'] ?? null);
    if (($e['country'] ?? '') === '') {
        $e['country'] = events_venue_default_country();
    }
    if (array_key_exists('linked_venue_id', $e) && $e['linked_venue_id'] !== null && $e['linked_venue_id'] !== '') {
        $e['linked_venue_id'] = (string) (int) $e['linked_venue_id'];
    } else {
        $e['linked_venue_id'] = '';
    }
    return $e;
}
