<?php
declare(strict_types=1);

/**
 * Esemény URL-slug: kisbetű, szám, kötőjel; magyar ékezetek egyszerűsítése.
 */
function events_slugify(string $name): string {
    $lower = mb_strtolower(trim($name), 'UTF-8');
    $map = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o', 'ő' => 'o',
        'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
        'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ö' => 'o', 'Ő' => 'o',
        'Ú' => 'u', 'Ü' => 'u', 'Ű' => 'u',
    ];
    $s = strtr($lower, $map);
    $s = preg_replace('/[^a-z0-9]+/u', '-', $s);
    $s = trim((string) $s, '-');
    return $s !== '' ? $s : 'esemeny';
}

function events_slug_exists(PDO $db, string $slug, ?int $excludeId): bool {
    $sql = 'SELECT 1 FROM `events_calendar_events` WHERE `event_slug` = ?';
    $params = [$slug];
    if ($excludeId !== null) {
        $sql .= ' AND `id` != ?';
        $params[] = $excludeId;
    }
    $stmt = $db->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    return (bool) $stmt->fetchColumn();
}

/**
 * Egyedi slug: ütközésnél -2, -3, …
 */
function events_ensure_unique_slug(PDO $db, string $base, ?int $excludeId): string {
    $slug = $base;
    $n = 2;
    $max = 500;
    while (events_slug_exists($db, $slug, $excludeId)) {
        if ($n > $max) {
            $slug = $base . '-' . bin2hex(random_bytes(4));
            if (!events_slug_exists($db, $slug, $excludeId)) {
                return $slug;
            }
            throw new RuntimeException('Nem sikerült egyedi slugot generálni.');
        }
        $slug = $base . '-' . $n;
        $n++;
    }
    return $slug;
}

function events_venue_slug_exists(PDO $db, string $slug, ?int $excludeVenueId): bool {
    $sql = 'SELECT 1 FROM `events_venues` WHERE `slug` = ?';
    $params = [$slug];
    if ($excludeVenueId !== null) {
        $sql .= ' AND `id` != ?';
        $params[] = $excludeVenueId;
    }
    $stmt = $db->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    return (bool) $stmt->fetchColumn();
}

function events_ensure_unique_venue_slug(PDO $db, string $base, ?int $excludeVenueId): string {
    $slug = $base;
    $n = 2;
    $max = 500;
    while (events_venue_slug_exists($db, $slug, $excludeVenueId)) {
        if ($n > $max) {
            $slug = $base . '-' . bin2hex(random_bytes(4));
            if (!events_venue_slug_exists($db, $slug, $excludeVenueId)) {
                return $slug;
            }
            throw new RuntimeException('Nem sikerült egyedi helyszín-slugot generálni.');
        }
        $slug = $base . '-' . $n;
        $n++;
    }
    return $slug;
}

/**
 * DJ URL-slug: kisbetű, szám, aláhúzás (pl. DJ Adalberto → dj_adalberto).
 */
function events_dj_slugify(string $name): string {
    $lower = mb_strtolower(trim($name), 'UTF-8');
    $map = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o', 'ő' => 'o',
        'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
        'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ö' => 'o', 'Ő' => 'o',
        'Ú' => 'u', 'Ü' => 'u', 'Ű' => 'u',
    ];
    $s = strtr($lower, $map);
    $s = preg_replace('/[^a-z0-9]+/u', '_', $s);
    $s = trim((string) $s, '_');
    $s = preg_replace('/_+/', '_', (string) $s) ?? $s;

    return $s !== '' ? $s : 'dj';
}

function events_tag_slug_exists(PDO $db, string $slug, ?int $excludeTagId): bool {
    if (!events_tags_slug_column_available($db)) {
        return false;
    }
    $sql = 'SELECT 1 FROM `events_tags` WHERE `slug` = ?';
    $params = [$slug];
    if ($excludeTagId !== null) {
        $sql .= ' AND `id` != ?';
        $params[] = $excludeTagId;
    }
    $stmt = $db->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);

    return (bool) $stmt->fetchColumn();
}

/**
 * Egyedi címke-slug: ütközésnél _2, _3, …
 */
function events_ensure_unique_tag_slug(PDO $db, string $base, ?int $excludeTagId): string {
    $slug = $base;
    $n = 2;
    $max = 500;
    while (events_tag_slug_exists($db, $slug, $excludeTagId)) {
        if ($n > $max) {
            $slug = $base . '_' . bin2hex(random_bytes(4));
            if (!events_tag_slug_exists($db, $slug, $excludeTagId)) {
                return $slug;
            }
            throw new RuntimeException('Nem sikerült egyedi címke-slugot generálni.');
        }
        $slug = $base . '_' . $n;
        $n++;
    }

    return $slug;
}

function events_tags_slug_column_available(PDO $db, bool $forceRefresh = false): bool {
    static $cached = null;
    if (!$forceRefresh && $cached !== null) {
        return $cached;
    }
    try {
        $st = $db->query("
            SELECT COUNT(*)
            FROM `information_schema`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = DATABASE()
              AND `TABLE_NAME` = 'events_tags'
              AND `COLUMN_NAME` = 'slug'
        ");
        $cached = ((int) $st->fetchColumn()) > 0;
    } catch (PDOException) {
        $cached = false;
    }

    return $cached;
}

/**
 * events_tags.slug oszlop + egyedi index (futás közben, migráció nélkül).
 */
function events_tags_ensure_slug_column(PDO $db): void {
    if (events_tags_slug_column_available($db)) {
        return;
    }
    if ($db->inTransaction()) {
        return;
    }
    try {
        $db->exec('ALTER TABLE `events_tags` ADD COLUMN `slug` VARCHAR(255) NULL DEFAULT NULL AFTER `name`');
    } catch (PDOException) {
        // már létezik / párhuzamos kérés
    }
    try {
        $db->exec('ALTER TABLE `events_tags` ADD UNIQUE INDEX `uk_events_tags_slug` (`slug`)');
    } catch (PDOException) {
        // index már létezik
    }
    events_tags_slug_column_available($db, true);
}
