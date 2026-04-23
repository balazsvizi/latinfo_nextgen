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
    while (events_slug_exists($db, $slug, $excludeId)) {
        $slug = $base . '-' . $n;
        $n++;
    }
    return $slug;
}
