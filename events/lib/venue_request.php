<?php
declare(strict_types=1);

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
 * @param array<string, mixed> $defaults name, slug, description, address
 * @return array{0: array<string, mixed>, 1: ?string}
 */
function events_venue_row_from_post(PDO $db, array $defaults, ?int $excludeIdForSlug): array {
    $row = $defaults;
    $row['name'] = trim((string) ($_POST['name'] ?? ''));
    $slugInput = trim((string) ($_POST['slug'] ?? ''));
    $row['slug'] = $slugInput !== '' ? events_slugify($slugInput) : '';
    $row['description'] = (string) ($_POST['description'] ?? '');
    $row['address'] = (string) ($_POST['address'] ?? '');

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
    foreach (['name', 'slug', 'description', 'address'] as $k) {
        $e[$k] = isset($e[$k]) && $e[$k] !== null ? (string) $e[$k] : '';
    }
    return $e;
}
