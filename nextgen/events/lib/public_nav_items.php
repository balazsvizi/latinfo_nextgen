<?php
declare(strict_types=1);

require_once __DIR__ . '/html_security.php';
require_once __DIR__ . '/event_public_lang.php';

/**
 * Nyilvános főmenü elemei: fa, sorrend, HU/EN felirat, belső oldal vagy egyéni URL.
 */

const EVENTS_PUBLIC_NAV_SORT_STEP = 10;
const EVENTS_PUBLIC_NAV_MAX_DEPTH = 2;
const EVENTS_PUBLIC_NAV_PRESET_KEYS = [
    'calendar',
    'calendar_month',
    'calendar_list',
    'djs',
    'organizers',
    'partners',
    'latinfo',
];

/**
 * @return array<string, string>
 */
function events_public_nav_preset_labels(): array {
    return [
        'calendar' => 'Naptár (főoldal)',
        'calendar_month' => 'Havi naptár',
        'calendar_list' => 'Eseménylista',
        'djs' => 'DJ-k',
        'organizers' => 'Szervezők',
        'partners' => 'Partnereink',
        'latinfo' => 'Latinfo.hu',
    ];
}

function events_public_nav_normalize_preset(string $preset): string {
    return in_array($preset, EVENTS_PUBLIC_NAV_PRESET_KEYS, true) ? $preset : '';
}

function events_public_nav_normalize_link_type(string $type): string {
    return $type === 'preset' ? 'preset' : 'custom';
}

function events_public_nav_preset_menu_key(string $preset): string {
    return match (events_public_nav_normalize_preset($preset)) {
        'calendar_month' => 'calendar-month',
        'calendar_list' => 'calendar-list',
        default => events_public_nav_normalize_preset($preset),
    };
}

function events_public_nav_preset_href(string $preset, string $lang = 'hu'): string {
    $preset = events_public_nav_normalize_preset($preset);
    $lang = $lang === 'en' ? 'en' : 'hu';

    return match ($preset) {
        'calendar' => events_public_home_page_url($lang),
        'calendar_month' => events_public_home_url($lang, ['view' => 'cal']),
        'calendar_list' => events_public_home_url($lang, ['view' => 'list']),
        'djs' => events_public_djs_page_url($lang),
        'organizers' => events_public_organizers_catalog_page_url($lang),
        'partners' => events_public_partners_page_url($lang),
        'latinfo' => defined('LATINFO_PUBLIC_HOME_URL') ? (string) LATINFO_PUBLIC_HOME_URL : '/',
        default => '',
    };
}

function events_public_nav_items_table_available(PDO $db): bool {
    try {
        $db->query('SELECT 1 FROM `events_public_nav_items` LIMIT 1');

        return true;
    } catch (Throwable) {
        return false;
    }
}

/**
 * Tábla létrehozása és első feltöltés az eddigi beégetett menüvel.
 */
function events_public_nav_items_ensure_schema(PDO $db): bool {
    static $done = false;
    if ($done) {
        return true;
    }

    try {
        $existed = events_public_nav_items_table_available($db);
        if (!$existed) {
            $db->exec('
                CREATE TABLE IF NOT EXISTS `events_public_nav_items` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `parent_id` INT UNSIGNED NULL DEFAULT NULL,
                    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
                    `is_visible` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
                    `menu_key` VARCHAR(64) NOT NULL DEFAULT \'\',
                    `label_hu` VARCHAR(120) NOT NULL DEFAULT \'\',
                    `label_en` VARCHAR(120) NOT NULL DEFAULT \'\',
                    `link_type` VARCHAR(16) NOT NULL DEFAULT \'custom\',
                    `preset_key` VARCHAR(32) NOT NULL DEFAULT \'\',
                    `href` VARCHAR(500) NOT NULL DEFAULT \'\',
                    `is_external` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                    `open_in_new_tab` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                    `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_events_public_nav_items_key` (`menu_key`),
                    KEY `idx_events_public_nav_items_parent` (`parent_id`, `sort_order`, `id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ');
            events_public_nav_items_seed_defaults($db);
        }

        $done = true;

        return true;
    } catch (Throwable $e) {
        error_log('events_public_nav_items_ensure_schema: ' . $e->getMessage());

        return false;
    }
}

function events_public_nav_items_seed_defaults(PDO $db): void {
    $insert = $db->prepare('
        INSERT INTO `events_public_nav_items`
            (`parent_id`, `sort_order`, `is_visible`, `menu_key`, `label_hu`, `label_en`,
             `link_type`, `preset_key`, `href`, `is_external`, `open_in_new_tab`)
        VALUES (?, ?, 1, ?, ?, ?, \'preset\', ?, \'\', ?, 0)
    ');

    $insert->execute([null, 10, 'calendar', 'Naptár', 'Calendar', 'calendar', 0]);
    $calendarId = (int) $db->lastInsertId();
    $insert->execute([$calendarId, 10, 'calendar-month', 'Havi naptár', 'Month view', 'calendar_month', 0]);
    $insert->execute([$calendarId, 20, 'calendar-list', 'Eseménylista', 'Event list', 'calendar_list', 0]);

    $insert->execute([null, 20, 'djs', 'DJ-k', 'DJs', 'djs', 0]);
    $insert->execute([null, 30, 'organizers', 'Szervezők', 'Organizers', 'organizers', 0]);

    $insert->execute([null, 40, 'latinfo', 'Latinfo.hu', 'Latinfo.hu', 'latinfo', 1]);
    $latinfoId = (int) $db->lastInsertId();
    $insert->execute([$latinfoId, 10, 'partners', 'Partnereink', 'Our partners', 'partners', 0]);
}

/**
 * @return list<array<string, mixed>>
 */
function events_public_nav_items_all(PDO $db): array {
    try {
        $st = $db->query('
            SELECT * FROM `events_public_nav_items`
            ORDER BY `sort_order` ASC, `id` ASC
        ');

        return $st === false ? [] : $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('events_public_nav_items_all: ' . $e->getMessage());

        return [];
    }
}

/**
 * @return array<string, mixed>|null
 */
function events_public_nav_items_find(PDO $db, int $id): ?array {
    if ($id <= 0) {
        return null;
    }
    $st = $db->prepare('SELECT * FROM `events_public_nav_items` WHERE `id` = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<int, list<array<string, mixed>>>
 */
function events_public_nav_items_group_by_parent(array $rows): array {
    $byParent = [];
    foreach ($rows as $row) {
        $pid = isset($row['parent_id']) && $row['parent_id'] !== null ? (int) $row['parent_id'] : 0;
        $byParent[$pid][] = $row;
    }
    foreach ($byParent as $pid => $children) {
        usort($children, static function (array $a, array $b): int {
            $sa = (int) ($a['sort_order'] ?? 0);
            $sb = (int) ($b['sort_order'] ?? 0);
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }

            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });
        $byParent[$pid] = $children;
    }

    return $byParent;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function events_public_nav_items_flatten_tree(array $rows): array {
    $byParent = events_public_nav_items_group_by_parent($rows);
    $seen = [];
    $out = [];
    $walk = static function (int $parent, int $depth) use (&$walk, &$byParent, &$seen, &$out): void {
        foreach ($byParent[$parent] ?? [] as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $row['_depth'] = $depth;
            $out[] = $row;
            $walk($id, $depth + 1);
        }
    };
    $walk(0, 0);

    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id > 0 && !isset($seen[$id])) {
            $row['_depth'] = 0;
            $row['_orphan'] = 1;
            $out[] = $row;
        }
    }

    return $out;
}

/**
 * @param array<int, int|null> $parentById
 */
function events_public_nav_items_parent_is_valid(array $parentById, int $itemId, ?int $candidateParent): bool {
    if ($candidateParent === null || $candidateParent <= 0) {
        return true;
    }
    if ($candidateParent === $itemId) {
        return false;
    }
    $guard = 0;
    $cur = $candidateParent;
    while ($cur !== null && $cur > 0) {
        if ($cur === $itemId) {
            return false;
        }
        $cur = $parentById[$cur] ?? null;
        $guard++;
        if ($guard > 1000) {
            return false;
        }
    }

    return true;
}

/**
 * @param list<array<string, mixed>> $rows
 */
function events_public_nav_items_depth_of(array $rows, int $id): int {
    $parentById = [];
    foreach ($rows as $row) {
        $rid = (int) ($row['id'] ?? 0);
        if ($rid <= 0) {
            continue;
        }
        $pid = isset($row['parent_id']) && $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
        $parentById[$rid] = $pid;
    }
    $depth = 0;
    $cur = $parentById[$id] ?? null;
    $guard = 0;
    while ($cur !== null && $cur > 0) {
        $depth++;
        $cur = $parentById[$cur] ?? null;
        $guard++;
        if ($guard > 1000) {
            break;
        }
    }

    return $depth;
}

/**
 * @param array<int, list<array<string, mixed>>> $byParent
 */
function events_public_nav_items_subtree_height(array $byParent, int $id): int {
    $children = $byParent[$id] ?? [];
    if ($children === []) {
        return 0;
    }
    $max = 0;
    foreach ($children as $child) {
        $cid = (int) ($child['id'] ?? 0);
        if ($cid <= 0) {
            continue;
        }
        $max = max($max, 1 + events_public_nav_items_subtree_height($byParent, $cid));
    }

    return $max;
}

/**
 * @param array<int, list<array<string, mixed>>> $byParent
 * @return list<int>
 */
function events_public_nav_items_descendant_ids(array $byParent, int $id): array {
    $ids = [];
    foreach ($byParent[$id] ?? [] as $child) {
        $cid = (int) ($child['id'] ?? 0);
        if ($cid <= 0) {
            continue;
        }
        $ids[] = $cid;
        foreach (events_public_nav_items_descendant_ids($byParent, $cid) as $did) {
            $ids[] = $did;
        }
    }

    return $ids;
}

/**
 * @param list<array<string, mixed>> $rows
 */
function events_public_nav_items_parent_depth_ok(array $rows, int $itemId, ?int $parentId): bool {
    $byParent = events_public_nav_items_group_by_parent($rows);
    $parentDepth = -1;
    if ($parentId !== null && $parentId > 0) {
        $parentDepth = events_public_nav_items_depth_of($rows, $parentId);
    }
    $newDepth = $parentDepth + 1;
    $height = events_public_nav_items_subtree_height($byParent, $itemId);

    return ($newDepth + $height) <= EVENTS_PUBLIC_NAV_MAX_DEPTH;
}

function events_public_nav_items_next_sort(PDO $db, ?int $parentId): int {
    $st = $db->prepare('
        SELECT COALESCE(MAX(`sort_order`), 0)
        FROM `events_public_nav_items`
        WHERE (`parent_id` <=> ?)
    ');
    $st->execute([$parentId]);

    return (int) $st->fetchColumn() + EVENTS_PUBLIC_NAV_SORT_STEP;
}

function events_public_nav_items_key_taken(PDO $db, string $key, int $exceptId = 0): bool {
    $st = $db->prepare('SELECT `id` FROM `events_public_nav_items` WHERE `menu_key` = ? AND `id` <> ? LIMIT 1');
    $st->execute([$key, $exceptId]);

    return $st->fetchColumn() !== false;
}

function events_public_nav_items_allocate_key(PDO $db, string $wanted, int $exceptId = 0): string {
    $base = trim($wanted);
    if ($base === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $base)) {
        $base = 'item';
    }
    $base = mb_substr($base, 0, 50);
    $key = $base;
    $n = 2;
    while (events_public_nav_items_key_taken($db, $key, $exceptId)) {
        $key = mb_substr($base, 0, 40) . '-' . $n;
        $n++;
        if ($n > 500) {
            $key = 'item-' . $exceptId . '-' . bin2hex(random_bytes(3));
            break;
        }
    }

    return $key;
}

function events_public_nav_items_create(PDO $db): int {
    $sort = events_public_nav_items_next_sort($db, null);
    $st = $db->prepare('
        INSERT INTO `events_public_nav_items`
            (`parent_id`, `sort_order`, `is_visible`, `menu_key`, `label_hu`, `label_en`,
             `link_type`, `preset_key`, `href`, `is_external`, `open_in_new_tab`)
        VALUES (NULL, ?, 1, ?, \'Új menüpont\', \'\', \'custom\', \'\', \'#\', 0, 0)
    ');
    $placeholder = 'item-new-' . bin2hex(random_bytes(4));
    $st->execute([$sort, $placeholder]);
    $id = (int) $db->lastInsertId();
    $finalKey = events_public_nav_items_allocate_key($db, 'item-' . $id, $id);
    $up = $db->prepare('UPDATE `events_public_nav_items` SET `menu_key` = ? WHERE `id` = ?');
    $up->execute([$finalKey, $id]);

    return $id;
}

/**
 * @param array<string, mixed> $input
 * @throws InvalidArgumentException
 */
function events_public_nav_items_save(PDO $db, int $id, array $input): void {
    $current = events_public_nav_items_find($db, $id);
    if ($current === null) {
        throw new InvalidArgumentException('A menüpont nem található.');
    }

    $labelHu = trim((string) ($input['label_hu'] ?? ''));
    $labelEn = trim((string) ($input['label_en'] ?? ''));
    if ($labelHu === '') {
        throw new InvalidArgumentException('A magyar felirat kötelező.');
    }
    if (mb_strlen($labelHu) > 120 || mb_strlen($labelEn) > 120) {
        throw new InvalidArgumentException('A felirat legfeljebb 120 karakter lehet.');
    }

    $linkType = events_public_nav_normalize_link_type((string) ($input['link_type'] ?? 'custom'));
    $presetKey = events_public_nav_normalize_preset((string) ($input['preset_key'] ?? ''));
    $href = '';
    $isExternal = empty($input['is_external']) ? 0 : 1;
    $openInNewTab = empty($input['open_in_new_tab']) ? 0 : 1;

    if ($linkType === 'preset') {
        if ($presetKey === '') {
            throw new InvalidArgumentException('Válassz egy belső oldalt.');
        }
        $href = '';
        $isExternal = $presetKey === 'latinfo' ? 1 : 0;
    } else {
        $presetKey = '';
        $rawHref = trim((string) ($input['href'] ?? ''));
        if ($rawHref === '') {
            throw new InvalidArgumentException('Add meg a linket, vagy # ha csak almenü kell.');
        }
        if ($rawHref === '#') {
            $href = '#';
        } else {
            [$normalized, $urlError] = events_normalize_safe_url($rawHref, true);
            if ($urlError !== null || $normalized === null || $normalized === '') {
                throw new InvalidArgumentException($urlError ?? 'A link érvénytelen.');
            }
            if (mb_strlen((string) $normalized) > 500) {
                throw new InvalidArgumentException('A link legfeljebb 500 karakter lehet.');
            }
            $href = (string) $normalized;
        }
        if ($isExternal === 0 && preg_match('#^https?://#i', $href) === 1) {
            $isExternal = 1;
        }
    }

    $parentRaw = $input['parent_id'] ?? null;
    $parentId = null;
    if ($parentRaw !== null && $parentRaw !== '') {
        $parentId = (int) $parentRaw;
        if ($parentId <= 0) {
            $parentId = null;
        }
    }

    $rows = events_public_nav_items_all($db);
    $parentById = [];
    foreach ($rows as $row) {
        $rid = (int) ($row['id'] ?? 0);
        $pid = isset($row['parent_id']) && $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
        $parentById[$rid] = $pid;
    }
    if ($parentId !== null && !array_key_exists($parentId, $parentById)) {
        throw new InvalidArgumentException('A kiválasztott szülő menüpont nem létezik.');
    }
    if (!events_public_nav_items_parent_is_valid($parentById, $id, $parentId)) {
        throw new InvalidArgumentException('A kiválasztott szülő körkörös hierarchiát okozna.');
    }
    if (!events_public_nav_items_parent_depth_ok($rows, $id, $parentId)) {
        throw new InvalidArgumentException('A menü legfeljebb ' . (EVENTS_PUBLIC_NAV_MAX_DEPTH + 1) . ' szint mély lehet.');
    }

    $wantedKey = $linkType === 'preset'
        ? events_public_nav_preset_menu_key($presetKey)
        : (string) ($current['menu_key'] ?? '');
    $menuKey = events_public_nav_items_allocate_key($db, $wantedKey, $id);

    $currentParent = isset($current['parent_id']) && $current['parent_id'] !== null ? (int) $current['parent_id'] : null;
    $sortOrder = (int) ($current['sort_order'] ?? 0);
    if ($currentParent !== $parentId) {
        $sortOrder = events_public_nav_items_next_sort($db, $parentId);
    }

    $st = $db->prepare('
        UPDATE `events_public_nav_items`
        SET `parent_id` = ?, `sort_order` = ?, `is_visible` = ?, `menu_key` = ?,
            `label_hu` = ?, `label_en` = ?, `link_type` = ?, `preset_key` = ?,
            `href` = ?, `is_external` = ?, `open_in_new_tab` = ?
        WHERE `id` = ?
    ');
    $st->execute([
        $parentId,
        $sortOrder,
        empty($input['is_visible']) ? 0 : 1,
        $menuKey,
        $labelHu,
        $labelEn,
        $linkType,
        $presetKey,
        $href,
        $isExternal,
        $openInNewTab,
        $id,
    ]);
}

function events_public_nav_items_has_children(PDO $db, int $id): bool {
    $st = $db->prepare('SELECT 1 FROM `events_public_nav_items` WHERE `parent_id` = ? LIMIT 1');
    $st->execute([$id]);

    return $st->fetchColumn() !== false;
}

function events_public_nav_items_delete(PDO $db, int $id): void {
    if ($id <= 0) {
        return;
    }
    if (events_public_nav_items_has_children($db, $id)) {
        throw new InvalidArgumentException('Előbb töröld vagy helyezd át az almenüpontokat.');
    }
    $st = $db->prepare('DELETE FROM `events_public_nav_items` WHERE `id` = ?');
    $st->execute([$id]);
}

/**
 * @param int $direction -1 = fel, 1 = le
 */
function events_public_nav_items_move(PDO $db, int $id, int $direction): bool {
    $item = events_public_nav_items_find($db, $id);
    if ($item === null) {
        return false;
    }
    $parentId = isset($item['parent_id']) && $item['parent_id'] !== null ? (int) $item['parent_id'] : null;
    $siblings = [];
    foreach (events_public_nav_items_all($db) as $row) {
        $rowParent = isset($row['parent_id']) && $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
        if ($rowParent === $parentId) {
            $siblings[] = $row;
        }
    }
    $index = null;
    foreach ($siblings as $i => $row) {
        if ((int) $row['id'] === $id) {
            $index = $i;
            break;
        }
    }
    if ($index === null) {
        return false;
    }
    $target = $direction < 0 ? $index - 1 : $index + 1;
    if ($target < 0 || $target >= count($siblings)) {
        return false;
    }

    $order = $siblings;
    [$order[$index], $order[$target]] = [$order[$target], $order[$index]];

    $db->beginTransaction();
    try {
        $st = $db->prepare('UPDATE `events_public_nav_items` SET `sort_order` = ? WHERE `id` = ?');
        foreach ($order as $position => $row) {
            $st->execute([($position + 1) * EVENTS_PUBLIC_NAV_SORT_STEP, (int) $row['id']]);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        error_log('events_public_nav_items_move: ' . $e->getMessage());

        return false;
    }

    return true;
}

function events_public_nav_items_restore_defaults(PDO $db): void {
    $db->beginTransaction();
    try {
        $db->exec('DELETE FROM `events_public_nav_items`');
        events_public_nav_items_seed_defaults($db);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function events_public_nav_items_resolve_href(array $row, string $lang): string {
    $linkType = events_public_nav_normalize_link_type((string) ($row['link_type'] ?? 'custom'));
    if ($linkType === 'preset') {
        return events_public_nav_preset_href((string) ($row['preset_key'] ?? ''), $lang);
    }

    return trim((string) ($row['href'] ?? ''));
}

function events_public_nav_items_href_is_safe(string $href): bool {
    $href = trim($href);
    if ($href === '' || $href === '#') {
        return $href === '#';
    }
    $lower = strtolower($href);
    if (str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'data:') || str_starts_with($lower, 'vbscript:')) {
        return false;
    }

    return true;
}

/**
 * @return list<array{key: string, label: string, href: string, external?: bool, new_tab?: bool, children?: list<array<string, mixed>>}>
 */
function events_public_nav_items_tree_for_public(PDO $db, string $lang): array {
    $rows = events_public_nav_items_all($db);
    $byParent = events_public_nav_items_group_by_parent($rows);
    $lang = $lang === 'en' ? 'en' : 'hu';

    $build = static function (int $parent) use (&$build, $byParent, $lang): array {
        $out = [];
        foreach ($byParent[$parent] ?? [] as $row) {
            if ((int) ($row['is_visible'] ?? 0) !== 1) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            $labelHu = trim((string) ($row['label_hu'] ?? ''));
            $labelEn = trim((string) ($row['label_en'] ?? ''));
            $label = $lang === 'en' && $labelEn !== '' ? $labelEn : $labelHu;
            $href = events_public_nav_items_resolve_href($row, $lang);
            if ($label === '' || $href === '' || !events_public_nav_items_href_is_safe($href)) {
                continue;
            }
            $item = [
                'key' => trim((string) ($row['menu_key'] ?? '')),
                'label' => $label,
                'href' => $href,
            ];
            if ((int) ($row['is_external'] ?? 0) === 1) {
                $item['external'] = true;
            }
            if ((int) ($row['open_in_new_tab'] ?? 0) === 1) {
                $item['new_tab'] = true;
            }
            $children = $id > 0 ? $build($id) : [];
            if ($children !== []) {
                $item['children'] = $children;
            }
            $out[] = $item;
        }

        return $out;
    };

    return $build(0);
}
