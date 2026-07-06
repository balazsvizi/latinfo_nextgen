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

function events_leaflet_marker_cluster_css_url(): string {
    return 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css';
}

function events_leaflet_marker_cluster_default_css_url(): string {
    return 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css';
}

function events_leaflet_marker_cluster_js_url(): string {
    return 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js';
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
 * Útvonaltervezés célpontja: GPS előnyben, különben cím, végül név.
 *
 * @param array{lat: float, lng: float}|null $coords
 */
function events_venue_directions_destination(?array $coords, string $addressLine, string $venueName = ''): ?string {
    if ($coords !== null) {
        $lat = events_venue_format_coord_for_form($coords['lat']);
        $lng = events_venue_format_coord_for_form($coords['lng']);
        if ($lat !== '' && $lng !== '') {
            return $lat . ',' . $lng;
        }
    }
    $addr = trim($addressLine);
    if ($addr !== '') {
        return $addr;
    }
    $name = trim($venueName);

    return $name !== '' ? $name : null;
}

function events_venue_google_directions_url(?string $destination): ?string {
    if ($destination === null || $destination === '') {
        return null;
    }

    return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($destination) . '&travelmode=driving';
}

function events_venue_apple_directions_url(?string $destination): ?string {
    if ($destination === null || $destination === '') {
        return null;
    }

    return 'https://maps.apple.com/?daddr=' . rawurlencode($destination);
}

/**
 * @param array{lat: float, lng: float}|null $coords
 */
function events_venue_has_directions_target(?array $coords, string $addressLine, string $venueName = ''): bool {
    return events_venue_directions_destination($coords, $addressLine, $venueName) !== null;
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

/**
 * @return array{0: ?string, 1: ?string}
 */
function events_normalize_google_maps_url(?string $raw): array {
    [$url, $err] = events_normalize_safe_url($raw, false);
    if ($err !== null) {
        return [null, $err];
    }
    if ($url === null) {
        return [null, null];
    }
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $path = strtolower((string) parse_url($url, PHP_URL_PATH));
    $isGoogleMaps = $host === 'maps.app.goo.gl'
        || $host === 'goo.gl'
        || str_ends_with($host, '.google.com')
        || str_ends_with($host, '.google.hu')
        || (str_contains($host, 'google.') && str_contains($path, '/maps'));
    if (!$isGoogleMaps) {
        return [null, 'A Google Maps link maps.google.com, google.com/maps vagy goo.gl/maps formátumú legyen.'];
    }

    return [$url, null];
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
 * Nominatim keresőszöveg helyszín mezőkből (venue szerkesztő / térkép geokódolás).
 *
 * @param array<string, mixed> $r address, city, postal_code, country
 */
function events_venue_geocode_query(array $r): string {
    $parts = [];
    $address = trim((string) ($r['address'] ?? ''));
    $city = trim((string) ($r['city'] ?? ''));
    $postal = trim((string) ($r['postal_code'] ?? ''));
    $country = trim((string) ($r['country'] ?? ''));
    if ($address !== '') {
        $parts[] = $address;
    }
    $cityLine = trim($postal . ' ' . $city);
    if ($cityLine !== '') {
        $parts[] = $cityLine;
    }
    if ($country !== '') {
        $parts[] = $country;
    }

    return implode(', ', $parts);
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

    [$websiteUrl, $websiteErr] = events_normalize_safe_url((string) ($_POST['website_url'] ?? ''), false);
    if ($websiteErr !== null) {
        return [$row, $websiteErr];
    }
    $row['website_url'] = $websiteUrl;

    [$mapsUrl, $mapsErr] = events_normalize_google_maps_url((string) ($_POST['google_maps_url'] ?? ''));
    if ($mapsErr !== null) {
        return [$row, $mapsErr];
    }
    $row['google_maps_url'] = $mapsUrl;

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
    foreach (['name', 'slug', 'description', 'country', 'city', 'postal_code', 'address', 'website_url', 'google_maps_url'] as $k) {
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

function events_venue_nominatim_user_agent(): string
{
    $site = defined('SITE_NAME') ? (string) SITE_NAME : 'Latinfo';

    return preg_replace('/\s+/', '', $site) . 'Events/1.0 (venue geocoding)';
}

function events_venue_nominatim_throttle(): void
{
    $dir = dirname(__DIR__) . '/data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $path = $dir . '/.nominatim_throttle';
    $now = microtime(true);
    if (is_file($path)) {
        $last = (float) trim((string) file_get_contents($path));
        $wait = 1.1 - ($now - $last);
        if ($wait > 0) {
            usleep((int) round($wait * 1_000_000));
        }
    }
    @file_put_contents($path, (string) microtime(true));
}

/**
 * @return array{lat: float, lng: float}|null
 */
function events_venue_geocode_nominatim(string $query, string $countryCode = ''): ?array
{
    $query = trim($query);
    if ($query === '') {
        return null;
    }

    events_venue_nominatim_throttle();

    $params = [
        'format' => 'json',
        'limit' => '1',
        'q' => $query,
    ];
    if (strlen($countryCode) === 2) {
        $params['countrycodes'] = strtolower($countryCode);
    }

    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query($params);
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", [
                'User-Agent: ' . events_venue_nominatim_user_agent(),
                'Accept: application/json',
                'Accept-Language: hu,en;q=0.8',
            ]),
            'timeout' => 20,
            'ignore_errors' => true,
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    if ($raw === false || $raw === '') {
        error_log('events venue geocode: nominatim request failed for ' . $query);

        return null;
    }

    try {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $ex) {
        error_log('events venue geocode: invalid json for ' . $query . ' – ' . $ex->getMessage());

        return null;
    }

    if (!is_array($data) || $data === []) {
        return null;
    }

    $hit = $data[0] ?? null;
    if (!is_array($hit)) {
        return null;
    }

    $latRaw = $hit['lat'] ?? null;
    $lngRaw = $hit['lon'] ?? null;
    if (!is_numeric((string) $latRaw) || !is_numeric((string) $lngRaw)) {
        return null;
    }

    $lat = (float) $latRaw;
    $lng = (float) $lngRaw;
    if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
        return null;
    }

    return ['lat' => round($lat, 7), 'lng' => round($lng, 7)];
}

/**
 * Cím alapján kitölti a koordinátákat, ha üresek.
 *
 * @param array<string, mixed> $row country, city, postal_code, address, latitude, longitude
 * @return array<string, mixed>
 */
function events_venue_apply_geocode_if_needed(array $row): array
{
    if (events_venue_coordinates_from_row([
        'latitude' => $row['latitude'] ?? null,
        'longitude' => $row['longitude'] ?? null,
    ]) !== null) {
        return $row;
    }

    $venueFields = [
        'address' => (string) ($row['address'] ?? ''),
        'city' => (string) ($row['city'] ?? ''),
        'postal_code' => (string) ($row['postal_code'] ?? ''),
        'country' => (string) ($row['country'] ?? ''),
    ];
    $query = events_venue_geocode_query($venueFields);
    if ($query === '') {
        return $row;
    }

    $countryCode = events_venue_country_nominatim_code($venueFields['country']);
    $coords = events_venue_geocode_nominatim($query, $countryCode);
    if ($coords === null && $countryCode !== '') {
        $coords = events_venue_geocode_nominatim($query, '');
    }
    if ($coords === null) {
        return $row;
    }

    $row['latitude'] = $coords['lat'];
    $row['longitude'] = $coords['lng'];

    return $row;
}

/**
 * SQL kifejezés: érvényes WGS-84 koordináták vannak-e (lista szűrés / rendezés).
 */
function events_venue_sql_has_valid_coordinates(string $alias = 'v'): string
{
    $p = $alias !== '' ? $alias . '.' : '';

    return '(' . $p . '`latitude` IS NOT NULL AND ' . $p . '`longitude` IS NOT NULL'
        . ' AND TRIM(CAST(' . $p . '`latitude` AS CHAR)) != \'\''
        . ' AND TRIM(CAST(' . $p . '`longitude` AS CHAR)) != \'\''
        . ' AND CAST(' . $p . '`latitude` AS DECIMAL(12,7)) BETWEEN -90 AND 90'
        . ' AND CAST(' . $p . '`longitude` AS DECIMAL(12,7)) BETWEEN -180 AND 180)';
}

function events_venues_geocode_candidates_where_sql(): string
{
    return "
        (
            v.`latitude` IS NULL OR v.`longitude` IS NULL
            OR TRIM(CAST(v.`latitude` AS CHAR)) = '' OR TRIM(CAST(v.`longitude` AS CHAR)) = ''
        )
        AND (
            (v.`address` IS NOT NULL AND TRIM(v.`address`) != '')
            OR (v.`city` IS NOT NULL AND TRIM(v.`city`) != '')
        )
    ";
}

function events_venues_geocode_candidates_count(PDO $db): int
{
    $sql = 'SELECT COUNT(*) FROM `events_venues` v WHERE ' . events_venues_geocode_candidates_where_sql();
    $count = $db->query($sql)->fetchColumn();

    return (int) $count;
}

/**
 * @return list<array<string, mixed>>
 */
function events_venues_fetch_geocode_candidates(PDO $db, int $limit = 12): array
{
    $limit = max(1, min(25, $limit));
    $sql = '
        SELECT v.`id`, v.`name`, v.`country`, v.`city`, v.`postal_code`, v.`address`, v.`latitude`, v.`longitude`
        FROM `events_venues` v
        WHERE ' . events_venues_geocode_candidates_where_sql() . '
        ORDER BY v.`id` ASC
        LIMIT ' . $limit;
    $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    return is_array($rows) ? $rows : [];
}

function events_venue_geocode_and_save(PDO $db, int $venueId): bool
{
    if ($venueId <= 0) {
        return false;
    }

    $st = $db->prepare('
        SELECT `id`, `name`, `country`, `city`, `postal_code`, `address`, `latitude`, `longitude`
        FROM `events_venues`
        WHERE `id` = ?
        LIMIT 1
    ');
    $st->execute([$venueId]);
    $venue = $st->fetch(PDO::FETCH_ASSOC);
    if (!$venue) {
        return false;
    }

    $updated = events_venue_apply_geocode_if_needed($venue);
    if (events_venue_coordinates_from_row([
        'latitude' => $updated['latitude'] ?? null,
        'longitude' => $updated['longitude'] ?? null,
    ]) === null) {
        return false;
    }

    $upd = $db->prepare('UPDATE `events_venues` SET `latitude` = ?, `longitude` = ? WHERE `id` = ?');
    $upd->execute([
        $updated['latitude'],
        $updated['longitude'],
        $venueId,
    ]);

    return true;
}
