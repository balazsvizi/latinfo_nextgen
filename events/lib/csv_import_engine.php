<?php
declare(strict_types=1);

require_once __DIR__ . '/csv_import_schema.php';
require_once __DIR__ . '/slug.php';
require_once __DIR__ . '/venue_request.php';

/**
 * @return array{headers: list<string>, rows: list<array<string,string>>, file_skipped: list<string>}
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
    $fileSkipped = [];
    $physicalLine = 1;
    while (($data = fgetcsv($fh, 0, $del)) !== false) {
        $physicalLine++;
        if ($data === [null] || $data === false) {
            $fileSkipped[] = "Fájl sor {$physicalLine}: kihagyva – üres CSV-sor.";
            continue;
        }
        if (count($data) === 1 && trim((string) ($data[0] ?? '')) === '') {
            $fileSkipped[] = "Fájl sor {$physicalLine}: kihagyva – egyetlen üres cella.";
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
            $fileSkipped[] = "Fájl sor {$physicalLine}: kihagyva – minden cella üres (a fejlécnek megfelelő oszlopokban nincs adat).";
            continue;
        }
        $rows[] = $assoc;
    }
    fclose($fh);
    return ['headers' => $headers, 'rows' => $rows, 'file_skipped' => $fileSkipped];
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
    ?int $slugExcludeId = null,
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

    if ($table === 'events_venues') {
        if (array_key_exists('country', $values)) {
            $c = trim((string) $values['country']);
            $values['country'] = $c === '' ? events_venue_default_country() : $c;
        } elseif (!$forUpdate) {
            $values['country'] = events_venue_default_country();
        }
        if (!$forUpdate) {
            $name = (string) ($values['name'] ?? '');
            if ($name === '') {
                return [[], 'name kötelező új sorhoz.'];
            }
            $slug = isset($values['slug']) ? trim((string) $values['slug']) : '';
            if ($slug === '') {
                $base = events_slugify($name);
                $values['slug'] = events_ensure_unique_venue_slug($db, $base, null);
            } else {
                $values['slug'] = events_ensure_unique_venue_slug($db, events_slugify($slug), null);
            }
        } else {
            if (array_key_exists('slug', $values)) {
                $slug = trim((string) $values['slug']);
                if ($slug === '') {
                    unset($values['slug']);
                } else {
                    $values['slug'] = events_ensure_unique_venue_slug($db, events_slugify($slug), $slugExcludeId);
                }
            }
        }
        if (array_key_exists('linked_venue_id', $values) && $values['linked_venue_id'] !== null) {
            $lid = (int) $values['linked_venue_id'];
            if ($lid <= 0) {
                unset($values['linked_venue_id']);
            } elseif (!events_normalize_venue_id($db, $lid)) {
                return [[], 'linked_venue_id: nem létező helyszín.'];
            } else {
                $values['linked_venue_id'] = $lid;
                if ($slugExcludeId !== null && $lid === $slugExcludeId) {
                    return [[], 'linked_venue_id: nem lehet önmaga.'];
                }
            }
        }
    }

    if ($table === 'events_calendar_event_organizers') {
        $ev = (int) ($values['event_id'] ?? 0);
        $org = (int) ($values['organizer_id'] ?? 0);
        if ($ev <= 0 || $org <= 0) {
            return [[], 'event_id és organizer_id kötelező minden sorhoz.'];
        }
        if (!array_key_exists('sort_order', $values) || $values['sort_order'] === null) {
            $values['sort_order'] = 0;
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
function events_csv_event_organizer_link_exists(PDO $db, int $eventId, int $organizerId): bool {
    $st = $db->prepare('SELECT 1 FROM `events_calendar_event_organizers` WHERE `event_id` = ? AND `organizer_id` = ? LIMIT 1');
    $st->execute([$eventId, $organizerId]);
    return (bool) $st->fetchColumn();
}

/**
 * @param array<string,mixed> $values event_id, organizer_id, sort_order
 * @return 'inserted'|'updated'
 */
function events_csv_upsert_event_organizer_link(PDO $db, array $values): string {
    $eventId = (int) ($values['event_id'] ?? 0);
    $organizerId = (int) ($values['organizer_id'] ?? 0);
    $sortOrder = (int) ($values['sort_order'] ?? 0);
    if ($eventId <= 0 || $organizerId <= 0) {
        throw new RuntimeException('event_id és organizer_id kötelező.');
    }
    $chkEv = $db->prepare('SELECT 1 FROM `events_calendar_events` WHERE `id` = ? LIMIT 1');
    $chkEv->execute([$eventId]);
    if (!$chkEv->fetchColumn()) {
        throw new RuntimeException('Nem létezik esemény ezzel az ID-val: ' . $eventId);
    }
    $chkOr = $db->prepare('SELECT 1 FROM `events_organizers` WHERE `id` = ? LIMIT 1');
    $chkOr->execute([$organizerId]);
    if (!$chkOr->fetchColumn()) {
        throw new RuntimeException('Nem létezik szervező ezzel az ID-val: ' . $organizerId);
    }
    if (events_csv_event_organizer_link_exists($db, $eventId, $organizerId)) {
        $db->prepare('UPDATE `events_calendar_event_organizers` SET `sort_order` = ? WHERE `event_id` = ? AND `organizer_id` = ?')
            ->execute([$sortOrder, $eventId, $organizerId]);
        return 'updated';
    }
    $db->prepare('INSERT INTO `events_calendar_event_organizers` (`event_id`, `organizer_id`, `sort_order`) VALUES (?,?,?)')
        ->execute([$eventId, $organizerId, $sortOrder]);
    return 'inserted';
}

/**
 * @return bool True, ha futott UPDATE; false, ha nem volt egyetlen frissítendő mező sem (kihagyás).
 */
function events_csv_do_update(PDO $db, string $table, int $id, array $values): bool {
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
        return false;
    }
    $params[] = $id;
    $sql = 'UPDATE ' . events_csv_quote_table($table) . ' SET ' . implode(',', $sets) . ' WHERE id = ?';
    $db->prepare($sql)->execute($params);
    return true;
}

/**
 * @param array<string,string> $map
 * @return array{inserted: int, updated: int, errors: list<string>, skipped: list<string>}
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
    $emptyResult = static fn (array $err, array $skip = []): array => [
        'inserted' => 0,
        'updated' => 0,
        'errors' => $err,
        'skipped' => $skip,
    ];

    $schemaAll = events_csv_import_schema();
    if (!isset($schemaAll[$table])) {
        return $emptyResult(['Ismeretlen tábla.']);
    }
    $tableSchema = $schemaAll[$table];
    $map = events_csv_filter_map($map);

    $basename = basename(str_replace('\\', '/', $uploadOriginalName));
    if ($requiredFilenameSubstring !== '' && mb_strpos($basename, $requiredFilenameSubstring, 0, 'UTF-8') === false) {
        return $emptyResult([
            'A fájlnévnek tartalmaznia kell ezt a szövegrészletet: "' . $requiredFilenameSubstring . '". Feltöltött név: ' . $basename,
        ]);
    }

    try {
        $parsed = events_csv_read_file($tmpPath, $delimiter);
    } catch (Throwable $e) {
        return $emptyResult([$e->getMessage()]);
    }

    if ($map === []) {
        return $emptyResult(['Legalább egy oszlop mapping szükséges.']);
    }

    $inserted = 0;
    $updated = 0;
    $errors = [];
    $skipped = array_merge([], $parsed['file_skipped'] ?? []);
    $lineNo = 1;

    if (!empty($tableSchema['composite_key'])) {
        foreach ($parsed['rows'] as $csvRow) {
            $lineNo++;
            [$vals, $err] = events_csv_build_row_values($db, $table, $map, $csvRow, $tableSchema, false, null);
            if ($err !== null) {
                $skipped[] = "Adatsor {$lineNo} (import): kihagyva – {$err}";
                continue;
            }
            try {
                $op = events_csv_upsert_event_organizer_link($db, $vals);
                if ($op === 'updated') {
                    $updated++;
                } else {
                    $inserted++;
                }
            } catch (Throwable $e) {
                $skipped[] = 'Adatsor ' . $lineNo . ' (import): kihagyva – ' . $e->getMessage();
            }
        }

        return ['inserted' => $inserted, 'updated' => $updated, 'errors' => $errors, 'skipped' => $skipped];
    }

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
                        $skipped[] = "Adatsor {$lineNo} (import): kihagyva – az ID nem lehet nagyobb, mint {$tableSchema['id_max_import']} (kapott érték: {$idVal}).";
                        continue;
                    }
                }
            }
        }

        try {
            if ($idVal !== null && $idVal > 0) {
                $exists = events_csv_row_exists($db, $table, $idVal);
                if ($exists) {
                    [$vals, $err] = events_csv_build_row_values($db, $table, $map, $csvRow, $tableSchema, true, $idVal);
                    if ($err !== null) {
                        $skipped[] = "Adatsor {$lineNo} (import): kihagyva – {$err}";
                        continue;
                    }
                    $didUpdate = events_csv_do_update($db, $table, $idVal, $vals);
                    if ($didUpdate) {
                        $updated++;
                    } else {
                        $skipped[] = "Adatsor {$lineNo} (import): kihagyva – létező rekord (id={$idVal}), de nincs frissítendő mező: a mapolt oszlopok CSV értékei üresek, vagy csak `created` / tiltott mezők változnának.";
                    }
                } else {
                    [$vals, $err] = events_csv_build_row_values($db, $table, $map, $csvRow, $tableSchema, false, null);
                    if ($err !== null) {
                        $skipped[] = "Adatsor {$lineNo} (import): kihagyva – {$err}";
                        continue;
                    }
                    $vals['id'] = $idVal;
                    events_csv_do_insert($db, $table, $vals);
                    $inserted++;
                }
            } else {
                [$vals, $err] = events_csv_build_row_values($db, $table, $map, $csvRow, $tableSchema, false, null);
                if ($err !== null) {
                    $skipped[] = "Adatsor {$lineNo} (import): kihagyva – {$err}";
                    continue;
                }
                events_csv_do_insert($db, $table, $vals);
                $inserted++;
            }
        } catch (Throwable $e) {
            $skipped[] = 'Adatsor ' . $lineNo . ' (import): kihagyva – ' . $e->getMessage();
        }
    }

    return ['inserted' => $inserted, 'updated' => $updated, 'errors' => $errors, 'skipped' => $skipped];
}

/**
 * Engedélyezett CSV cél tábla sorainak száma (teljes törlés előnézethez).
 */
function events_csv_import_count_rows(PDO $db, string $table): int {
    $schema = events_csv_import_schema();
    if (!isset($schema[$table])) {
        throw new InvalidArgumentException('Ismeretlen tábla: ' . $table);
    }
    $q = events_csv_quote_table($table);
    return (int) $db->query('SELECT COUNT(*) FROM ' . $q)->fetchColumn();
}

/**
 * A cél tábla összes sorának törlése (CSV import „üres tábla”). Visszaadja a törlés előtti sorok számát.
 */
function events_csv_import_delete_all_rows(PDO $db, string $table): int {
    $schema = events_csv_import_schema();
    if (!isset($schema[$table])) {
        throw new InvalidArgumentException('Ismeretlen tábla: ' . $table);
    }
    $q = events_csv_quote_table($table);
    $cnt = (int) $db->query('SELECT COUNT(*) FROM ' . $q)->fetchColumn();
    $db->exec('DELETE FROM ' . $q);
    return $cnt;
}
