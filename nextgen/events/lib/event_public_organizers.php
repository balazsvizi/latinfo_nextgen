<?php
declare(strict_types=1);

/**
 * Nyilvános: szervezők (megjelenit.php) és szervező eseménylistája (organizer.php).
 */

/**
 * @return list<array{id:int,name:string}>
 */
function events_public_event_organizers_for_display(PDO $db, int $eventId): array {
    $st = $db->prepare('
        SELECT o.`id`, o.`name`
        FROM `events_organizers` o
        INNER JOIN `events_calendar_event_organizers` eco ON eco.`organizer_id` = o.`id`
        WHERE eco.`event_id` = ?
        ORDER BY eco.`sort_order` ASC, o.`id` ASC
    ');
    $st->execute([$eventId]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[] = ['id' => (int) $r['id'], 'name' => (string) $r['name']];
    }

    return $out;
}

/**
 * Egy szervező összes közzétett eseménye (egy sor / esemény).
 *
 * @return list<array<string,mixed>>
 */
function events_public_organizer_published_events(PDO $db, int $organizerId, string $publishedStatus): array {
    if ($organizerId <= 0) {
        return [];
    }
    $st = $db->prepare('
        SELECT DISTINCT e.`id`, e.`event_slug`, e.`event_name`, e.`event_featured_image_url`, e.`event_start`, e.`event_end`, e.`event_allday`,
               v.`city` AS `venue_city`
        FROM `events_calendar_events` e
        INNER JOIN `events_calendar_event_organizers` eco ON eco.`event_id` = e.`id`
        LEFT JOIN `events_venues` v ON v.`id` = e.`venue_id`
        WHERE eco.`organizer_id` = ? AND e.`event_status` = ?
        ORDER BY e.`id` DESC
    ');
    $st->execute([$organizerId, $publishedStatus]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Lezajlott-e az esemény (vége múltbeli, vagy nincs vége de a kezdete múltbeli).
 *
 * @param array<string,mixed> $row
 */
function events_public_event_row_is_past(array $row, int $nowTs): bool {
    $endTs = !empty($row['event_end']) ? strtotime((string) $row['event_end']) : false;
    if ($endTs !== false) {
        return $endTs < $nowTs;
    }
    $startTs = !empty($row['event_start']) ? strtotime((string) $row['event_start']) : false;
    if ($startTs !== false) {
        return $startTs < $nowTs;
    }

    return false;
}

/**
 * @param array<string,mixed> $row
 */
function events_public_event_row_sort_start_ts(array $row): int {
    if (empty($row['event_start'])) {
        return PHP_INT_MAX;
    }
    $t = strtotime((string) $row['event_start']);

    return $t !== false ? $t : PHP_INT_MAX;
}

/**
 * Legutóbbi befejezés szerinti rendezéshez (lezajlott lista).
 *
 * @param array<string,mixed> $row
 */
function events_public_event_row_sort_end_or_start_ts(array $row): int {
    if (!empty($row['event_end'])) {
        $t = strtotime((string) $row['event_end']);

        return $t !== false ? $t : 0;
    }
    if (!empty($row['event_start'])) {
        $t = strtotime((string) $row['event_start']);

        return $t !== false ? $t : 0;
    }

    return 0;
}

/**
 * Aktuális (következő legfelül: legkorábbi kezdő) és lezajlott (legfelül: legutóbb véget ért).
 *
 * @param list<array<string,mixed>> $rows
 * @return array{upcoming: list<array<string,mixed>>, past: list<array<string,mixed>>}
 */
function events_public_organizer_partition_events(array $rows, ?int $nowTs = null): array {
    $nowTs = $nowTs ?? time();
    $upcoming = [];
    $past = [];
    foreach ($rows as $r) {
        if (events_public_event_row_is_past($r, $nowTs)) {
            $past[] = $r;
        } else {
            $upcoming[] = $r;
        }
    }
    usort($upcoming, static function (array $a, array $b): int {
        $cmp = events_public_event_row_sort_start_ts($a) <=> events_public_event_row_sort_start_ts($b);
        if ($cmp !== 0) {
            return $cmp;
        }

        return (int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0);
    });
    usort($past, static function (array $a, array $b): int {
        $cmp = events_public_event_row_sort_end_or_start_ts($b) <=> events_public_event_row_sort_end_or_start_ts($a);
        if ($cmp !== 0) {
            return $cmp;
        }

        return (int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0);
    });

    return ['upcoming' => $upcoming, 'past' => $past];
}

/**
 * Lista nézet: Ma / Hamarosan / Lezajlott (admin + publikus főoldal).
 *
 * @param list<array<string, mixed>> $rows
 * @return array{today: list<array<string, mixed>>, soon: list<array<string, mixed>>, past: list<array<string, mixed>>}
 */
function events_public_list_partition_events(array $rows, ?int $nowTs = null): array {
    require_once __DIR__ . '/admin_event_calendar.php';

    $nowTs = $nowTs ?? time();
    $todayKey = date('Y-m-d', $nowTs);
    $today = [];
    $soon = [];
    $past = [];

    foreach ($rows as $r) {
        if (events_public_event_row_is_past($r, $nowTs)) {
            $past[] = $r;
            continue;
        }
        $range = events_admin_calendar_event_date_range($r);
        if ($range !== null) {
            $startKey = $range['start']->format('Y-m-d');
            $endKey = $range['end']->format('Y-m-d');
            if ($todayKey >= $startKey && $todayKey <= $endKey) {
                $today[] = $r;
                continue;
            }
        }
        $soon[] = $r;
    }

    usort($today, static function (array $a, array $b): int {
        $cmp = events_public_event_row_sort_start_ts($a) <=> events_public_event_row_sort_start_ts($b);
        if ($cmp !== 0) {
            return $cmp;
        }

        return (int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0);
    });
    usort($soon, static function (array $a, array $b): int {
        $cmp = events_public_event_row_sort_start_ts($a) <=> events_public_event_row_sort_start_ts($b);
        if ($cmp !== 0) {
            return $cmp;
        }

        return (int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0);
    });
    usort($past, static function (array $a, array $b): int {
        $cmp = events_public_event_row_sort_end_or_start_ts($b) <=> events_public_event_row_sort_end_or_start_ts($a);
        if ($cmp !== 0) {
            return $cmp;
        }

        return (int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0);
    });

    return ['today' => $today, 'soon' => $soon, 'past' => $past];
}

/**
 * @return list<array{
 *   id: int,
 *   name: string,
 *   event_total: int,
 *   event_upcoming: int,
 *   next_event_start: ?string
 * }>
 */
function events_public_organizer_catalog(PDO $db, string $publishedStatus, ?int $listLimit = null): array {
    require_once __DIR__ . '/admin_event_filters.php';
    $poolFrom = events_admin_table_pool_from_sql('events_organizers', 'o', $listLimit);

    $st = $db->query('
        SELECT o.`id`, o.`name`
        FROM ' . $poolFrom . '
        ORDER BY o.`name` ASC, o.`id` ASC
    ');
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if ($rows === []) {
        return [];
    }

    $byId = [];
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $byId[$id] = [
            'id' => $id,
            'name' => (string) ($row['name'] ?? ''),
            'event_total' => 0,
            'event_upcoming' => 0,
            'next_event_start' => null,
        ];
    }
    if ($byId === []) {
        return [];
    }

    $orgIds = array_keys($byId);
    $ph = implode(',', array_fill(0, count($orgIds), '?'));
    $evSt = $db->prepare("
        SELECT eco.`organizer_id`, e.`id`, e.`event_start`, e.`event_end`, e.`event_allday`
        FROM `events_calendar_event_organizers` eco
        INNER JOIN `events_calendar_events` e ON e.`id` = eco.`event_id`
        WHERE eco.`organizer_id` IN ({$ph}) AND e.`event_status` = ?
    ");
    $evSt->execute(array_merge($orgIds, [$publishedStatus]));
    $nowTs = time();

    foreach ($evSt->fetchAll(PDO::FETCH_ASSOC) as $evRow) {
        $oid = (int) ($evRow['organizer_id'] ?? 0);
        if (!isset($byId[$oid])) {
            continue;
        }
        $byId[$oid]['event_total']++;
        if (events_public_event_row_is_past($evRow, $nowTs)) {
            continue;
        }
        $byId[$oid]['event_upcoming']++;
        $start = (string) ($evRow['event_start'] ?? '');
        if ($start !== '') {
            $cur = $byId[$oid]['next_event_start'];
            if ($cur === null || $start < $cur) {
                $byId[$oid]['next_event_start'] = $start;
            }
        }
    }

    return array_values($byId);
}

function events_public_organizer_total_count(PDO $db): int {
    require_once __DIR__ . '/admin_event_filters.php';

    return events_admin_table_total_count($db, 'events_organizers');
}
