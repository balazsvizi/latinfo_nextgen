<?php
declare(strict_types=1);

require_once __DIR__ . '/csv_import_schema.php';
require_once __DIR__ . '/slug.php';
require_once __DIR__ . '/venue_request.php';
require_once __DIR__ . '/event_request.php';
require_once __DIR__ . '/style_request.php';
require_once __DIR__ . '/tag_type.php';
require_once __DIR__ . '/import_presets.php';
require_once dirname(__DIR__) . '/bootstrap.php';

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
        $key = preg_replace('/^\xEF\xBB\xBF/', '', $key) ?? $key;
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
/**
 * Virtuális category_ids CSV cella feldolgozása → egyedi pozitív ID-k sorrend szerint.
 *
 * @return array{0: list<int>, 1: ?string}
 */
function events_csv_parse_category_id_list_cell(string $raw): array {
    $raw = trim($raw);
    if ($raw === '') {
        return [[], null];
    }
    $parts = preg_split('/[,;|]+/u', $raw) ?: [];
    $ids = [];
    foreach ($parts as $p) {
        $t = trim((string) $p);
        if ($t === '') {
            continue;
        }
        if (!preg_match('/^\d+$/', $t)) {
            return [[], 'Érvénytelen kategória ID (nem természetes szám): ' . $t];
        }
        $n = (int) $t;
        if ($n <= 0) {
            return [[], 'Érvénytelen kategória ID (≥1 kell): ' . $t];
        }
        $ids[] = $n;
    }
    $seen = [];
    $uniq = [];
    foreach ($ids as $id) {
        if (isset($seen[$id])) {
            continue;
        }
        $seen[$id] = true;
        $uniq[] = $id;
    }

    return [$uniq, null];
}

/**
 * Ellenőrzi, hogy minden ID létezik-e az events_categories táblában.
 *
 * @param list<int> $ids
 * @return ?string Hiba szöveg, vagy null
 */
function events_csv_validate_existing_category_ids(PDO $db, array $ids): ?string {
    if ($ids === []) {
        return null;
    }
    $ids = array_values(array_unique(array_map('intval', $ids)));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $st = $db->prepare("SELECT COUNT(*) FROM `events_categories` WHERE `id` IN ({$placeholders})");
    $st->execute($ids);
    $cnt = (int) $st->fetchColumn();

    return $cnt === count($ids) ? null : 'Egy vagy több megadott kategória ID nem létezik (events_categories).';
}

/**
 * @param list<int> $categoryIds
 */
function events_csv_replace_event_categories(PDO $db, int $eventId, array $categoryIds): void {
    $db->prepare('DELETE FROM `events_calendar_event_categories` WHERE `event_id` = ?')->execute([$eventId]);
    if ($categoryIds === []) {
        return;
    }
    $ins = $db->prepare('INSERT INTO `events_calendar_event_categories` (`event_id`, `category_id`) VALUES (?,?)');
    foreach ($categoryIds as $cid) {
        $ins->execute([$eventId, (int) $cid]);
    }
}

/**
 * Kiszedi a virtuálisan importált kategória-ID listát. null = nem mapolt category_ids → kapcsoló nem változik.
 *
 * @return list<int>|null
 */
function events_csv_take_category_sync_payload(array &$values): ?array {
    if (!array_key_exists('_category_sync_ids', $values)) {
        return null;
    }
    $ids = $values['_category_sync_ids'];
    unset($values['_category_sync_ids']);

    return is_array($ids) ? array_values(array_map('intval', $ids)) : [];
}

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
        $colMetaRow = $colsMeta[$dbCol];
        if (!empty($colMetaRow['virtual'])) {
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
        if (array_key_exists('event_content', $values) && $values['event_content'] !== null) {
            $values['event_content'] = events_sanitize_html_fragment((string) $values['event_content']);
        }
        if (array_key_exists('event_url', $values)) {
            [$safeUrl, $urlErr] = events_normalize_safe_url((string) ($values['event_url'] ?? ''), true);
            if ($urlErr !== null) {
                return [[], $urlErr . ' (event_url)'];
            }
            $values['event_url'] = $safeUrl;
        }
        if (array_key_exists('event_featured_image_url', $values)) {
            $feat = $values['event_featured_image_url'];
            if ($feat === null || $feat === '') {
                $values['event_featured_image_url'] = null;
            } else {
                [$featNorm, $featErr] = events_normalize_featured_image_url((string) $feat);
                if ($featErr !== null) {
                    return [[], $featErr . ' (event_featured_image_url)'];
                }
                $values['event_featured_image_url'] = $featNorm;
            }
        }
        // Virtuális category_ids → kapcsolótábla (nem kerül INSERT/UPDATE mezőlistába).
        if (isset($map['category_ids'])) {
            $hCat = $map['category_ids'];
            $catRaw = array_key_exists($hCat, $csvRow) ? (string) $csvRow[$hCat] : '';
            [$catIds, $catErr] = events_csv_parse_category_id_list_cell($catRaw);
            if ($catErr !== null) {
                return [[], $catErr . ' (category_ids)'];
            }
            $exErr = events_csv_validate_existing_category_ids($db, $catIds);
            if ($exErr !== null) {
                return [[], $exErr . ' (category_ids)'];
            }
            $values['_category_sync_ids'] = $catIds;
        }

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
        if (array_key_exists('description', $values) && $values['description'] !== null) {
            $values['description'] = events_sanitize_html_fragment((string) $values['description']);
        }
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

    if ($table === 'events_categories') {
        if (array_key_exists('color', $values)) {
            $colorRaw = trim((string) $values['color']);
            if ($colorRaw === '') {
                $values['color'] = '#6D8F63';
            } else {
                $color = strtoupper($colorRaw);
                if (!preg_match('/^#[0-9A-F]{6}$/', $color)) {
                    return [[], 'color: formátum #RRGGBB legyen.'];
                }
                $values['color'] = $color;
            }
        } elseif (!$forUpdate) {
            $values['color'] = '#6D8F63';
        }
        if (array_key_exists('parent_id', $values) && $values['parent_id'] !== null) {
            $pid = (int) $values['parent_id'];
            if ($pid <= 0) {
                $values['parent_id'] = null;
            } else {
                $stParent = $db->prepare('SELECT 1 FROM `events_categories` WHERE `id` = ? LIMIT 1');
                $stParent->execute([$pid]);
                if (!$stParent->fetchColumn()) {
                    return [[], 'parent_id: nem létező kategória.'];
                }
                if ($slugExcludeId !== null && $pid === $slugExcludeId) {
                    return [[], 'parent_id: nem lehet önmaga.'];
                }
                $values['parent_id'] = $pid;
            }
        }
        if (!array_key_exists('sort_order', $values) || $values['sort_order'] === null) {
            $values['sort_order'] = 0;
        }
        if (events_categories_name_en_available($db)) {
            if (array_key_exists('name_en', $values) && $values['name_en'] !== null) {
                $ne = trim((string) $values['name_en']);
                if (strlen($ne) > 255) {
                    return [[], 'name_en legfeljebb 255 karakter lehet.'];
                }
                $values['name_en'] = $ne;
            } elseif (!$forUpdate) {
                $values['name_en'] = '';
            }
        } else {
            unset($values['name_en']);
        }
        if (!$forUpdate) {
            $name = trim((string) ($values['name'] ?? ''));
            if ($name === '') {
                return [[], 'name kötelező új sorhoz.'];
            }
            $values['name'] = $name;
        }
    }

    if ($table === 'events_tags') {
        if (!events_tags_tables_available($db)) {
            return [[], 'Az events_tags tábla nem elérhető (migration_tags.sql).'];
        }
        if (!$forUpdate) {
            $name = trim((string) ($values['name'] ?? ''));
            if ($name === '') {
                return [[], 'name kötelező minden új címke sorhoz.'];
            }
            $values['name'] = $name;
        } else {
            if (array_key_exists('name', $values)) {
                $name = trim((string) $values['name']);
                if ($name === '') {
                    return [[], 'name nem lehet üres (frissítés).'];
                }
                $values['name'] = $name;
            }
        }
        $tagTypesRaw = '';
        if (isset($map['tag_types'])) {
            $hTypes = $map['tag_types'];
            $tagTypesRaw = trim(array_key_exists($hTypes, $csvRow) ? (string) $csvRow[$hTypes] : '');
            if (strlen($tagTypesRaw) > 255) {
                return [[], 'tag_types legfeljebb 255 karakter lehet.'];
            }
        }
        if ($tagTypesRaw !== '') {
            $parsedTypes = events_tag_type_parse_csv_string($tagTypesRaw);
            if ($parsedTypes === []) {
                return [[], 'Érvénytelen tag_types érték (DJ, Zenekar, Tanár, Művész, Szervező).'];
            }
            $values['_tag_types'] = $parsedTypes;
        }
    }

    if ($table === 'events_styles') {
        if (!events_styles_tables_available($db)) {
            return [[], 'Az events_styles tábla nem elérhető (migration_styles.sql).'];
        }
        if (!$forUpdate) {
            $name = trim((string) ($values['name'] ?? ''));
            if ($name === '') {
                return [[], 'name kötelező minden új stílus sorhoz.'];
            }
            $values['name'] = $name;
        } else {
            if (array_key_exists('name', $values)) {
                $name = trim((string) $values['name']);
                if ($name === '') {
                    return [[], 'name nem lehet üres (frissítés).'];
                }
                $values['name'] = $name;
            }
        }
    }

    if ($table === 'events_calendar_event_main_styles' || $table === 'events_calendar_event_supplementary_styles') {
        if (!events_styles_tables_available($db)) {
            return [[], 'Az events_styles / stílus kapcsoló táblák nem elérhetők (migration_styles.sql).'];
        }
        $ev = (int) ($values['event_id'] ?? 0);
        if ($ev <= 0) {
            return [[], 'event_id kötelező minden sorhoz.'];
        }
        $styleId = (int) ($values['style_id'] ?? 0);
        $styleName = '';
        if (isset($map['style_name'])) {
            $hStyle = $map['style_name'];
            $styleName = trim(array_key_exists($hStyle, $csvRow) ? (string) $csvRow[$hStyle] : '');
            if (strlen($styleName) > 255) {
                return [[], 'style_name legfeljebb 255 karakter lehet.'];
            }
        }
        if ($styleId <= 0 && $styleName === '') {
            return [[], 'style_id vagy style_name kötelező minden sorhoz.'];
        }
        if ($styleName !== '') {
            $values['_style_name'] = $styleName;
        }
    }

    if ($table === 'events_calendar_event_tags') {
        if (!events_tags_tables_available($db)) {
            return [[], 'Az events_tags / events_calendar_event_tags táblák nem elérhetők (migration_tags.sql).'];
        }
        $ev = (int) ($values['event_id'] ?? 0);
        if ($ev <= 0) {
            return [[], 'event_id kötelező minden sorhoz.'];
        }
        $tagId = (int) ($values['tag_id'] ?? 0);
        $tagName = '';
        if (isset($map['tag_name'])) {
            $hTag = $map['tag_name'];
            $tagName = trim(array_key_exists($hTag, $csvRow) ? (string) $csvRow[$hTag] : '');
            if (strlen($tagName) > 255) {
                return [[], 'tag_name legfeljebb 255 karakter lehet.'];
            }
        }
        if ($tagId <= 0 && $tagName === '') {
            return [[], 'tag_id vagy tag_name kötelező minden sorhoz.'];
        }
        if ($tagName !== '') {
            $values['_tag_name'] = $tagName;
        }
        $tagTypesRaw = '';
        if (isset($map['tag_types'])) {
            $hTypes = $map['tag_types'];
            $tagTypesRaw = trim(array_key_exists($hTypes, $csvRow) ? (string) $csvRow[$hTypes] : '');
            if (strlen($tagTypesRaw) > 255) {
                return [[], 'tag_types legfeljebb 255 karakter lehet.'];
            }
        }
        if ($tagTypesRaw !== '') {
            $parsedTypes = events_tag_type_parse_csv_string($tagTypesRaw);
            if ($parsedTypes === []) {
                return [[], 'Érvénytelen tag_types érték (DJ, Zenekar, Tanár, Művész, Szervező).'];
            }
            $values['_tag_types'] = $parsedTypes;
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
    if ($table === 'events_calendar_event_categories') {
        $ev = (int) ($values['event_id'] ?? 0);
        $cat = (int) ($values['category_id'] ?? 0);
        if ($ev <= 0 || $cat <= 0) {
            return [[], 'event_id és category_id kötelező minden sorhoz.'];
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
function events_csv_do_insert(PDO $db, string $table, array $values): int {
    $allowed = array_keys(events_csv_import_schema()[$table]['columns']);
    $pairs = [];
    foreach ($values as $k => $v) {
        if (str_starts_with((string) $k, '_')) {
            continue;
        }
        if (!in_array($k, $allowed, true)) {
            continue;
        }
        $colMetaRow = events_csv_import_schema()[$table]['columns'][$k] ?? [];
        if (!empty($colMetaRow['virtual'])) {
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
    if (!empty($pairs['id'])) {
        return (int) $pairs['id'];
    }
    return (int) $db->lastInsertId();
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

function events_csv_event_category_link_exists(PDO $db, int $eventId, int $categoryId): bool {
    $st = $db->prepare('SELECT 1 FROM `events_calendar_event_categories` WHERE `event_id` = ? AND `category_id` = ? LIMIT 1');
    $st->execute([$eventId, $categoryId]);
    return (bool) $st->fetchColumn();
}

/**
 * @param array<string,mixed> $values event_id, category_id
 * @return 'inserted'|'updated'
 */
function events_csv_event_tag_link_exists(PDO $db, int $eventId, int $tagId): bool {
    $st = $db->prepare('SELECT 1 FROM `events_calendar_event_tags` WHERE `event_id` = ? AND `tag_id` = ? LIMIT 1');
    $st->execute([$eventId, $tagId]);
    return (bool) $st->fetchColumn();
}

/**
 * @param array<string,mixed> $values event_id, tag_id, opcionálisan _tag_name
 * @return 'inserted'|'updated'
 */
function events_csv_upsert_event_tag_link(PDO $db, array $values): string {
    if (!events_tags_tables_available($db)) {
        throw new RuntimeException('Az events_tags tábla nem elérhető (migration_tags.sql).');
    }
    $eventId = (int) ($values['event_id'] ?? 0);
    $tagId = (int) ($values['tag_id'] ?? 0);
    $tagName = trim((string) ($values['_tag_name'] ?? ''));
    if ($eventId <= 0) {
        throw new RuntimeException('event_id kötelező.');
    }
    if ($tagId <= 0) {
        if ($tagName === '') {
            throw new RuntimeException('tag_id vagy tag_name kötelező.');
        }
        $tagId = events_find_or_create_tag_by_name($db, $tagName);
        $values['tag_id'] = $tagId;
    }
    $chkEv = $db->prepare('SELECT 1 FROM `events_calendar_events` WHERE `id` = ? LIMIT 1');
    $chkEv->execute([$eventId]);
    if (!$chkEv->fetchColumn()) {
        throw new RuntimeException('Nem létezik esemény ezzel az ID-val: ' . $eventId);
    }
    $chkTag = $db->prepare('SELECT 1 FROM `events_tags` WHERE `id` = ? LIMIT 1');
    $chkTag->execute([$tagId]);
    if (!$chkTag->fetchColumn()) {
        throw new RuntimeException('Nem létezik címke ezzel az ID-val: ' . $tagId);
    }
    if (events_csv_event_tag_link_exists($db, $eventId, $tagId)) {
        events_csv_apply_tag_types_to_tag($db, $tagId, $values);

        return 'updated';
    }
    $db->prepare('INSERT INTO `events_calendar_event_tags` (`event_id`, `tag_id`) VALUES (?,?)')
        ->execute([$eventId, $tagId]);
    events_csv_apply_tag_types_to_tag($db, $tagId, $values);

    return 'inserted';
}

/**
 * @param array<string,mixed> $values
 */
/**
 * @param array<string, mixed> $values
 */
function events_csv_enrich_event_tag_row_for_import_type(array $values, ?string $importTypeId): array {
    if ($importTypeId === null || $importTypeId === '') {
        return $values;
    }
    $opts = events_import_type_tag_row_options($importTypeId);
    $hasTypes = isset($values['_tag_types']) && is_array($values['_tag_types']) && $values['_tag_types'] !== [];
    if (!$hasTypes && $opts['default_tag_types'] !== []) {
        $values['_tag_types'] = $opts['default_tag_types'];
    }
    if ($opts['merge_tag_types_on_existing']) {
        $values['_merge_tag_types'] = true;
    }

    return $values;
}

/**
 * @param array<string, mixed> $values
 */
function events_csv_apply_tag_types_to_tag(PDO $db, int $tagId, array $values): void {
    if ($tagId <= 0 || !isset($values['_tag_types']) || !is_array($values['_tag_types'])) {
        return;
    }
    $typeCodes = events_tag_type_normalize_codes($values['_tag_types']);
    if ($typeCodes === []) {
        return;
    }
    if (!empty($values['_merge_tag_types'])) {
        $existing = events_load_tag_type_codes($db, $tagId);
        $typeCodes = events_tag_type_normalize_codes(array_merge($existing, $typeCodes));
    }
    events_save_tag_types($db, $tagId, $typeCodes);
}

function events_csv_event_style_link_exists(PDO $db, int $eventId, int $styleId, string $table): bool {
    if ($table !== 'events_calendar_event_main_styles' && $table !== 'events_calendar_event_supplementary_styles') {
        return false;
    }
    $st = $db->prepare("SELECT 1 FROM `{$table}` WHERE `event_id` = ? AND `style_id` = ? LIMIT 1");
    $st->execute([$eventId, $styleId]);

    return (bool) $st->fetchColumn();
}

/**
 * @param array<string,mixed> $values event_id, style_id, opcionálisan _style_name
 */
function events_csv_upsert_event_style_link(PDO $db, array $values, string $table): string {
    if (!events_styles_tables_available($db)) {
        throw new RuntimeException('Az events_styles tábla nem elérhető (migration_styles.sql).');
    }
    if ($table !== 'events_calendar_event_main_styles' && $table !== 'events_calendar_event_supplementary_styles') {
        throw new InvalidArgumentException('Érvénytelen stílus kapcsoló tábla.');
    }
    $eventId = (int) ($values['event_id'] ?? 0);
    $styleId = (int) ($values['style_id'] ?? 0);
    $styleName = trim((string) ($values['_style_name'] ?? ''));
    if ($eventId <= 0) {
        throw new RuntimeException('event_id kötelező.');
    }
    if ($styleId <= 0) {
        if ($styleName === '') {
            throw new RuntimeException('style_id vagy style_name kötelező.');
        }
        $styleId = events_find_or_create_style_by_name($db, $styleName);
        $values['style_id'] = $styleId;
    }
    $chkEv = $db->prepare('SELECT 1 FROM `events_calendar_events` WHERE `id` = ? LIMIT 1');
    $chkEv->execute([$eventId]);
    if (!$chkEv->fetchColumn()) {
        throw new RuntimeException('Nem létezik esemény ezzel az ID-val: ' . $eventId);
    }
    $chkStyle = $db->prepare('SELECT 1 FROM `events_styles` WHERE `id` = ? LIMIT 1');
    $chkStyle->execute([$styleId]);
    if (!$chkStyle->fetchColumn()) {
        throw new RuntimeException('Nem létezik stílus ezzel az ID-val: ' . $styleId);
    }
    if (events_csv_event_style_link_exists($db, $eventId, $styleId, $table)) {
        return 'updated';
    }
    $db->prepare("INSERT INTO `{$table}` (`event_id`, `style_id`) VALUES (?,?)")
        ->execute([$eventId, $styleId]);

    return 'inserted';
}

function events_csv_upsert_event_category_link(PDO $db, array $values): string {
    $eventId = (int) ($values['event_id'] ?? 0);
    $categoryId = (int) ($values['category_id'] ?? 0);
    if ($eventId <= 0 || $categoryId <= 0) {
        throw new RuntimeException('event_id és category_id kötelező.');
    }
    $chkEv = $db->prepare('SELECT 1 FROM `events_calendar_events` WHERE `id` = ? LIMIT 1');
    $chkEv->execute([$eventId]);
    if (!$chkEv->fetchColumn()) {
        throw new RuntimeException('Nem létezik esemény ezzel az ID-val: ' . $eventId);
    }
    $chkCat = $db->prepare('SELECT 1 FROM `events_categories` WHERE `id` = ? LIMIT 1');
    $chkCat->execute([$categoryId]);
    if (!$chkCat->fetchColumn()) {
        throw new RuntimeException('Nem létezik kategória ezzel az ID-val: ' . $categoryId);
    }
    if (events_csv_event_category_link_exists($db, $eventId, $categoryId)) {
        return 'updated';
    }
    $db->prepare('INSERT INTO `events_calendar_event_categories` (`event_id`, `category_id`) VALUES (?,?)')
        ->execute([$eventId, $categoryId]);
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
        if (str_starts_with((string) $k, '_')) {
            continue;
        }
        $colMetaRow = events_csv_import_schema()[$table]['columns'][$k] ?? [];
        if (!empty($colMetaRow['virtual'])) {
            continue;
        }
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
    ?string $importTypeId = null,
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
                if ($table === 'events_calendar_event_organizers') {
                    $op = events_csv_upsert_event_organizer_link($db, $vals);
                } elseif ($table === 'events_calendar_event_categories') {
                    $op = events_csv_upsert_event_category_link($db, $vals);
                } elseif ($table === 'events_calendar_event_main_styles') {
                    $op = events_csv_upsert_event_style_link($db, $vals, 'events_calendar_event_main_styles');
                } elseif ($table === 'events_calendar_event_supplementary_styles') {
                    $op = events_csv_upsert_event_style_link($db, $vals, 'events_calendar_event_supplementary_styles');
                } elseif ($table === 'events_calendar_event_tags') {
                    $vals = events_csv_enrich_event_tag_row_for_import_type($vals, $importTypeId);
                    $op = events_csv_upsert_event_tag_link($db, $vals);
                } else {
                    throw new RuntimeException('Nem támogatott composite cél tábla: ' . $table);
                }
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
            if ($table === 'events_styles') {
                if (!events_styles_tables_available($db)) {
                    $skipped[] = "Adatsor {$lineNo} (import): kihagyva – az events_styles tábla nem elérhető.";
                    continue;
                }
                if ($idVal !== null && $idVal > 0) {
                    $exists = events_csv_row_exists($db, 'events_styles', $idVal);
                    if ($exists) {
                        [$vals, $err] = events_csv_build_row_values($db, $table, $map, $csvRow, $tableSchema, true, $idVal);
                        if ($err !== null) {
                            $skipped[] = "Adatsor {$lineNo} (import): kihagyva – {$err}";
                            continue;
                        }
                        if (array_key_exists('name', $vals)) {
                            $newName = trim((string) $vals['name']);
                            if ($newName !== '') {
                                $stDup = $db->prepare('SELECT `id` FROM `events_styles` WHERE `name` = ? AND `id` <> ? LIMIT 1');
                                $stDup->execute([$newName, $idVal]);
                                if ($stDup->fetchColumn()) {
                                    $skipped[] = "Adatsor {$lineNo} (import): kihagyva – a „{$newName}” név már egy másik stílushoz tartozik.";
                                    continue;
                                }
                            }
                        }
                        $didUpdate = events_csv_do_update($db, $table, $idVal, $vals);
                        if ($didUpdate) {
                            $updated++;
                        } else {
                            $skipped[] = "Adatsor {$lineNo} (import): kihagyva – létező stílus (id={$idVal}), nincs frissítendő mező.";
                        }
                    } else {
                        [$vals, $err] = events_csv_build_row_values($db, $table, $map, $csvRow, $tableSchema, false, null);
                        if ($err !== null) {
                            $skipped[] = "Adatsor {$lineNo} (import): kihagyva – {$err}";
                            continue;
                        }
                        $nm = trim((string) ($vals['name'] ?? ''));
                        if ($nm === '') {
                            $skipped[] = "Adatsor {$lineNo} (import): kihagyva – üres név.";
                            continue;
                        }
                        $stName = $db->prepare('SELECT `id` FROM `events_styles` WHERE `name` = ? LIMIT 1');
                        $stName->execute([$nm]);
                        if ($stName->fetchColumn()) {
                            $skipped[] = "Adatsor {$lineNo} (import): kihagyva – a „{$nm}” nevű stílus már létezik.";
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
                    $nm = trim((string) ($vals['name'] ?? ''));
                    if ($nm === '') {
                        $skipped[] = "Adatsor {$lineNo} (import): kihagyva – üres név.";
                        continue;
                    }
                    $stName = $db->prepare('SELECT `id` FROM `events_styles` WHERE `name` = ? LIMIT 1');
                    $stName->execute([$nm]);
                    if ($stName->fetchColumn()) {
                        $skipped[] = "Adatsor {$lineNo} (import): kihagyva – a „{$nm}” nevű stílus már létezik.";
                        continue;
                    }
                    events_csv_do_insert($db, $table, $vals);
                    $inserted++;
                }
                continue;
            }
            if ($table === 'events_tags') {
                if (!events_tags_tables_available($db)) {
                    $skipped[] = "Adatsor {$lineNo} (import): kihagyva – az events_tags tábla nem elérhető.";
                    continue;
                }
                if ($idVal !== null && $idVal > 0) {
                    $exists = events_csv_row_exists($db, 'events_tags', $idVal);
                    if ($exists) {
                        [$vals, $err] = events_csv_build_row_values($db, $table, $map, $csvRow, $tableSchema, true, $idVal);
                        if ($err !== null) {
                            $skipped[] = "Adatsor {$lineNo} (import): kihagyva – {$err}";
                            continue;
                        }
                        if (array_key_exists('name', $vals)) {
                            $newName = trim((string) $vals['name']);
                            if ($newName !== '') {
                                $stDup = $db->prepare('SELECT `id` FROM `events_tags` WHERE `name` = ? AND `id` <> ? LIMIT 1');
                                $stDup->execute([$newName, $idVal]);
                                if ($stDup->fetchColumn()) {
                                    $skipped[] = "Adatsor {$lineNo} (import): kihagyva – a „{$newName}” név már egy másik címkéhez tartozik.";
                                    continue;
                                }
                            }
                        }
                        $didUpdate = events_csv_do_update($db, $table, $idVal, $vals);
                        events_csv_apply_tag_types_to_tag($db, $idVal, $vals);
                        if ($didUpdate) {
                            $updated++;
                        } else {
                            $skipped[] = "Adatsor {$lineNo} (import): kihagyva – létező címke (id={$idVal}), nincs frissítendő mező.";
                        }
                    } else {
                        [$vals, $err] = events_csv_build_row_values($db, $table, $map, $csvRow, $tableSchema, false, null);
                        if ($err !== null) {
                            $skipped[] = "Adatsor {$lineNo} (import): kihagyva – {$err}";
                            continue;
                        }
                        $nm = trim((string) ($vals['name'] ?? ''));
                        if ($nm === '') {
                            $skipped[] = "Adatsor {$lineNo} (import): kihagyva – üres név.";
                            continue;
                        }
                        $stName = $db->prepare('SELECT `id` FROM `events_tags` WHERE `name` = ? LIMIT 1');
                        $stName->execute([$nm]);
                        if ($stName->fetchColumn()) {
                            $skipped[] = "Adatsor {$lineNo} (import): kihagyva – a „{$nm}” nevű címke már létezik.";
                            continue;
                        }
                        $vals['id'] = $idVal;
                        events_csv_do_insert($db, $table, $vals);
                        events_csv_apply_tag_types_to_tag($db, $idVal, $vals);
                        $inserted++;
                    }
                } else {
                    [$vals, $err] = events_csv_build_row_values($db, $table, $map, $csvRow, $tableSchema, false, null);
                    if ($err !== null) {
                        $skipped[] = "Adatsor {$lineNo} (import): kihagyva – {$err}";
                        continue;
                    }
                    $nm = trim((string) ($vals['name'] ?? ''));
                    if ($nm === '') {
                        $skipped[] = "Adatsor {$lineNo} (import): kihagyva – üres név.";
                        continue;
                    }
                    $stName = $db->prepare('SELECT `id` FROM `events_tags` WHERE `name` = ? LIMIT 1');
                    $stName->execute([$nm]);
                    if ($stName->fetchColumn()) {
                        $skipped[] = "Adatsor {$lineNo} (import): kihagyva – a „{$nm}” nevű címke már létezik.";
                        continue;
                    }
                    events_csv_do_insert($db, $table, $vals);
                    $newTagId = (int) $db->lastInsertId();
                    events_csv_apply_tag_types_to_tag($db, $newTagId, $vals);
                    $inserted++;
                }
                continue;
            }
            if ($idVal !== null && $idVal > 0) {
                $exists = events_csv_row_exists($db, $table, $idVal);
                if ($exists) {
                    [$vals, $err] = events_csv_build_row_values($db, $table, $map, $csvRow, $tableSchema, true, $idVal);
                    if ($err !== null) {
                        $skipped[] = "Adatsor {$lineNo} (import): kihagyva – {$err}";
                        continue;
                    }
                    $cats = events_csv_take_category_sync_payload($vals);
                    $didUpdate = events_csv_do_update($db, $table, $idVal, $vals);
                    if ($table === 'events_calendar_events' && $cats !== null) {
                        events_csv_replace_event_categories($db, $idVal, $cats);
                    }
                    if ($didUpdate || $cats !== null) {
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
                    $cats = events_csv_take_category_sync_payload($vals);
                    $vals['id'] = $idVal;
                    $eventPk = events_csv_do_insert($db, $table, $vals);
                    if ($table === 'events_calendar_events' && $cats !== null) {
                        events_csv_replace_event_categories($db, $eventPk, $cats);
                    }
                    $inserted++;
                }
            } else {
                [$vals, $err] = events_csv_build_row_values($db, $table, $map, $csvRow, $tableSchema, false, null);
                if ($err !== null) {
                    $skipped[] = "Adatsor {$lineNo} (import): kihagyva – {$err}";
                    continue;
                }
                $cats = events_csv_take_category_sync_payload($vals);
                $eventPk = events_csv_do_insert($db, $table, $vals);
                if ($table === 'events_calendar_events' && $cats !== null) {
                    events_csv_replace_event_categories($db, $eventPk, $cats);
                }
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
