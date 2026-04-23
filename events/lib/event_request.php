<?php
declare(strict_types=1);

function events_load_organizer_options(PDO $db): array {
    $rows = $db->query('SELECT id, name FROM events_organizers ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $r) {
        $out[(int) $r['id']] = (string) $r['name'];
    }
    return $out;
}

/**
 * @return list<int>
 */
function events_organizer_ids_from_post(): array {
    $raw = $_POST['organizer_ids'] ?? [];
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
 * @param list<int> $organizerIds
 */
function events_save_event_organizers(PDO $db, int $eventId, array $organizerIds): void {
    $db->prepare('DELETE FROM `events_calendar_event_organizers` WHERE `event_id` = ?')->execute([$eventId]);
    if ($organizerIds === []) {
        return;
    }
    $ins = $db->prepare('INSERT INTO `events_calendar_event_organizers` (`event_id`, `organizer_id`, `sort_order`) VALUES (?,?,?)');
    $ord = 0;
    foreach ($organizerIds as $oid) {
        if ($oid <= 0) {
            continue;
        }
        $ins->execute([$eventId, $oid, $ord]);
        $ord++;
    }
}

/**
 * @return list<int>
 */
function events_load_event_organizer_ids(PDO $db, int $eventId): array {
    $st = $db->prepare('
        SELECT `organizer_id` FROM `events_calendar_event_organizers`
        WHERE `event_id` = ?
        ORDER BY `sort_order` ASC, `organizer_id` ASC
    ');
    $st->execute([$eventId]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN, 0));
}

/**
 * Űrlap → adatbázis mezők + slug egyediség.
 *
 * @param array<string,mixed> $defaults Alapértelmezett értékek (pl. DB sor szerkesztésnél)
 * @return array{0: array<string,mixed>, 1: ?string, 2: list<int>} [row, hibaüzenet vagy null, szervező ID-k]
 */
function events_row_from_request(PDO $db, array $defaults, ?int $excludeIdForSlug): array {
    $row = $defaults;
    $row['event_name'] = trim((string) ($_POST['event_name'] ?? ''));
    $slugInput = trim((string) ($_POST['event_slug'] ?? ''));
    $row['event_slug'] = $slugInput !== '' ? events_slugify($slugInput) : '';
    $row['event_content'] = (string) ($_POST['event_content'] ?? '');
    $st = (string) ($_POST['event_status'] ?? events_default_post_status());
    if (!events_is_allowed_post_status($st)) {
        $st = events_default_post_status();
    }
    $row['event_status'] = $st;

    $sd = trim((string) ($_POST['event_start_date'] ?? ''));
    $stt = trim((string) ($_POST['event_start_time'] ?? ''));
    $ed = trim((string) ($_POST['event_end_date'] ?? ''));
    $ett = trim((string) ($_POST['event_end_time'] ?? ''));
    $row['event_start'] = events_build_event_datetime($sd, $stt);
    $row['event_end'] = events_build_event_datetime($ed, $ett);
    $row['event_allday'] = isset($_POST['event_allday']) ? 1 : 0;

    $cf = trim((string) ($_POST['event_cost_from'] ?? ''));
    $ct = trim((string) ($_POST['event_cost_to'] ?? ''));
    $row['event_cost_from'] = $cf === '' ? null : (float) str_replace(',', '.', $cf);
    $row['event_cost_to'] = $ct === '' ? null : (float) str_replace(',', '.', $ct);
    $row['event_url'] = trim((string) ($_POST['event_url'] ?? ''));
    if ($row['event_url'] === '') {
        $row['event_url'] = null;
    }
    $row['event_latinfohu_partner'] = isset($_POST['event_latinfohu_partner']) ? 1 : 0;

    $vid = trim((string) ($_POST['venue_id'] ?? ''));
    $row['venue_id'] = $vid === '' ? null : (int) $vid;

    $organizerIds = events_organizer_ids_from_post();

    if ($row['event_name'] === '') {
        return [$row, 'Az esemény neve kötelező.', $organizerIds];
    }

    $baseSlug = $row['event_slug'] !== '' ? $row['event_slug'] : events_slugify($row['event_name']);
    $row['event_slug'] = events_ensure_unique_slug($db, $baseSlug, $excludeIdForSlug);

    return [$row, null, $organizerIds];
}

/**
 * @param array<string,mixed> $row
 */
function events_row_for_form(array $row): array {
    $e = $row;
    foreach (['event_start', 'event_end', 'event_url'] as $k) {
        $e[$k] = $e[$k] !== null ? (string) $e[$k] : '';
    }
    [$e['event_start_date'], $e['event_start_time']] = events_split_event_datetime($e['event_start']);
    [$e['event_end_date'], $e['event_end_time']] = events_split_event_datetime($e['event_end']);
    foreach (['event_start_time', 'event_end_time'] as $tk) {
        if ($e[$tk] !== '' && strlen($e[$tk]) > 5) {
            $e[$tk] = substr($e[$tk], 0, 5);
        }
    }
    foreach (['event_cost_from', 'event_cost_to'] as $k) {
        if ($e[$k] === null) {
            $e[$k] = '';
        } else {
            $e[$k] = is_float($e[$k]) ? (string) $e[$k] : (string) $e[$k];
        }
    }
    $e['venue_id'] = isset($e['venue_id']) && $e['venue_id'] !== null ? (string) (int) $e['venue_id'] : '';
    if (!isset($e['organizer_ids']) || !is_array($e['organizer_ids'])) {
        $e['organizer_ids'] = [];
    } else {
        $e['organizer_ids'] = array_values(array_unique(array_map('intval', $e['organizer_ids'])));
    }
    $e['event_allday'] = !empty($e['event_allday']);
    $e['event_latinfohu_partner'] = !empty($e['event_latinfohu_partner']);
    $st = (string) ($e['event_status'] ?? '');
    if (!events_is_allowed_post_status($st)) {
        $e['event_status'] = events_default_post_status();
    }
    return $e;
}

function events_build_event_datetime(string $date, string $time): ?string {
    if ($date === '') {
        return null;
    }
    $tm = $time !== '' ? $time : '00:00';
    $dt = date_create($date . ' ' . $tm);
    return $dt ? $dt->format('Y-m-d H:i:s') : null;
}

/**
 * @return array{0: string, 1: string}
 */
function events_split_event_datetime(?string $datetime): array {
    if ($datetime === null || $datetime === '') {
        return ['', ''];
    }
    $dt = date_create($datetime);
    if (!$dt) {
        return ['', ''];
    }
    return [$dt->format('Y-m-d'), $dt->format('H:i')];
}
