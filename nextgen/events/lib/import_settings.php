<?php
declare(strict_types=1);

/**
 * CSV import mentett beállítások (cél tábla = egyedi kulcs).
 */
function events_import_settings_ensure_table(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS `events_import_settings` (
        `target_table` VARCHAR(64) NOT NULL,
        `delimiter` VARCHAR(8) NOT NULL DEFAULT ';',
        `required_substring` VARCHAR(500) NOT NULL DEFAULT '',
        `column_map` JSON NOT NULL,
        `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`target_table`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/**
 * @return array<string, array{delimiter: string, required_substring: string, map: array<string, string>}>
 */
function events_import_settings_load_all(PDO $db): array {
    try {
        events_import_settings_ensure_table($db);
    } catch (Throwable $e) {
        return [];
    }
    try {
        $rows = $db->query('SELECT `target_table`, `delimiter`, `required_substring`, `column_map` FROM `events_import_settings`')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
    $out = [];
    foreach ($rows as $r) {
        $tbl = (string) ($r['target_table'] ?? '');
        if ($tbl === '') {
            continue;
        }
        $map = json_decode((string) ($r['column_map'] ?? '{}'), true);
        if (!is_array($map)) {
            $map = [];
        }
        $clean = [];
        foreach ($map as $k => $v) {
            $clean[(string) $k] = (string) $v;
        }
        $out[$tbl] = [
            'delimiter' => in_array($r['delimiter'] ?? ';', [',', ';', 'tab'], true) ? (string) $r['delimiter'] : ';',
            'required_substring' => (string) ($r['required_substring'] ?? ''),
            'map' => $clean,
        ];
    }
    return $out;
}

/**
 * @param array<string, string> $columnMap DB oszlop => CSV fejléc név
 */
function events_import_settings_save(PDO $db, string $targetTable, string $delimiter, string $requiredSubstring, array $columnMap): void {
    events_import_settings_ensure_table($db);
    if (!in_array($delimiter, [',', ';', 'tab'], true)) {
        $delimiter = ';';
    }
    $json = json_encode($columnMap, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('JSON kódolási hiba.');
    }
    $stmt = $db->prepare('INSERT INTO `events_import_settings` (`target_table`, `delimiter`, `required_substring`, `column_map`)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE `delimiter` = VALUES(`delimiter`), `required_substring` = VALUES(`required_substring`), `column_map` = VALUES(`column_map`)');
    $stmt->execute([$targetTable, $delimiter, $requiredSubstring, $json]);
}
