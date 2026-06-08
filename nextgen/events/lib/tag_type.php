<?php
declare(strict_types=1);

require_once __DIR__ . '/slug.php';

/**
 * Címke típusok – adatbázisban tárolt, bővíthető törzsadat (events_tag_types).
 */

/** @var array<int, array{id:int,code:string,name:string,icon:string,tone:string,sort_order:int}>|null */
$GLOBALS['_events_tag_types_registry_cache'] = null;

function events_tag_types_clear_cache(): void {
    $GLOBALS['_events_tag_types_registry_cache'] = null;
}

function events_tag_types_registry_table_available(PDO $db): bool {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    try {
        $db->query('SELECT 1 FROM `events_tag_types` LIMIT 1');
        $cached = true;
    } catch (PDOException) {
        $cached = false;
    }

    return $cached;
}

function events_tag_types_tables_available(PDO $db): bool {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    try {
        $db->query('SELECT 1 FROM `events_tag_type_links` LIMIT 1');
        if (!events_tag_types_registry_table_available($db)) {
            $cached = false;
            return $cached;
        }
        $st = $db->query("
            SELECT COUNT(*)
            FROM `information_schema`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = DATABASE()
              AND `TABLE_NAME` = 'events_tag_type_links'
              AND `COLUMN_NAME` = 'tag_type_id'
        ");
        $cached = ((int) $st->fetchColumn()) > 0;
    } catch (PDOException) {
        $cached = false;
    }

    return $cached;
}

/**
 * @return list<array{id:int,code:string,name:string,icon:string,tone:string,sort_order:int}>
 */
function events_tag_types_default_seed_rows(): array {
    return [
        ['code' => 'dj', 'name' => 'DJ', 'icon' => '🎧', 'tone' => 'dj', 'sort_order' => 10],
        ['code' => 'zenekar', 'name' => 'Zenekar', 'icon' => '🎸', 'tone' => 'zenekar', 'sort_order' => 20],
        ['code' => 'tanar', 'name' => 'Tanár', 'icon' => '📚', 'tone' => 'tanar', 'sort_order' => 30],
        ['code' => 'muvesz', 'name' => 'Művész', 'icon' => '🎨', 'tone' => 'muvesz', 'sort_order' => 40],
        ['code' => 'szervezo', 'name' => 'Szervező', 'icon' => '🎪', 'tone' => 'szervezo', 'sort_order' => 50],
    ];
}

function events_tag_types_ensure_seeded(PDO $db): void {
    if (!events_tag_types_registry_table_available($db)) {
        return;
    }
    try {
        $cnt = (int) $db->query('SELECT COUNT(*) FROM `events_tag_types`')->fetchColumn();
        if ($cnt > 0) {
            return;
        }
    } catch (PDOException) {
        return;
    }
    $ins = $db->prepare('INSERT INTO `events_tag_types` (`code`, `name`, `icon`, `tone`, `sort_order`) VALUES (?,?,?,?,?)');
    foreach (events_tag_types_default_seed_rows() as $row) {
        $ins->execute([
            (string) $row['code'],
            (string) $row['name'],
            (string) $row['icon'],
            (string) $row['tone'],
            (int) $row['sort_order'],
        ]);
    }
    events_tag_types_clear_cache();
}

/**
 * @return list<array{id:int,code:string,name:string,icon:string,tone:string,sort_order:int}>
 */
function events_tag_types_load_registry(PDO $db, bool $forceRefresh = false): array {
    if (!$forceRefresh && is_array($GLOBALS['_events_tag_types_registry_cache'])) {
        return $GLOBALS['_events_tag_types_registry_cache'];
    }
    if (!events_tag_types_registry_table_available($db)) {
        $GLOBALS['_events_tag_types_registry_cache'] = [];

        return [];
    }
    events_tag_types_ensure_seeded($db);
    try {
        $rows = $db->query('
            SELECT `id`, `code`, `name`, `icon`, `tone`, `sort_order`
            FROM `events_tag_types`
            ORDER BY `sort_order` ASC, `name` ASC, `id` ASC
        ')->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException) {
        $GLOBALS['_events_tag_types_registry_cache'] = [];

        return [];
    }
    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'id' => (int) ($row['id'] ?? 0),
            'code' => (string) ($row['code'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'icon' => (string) ($row['icon'] ?? '🏷️'),
            'tone' => (string) ($row['tone'] ?? 'default'),
            'sort_order' => (int) ($row['sort_order'] ?? 0),
        ];
    }
    $GLOBALS['_events_tag_types_registry_cache'] = $out;

    return $out;
}

/**
 * @return list<string>
 */
function events_tag_type_codes(?PDO $db = null): array {
    $db = $db ?? getDb();
    $codes = [];
    foreach (events_tag_types_load_registry($db) as $row) {
        if ($row['code'] !== '') {
            $codes[] = $row['code'];
        }
    }

    return $codes;
}

/**
 * @return array<string, string>
 */
function events_tag_type_labels(?PDO $db = null): array {
    $db = $db ?? getDb();
    $labels = [];
    foreach (events_tag_types_load_registry($db) as $row) {
        if ($row['code'] !== '') {
            $labels[$row['code']] = $row['name'];
        }
    }

    return $labels;
}

function events_tag_type_label(string $code, ?PDO $db = null): string {
    $labels = events_tag_type_labels($db);

    return $labels[$code] ?? $code;
}

/**
 * @return array<string, array{icon: string, tone: string}>
 */
function events_tag_type_display_meta(?PDO $db = null): array {
    $db = $db ?? getDb();
    $meta = [];
    foreach (events_tag_types_load_registry($db) as $row) {
        if ($row['code'] === '') {
            continue;
        }
        $meta[$row['code']] = [
            'icon' => $row['icon'] !== '' ? $row['icon'] : '🏷️',
            'tone' => $row['tone'] !== '' ? $row['tone'] : 'default',
        ];
    }

    return $meta;
}

function events_tag_type_is_valid(string $code, ?PDO $db = null): bool {
    $code = strtolower(trim($code));

    return $code !== '' && in_array($code, events_tag_type_codes($db), true);
}

function events_tag_type_id_by_code(PDO $db, string $code): ?int {
    $code = strtolower(trim($code));
    if ($code === '') {
        return null;
    }
    foreach (events_tag_types_load_registry($db) as $row) {
        if ($row['code'] === $code) {
            return $row['id'] > 0 ? $row['id'] : null;
        }
    }

    return null;
}

function events_tag_type_code_by_id(PDO $db, int $typeId): ?string {
    if ($typeId <= 0) {
        return null;
    }
    foreach (events_tag_types_load_registry($db) as $row) {
        if ($row['id'] === $typeId) {
            return $row['code'] !== '' ? $row['code'] : null;
        }
    }

    return null;
}

/**
 * @param list<string> $raw
 * @return list<string>
 */
function events_tag_type_normalize_codes(array $raw, ?PDO $db = null): array {
    $db = $db ?? getDb();
    $valid = array_fill_keys(events_tag_type_codes($db), true);
    $out = [];
    foreach ($raw as $v) {
        $code = strtolower(trim((string) $v));
        if ($code === '' || !isset($valid[$code])) {
            continue;
        }
        if (!in_array($code, $out, true)) {
            $out[] = $code;
        }
    }
    $order = events_tag_type_codes($db);
    usort($out, static function (string $a, string $b) use ($order): int {
        $ia = array_search($a, $order, true);
        $ib = array_search($b, $order, true);
        $ia = $ia === false ? PHP_INT_MAX : (int) $ia;
        $ib = $ib === false ? PHP_INT_MAX : (int) $ib;

        return $ia <=> $ib;
    });

    return $out;
}

/**
 * @return list<string>
 */
function events_tag_type_parse_csv_string(string $raw, ?PDO $db = null): array {
    $db = $db ?? getDb();
    if (trim($raw) === '') {
        return [];
    }
    $parts = preg_split('/[,;|]+/u', $raw) ?: [];
    $out = [];
    $labelMap = [];
    foreach (events_tag_type_labels($db) as $code => $label) {
        $labelMap[mb_strtolower($label, 'UTF-8')] = $code;
        $labelMap[$code] = $code;
    }
    foreach ($parts as $part) {
        $token = trim((string) $part);
        if ($token === '') {
            continue;
        }
        $key = mb_strtolower($token, 'UTF-8');
        $code = $labelMap[$key] ?? null;
        if ($code === null && events_tag_type_is_valid($key, $db)) {
            $code = $key;
        }
        if ($code !== null && !in_array($code, $out, true)) {
            $out[] = $code;
        }
    }

    return events_tag_type_normalize_codes($out, $db);
}

function events_tag_type_allowed_labels_text(?PDO $db = null): string {
    $labels = array_values(events_tag_type_labels($db));
    if ($labels === []) {
        return '(nincs típus a rendszerben)';
    }

    return implode(', ', $labels);
}

/**
 * @param list<string> $typeCodes
 * @return list<int>
 */
function events_tag_type_codes_to_ids(PDO $db, array $typeCodes): array {
    $typeCodes = events_tag_type_normalize_codes($typeCodes, $db);
    $ids = [];
    foreach ($typeCodes as $code) {
        $id = events_tag_type_id_by_code($db, $code);
        if ($id !== null && $id > 0) {
            $ids[] = $id;
        }
    }

    return array_values(array_unique($ids));
}

/**
 * @param list<string> $typeCodes
 */
function events_save_tag_types(PDO $db, int $tagId, array $typeCodes): void {
    if ($tagId <= 0 || !events_tag_types_tables_available($db)) {
        return;
    }
    $typeIds = events_tag_type_codes_to_ids($db, $typeCodes);
    $db->prepare('DELETE FROM `events_tag_type_links` WHERE `tag_id` = ?')->execute([$tagId]);
    if ($typeIds === []) {
        return;
    }
    $ins = $db->prepare('INSERT INTO `events_tag_type_links` (`tag_id`, `tag_type_id`) VALUES (?,?)');
    foreach ($typeIds as $typeId) {
        $ins->execute([$tagId, $typeId]);
    }
}

/**
 * @return list<string>
 */
function events_load_tag_type_codes(PDO $db, int $tagId): array {
    if ($tagId <= 0 || !events_tag_types_tables_available($db)) {
        return [];
    }
    $st = $db->prepare('
        SELECT ty.`code`
        FROM `events_tag_type_links` l
        INNER JOIN `events_tag_types` ty ON ty.`id` = l.`tag_type_id`
        WHERE l.`tag_id` = ?
        ORDER BY ty.`sort_order` ASC, ty.`name` ASC, ty.`id` ASC
    ');
    $st->execute([$tagId]);

    return events_tag_type_normalize_codes($st->fetchAll(PDO::FETCH_COLUMN, 0), $db);
}

/**
 * @param list<int> $tagIds
 * @return array<int, list<string>>
 */
function events_load_tag_types_map(PDO $db, array $tagIds): array {
    $map = [];
    if ($tagIds === [] || !events_tag_types_tables_available($db)) {
        return $map;
    }
    $tagIds = array_values(array_unique(array_filter(array_map('intval', $tagIds), static fn (int $id): bool => $id > 0)));
    if ($tagIds === []) {
        return $map;
    }
    $ph = implode(',', array_fill(0, count($tagIds), '?'));
    $st = $db->prepare("
        SELECT l.`tag_id`, ty.`code`
        FROM `events_tag_type_links` l
        INNER JOIN `events_tag_types` ty ON ty.`id` = l.`tag_type_id`
        WHERE l.`tag_id` IN ({$ph})
        ORDER BY ty.`sort_order` ASC, ty.`name` ASC, ty.`id` ASC
    ");
    $st->execute($tagIds);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $tid = (int) $row['tag_id'];
        $code = (string) $row['code'];
        if (!isset($map[$tid])) {
            $map[$tid] = [];
        }
        if (events_tag_type_is_valid($code, $db) && !in_array($code, $map[$tid], true)) {
            $map[$tid][] = $code;
        }
    }

    return $map;
}

/**
 * @param list<string> $typeCodes
 * @return array<int, string>
 */
function events_load_tag_options_by_types(PDO $db, array $typeCodes): array {
    if (!events_tags_tables_available($db) || !events_tag_types_tables_available($db)) {
        return [];
    }
    $typeCodes = events_tag_type_normalize_codes($typeCodes, $db);
    if ($typeCodes === []) {
        return [];
    }
    $ph = implode(',', array_fill(0, count($typeCodes), '?'));
    $st = $db->prepare("
        SELECT DISTINCT t.`id`, t.`name`
        FROM `events_tags` t
        INNER JOIN `events_tag_type_links` l ON l.`tag_id` = t.`id`
        INNER JOIN `events_tag_types` ty ON ty.`id` = l.`tag_type_id`
        WHERE ty.`code` IN ({$ph})
        ORDER BY t.`name` ASC, t.`id` ASC
    ");
    $st->execute($typeCodes);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[(int) $r['id']] = (string) $r['name'];
    }

    return $out;
}

/**
 * @param list<string> $typeCodes
 * @return list<array{id:int,name:string}>
 */
function events_public_event_tags_by_types(PDO $db, int $eventId, array $typeCodes): array {
    if (!events_tags_tables_available($db) || !events_tag_types_tables_available($db) || $eventId <= 0) {
        return [];
    }
    $typeCodes = events_tag_type_normalize_codes($typeCodes, $db);
    if ($typeCodes === []) {
        return [];
    }
    $ph = implode(',', array_fill(0, count($typeCodes), '?'));
    $st = $db->prepare("
        SELECT DISTINCT t.`id`, t.`name`
        FROM `events_tags` t
        INNER JOIN `events_calendar_event_tags` et ON et.`tag_id` = t.`id`
        INNER JOIN `events_tag_type_links` l ON l.`tag_id` = t.`id`
        INNER JOIN `events_tag_types` ty ON ty.`id` = l.`tag_type_id`
        WHERE et.`event_id` = ? AND ty.`code` IN ({$ph})
        ORDER BY t.`name` ASC, t.`id` ASC
    ");
    $st->execute(array_merge([$eventId], $typeCodes));
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[] = ['id' => (int) $r['id'], 'name' => (string) $r['name']];
    }

    return $out;
}

/**
 * @param list<string> $excludeTypeCodes
 * @return list<array{id:int,name:string}>
 */
function events_public_event_tags_for_display(PDO $db, int $eventId, array $excludeTypeCodes = []): array {
    if (!events_tags_tables_available($db) || $eventId <= 0) {
        return [];
    }
    $excludeTypeCodes = events_tag_type_normalize_codes($excludeTypeCodes, $db);
    if ($excludeTypeCodes !== [] && events_tag_types_tables_available($db)) {
        $ph = implode(',', array_fill(0, count($excludeTypeCodes), '?'));
        $st = $db->prepare("
            SELECT t.`id`, t.`name`
            FROM `events_tags` t
            INNER JOIN `events_calendar_event_tags` et ON et.`tag_id` = t.`id`
            WHERE et.`event_id` = ?
              AND NOT EXISTS (
                  SELECT 1 FROM `events_tag_type_links` l
                  INNER JOIN `events_tag_types` ty ON ty.`id` = l.`tag_type_id`
                  WHERE l.`tag_id` = t.`id` AND ty.`code` IN ({$ph})
              )
            ORDER BY t.`name` ASC, t.`id` ASC
        ");
        $st->execute(array_merge([$eventId], $excludeTypeCodes));
    } else {
        $st = $db->prepare('
            SELECT t.`id`, t.`name`
            FROM `events_tags` t
            INNER JOIN `events_calendar_event_tags` et ON et.`tag_id` = t.`id`
            WHERE et.`event_id` = ?
            ORDER BY t.`name` ASC, t.`id` ASC
        ');
        $st->execute([$eventId]);
    }
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[] = ['id' => (int) $r['id'], 'name' => (string) $r['name']];
    }

    return $out;
}

function events_tag_type_ensure_unique_code(PDO $db, string $code, ?int $excludeId = null): string {
    $base = events_slugify($code);
    if ($base === 'esemeny') {
        $base = 'tipus';
    }
    $candidate = $base;
    $n = 2;
    while (true) {
        $sql = 'SELECT 1 FROM `events_tag_types` WHERE `code` = ?';
        $params = [$candidate];
        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' AND `id` <> ?';
            $params[] = $excludeId;
        }
        $st = $db->prepare($sql . ' LIMIT 1');
        $st->execute($params);
        if (!$st->fetchColumn()) {
            return $candidate;
        }
        $candidate = $base . '-' . $n;
        $n++;
    }
}

/**
 * @return array{id:int,code:string,name:string,icon:string,tone:string,sort_order:int}|null
 */
function events_tag_type_row_by_id(PDO $db, int $id): ?array {
    if ($id <= 0 || !events_tag_types_registry_table_available($db)) {
        return null;
    }
    $st = $db->prepare('SELECT `id`, `code`, `name`, `icon`, `tone`, `sort_order` FROM `events_tag_types` WHERE `id` = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'code' => (string) ($row['code'] ?? ''),
        'name' => (string) ($row['name'] ?? ''),
        'icon' => (string) ($row['icon'] ?? '🏷️'),
        'tone' => (string) ($row['tone'] ?? 'default'),
        'sort_order' => (int) ($row['sort_order'] ?? 0),
    ];
}

function events_tag_type_count_links(PDO $db, int $typeId): int {
    if ($typeId <= 0 || !events_tag_types_tables_available($db)) {
        return 0;
    }
    $st = $db->prepare('SELECT COUNT(*) FROM `events_tag_type_links` WHERE `tag_type_id` = ?');
    $st->execute([$typeId]);

    return (int) $st->fetchColumn();
}
