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
    $row['description'] = (string) ($_POST['description'] ?? '');
    $row['country'] = trim((string) ($_POST['country'] ?? ''));
    if ($row['country'] === '') {
        $row['country'] = events_venue_default_country();
    }
    $row['city'] = trim((string) ($_POST['city'] ?? ''));
    $row['postal_code'] = trim((string) ($_POST['postal_code'] ?? ''));
    $row['address'] = trim((string) ($_POST['address'] ?? ''));

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
