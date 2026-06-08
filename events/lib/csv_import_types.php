<?php
declare(strict_types=1);

require_once __DIR__ . '/csv_import_schema.php';
require_once __DIR__ . '/import_presets.php';

/**
 * @return array<string, array{import_code: string, label: string, target_table: string, option_label: string, preset_id?: string}>
 */
function events_csv_import_types(): array {
    $types = [];
    foreach (events_csv_import_schema() as $table => $info) {
        $code = (string) ($info['import_code'] ?? '');
        $label = (string) ($info['label'] ?? $table);
        $types[$table] = [
            'import_code' => $code,
            'label' => $label,
            'target_table' => $table,
            'option_label' => events_csv_import_format_option_label($code, $label),
        ];
    }
    foreach (events_import_builtin_presets() as $presetId => $preset) {
        $code = (string) ($preset['import_code'] ?? '');
        $label = (string) ($preset['label'] ?? $presetId);
        $targetTable = (string) ($preset['target_table'] ?? '');
        if ($targetTable === '') {
            continue;
        }
        $types[$presetId] = [
            'import_code' => $code,
            'label' => $label,
            'target_table' => $targetTable,
            'option_label' => events_csv_import_format_option_label($code, $label),
            'preset_id' => $presetId,
        ];
    }

    return $types;
}

function events_csv_import_format_option_label(string $code, string $label): string {
    return $code !== '' ? $code . ' – ' . $label : $label;
}

/**
 * @return array{import_code: string, label: string, target_table: string, option_label: string, preset_id?: string}|null
 */
function events_csv_import_resolve_type(string $typeId): ?array {
    $typeId = trim($typeId);
    if ($typeId === '') {
        return null;
    }
    $types = events_csv_import_types();

    return $types[$typeId] ?? null;
}

function events_csv_import_default_required_substring(string $typeId): string {
    $type = events_csv_import_resolve_type($typeId);

    return (string) ($type['import_code'] ?? '');
}
