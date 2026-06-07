<?php
declare(strict_types=1);

/**
 * DJ-k (events_djs) és esemény–DJ kapcsolók kezelése.
 */

/**
 * @return array<int, string> id => név
 */
function events_load_dj_options(PDO $db): array {
    if (!events_djs_tables_available($db)) {
        return [];
    }
    $rows = $db->query('SELECT `id`, `name` FROM `events_djs` ORDER BY `name` ASC, `id` ASC')->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $r) {
        $out[(int) $r['id']] = (string) $r['name'];
    }

    return $out;
}

/**
 * @return list<int>
 */
function events_dj_ids_from_post(): array {
    $raw = $_POST['dj_ids'] ?? [];
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
 * @param list<int> $djIds
 */
function events_save_event_djs(PDO $db, int $eventId, array $djIds): void {
    if (!events_djs_tables_available($db)) {
        return;
    }
    $db->prepare('DELETE FROM `events_calendar_event_djs` WHERE `event_id` = ?')->execute([$eventId]);
    if ($djIds === []) {
        return;
    }
    $ins = $db->prepare('INSERT INTO `events_calendar_event_djs` (`event_id`, `dj_id`) VALUES (?,?)');
    foreach ($djIds as $did) {
        if ($did <= 0) {
            continue;
        }
        $ins->execute([$eventId, $did]);
    }
}

/**
 * @return list<int>
 */
function events_load_event_dj_ids(PDO $db, int $eventId): array {
    if (!events_djs_tables_available($db)) {
        return [];
    }
    $st = $db->prepare('
        SELECT `dj_id` FROM `events_calendar_event_djs`
        WHERE `event_id` = ?
        ORDER BY `dj_id` ASC
    ');
    $st->execute([$eventId]);

    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN, 0));
}

/**
 * Név alapján keresés; ha nincs, új DJ rekord.
 */
function events_find_or_create_dj_by_name(PDO $db, string $name): int {
    $name = trim($name);
    if ($name === '') {
        throw new InvalidArgumentException('Üres DJ név.');
    }
    if (!events_djs_tables_available($db)) {
        throw new RuntimeException('Az events_djs tábla nem elérhető (migration_djs.sql).');
    }
    $st = $db->prepare('SELECT `id` FROM `events_djs` WHERE `name` = ? LIMIT 1');
    $st->execute([$name]);
    $existing = $st->fetchColumn();
    if ($existing !== false) {
        return (int) $existing;
    }
    $ins = $db->prepare('INSERT INTO `events_djs` (`name`) VALUES (?)');
    $ins->execute([$name]);

    return (int) $db->lastInsertId();
}

/**
 * Admin: egy DJ eseményei (minden státusz).
 *
 * @return list<array{id:int,event_name:string,event_slug:string,event_status:string,event_start:?string}>
 */
function events_admin_dj_events(PDO $db, int $djId): array {
    if ($djId <= 0 || !events_djs_tables_available($db)) {
        return [];
    }
    $st = $db->prepare('
        SELECT e.`id`, e.`event_name`, e.`event_slug`, e.`event_status`, e.`event_start`
        FROM `events_calendar_events` e
        INNER JOIN `events_calendar_event_djs` ed ON ed.`event_id` = e.`id`
        WHERE ed.`dj_id` = ?
        ORDER BY e.`event_start` DESC, e.`id` DESC
    ');
    $st->execute([$djId]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}
