<?php
declare(strict_types=1);

/**
 * Címke ellenőrző / áttöltés: a forráscímkével ellátott eseményekre beírja a célcímkét.
 */

/**
 * Címkék admin URL (megtartja a lista limitet).
 *
 * @param array<string, scalar|null> $extra
 */
function events_tags_admin_url(array $extra = []): string {
    $q = [];
    $listLimit = trim((string) ($_GET['list_limit'] ?? ''));
    if ($listLimit !== '') {
        $q['list_limit'] = $listLimit;
    }
    foreach ($extra as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        if (is_int($value) && $value <= 0) {
            continue;
        }
        $q[(string) $key] = $value;
    }
    $base = events_url('tags.php');
    if ($q === []) {
        return $base;
    }

    return $base . '?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
}

/**
 * @return array{id:int,name:string}|null
 */
function events_tag_copy_load_tag(PDO $db, int $tagId): ?array {
    if ($tagId <= 0 || !events_tags_tables_available($db)) {
        return null;
    }
    $st = $db->prepare('SELECT `id`, `name` FROM `events_tags` WHERE `id` = ? LIMIT 1');
    $st->execute([$tagId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    return [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
    ];
}

/**
 * @return array{
 *     ok: bool,
 *     error: ?string,
 *     from: ?array{id:int,name:string},
 *     to: ?array{id:int,name:string}
 * }
 */
function events_tag_copy_validate_pair(PDO $db, int $fromId, int $toId): array {
    $from = events_tag_copy_load_tag($db, $fromId);
    $to = events_tag_copy_load_tag($db, $toId);
    if ($from === null || $to === null) {
        return [
            'ok' => false,
            'error' => 'Válassz ki két érvényes címkét: miből és mi legyen.',
            'from' => $from,
            'to' => $to,
        ];
    }
    if ($from['id'] === $to['id']) {
        return [
            'ok' => false,
            'error' => 'A „miből” és a „mi legyen” címke legyen különböző.',
            'from' => $from,
            'to' => $to,
        ];
    }

    return [
        'ok' => true,
        'error' => null,
        'from' => $from,
        'to' => $to,
    ];
}

/**
 * @param array{id:int,name:string} $from
 * @param array{id:int,name:string} $to
 * @return array{
 *     from: array{id:int,name:string},
 *     to: array{id:int,name:string},
 *     source_count: int,
 *     already_count: int,
 *     pending_count: int,
 *     pending_events: list<array<string,mixed>>,
 *     pending_listed: int
 * }
 */
function events_tag_copy_preview(PDO $db, array $from, array $to, int $listLimit = 40): array {
    $fromId = (int) $from['id'];
    $toId = (int) $to['id'];
    $listLimit = max(1, min(100, $listLimit));

    $stSrc = $db->prepare('SELECT COUNT(DISTINCT `event_id`) FROM `events_calendar_event_tags` WHERE `tag_id` = ?');
    $stSrc->execute([$fromId]);
    $sourceCount = (int) $stSrc->fetchColumn();

    $stAlready = $db->prepare('
        SELECT COUNT(DISTINCT src.`event_id`)
        FROM `events_calendar_event_tags` src
        INNER JOIN `events_calendar_event_tags` dst
            ON dst.`event_id` = src.`event_id` AND dst.`tag_id` = ?
        WHERE src.`tag_id` = ?
    ');
    $stAlready->execute([$toId, $fromId]);
    $alreadyCount = (int) $stAlready->fetchColumn();

    $stPending = $db->prepare('
        SELECT COUNT(DISTINCT src.`event_id`)
        FROM `events_calendar_event_tags` src
        WHERE src.`tag_id` = ?
          AND NOT EXISTS (
              SELECT 1 FROM `events_calendar_event_tags` dst
              WHERE dst.`event_id` = src.`event_id` AND dst.`tag_id` = ?
          )
    ');
    $stPending->execute([$fromId, $toId]);
    $pendingCount = (int) $stPending->fetchColumn();

    $stList = $db->prepare('
        SELECT e.`id`, e.`event_name`, e.`event_start`, e.`event_end`, e.`event_allday`, e.`event_status`
        FROM `events_calendar_event_tags` src
        INNER JOIN `events_calendar_events` e ON e.`id` = src.`event_id`
        WHERE src.`tag_id` = ?
          AND NOT EXISTS (
              SELECT 1 FROM `events_calendar_event_tags` dst
              WHERE dst.`event_id` = src.`event_id` AND dst.`tag_id` = ?
          )
        GROUP BY e.`id`, e.`event_name`, e.`event_start`, e.`event_end`, e.`event_allday`, e.`event_status`
        ORDER BY e.`event_start` IS NULL ASC, e.`event_start` DESC, e.`id` DESC
        LIMIT ' . $listLimit . '
    ');
    $stList->execute([$fromId, $toId]);
    /** @var list<array<string,mixed>> $pendingEvents */
    $pendingEvents = $stList->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return [
        'from' => $from,
        'to' => $to,
        'source_count' => $sourceCount,
        'already_count' => $alreadyCount,
        'pending_count' => $pendingCount,
        'pending_events' => $pendingEvents,
        'pending_listed' => count($pendingEvents),
    ];
}

/**
 * Beírja a célcímkét azokra az eseményekre, ahol a forráscímke szerepel, a cél viszont még nem.
 */
function events_tag_copy_apply(PDO $db, int $fromId, int $toId): int {
    $ins = $db->prepare('
        INSERT IGNORE INTO `events_calendar_event_tags` (`event_id`, `tag_id`)
        SELECT d.`event_id`, ?
        FROM (
            SELECT DISTINCT src.`event_id` AS `event_id`
            FROM `events_calendar_event_tags` src
            WHERE src.`tag_id` = ?
              AND NOT EXISTS (
                  SELECT 1 FROM `events_calendar_event_tags` dst
                  WHERE dst.`event_id` = src.`event_id` AND dst.`tag_id` = ?
              )
        ) AS d
    ');
    $ins->execute([$toId, $fromId, $toId]);

    return $ins->rowCount();
}
