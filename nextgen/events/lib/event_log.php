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
    if ($table !== 'events_organizers' && $table !== 'events_categories') {
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
