<?php
declare(strict_types=1);

/**
 * Címke típusok (több választható): DJ, Zenekar, Tanár, Művész, Szervező.
 */

/**
 * @return list<string>
 */
function events_tag_type_codes(): array {
    return ['dj', 'zenekar', 'tanar', 'muvesz', 'szervezo'];
}

/**
 * @return array<string, string>
 */
function events_tag_type_labels(): array {
    return [
        'dj' => 'DJ',
        'zenekar' => 'Zenekar',
        'tanar' => 'Tanár',
        'muvesz' => 'Művész',
        'szervezo' => 'Szervező',
    ];
}

function events_tag_type_label(string $code): string {
    $labels = events_tag_type_labels();

    return $labels[$code] ?? $code;
}

function events_tag_type_is_valid(string $code): bool {
    return in_array($code, events_tag_type_codes(), true);
}

function events_tag_types_tables_available(PDO $db): bool {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    try {
        $db->query('SELECT 1 FROM `events_tag_type_links` LIMIT 1');
        $cached = true;
    } catch (PDOException) {
        $cached = false;
    }

    return $cached;
}

/**
 * @param list<string> $raw
 * @return list<string>
 */
function events_tag_type_normalize_codes(array $raw): array {
    $out = [];
    foreach ($raw as $v) {
        $code = strtolower(trim((string) $v));
        if ($code === '' || !events_tag_type_is_valid($code)) {
            continue;
        }
        if (!in_array($code, $out, true)) {
            $out[] = $code;
        }
    }
    sort($out);

    return $out;
}

/**
 * Vessző / pontosvessző / pipe elválasztott típuslista (CSV).
 *
 * @return list<string>
 */
function events_tag_type_parse_csv_string(string $raw): array {
    if (trim($raw) === '') {
        return [];
    }
    $parts = preg_split('/[,;|]+/u', $raw) ?: [];
    $out = [];
    $labelMap = [];
    foreach (events_tag_type_labels() as $code => $label) {
        $labelMap[mb_strtolower($label, 'UTF-8')] = $code;
        $labelMap[$code] = $code;
    }
    $labelMap['tanár'] = 'tanar';
    $labelMap['művész'] = 'muvesz';
    foreach ($parts as $part) {
        $token = trim((string) $part);
        if ($token === '') {
            continue;
        }
        $key = mb_strtolower($token, 'UTF-8');
        $code = $labelMap[$key] ?? null;
        if ($code === null && events_tag_type_is_valid($key)) {
            $code = $key;
        }
        if ($code !== null && !in_array($code, $out, true)) {
            $out[] = $code;
        }
    }
    sort($out);

    return $out;
}

/**
 * @param list<string> $typeCodes
 */
function events_save_tag_types(PDO $db, int $tagId, array $typeCodes): void {
    if ($tagId <= 0 || !events_tag_types_tables_available($db)) {
        return;
    }
    $typeCodes = events_tag_type_normalize_codes($typeCodes);
    $db->prepare('DELETE FROM `events_tag_type_links` WHERE `tag_id` = ?')->execute([$tagId]);
    if ($typeCodes === []) {
        return;
    }
    $ins = $db->prepare('INSERT INTO `events_tag_type_links` (`tag_id`, `tag_type`) VALUES (?,?)');
    foreach ($typeCodes as $code) {
        $ins->execute([$tagId, $code]);
    }
}

/**
 * @return list<string>
 */
function events_load_tag_type_codes(PDO $db, int $tagId): array {
    if ($tagId <= 0 || !events_tag_types_tables_available($db)) {
        return [];
    }
    $st = $db->prepare('SELECT `tag_type` FROM `events_tag_type_links` WHERE `tag_id` = ? ORDER BY `tag_type` ASC');
    $st->execute([$tagId]);

    return events_tag_type_normalize_codes($st->fetchAll(PDO::FETCH_COLUMN, 0));
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
    $st = $db->prepare("SELECT `tag_id`, `tag_type` FROM `events_tag_type_links` WHERE `tag_id` IN ({$ph}) ORDER BY `tag_type` ASC");
    $st->execute($tagIds);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $tid = (int) $row['tag_id'];
        $code = (string) $row['tag_type'];
        if (!isset($map[$tid])) {
            $map[$tid] = [];
        }
        if (events_tag_type_is_valid($code) && !in_array($code, $map[$tid], true)) {
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
    $typeCodes = events_tag_type_normalize_codes($typeCodes);
    if ($typeCodes === []) {
        return [];
    }
    $ph = implode(',', array_fill(0, count($typeCodes), '?'));
    $st = $db->prepare("
        SELECT DISTINCT t.`id`, t.`name`
        FROM `events_tags` t
        INNER JOIN `events_tag_type_links` tt ON tt.`tag_id` = t.`id`
        WHERE tt.`tag_type` IN ({$ph})
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
    $typeCodes = events_tag_type_normalize_codes($typeCodes);
    if ($typeCodes === []) {
        return [];
    }
    $ph = implode(',', array_fill(0, count($typeCodes), '?'));
    $st = $db->prepare("
        SELECT DISTINCT t.`id`, t.`name`
        FROM `events_tags` t
        INNER JOIN `events_calendar_event_tags` et ON et.`tag_id` = t.`id`
        INNER JOIN `events_tag_type_links` tt ON tt.`tag_id` = t.`id`
        WHERE et.`event_id` = ? AND tt.`tag_type` IN ({$ph})
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
 * Esemény címkék típus nélkül vagy nem felsorolt típusokkal (általános címke lista).
 *
 * @param list<string> $excludeTypeCodes
 * @return list<array{id:int,name:string}>
 */
function events_public_event_tags_for_display(PDO $db, int $eventId, array $excludeTypeCodes = []): array {
    if (!events_tags_tables_available($db) || $eventId <= 0) {
        return [];
    }
    $excludeTypeCodes = events_tag_type_normalize_codes($excludeTypeCodes);
    if ($excludeTypeCodes !== [] && events_tag_types_tables_available($db)) {
        $ph = implode(',', array_fill(0, count($excludeTypeCodes), '?'));
        $st = $db->prepare("
            SELECT t.`id`, t.`name`
            FROM `events_tags` t
            INNER JOIN `events_calendar_event_tags` et ON et.`tag_id` = t.`id`
            WHERE et.`event_id` = ?
              AND NOT EXISTS (
                  SELECT 1 FROM `events_tag_type_links` tt
                  WHERE tt.`tag_id` = t.`id` AND tt.`tag_type` IN ({$ph})
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
