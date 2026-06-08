<?php
declare(strict_types=1);

/**
 * CSV import beépített presetek és minta fájlok.
 */

/**
 * @return array<string, array{label: string, target_table: string, delimiter: string, required_substring: string, map: array<string, string>}>
 */
function events_import_builtin_presets(): array {
    return [
        'esemeny-cimke-dj' => [
            'label' => 'Esemény – címke (DJ)',
            'target_table' => 'events_calendar_event_tags',
            'delimiter' => ';',
            'required_substring' => 'esemény-címke-DJ',
            'map' => [
                'event_id' => 'event_id',
                'tag_name' => 'dj_name',
                'tag_types' => 'tag_types',
            ],
        ],
    ];
}

/**
 * Beépített + DB presetek (tábla kulcs; DB felülírja az alapértelmezettet).
 *
 * @return array<string, array{delimiter: string, required_substring: string, map: array<string, string>}>
 */
function events_import_presets_merged(PDO $db): array {
    $merged = [];
    foreach (events_import_builtin_presets() as $builtin) {
        $tbl = (string) ($builtin['target_table'] ?? '');
        if ($tbl === '') {
            continue;
        }
        $merged[$tbl] = [
            'delimiter' => (string) ($builtin['delimiter'] ?? ';'),
            'required_substring' => (string) ($builtin['required_substring'] ?? ''),
            'map' => is_array($builtin['map'] ?? null) ? $builtin['map'] : [],
        ];
    }
    foreach (events_import_settings_load_all($db) as $tbl => $saved) {
        $merged[$tbl] = $saved;
    }

    return $merged;
}

/**
 * @return array<string, array{filename: string, content: string, mime: string}>
 */
function events_import_sample_csv_files(): array {
    return [
        'esemeny-cimke-dj' => [
            'filename' => 'esemény-címke-DJ.csv',
            'mime' => 'text/csv; charset=UTF-8',
            'content' => "event_id;dj_name;tag_types\r\n"
                . "10001;DJ Példa;DJ\r\n"
                . "10002;DJ Másik;DJ\r\n",
        ],
    ];
}

/**
 * Beépített presetek telepítése DB-be (csak ha még nincs mentve az adott táblához).
 */
function events_import_seed_builtin_presets(PDO $db): void {
    events_import_settings_ensure_table($db);
    $existing = events_import_settings_load_all($db);
    foreach (events_import_builtin_presets() as $builtin) {
        $tbl = (string) ($builtin['target_table'] ?? '');
        if ($tbl === '' || isset($existing[$tbl])) {
            continue;
        }
        $map = is_array($builtin['map'] ?? null) ? $builtin['map'] : [];
        events_import_settings_save(
            $db,
            $tbl,
            (string) ($builtin['delimiter'] ?? ';'),
            (string) ($builtin['required_substring'] ?? ''),
            $map
        );
    }
}
