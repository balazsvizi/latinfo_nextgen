<?php
declare(strict_types=1);

function events_venue_default_country(): string {
    return 'Magyarország';
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
    if ($country !== '' && $country !== events_venue_default_country()) {
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

function events_normalize_venue_id(PDO $db, ?int $id): ?int {
    if ($id === null || $id <= 0) {
        return null;
    }
    $st = $db->prepare('SELECT 1 FROM `events_venues` WHERE `id` = ? LIMIT 1');
    $st->execute([$id]);
    return $st->fetchColumn() ? $id : null;
}

/**
 * @param array<string, mixed> $defaults name, slug, description, country, city, postal_code, address
 * @return array{0: array<string, mixed>, 1: ?string}
 */
function events_venue_row_from_post(PDO $db, array $defaults, ?int $excludeIdForSlug): array {
    $row = $defaults;
    $row['name'] = trim((string) ($_POST['name'] ?? ''));
    $slugInput = trim((string) ($_POST['slug'] ?? ''));
    $row['slug'] = $slugInput !== '' ? events_slugify($slugInput) : '';
    $row['description'] = (string) ($_POST['description'] ?? '');
    $row['country'] = trim((string) ($_POST['country'] ?? ''));
    if ($row['country'] === '') {
        $row['country'] = events_venue_default_country();
    }
    $row['city'] = trim((string) ($_POST['city'] ?? ''));
    $row['postal_code'] = trim((string) ($_POST['postal_code'] ?? ''));
    $row['address'] = trim((string) ($_POST['address'] ?? ''));

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
    if (($e['country'] ?? '') === '') {
        $e['country'] = events_venue_default_country();
    }
    return $e;
}
