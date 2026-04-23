<?php
declare(strict_types=1);

require_once __DIR__ . '/csv_import_schema.php';
require_once __DIR__ . '/slug.php';

/**
 * @return array{headers: list<string>, rows: list<array<string,string>>}
 */
function events_csv_read_file(string $path, string $delimiter): array {
    $fh = fopen($path, 'rb');
    if ($fh === false) {
        throw new RuntimeException('A fájl nem olvasható.');
    }
    $del = match ($delimiter) {
        'tab' => "\t",
        ';' => ';',
        default => ',',
    };
    $first = fgetcsv($fh, 0, $del);
    if ($first === false || $first === [null] || $first === ['']) {
        fclose($fh);
        throw new RuntimeException('Üres vagy érvénytelen CSV (nincs fejléc sor).');
    }
    $headers = [];
    foreach ($first as $i => $h) {
        $key = trim((string) $h);
        if ($i === 0) {
            $key = preg_replace('/^\xEF\xBB\xBF/', '', $key) ?? $key;
        }
        $headers[] = $key;
    }
    $rows = [];
    while (($data = fgetcsv($fh, 0, $del)) !== false) {
        if ($data === [null] || $data === false) {
            continue;
        }
        if (count($data) === 1 && trim((string) ($data[0] ?? '')) === '') {
            continue;
        }
        $assoc = [];
        foreach ($headers as $i => $hn) {
            if ($hn === '') {
                continue;
            }
            $assoc[$hn] = isset($data[$i]) ? (string) $data[$i] : '';
        }
        if ($assoc === [] || events_csv_row_all_empty($assoc)) {
            continue;
        }
        $rows[] = $assoc;
    }
    fclose($fh);
    return ['headers' => $headers, 'rows' => $rows];
}

function events_csv_row_all_empty(array $assoc): bool {
    foreach ($assoc as $v) {
        if (trim((string) $v) !== '') {
            return false;
        }
    }
    return true;
}

/**
 * @param array<string,string> $map db_col => csv_header (üres = ne importáld)
 * @return array<string,string> csak kitöltött mapping
 */
function events_csv_filter_map(array $map): array {
    $out = [];
    foreach ($map as $dbCol => $csvHeader) {
        $csvHeader = trim((string) $csvHeader);
        if ($csvHeader !== '') {
            $out[(string) $dbCol] = $csvHeader;
        }
    }
    return $out;
}

/**
 * @param array<string,mixed> $meta
 */
function events_csv_coerce_cell(string $raw, array $meta, int $idMaxImport, string $dbCol, ?string &$err): mixed {
    $raw = trim($raw);
    $nullable = !empty($meta['nullable']);
    $type = $meta['type'] ?? 'string';

    if ($raw === '') {
        if ($nullable) {
            return null;
        }
        if ($type === 'text') {
            return '';
        }
        if ($type === 'string') {
            return '';
        }
    }

    switch ($type) {
        case 'uint':
            if ($raw === '') {
                return $nullable ? null : 0;
            }
            if (!preg_match('/^\d+$/', $raw)) {
                $err = 'Egész szám (≥0) kell: ' . $dbCol;
                return null;
            }
            $n = (int) $raw;
            if ($dbCol === 'id' && $n > $idMaxImport) {
                $err = 'Az import ID nem lehet nagyobb, mint ' . $idMaxImport . ': ' . $n;
                return null;
            }
            return $n;

        case 'bool':
            if ($raw === '') {
                return 0;
            }
            $l = mb_strtolower($raw, 'UTF-8');
            return in_array($l, ['1', 'igen', 'i', 'true', 'yes', 'y'], true) ? 1 : 0;

        case 'decimal':
            if ($raw === '') {
                return null;
            }
            return (float) str_replace(',', '.', preg_replace('/\s+/', '', $raw));

        case 'date':
            if ($raw === '') {
                return null;
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
                $err = 'Dátum YYYY-MM-DD: ' . $dbCol;
                return null;
            }
            return $raw;

        case 'time':
            if ($raw === '') {
                return null;
            }
            $t = strlen($raw) >= 8 ? substr($raw, 0, 8) : $raw;
            if (!preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $t)) {
                $err = 'Idő HH:MM vagy HH:MM:SS: ' . $dbCol;
                return null;
            }
            return strlen($t) === 5 ? $t . ':00' : $t;

        case 'datetime':
            if ($raw === '') {
                return null;
            }
            $ts = strtotime($raw);
            if ($ts === false) {
                $err = 'Érvénytelen dátum/idő: ' . $dbCol;
                return null;
            }
            return date('Y-m-d H:i:s', $ts);

        case 'enum':
            $vals = $meta['values'] ?? [];
            if ($raw === '' && $nullable) {
                return null;
            }
            if ($raw === '') {
                return $vals[0] ?? events_default_post_status();
            }
            if (!in_array($raw, $vals, true)) {
                $err = 'Érvénytelen érték (' . implode(', ', $vals) . '): ' . $dbCol;
                return null;
            }
            return $raw;

        case 'text':
            return $raw;

        case 'string':
        default:
            $max = (int) ($meta['max'] ?? 65535);
            if (mb_strlen($raw, 'UTF-8') > $max) {
                $err = 'Túl hosszú szöveg (max ' . $max . '): ' . $dbCol;
                return null;
            }
            return $raw;
    }
}

/**
 * @param array<string,string> $map db => csv header
 * @param array<string,string> $csvRow
 * @return array{0: array<string,mixed>, 1: ?string} values keyed by db col, error
 */
function events_csv_build_row_values(
    PDO $db,
    string $table,
    array $map,
    array $csvRow,
    array $tableSchema,
    bool $forUpdate,
): array {
    $idMax = (int) ($tableSchema['id_max_import'] ?? 100000);
    $colsMeta = $tableSchema['columns'];
    $values = [];

    foreach ($map as $dbCol => $csvHeader) {
        if (!isset($colsMeta[$dbCol])) {
            continue;
        }
        if ($forUpdate && $dbCol === 'id') {
            continue;
        }
        $meta = $colsMeta[$dbCol];
        $raw = array_key_exists($csvHeader, $csvRow) ? (string) $csvRow[$csvHeader] : '';
        $err = null;
        $coerced = events_csv_coerce_cell($raw, $meta, $idMax, $dbCol, $err);
        if ($err !== null) {
            return [[], $err];
        }
        if ($dbCol === 'id') {
            if ($coerced === null || (int) $coerced === 0) {
                continue;
            }
            $values['id'] = (int) $coerced;
            continue;
        }
        $values[$dbCol] = $coerced;
    }

    if ($table === 'events_calendar_events') {
        if (!$forUpdate) {
            $name = (string) ($values['event_name'] ?? '');
            if ($name === '') {
                return [[], 'event_name kötelező új sorhoz.'];
            }
            $slug = isset($values['event_slug']) ? trim((string) $values['event_slug']) : '';
            if ($slug === '') {
                $base = events_slugify($name);
                $values['event_slug'] = events_ensure_unique_slug($db, $base, null);
            } else {
                $values['event_slug'] = events_ensure_unique_slug($db, events_slugify($slug), null);
            }
            if (!array_key_exists('event_content', $values) || $values['event_content'] === null) {
                $values['event_content'] = '';
            }
            if (!array_key_exists('event_status', $values) || $values['event_status'] === null) {
                $values['event_status'] = events_default_post_status();
            }
            if (!array_key_exists('event_allday', $values)) {
                $values['event_allday'] = 0;
            }
            if (!array_key_exists('event_latinfohu_partner', $values)) {
                $values['event_latinfohu_partner'] = 0;
            }
        }
    }

    if ($table === 'events_organizers') {
        if (!$forUpdate) {
            $name = (string) ($values['name'] ?? '');
            if ($name === '') {
                return [[], 'name kötelező új sorhoz.'];
            }
        }
    }

    return [$values, null];
}

function events_csv_quote_table(string $table): string {
    return '`' . str_replace('`', '``', $table) . '`';
}

function events_csv_row_exists(PDO $db, string $table, int $id): bool {
    $st = $db->prepare('SELECT 1 FROM ' . events_csv_quote_table($table) . ' WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    return (bool) $st->fetchColumn();
}

/**
 * @param array<string,mixed> $values
 */
function events_csv_do_insert(PDO $db, string $table, array $values): void {
    $allowed = array_keys(events_csv_import_schema()[$table]['columns']);
    $pairs = [];
    foreach ($values as $k => $v) {
        if (!in_array($k, $allowed, true)) {
            continue;
        }
        if ($k === 'id' && ($v === null || $v === '')) {
            continue;
        }
        $pairs[$k] = $v;
    }
    if ($pairs === []) {
        throw new RuntimeException('Nincs beszúrandó mező.');
    }
    $cols = array_keys($pairs);
    $colSql = '`' . implode('`,`', array_map(static fn ($c) => str_replace('`', '``', $c), $cols)) . '`';
    $ph = implode(',', array_fill(0, count($cols), '?'));
    $sql = 'INSERT INTO ' . events_csv_quote_table($table) . " ({$colSql}) VALUES ({$ph})";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_values($pairs));
}

/**
 * @param array<string,mixed> $values
 */
function events_csv_do_update(PDO $db, string $table, int $id, array $values): void {
    $allowed = array_keys(events_csv_import_schema()[$table]['columns']);
    unset($values['id']);
    $sets = [];
    $params = [];
    foreach ($values as $k => $v) {
        if (!in_array($k, $allowed, true) || $k === 'id' || $k === 'created') {
            continue;
        }
        $sets[] = '`' . str_replace('`', '``', $k) . '` = ?';
        $params[] = $v;
    }
    if ($sets === []) {
        return;
    }
    $params[] = $id;
    $sql = 'UPDATE ' . events_csv_quote_table($table) . ' SET ' . implode(',', $sets) . ' WHERE id = ?';
    $db->prepare($sql)->execute($params);
}

/**
 * @param array<string,string> $map
 * @return array{inserted: int, updated: int, errors: list<string>}
 */
function events_csv_import_run(
    PDO $db,
    string $table,
    string $tmpPath,
    string $delimiter,
    string $requiredFilenameSubstring,
    string $uploadOriginalName,
    array $map,
): array {
    $schemaAll = events_csv_import_schema();
    if (!isset($schemaAll[$table])) {
        return ['inserted' => 0, 'updated' => 0, 'errors' => ['Ismeretlen tábla.']];
    }
    $tableSchema = $schemaAll[$table];
    $map = events_csv_filter_map($map);

    $basename = basename(str_replace('\\', '/', $uploadOriginalName));
    if ($requiredFilenameSubstring !== '' && mb_strpos($basename, $requiredFilenameSubstring, 0, 'UTF-8') === false) {
        return ['inserted' => 0, 'updated' => 0, 'errors' => [
            'A fájlnévnek tartalmaznia kell ezt a szövegrészletet: "' . $requiredFilenameSubstring . '". Feltöltött név: ' . $basename,
        ]];
    }

    try {
        $parsed = events_csv_read_file($tmpPath, $delimiter);
    } catch (Throwable $e) {
        return ['inserted' => 0, 'updated' => 0, 'errors' => [$e->getMessage()]];
    }

    if ($map === []) {
        return ['inserted' => 0, 'updated' => 0, 'errors' => ['Legalább egy oszlop mapping szükséges.']];
    }

    $inserted = 0;
    $updated = 0;
    $errors = [];
    $lineNo = 1;

    foreach ($parsed['rows'] as $csvRow) {
        $lineNo++;
        $idVal = null;
        if (isset($map['id'])) {
            $h = $map['id'];
            if (array_key_exists($h, $csvRow)) {
                $trim = trim((string) $csvRow[$h]);
                if ($trim !== '' && ctype_digit($trim)) {
                    $idVal = (int) $trim;
                    if ($idVal > $tableSchema['id_max_import']) {
                        $errors[] = "Sor {$lineNo}: az ID nem lehet nagyobb, mint {$tableSchema['id_max_import']} ({$idVal}).";
                        continue;
                    }
                }
            }
        }

        try {
            if ($idVal !== null && $idVal > 0) {
                $exists = events_csv_row_exists($db, $table, $idVal);
                if ($exists) {
                    [$vals, $err] = events_csv_build_row_values($db, $table, $map, $csvRow, $tableSchema, true);
                    if ($err !== null) {
                        $errors[] = "Sor {$lineNo}: {$err}";
                        continue;
                    }
                    events_csv_do_update($db, $table, $idVal, $vals);
                    $updated++;
                } else {
                    [$vals, $err] = events_csv_build_row_values($db, $table, $map, $csvRow, $tableSchema, false);
                    if ($err !== null) {
                        $errors[] = "Sor {$lineNo}: {$err}";
                        continue;
                    }
                    $vals['id'] = $idVal;
                    events_csv_do_insert($db, $table, $vals);
                    $inserted++;
                }
            } else {
                [$vals, $err] = events_csv_build_row_values($db, $table, $map, $csvRow, $tableSchema, false);
                if ($err !== null) {
                    $errors[] = "Sor {$lineNo}: {$err}";
                    continue;
                }
                events_csv_do_insert($db, $table, $vals);
                $inserted++;
            }
        } catch (Throwable $e) {
            $errors[] = 'Sor ' . $lineNo . ': ' . $e->getMessage();
        }
    }

    return ['inserted' => $inserted, 'updated' => $updated, 'errors' => $errors];
}
