<?php
declare(strict_types=1);

/**
 * Stílusok (events_styles) és esemény–stílus kapcsolók (fő + kiegészítő).
 */

/**
 * @return array<int, string> id => név
 */
function events_load_style_options(PDO $db): array {
    if (!events_styles_tables_available($db)) {
        return [];
    }
    $rows = $db->query('SELECT `id`, `name` FROM `events_styles` ORDER BY `name` ASC, `id` ASC')->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $r) {
        $out[(int) $r['id']] = (string) $r['name'];
    }

    return $out;
}

/**
 * @return list<int>
 */
function events_main_style_ids_from_post(): array {
    return events_style_ids_from_post_field('main_style_ids');
}

/**
 * @return list<int>
 */
function events_supplementary_style_ids_from_post(): array {
    return events_style_ids_from_post_field('supplementary_style_ids');
}

/**
 * @return list<int>
 */
function events_style_ids_from_post_field(string $field): array {
    $raw = $_POST[$field] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    $ids = [];
    foreach ($raw as $v) {
        $i = (int) $v;
        if ($i > 0 && !in_array($i, $ids, true)) {
            $ids[] = $i;
        }
    }

    return $ids;
}

/**
 * @param list<int> $styleIds
 */
function events_save_event_main_styles(PDO $db, int $eventId, array $styleIds): void {
    events_save_event_styles_for_table($db, $eventId, $styleIds, 'events_calendar_event_main_styles');
}

/**
 * @param list<int> $styleIds
 */
function events_save_event_supplementary_styles(PDO $db, int $eventId, array $styleIds): void {
    events_save_event_styles_for_table($db, $eventId, $styleIds, 'events_calendar_event_supplementary_styles');
}

/**
 * @param list<int> $styleIds
 */
function events_save_event_styles_for_table(PDO $db, int $eventId, array $styleIds, string $table): void {
    if (!events_styles_tables_available($db)) {
        return;
    }
    if ($table !== 'events_calendar_event_main_styles' && $table !== 'events_calendar_event_supplementary_styles') {
        throw new InvalidArgumentException('Érvénytelen stílus kapcsoló tábla.');
    }
    $db->prepare("DELETE FROM `{$table}` WHERE `event_id` = ?")->execute([$eventId]);
    if ($styleIds === []) {
        return;
    }
    $ins = $db->prepare("INSERT INTO `{$table}` (`event_id`, `style_id`) VALUES (?,?)");
    foreach ($styleIds as $sid) {
        if ($sid <= 0) {
            continue;
        }
        $ins->execute([$eventId, $sid]);
    }
}

/**
 * @return list<int>
 */
function events_load_event_main_style_ids(PDO $db, int $eventId): array {
    return events_load_event_style_ids_for_table($db, $eventId, 'events_calendar_event_main_styles');
}

/**
 * @return list<int>
 */
function events_load_event_supplementary_style_ids(PDO $db, int $eventId): array {
    return events_load_event_style_ids_for_table($db, $eventId, 'events_calendar_event_supplementary_styles');
}

/**
 * @return list<int>
 */
function events_load_event_style_ids_for_table(PDO $db, int $eventId, string $table): array {
    if (!events_styles_tables_available($db)) {
        return [];
    }
    if ($table !== 'events_calendar_event_main_styles' && $table !== 'events_calendar_event_supplementary_styles') {
        return [];
    }
    $st = $db->prepare("SELECT `style_id` FROM `{$table}` WHERE `event_id` = ? ORDER BY `style_id` ASC");
    $st->execute([$eventId]);

    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN, 0));
}

/**
 * Név alapján keresés; ha nincs, új stílus rekord.
 */
function events_find_or_create_style_by_name(PDO $db, string $name): int {
    $name = trim($name);
    if ($name === '') {
        throw new InvalidArgumentException('Üres stílus név.');
    }
    if (!events_styles_tables_available($db)) {
        throw new RuntimeException('Az events_styles tábla nem elérhető (migration_styles.sql).');
    }
    $st = $db->prepare('SELECT `id` FROM `events_styles` WHERE `name` = ? LIMIT 1');
    $st->execute([$name]);
    $existing = $st->fetchColumn();
    if ($existing !== false) {
        return (int) $existing;
    }
    $ins = $db->prepare('INSERT INTO `events_styles` (`name`) VALUES (?)');
    $ins->execute([$name]);

    return (int) $db->lastInsertId();
}

/**
 * Admin: egy stílus eseményei (minden státusz, fő vagy kiegészítő).
 *
 * @return list<array{id:int,event_name:string,event_slug:string,event_status:string,event_start:?string}>
 */
function events_admin_style_events(PDO $db, int $styleId): array {
    if ($styleId <= 0 || !events_styles_tables_available($db)) {
        return [];
    }
    $st = $db->prepare('
        SELECT DISTINCT e.`id`, e.`event_name`, e.`event_slug`, e.`event_status`, e.`event_start`
        FROM `events_calendar_events` e
        WHERE e.`id` IN (
            SELECT `event_id` FROM `events_calendar_event_main_styles` WHERE `style_id` = ?
            UNION
            SELECT `event_id` FROM `events_calendar_event_supplementary_styles` WHERE `style_id` = ?
        )
        ORDER BY e.`event_start` DESC, e.`id` DESC
    ');
    $st->execute([$styleId, $styleId]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Admin: hány esemény használja a stílust (fő vagy kiegészítő, egy esemény egyszer számít).
 */
function events_admin_style_event_count(PDO $db, int $styleId): int {
    if ($styleId <= 0 || !events_styles_tables_available($db)) {
        return 0;
    }
    $st = $db->prepare('
        SELECT COUNT(DISTINCT `event_id`) FROM (
            SELECT `event_id` FROM `events_calendar_event_main_styles` WHERE `style_id` = ?
            UNION
            SELECT `event_id` FROM `events_calendar_event_supplementary_styles` WHERE `style_id` = ?
        ) AS `linked`
    ');
    $st->execute([$styleId, $styleId]);

    return (int) $st->fetchColumn();
}
