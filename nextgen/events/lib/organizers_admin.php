<?php
declare(strict_types=1);

require_once __DIR__ . '/event_status.php';

/**
 * Szervező lista admin – eseménystatisztikák al-lekérdezés.
 */
function events_organizers_admin_stats_subquery_sql(): string
{
    $publishedSql = "'" . str_replace("'", "''", events_public_post_status()) . "'";

    return "
        SELECT
            eo.`organizer_id`,
            COUNT(DISTINCT e.`id`) AS `event_count`,
            COUNT(DISTINCT CASE WHEN e.`event_status` = {$publishedSql} THEN e.`id` END) AS `published_count`,
            COUNT(DISTINCT CASE
                WHEN e.`event_status` = {$publishedSql}
                    AND COALESCE(e.`event_end`, e.`event_start`) >= NOW()
                THEN e.`id`
            END) AS `upcoming_count`,
            MAX(COALESCE(e.`event_end`, e.`event_start`)) AS `last_event_at`,
            MIN(CASE
                WHEN e.`event_status` = {$publishedSql}
                    AND e.`event_start` IS NOT NULL
                    AND e.`event_start` >= CURDATE()
                THEN e.`event_start`
            END) AS `next_event_at`
        FROM `events_calendar_event_organizers` eo
        INNER JOIN `events_calendar_events` e ON e.`id` = eo.`event_id`
        WHERE e.`event_status` NOT IN ('trash')
        GROUP BY eo.`organizer_id`
    ";
}

/**
 * @return array{
 *   f_q: string,
 *   f_id: string,
 *   f_events: string,
 *   f_published: string,
 *   order: string,
 *   dir_param: string,
 *   where: list<string>,
 *   params: list<mixed>,
 *   order_sql: string,
 *   get_params: array<string, string>
 * }
 */
function events_organizers_admin_filters_from_request(): array
{
    $f_q = trim((string) ($_GET['f_q'] ?? ''));
    $f_id = trim((string) ($_GET['f_id'] ?? ''));
    $f_events = trim((string) ($_GET['f_events'] ?? ''));
    $f_published = trim((string) ($_GET['f_published'] ?? ''));

    if (!in_array($f_events, ['', 'yes', 'no'], true)) {
        $f_events = '';
    }
    if (!in_array($f_published, ['', 'yes', 'no'], true)) {
        $f_published = '';
    }

    $allowedOrder = ['id', 'name', 'events', 'published', 'upcoming', 'last_event', 'next_event'];
    if (isset($_GET['order']) && in_array((string) $_GET['order'], $allowedOrder, true)) {
        $order = (string) $_GET['order'];
        $dir_param = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'asc' : 'desc';
    } else {
        $order = 'name';
        $dir_param = 'asc';
    }

    $where = [];
    $params = [];

    if ($f_q !== '') {
        $like = '%' . $f_q . '%';
        $where[] = '(o.`name` LIKE ? OR CAST(o.`id` AS CHAR) LIKE ?)';
        array_push($params, $like, $like);
    }
    if ($f_id !== '') {
        if (ctype_digit($f_id)) {
            $where[] = 'o.`id` = ?';
            $params[] = (int) $f_id;
        } else {
            $where[] = 'CAST(o.`id` AS CHAR) LIKE ?';
            $params[] = '%' . $f_id . '%';
        }
    }
    if ($f_events === 'yes') {
        $where[] = 'COALESCE(st.`event_count`, 0) > 0';
    } elseif ($f_events === 'no') {
        $where[] = 'COALESCE(st.`event_count`, 0) = 0';
    }
    if ($f_published === 'yes') {
        $where[] = 'COALESCE(st.`published_count`, 0) > 0';
    } elseif ($f_published === 'no') {
        $where[] = 'COALESCE(st.`published_count`, 0) = 0';
    }

    $dirSql = $dir_param === 'asc' ? 'ASC' : 'DESC';
    $orderSql = match ($order) {
        'id' => "o.`id` $dirSql",
        'name' => "o.`name` $dirSql, o.`id` ASC",
        'events' => "COALESCE(st.`event_count`, 0) $dirSql, o.`name` ASC",
        'published' => "COALESCE(st.`published_count`, 0) $dirSql, o.`name` ASC",
        'upcoming' => "COALESCE(st.`upcoming_count`, 0) $dirSql, o.`name` ASC",
        'last_event' => "st.`last_event_at` IS NULL, st.`last_event_at` $dirSql, o.`name` ASC",
        'next_event' => "st.`next_event_at` IS NULL, st.`next_event_at` $dirSql, o.`name` ASC",
        default => 'o.`name` ASC, o.`id` ASC',
    };

    $get_params = array_filter([
        'f_q' => $f_q !== '' ? $f_q : null,
        'f_id' => $f_id !== '' ? $f_id : null,
        'f_events' => $f_events !== '' ? $f_events : null,
        'f_published' => $f_published !== '' ? $f_published : null,
    ]);

    return [
        'f_q' => $f_q,
        'f_id' => $f_id,
        'f_events' => $f_events,
        'f_published' => $f_published,
        'order' => $order,
        'dir_param' => $dir_param,
        'where' => $where,
        'params' => $params,
        'order_sql' => $orderSql,
        'get_params' => $get_params,
    ];
}

/**
 * @param array<string, mixed> $filters
 * @return list<array<string, mixed>>
 */
function events_organizers_admin_fetch(PDO $db, array $filters, ?int $listLimit): array
{
    $statsSql = events_organizers_admin_stats_subquery_sql();
    $whereSql = $filters['where'] !== [] ? 'WHERE ' . implode(' AND ', $filters['where']) : '';

    $sql = "
        SELECT
            o.`id`,
            o.`name`,
            COALESCE(st.`event_count`, 0) AS `event_count`,
            COALESCE(st.`published_count`, 0) AS `published_count`,
            COALESCE(st.`upcoming_count`, 0) AS `upcoming_count`,
            st.`last_event_at`,
            st.`next_event_at`
        FROM `events_organizers` o
        LEFT JOIN ({$statsSql}) st ON st.`organizer_id` = o.`id`
        {$whereSql}
        ORDER BY {$filters['order_sql']}
    ";
    if ($listLimit !== null) {
        $sql .= ' LIMIT ' . (int) $listLimit;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($filters['params']);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function events_organizers_admin_format_datetime(?string $value): string
{
    if ($value === null || trim($value) === '' || str_starts_with($value, '0000-00-00')) {
        return '–';
    }
    try {
        $dt = new DateTimeImmutable($value);

        return $dt->format('Y.m.d H:i');
    } catch (Throwable) {
        return '–';
    }
}

function events_organizers_admin_events_filter_url(string $organizerName): string
{
    $params = ['f_organizer' => $organizerName];

    return events_url('events_admin.php') . '?' . http_build_query($params);
}

/**
 * @return array{payer: bool, accounts: bool, partners: bool}
 */
function events_organizers_admin_delete_related_flags(PDO $db): array
{
    $hasPayerColumn = false;
    try {
        $payerCol = $db->query("SHOW COLUMNS FROM `events_calendar_events` LIKE 'finance_payer_organizer_id'")->fetch(PDO::FETCH_ASSOC);
        $hasPayerColumn = $payerCol !== false;
    } catch (Throwable) {
        $hasPayerColumn = false;
    }

    return [
        'payer' => $hasPayerColumn,
        'accounts' => db_table_exists($db, 'events_organizer_accounts'),
        'partners' => db_table_exists($db, 'nextgen_partner_events_organizers'),
    ];
}

/**
 * @param mixed $raw
 * @return list<int>
 */
function events_organizers_admin_ids_from_post(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $ids = [];
    foreach ($raw as $value) {
        $id = (int) $value;
        if ($id <= 0 || in_array($id, $ids, true)) {
            continue;
        }
        $ids[] = $id;
        if (count($ids) >= 500) {
            break;
        }
    }

    return $ids;
}

/**
 * @param array{payer: bool, accounts: bool, partners: bool} $flags
 */
function events_organizers_admin_delete_row(PDO $db, int $id, array $flags): void
{
    $db->prepare('DELETE FROM `events_calendar_event_organizers` WHERE `organizer_id` = ?')->execute([$id]);
    if ($flags['payer']) {
        $db->prepare('UPDATE `events_calendar_events` SET `finance_payer_organizer_id` = NULL WHERE `finance_payer_organizer_id` = ?')->execute([$id]);
    }
    if ($flags['accounts']) {
        $db->prepare('DELETE FROM `events_organizer_accounts` WHERE `organizer_id` = ?')->execute([$id]);
    }
    if ($flags['partners']) {
        $db->prepare('DELETE FROM `nextgen_partner_events_organizers` WHERE `organizer_id` = ?')->execute([$id]);
    }
    $db->prepare('DELETE FROM `events_organizers` WHERE `id` = ?')->execute([$id]);
}

/**
 * Szervező törlése: események megmaradnak, a kapcsolatok és a szervező rekord törlődik.
 *
 * @return array{ok: true, name: string}|array{ok: false, error: string}
 */
function events_organizers_admin_delete(PDO $db, int $id): array
{
    $many = events_organizers_admin_delete_many($db, [$id]);
    if ($many['ok'] && $many['deleted'] === 1) {
        return ['ok' => true, 'name' => $many['names'][0] ?? ('#' . $id)];
    }

    return ['ok' => false, 'error' => $many['error'] ?? 'Szervező nem található.'];
}

/**
 * @param list<int> $ids
 * @return array{ok: true, deleted: int, names: list<string>}|array{ok: false, error: string, deleted?: int, names?: list<string>}
 */
function events_organizers_admin_delete_many(PDO $db, array $ids): array
{
    $ids = events_organizers_admin_ids_from_post($ids);
    if ($ids === []) {
        return ['ok' => false, 'error' => 'Nincs kiválasztott szervező.'];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $st = $db->prepare("SELECT `id`, `name` FROM `events_organizers` WHERE `id` IN ({$placeholders})");
    $st->execute($ids);
    $found = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $found[(int) ($row['id'] ?? 0)] = (string) ($row['name'] ?? '');
    }
    if ($found === []) {
        return ['ok' => false, 'error' => 'Szervező nem található.'];
    }

    $flags = events_organizers_admin_delete_related_flags($db);
    $deleted = [];

    try {
        $db->beginTransaction();
        foreach ($ids as $id) {
            if (!isset($found[$id])) {
                continue;
            }
            events_organizers_admin_delete_row($db, $id, $flags);
            $deleted[$id] = $found[$id];
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('events_organizers_admin_delete_many: ' . $e->getMessage());

        return ['ok' => false, 'error' => 'A törlés nem sikerült. Kérlek próbáld újra.'];
    }

    foreach ($deleted as $id => $name) {
        rendszer_log('szervező', (int) $id, 'Törölve', $name);
    }

    return ['ok' => true, 'deleted' => count($deleted), 'names' => array_values($deleted)];
}
