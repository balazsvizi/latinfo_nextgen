<?php
declare(strict_types=1);

/**
 * Esemény modul adattáblák – áttekintés és ürítés (superadmin).
 */

/**
 * @return array<string, array{label: string, group: string}>
 */
function events_data_tables_registry(): array {
    return [
        'events_calendar_events' => ['label' => 'Események', 'group' => 'Események'],
        'events_calendar_event_views' => ['label' => 'Esemény megtekintések', 'group' => 'Események'],
        'events_calendar_event_organizers' => ['label' => 'Esemény – szervező kapcsolók', 'group' => 'Kapcsolótáblák'],
        'events_calendar_event_categories' => ['label' => 'Esemény – kategória kapcsolók', 'group' => 'Kapcsolótáblák'],
        'events_calendar_event_tags' => ['label' => 'Esemény – címke kapcsolók', 'group' => 'Kapcsolótáblák'],
        'events_calendar_event_main_styles' => ['label' => 'Esemény – fő stílus kapcsolók', 'group' => 'Kapcsolótáblák'],
        'events_calendar_event_supplementary_styles' => ['label' => 'Esemény – kiegészítő stílus kapcsolók', 'group' => 'Kapcsolótáblák'],
        'events_organizers' => ['label' => 'Szervezők', 'group' => 'Törzsadatok'],
        'events_venues' => ['label' => 'Helyszínek', 'group' => 'Törzsadatok'],
        'events_categories' => ['label' => 'Kategóriák', 'group' => 'Törzsadatok'],
        'events_tags' => ['label' => 'Címkék', 'group' => 'Törzsadatok'],
        'events_tag_types' => ['label' => 'Címke típusok', 'group' => 'Törzsadatok'],
        'events_tag_type_links' => ['label' => 'Címke – típus kapcsolók', 'group' => 'Törzsadatok'],
        'events_styles' => ['label' => 'Stílusok', 'group' => 'Törzsadatok'],
        'events_import_settings' => ['label' => 'CSV import beállítások', 'group' => 'Rendszer'],
        'events_specialtags' => ['label' => 'Speciális címke csoportok (régi)', 'group' => 'Régi / opcionális'],
        'events_special_tags' => ['label' => 'Címke – speciális csoport kapcsolók (régi)', 'group' => 'Régi / opcionális'],
        'events_djs' => ['label' => 'DJ-k (régi)', 'group' => 'Régi / opcionális'],
        'events_calendar_event_djs' => ['label' => 'Esemény – DJ kapcsolók (régi)', 'group' => 'Régi / opcionális'],
    ];
}

function events_data_table_is_allowed(string $table): bool {
    return isset(events_data_tables_registry()[$table]);
}

/**
 * @return list<array{table: string, label: string, group: string, exists: bool, count: int|null}>
 */
function events_data_tables_overview(PDO $db): array {
    $out = [];
    foreach (events_data_tables_registry() as $table => $meta) {
        $exists = db_table_exists($db, $table);
        $count = null;
        if ($exists) {
            try {
                $q = events_data_quote_table($table);
                $count = (int) $db->query('SELECT COUNT(*) FROM ' . $q)->fetchColumn();
            } catch (Throwable) {
                $count = null;
            }
        }
        $out[] = [
            'table' => $table,
            'label' => $meta['label'],
            'group' => $meta['group'],
            'exists' => $exists,
            'count' => $count,
        ];
    }

    return $out;
}

function events_data_quote_table(string $table): string {
    if (!events_data_table_is_allowed($table)) {
        throw new InvalidArgumentException('Nem engedélyezett tábla: ' . $table);
    }

    return '`' . str_replace('`', '``', $table) . '`';
}

/**
 * Tábla teljes ürítése. Visszaadja a törlés előtti sorok számát.
 */
function events_data_truncate_table(PDO $db, string $table): int {
    $q = events_data_quote_table($table);
    $cnt = (int) $db->query('SELECT COUNT(*) FROM ' . $q)->fetchColumn();
    if ($cnt === 0) {
        return 0;
    }
    $db->exec('SET FOREIGN_KEY_CHECKS=0');
    try {
        $db->exec('DELETE FROM ' . $q);
    } finally {
        $db->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    return $cnt;
}
