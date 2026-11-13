<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_event_filters.php';
require_once __DIR__ . '/event_change.php';

/**
 * Esemény naplóbejegyzés részletei: státusz és legfontosabb mezők mentéskor.
 *
 * @param array<string, mixed> $row events_row_from_request eredmény
 * @param list<int> $organizerIds
 * @param list<int> $categoryIds
 */
function events_build_log_details(
    PDO $db,
    array $row,
    array $organizerIds,
    array $categoryIds
): string {
    $lines = [];

    $name = trim((string) ($row['event_name'] ?? ''));
    if ($name !== '') {
        $lines[] = 'Név: ' . $name;
    }

    $status = (string) ($row['event_status'] ?? '');
    if ($status !== '') {
        $lines[] = 'Státusz: ' . events_post_status_label($status);
    }

    $lines[] = 'Szervező szerkesztheti: ' . (!empty($row['organizer_can_edit']) ? 'igen' : 'nem');

    $when = events_admin_format_datum_cell($row);
    if ($when !== '–') {
        $lines[] = 'Időpont: ' . $when;
    }

    $venueId = isset($row['venue_id']) && $row['venue_id'] !== null ? (int) $row['venue_id'] : 0;
    if ($venueId > 0) {
        $stVenue = $db->prepare('SELECT `name` FROM `events_venues` WHERE `id` = ? LIMIT 1');
        $stVenue->execute([$venueId]);
        $venueName = $stVenue->fetchColumn();
        if ($venueName !== false && (string) $venueName !== '') {
            $lines[] = 'Helyszín: ' . (string) $venueName;
        }
    }

    $organizers = events_log_names_for_ids($db, 'events_organizers', $organizerIds);
    if ($organizers !== '') {
        $lines[] = 'Szervezők: ' . $organizers;
    }

    $categories = events_log_names_for_ids($db, 'events_categories', $categoryIds);
    if ($categories !== '') {
        $lines[] = 'Kategóriák: ' . $categories;
    }

    if (!empty($row['event_change_active'])) {
        $changeLabel = events_event_change_type_label(
            isset($row['event_change_type']) ? (string) $row['event_change_type'] : null
        );
        $lines[] = 'Változás jelzés: ' . $changeLabel;
        $changeNote = trim((string) ($row['event_change_note'] ?? ''));
        if ($changeNote !== '') {
            $lines[] = 'Változás szövege: ' . $changeNote;
        }
    }

    $costLine = events_log_format_cost_line($row);
    if ($costLine !== '') {
        $lines[] = $costLine;
    }

    $slug = trim((string) ($row['event_slug'] ?? ''));
    if ($slug !== '') {
        $lines[] = 'Slug: ' . $slug;
    }

    return implode("\n", $lines);
}

/**
 * @param list<int> $ids
 */
function events_log_names_for_ids(PDO $db, string $table, array $ids): string {
    if (!in_array($table, ['events_organizers', 'events_categories', 'events_tags', 'events_styles'], true)) {
        return '';
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0)));
    if ($ids === []) {
        return '';
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $st = $db->prepare("SELECT `name` FROM `{$table}` WHERE `id` IN ({$ph}) ORDER BY `name` ASC");
    $st->execute($ids);
    $names = array_filter(array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN, 0)));

    return implode(', ', $names);
}

/**
 * @param array<string, mixed> $row
 */
function events_log_format_cost_line(array $row): string {
    $from = $row['event_cost_from'] ?? null;
    $to = $row['event_cost_to'] ?? null;
    $hasFrom = $from !== null && $from !== '' && (float) $from > 0;
    $hasTo = $to !== null && $to !== '' && (float) $to > 0;
    if (!$hasFrom && !$hasTo) {
        return '';
    }
    $fmt = static function ($v): string {
        $n = (float) $v;
        if (abs($n - round($n)) < 0.001) {
            return (string) (int) round($n);
        }

        return rtrim(rtrim(number_format($n, 2, ',', ' '), '0'), ',');
    };
    if ($hasFrom && $hasTo) {
        return 'Belépő: ' . $fmt($from) . '–' . $fmt($to) . ' Ft';
    }
    if ($hasFrom) {
        return 'Belépő: ' . $fmt($from) . ' Ft-tól';
    }

    return 'Belépő: ' . $fmt($to) . ' Ft-ig';
}

/**
 * Partner mentés naplója: csak a megváltozott mezők. A státusz külön bejegyzés.
 *
 * @param array<string, mixed> $before
 * @param array<string, mixed> $after
 * @return list<string>
 */
function events_partner_change_log_lines(PDO $db, array $before, array $after): array
{
    $lines = [];
    $push = static function (string $label, string $old, string $new) use (&$lines): void {
        if ($old === $new) {
            return;
        }
        $lines[] = $label . ': ' . ($old !== '' ? $old : '—') . ' → ' . ($new !== '' ? $new : '—');
    };

    $push('Név', trim((string) ($before['event_name'] ?? '')), trim((string) ($after['event_name'] ?? '')));
    $push('Slug', trim((string) ($before['event_slug'] ?? '')), trim((string) ($after['event_slug'] ?? '')));

    $beforeContent = events_sanitize_html_fragment((string) ($before['event_content'] ?? ''));
    $afterContent = trim((string) ($after['event_content'] ?? ''));
    if ($beforeContent !== $afterContent) {
        $lines[] = 'Leírás módosult';
    }

    $beforeWhen = events_log_norm_datetime($before['event_start'] ?? null) . '|'
        . events_log_norm_datetime($before['event_end'] ?? null) . '|'
        . (!empty($before['event_allday']) ? '1' : '0');
    $afterWhen = events_log_norm_datetime($after['event_start'] ?? null) . '|'
        . events_log_norm_datetime($after['event_end'] ?? null) . '|'
        . (!empty($after['event_allday']) ? '1' : '0');
    if ($beforeWhen !== $afterWhen) {
        $push('Időpont', events_admin_format_datum_cell($before), events_admin_format_datum_cell($after));
    }

    $push('Helyszín', events_log_venue_name($db, $before['venue_id'] ?? null), events_log_venue_name($db, $after['venue_id'] ?? null));
    $push('További információ', trim((string) ($before['event_url'] ?? '')), trim((string) ($after['event_url'] ?? '')));
    $push(
        'Borítókép',
        events_log_short(trim((string) ($before['event_featured_image_url'] ?? ''))),
        events_log_short(trim((string) ($after['event_featured_image_url'] ?? '')))
    );
    $push('Belépő', events_log_format_cost_line($before), events_log_format_cost_line($after));
    $push('Változás / elmaradás', events_log_change_summary($before), events_log_change_summary($after));
    $push('Kategóriák', events_log_names_for_ids($db, 'events_categories', events_log_id_list($before['category_ids'] ?? [])), events_log_names_for_ids($db, 'events_categories', events_log_id_list($after['category_ids'] ?? [])));
    $push('DJ-k/Előadók/Címkék', events_log_names_for_ids($db, 'events_tags', events_log_id_list($before['tag_ids'] ?? [])), events_log_names_for_ids($db, 'events_tags', events_log_id_list($after['tag_ids'] ?? [])));
    $push('Fő stílusok', events_log_names_for_ids($db, 'events_styles', events_log_id_list($before['main_style_ids'] ?? [])), events_log_names_for_ids($db, 'events_styles', events_log_id_list($after['main_style_ids'] ?? [])));
    $push('Kiegészítő stílusok', events_log_names_for_ids($db, 'events_styles', events_log_id_list($before['supplementary_style_ids'] ?? [])), events_log_names_for_ids($db, 'events_styles', events_log_id_list($after['supplementary_style_ids'] ?? [])));

    return $lines;
}

function events_log_norm_datetime(mixed $value): string
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return '';
    }
    $ts = strtotime($raw);

    return $ts === false ? $raw : date('Y-m-d H:i:s', $ts);
}

function events_log_venue_name(PDO $db, mixed $venueId): string
{
    $id = (int) $venueId;
    if ($id <= 0) {
        return '';
    }
    $stmt = $db->prepare('SELECT `name` FROM `events_venues` WHERE `id` = ? LIMIT 1');
    $stmt->execute([$id]);
    $name = $stmt->fetchColumn();

    return $name === false ? '' : trim((string) $name);
}

function events_log_short(string $value): string
{
    if ($value === '') {
        return '';
    }
    if (mb_strlen($value, 'UTF-8') > 80) {
        return mb_substr($value, 0, 77, 'UTF-8') . '…';
    }

    return $value;
}

/**
 * @param array<string, mixed> $row
 */
function events_log_change_summary(array $row): string
{
    if (empty($row['event_change_active'])) {
        return '';
    }
    $label = events_event_change_type_label(
        isset($row['event_change_type']) ? (string) $row['event_change_type'] : null
    );
    $note = trim((string) ($row['event_change_note'] ?? ''));

    return $note !== '' ? $label . ' (' . $note . ')' : $label;
}

/**
 * @return list<int>
 */
function events_log_id_list(mixed $ids): array
{
    if (!is_array($ids)) {
        return [];
    }
    $out = [];
    foreach ($ids as $id) {
        $value = (int) $id;
        if ($value > 0) {
            $out[] = $value;
        }
    }
    $out = array_values(array_unique($out));
    sort($out);

    return $out;
}

/**
 * Napló első sora: dátum | partner vagy admin neve.
 *
 * @param array<string, mixed> $row
 */
function events_log_actor_headline(array $row): string
{
    $date = trim((string) ($row['létrehozva'] ?? ''));
    $name = '';
    $details = (string) ($row['részletek'] ?? '');
    if (preg_match('/^Partner:\s*(.+?)\s*\(#\d+\)\s*$/mu', $details, $match) === 1) {
        $name = trim((string) ($match[1] ?? ''));
    }
    if ($name === '') {
        $name = trim((string) ($row['admin_név'] ?? ''));
    }
    if ($date === '') {
        return $name;
    }
    if ($name === '') {
        return $date;
    }

    return $date . ' | ' . $name;
}

/**
 * A partner/admin fejlécsorok nélkül, a tényleges naplószöveg.
 *
 * @return list<string>
 */
function events_log_visible_detail_lines(string $details): array
{
    $lines = [];
    foreach (preg_split("/\r\n|\n|\r/", $details) ?: [] as $line) {
        $line = trim((string) $line);
        if ($line === '' || $line === 'Partnerportál' || $line === 'Admin nézet' || str_starts_with($line, 'Partner:')) {
            continue;
        }
        $lines[] = $line;
    }

    return $lines;
}
