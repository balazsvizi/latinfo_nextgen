<?php
declare(strict_types=1);

require_once __DIR__ . '/csv_import_schema.php';
require_once __DIR__ . '/tag_type.php';

/**
 * CSV import beépített presetek és minta fájlok.
 */

/**
 * @return array<string, array{import_code: string, label: string, target_table: string, delimiter: string, required_substring: string, map: array<string, string>}>
 */
function events_import_builtin_presets(): array {
    return [
        'esemeny-cimke-dj' => [
            'import_code' => '012',
            'label' => 'Esemény-címke-DJ (`events_calendar_event_tags`, event_id + dj_name)',
            'target_table' => 'events_calendar_event_tags',
            'delimiter' => ';',
            'required_substring' => '012',
            'default_tag_types' => ['dj'],
            'merge_tag_types_on_existing' => true,
            'map' => [
                'event_id' => 'event_id',
                'tag_name' => 'dj_name',
                'tag_types' => 'tag_types',
            ],
        ],
    ];
}

/**
 * Beépített + DB presetek (import típus kulcs; DB felülírja).
 *
 * @return array<string, array{delimiter: string, required_substring: string, map: array<string, string>}>
 */
function events_import_presets_merged(PDO $db): array {
    $merged = [];
    foreach (events_import_builtin_presets() as $presetId => $builtin) {
        $merged[$presetId] = [
            'delimiter' => (string) ($builtin['delimiter'] ?? ';'),
            'required_substring' => (string) ($builtin['required_substring'] ?? ''),
            'map' => is_array($builtin['map'] ?? null) ? $builtin['map'] : [],
        ];
    }
    foreach (events_csv_import_schema() as $table => $info) {
        $code = (string) ($info['import_code'] ?? '');
        if ($code !== '' && !isset($merged[$table])) {
            $merged[$table] = [
                'delimiter' => ';',
                'required_substring' => $code,
                'map' => [],
            ];
        }
    }
    foreach (events_import_settings_load_all($db) as $typeId => $saved) {
        $merged[$typeId] = $saved;
    }

    return $merged;
}

/**
 * @return array<string, array{filename: string, content: string, mime: string}>
 */
function events_import_sample_csv_files(): array {
    return [
        'esemeny-cimke-dj' => [
            'filename' => '012-esemény-címke-DJ.csv',
            'mime' => 'text/csv; charset=UTF-8',
            'content' => "event_id;dj_name\r\n"
                . "10001;DJ Példa\r\n"
                . "10002;DJ Másik\r\n",
        ],
    ];
}

/**
 * @return array{default_tag_types: list<string>, merge_tag_types_on_existing: bool}
 */
function events_import_type_tag_row_options(string $importTypeId): array {
    $preset = events_import_builtin_presets()[$importTypeId] ?? null;
    if (!is_array($preset)) {
        return ['default_tag_types' => [], 'merge_tag_types_on_existing' => false];
    }
    $defaults = $preset['default_tag_types'] ?? [];
    if (!is_array($defaults)) {
        $defaults = [];
    }

    return [
        'default_tag_types' => events_tag_type_normalize_codes($defaults),
        'merge_tag_types_on_existing' => !empty($preset['merge_tag_types_on_existing']),
    ];
}

/**
 * Beépített presetek telepítése DB-be (csak ha még nincs mentve az adott típushoz).
 */
function events_import_seed_builtin_presets(PDO $db): void {
    events_import_settings_ensure_table($db);
    $existing = events_import_settings_load_all($db);
    foreach (events_import_builtin_presets() as $presetId => $builtin) {
        if (isset($existing[$presetId])) {
            continue;
        }
        $map = is_array($builtin['map'] ?? null) ? $builtin['map'] : [];
        events_import_settings_save(
            $db,
            $presetId,
            (string) ($builtin['delimiter'] ?? ';'),
            (string) ($builtin['required_substring'] ?? ''),
            $map
        );
    }
}
