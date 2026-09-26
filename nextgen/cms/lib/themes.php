<?php
declare(strict_types=1);

/**
 * CMS témák (szótár).
 */

/**
 * @return list<array<string, mixed>>
 */
function cms_themes_list(PDO $db, bool $activeOnly = false): array
{
    $sql = 'SELECT * FROM `cms_themes`';
    if ($activeOnly) {
        $sql .= ' WHERE `is_active` = 1';
    }
    $sql .= ' ORDER BY `sort_order` ASC, `name` ASC, `id` ASC';
    try {
        $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    } catch (Throwable $e) {
        error_log('cms_themes_list: ' . $e->getMessage());

        return [];
    }
}

/**
 * @return array<int, string> id => name
 */
function cms_themes_options(PDO $db, bool $activeOnly = true): array
{
    $out = [];
    foreach (cms_themes_list($db, $activeOnly) as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id > 0) {
            $out[$id] = (string) ($row['name'] ?? '');
        }
    }

    return $out;
}

function cms_theme_slugify(string $name): string
{
    $lower = mb_strtolower(trim($name), 'UTF-8');
    $map = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o', 'ő' => 'o',
        'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
    ];
    $s = strtr($lower, $map);
    $s = preg_replace('/[^a-z0-9]+/u', '-', (string) $s);
    $s = trim((string) $s, '-');

    return $s !== '' ? $s : 'tema';
}

function cms_theme_slug_exists(PDO $db, string $slug, ?int $excludeId): bool
{
    $sql = 'SELECT 1 FROM `cms_themes` WHERE `slug` = ?';
    $params = [$slug];
    if ($excludeId !== null) {
        $sql .= ' AND `id` != ?';
        $params[] = $excludeId;
    }
    $st = $db->prepare($sql . ' LIMIT 1');
    $st->execute($params);

    return (bool) $st->fetchColumn();
}

function cms_ensure_unique_theme_slug(PDO $db, string $base, ?int $excludeId): string
{
    $slug = $base;
    $n = 2;
    while (cms_theme_slug_exists($db, $slug, $excludeId)) {
        $slug = $base . '-' . $n;
        $n++;
        if ($n > 500) {
            $slug = $base . '-' . bin2hex(random_bytes(3));
            break;
        }
    }

    return $slug;
}

/**
 * @return array<int, int> theme_id => post count
 */
function cms_themes_post_count_map(PDO $db): array
{
    $map = [];
    try {
        $st = $db->query('
            SELECT `theme_id`, COUNT(*) AS cnt
            FROM `cms_posts`
            WHERE `theme_id` IS NOT NULL
            GROUP BY `theme_id`
        ');
        if ($st === false) {
            return [];
        }
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $tid = (int) ($row['theme_id'] ?? 0);
            if ($tid > 0) {
                $map[$tid] = (int) ($row['cnt'] ?? 0);
            }
        }
    } catch (Throwable $e) {
        error_log('cms_themes_post_count_map: ' . $e->getMessage());
    }

    return $map;
}
