<?php
declare(strict_types=1);

/**
 * Event borítókép mappa (megosztott képforrás).
 */
function events_eventpics_dir_path(): string {
    return BASE_PATH . '/events/eventpics';
}

function events_eventpics_web_prefix(): string {
    return '/events/eventpics/';
}

function events_eventpics_ensure_dir(): bool {
    $dir = events_eventpics_dir_path();
    if (is_dir($dir)) {
        return true;
    }

    return @mkdir($dir, 0775, true) || is_dir($dir);
}

/**
 * @return list<string> fájlnevek (újabb elöl)
 */
function events_eventpics_list_files(): array {
    $dir = events_eventpics_dir_path();
    if (!is_dir($dir)) {
        return [];
    }
    $items = @scandir($dir);
    if (!is_array($items)) {
        return [];
    }

    $out = [];
    foreach ($items as $f) {
        if ($f === '.' || $f === '..') {
            continue;
        }
        if (!events_eventpics_is_safe_filename($f)) {
            continue;
        }
        $path = $dir . '/' . $f;
        if (!is_file($path)) {
            continue;
        }
        $ext = strtolower((string) pathinfo($f, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            continue;
        }
        $out[$f] = (int) @filemtime($path);
    }
    arsort($out);

    return array_keys($out);
}

function events_eventpics_is_safe_filename(string $name): bool {
    return (bool) preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,190}$/', $name);
}

function events_eventpics_build_web_path(string $filename): string {
    return events_eventpics_web_prefix() . ltrim($filename, '/');
}

/**
 * @return array{0:?string,1:?string} [webPath, error]
 */
function events_eventpics_normalize_selected(?string $selected): array {
    $file = trim((string) $selected);
    if ($file === '') {
        return [null, null];
    }
    if (!events_eventpics_is_safe_filename($file)) {
        return [null, 'Az eventpics kiválasztott fájlnév érvénytelen.'];
    }
    $path = events_eventpics_dir_path() . '/' . $file;
    if (!is_file($path)) {
        return [null, 'A kiválasztott eventpics kép nem található.'];
    }

    return [events_eventpics_build_web_path($file), null];
}

/**
 * Fájl mentése az eventpics mappába (MIME + méret ellenőrzés).
 *
 * @return array{0:?string,1:?string} [webPath pl. /events/eventpics/a.jpg, hiba]
 */
function events_eventpics_store_from_tmp(string $tmpPath, string $origName, int $sizeBytes, bool $useMoveUploadedFile): array {
    if ($tmpPath === '' || !is_readable($tmpPath)) {
        return [null, 'A feltöltött fájl nem olvasható.'];
    }
    if ($sizeBytes <= 0 || $sizeBytes > 8 * 1024 * 1024) {
        return [null, 'A borítókép maximum 8 MB lehet.'];
    }
    if (!events_eventpics_ensure_dir()) {
        return [null, 'Az eventpics mappa nem létrehozható.'];
    }

    $origExt = strtolower((string) pathinfo($origName, PATHINFO_EXTENSION));
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmpPath);
    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    $ext = $map[$mime] ?? '';
    if ($ext === '' && in_array($origExt, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        $ext = $origExt === 'jpeg' ? 'jpg' : $origExt;
    }
    if ($ext === '') {
        return [null, 'Csak JPG, PNG, WEBP vagy GIF kép tölthető fel.'];
    }

    $base = preg_replace('/[^a-z0-9]+/i', '-', strtolower((string) pathinfo($origName, PATHINFO_FILENAME)));
    $base = trim((string) $base, '-');
    if ($base === '') {
        $base = 'eventpic';
    }
    $name = $base . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    $target = events_eventpics_dir_path() . '/' . $name;
    if ($useMoveUploadedFile) {
        if (!@move_uploaded_file($tmpPath, $target)) {
            return [null, 'A borítókép mentése nem sikerült.'];
        }
    } elseif (!@copy($tmpPath, $target)) {
        return [null, 'A borítókép mentése nem sikerült.'];
    }

    return [events_eventpics_build_web_path($name), null];
}

/**
 * @param array<string,mixed>|null $file
 * @return array{0:?string,1:?string} [webPath, error]
 */
function events_eventpics_handle_upload(?array $file): array {
    if (!is_array($file) || !isset($file['error'])) {
        return [null, null];
    }
    $err = (int) $file['error'];
    if ($err === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($err !== UPLOAD_ERR_OK) {
        return [null, 'A borítókép feltöltése sikertelen.'];
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return [null, 'A borítókép feltöltése érvénytelen.'];
    }
    $size = (int) ($file['size'] ?? 0);

    return events_eventpics_store_from_tmp($tmp, (string) ($file['name'] ?? ''), $size, true);
}

function events_eventpics_extract_selected_from_featured(?string $featured): string {
    $u = trim((string) $featured);
    if ($u === '') {
        return '';
    }
    $p = parse_url($u, PHP_URL_PATH);
    $path = is_string($p) ? $p : $u;
    $prefix = events_eventpics_web_prefix();
    if (!str_starts_with($path, $prefix)) {
        return '';
    }
    $f = substr($path, strlen($prefix));
    if ($f === '' || !events_eventpics_is_safe_filename($f)) {
        return '';
    }

    return $f;
}

/**
 * Események, ahol a kiemelt kép URL az adott eventpics fájlra mutat (path alapján).
 *
 * @return list<array{id: int, event_name: string, event_slug: string}>
 */
function events_events_using_eventpic(PDO $db, string $filename): array {
    if (!events_eventpics_is_safe_filename($filename)) {
        return [];
    }
    $st = $db->query('
        SELECT `id`, `event_name`, `event_slug`, `event_featured_image_url`
        FROM `events_calendar_events`
        WHERE `event_featured_image_url` IS NOT NULL AND TRIM(`event_featured_image_url`) != \'\'
          AND `event_featured_image_url` LIKE \'%/events/eventpics/%\'
    ');
    if ($st === false) {
        return [];
    }
    $out = [];
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        if (events_eventpics_extract_selected_from_featured((string) ($row['event_featured_image_url'] ?? '')) !== $filename) {
            continue;
        }
        $out[] = [
            'id' => (int) ($row['id'] ?? 0),
            'event_name' => (string) ($row['event_name'] ?? ''),
            'event_slug' => (string) ($row['event_slug'] ?? ''),
        ];
    }

    return $out;
}

/**
 * Eventpics fájl törlése: előbb az eseményekről leválasztjuk, majd lemez törlés. Sikertelen unlink esetén rollback.
 *
 * @return array{0: bool, 1: string}
 */
function events_eventpics_delete_with_clear(PDO $db, string $filename): array {
    if (!events_eventpics_is_safe_filename($filename)) {
        return [false, 'Érvénytelen fájlnév.'];
    }
    $path = events_eventpics_dir_path() . '/' . $filename;
    if (!is_file($path)) {
        return [false, 'A fájl nem található a lemezen.'];
    }
    $rows = events_events_using_eventpic($db, $filename);
    $ids = [];
    foreach ($rows as $r) {
        $i = (int) ($r['id'] ?? 0);
        if ($i > 0) {
            $ids[] = $i;
        }
    }
    try {
        $db->beginTransaction();
        if ($ids !== []) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $db->prepare("UPDATE `events_calendar_events` SET `event_featured_image_url` = NULL WHERE `id` IN ($ph)")->execute($ids);
        }
        if (!@unlink($path)) {
            $db->rollBack();

            return [false, 'A fájl törlése nem sikerült (fájlrendszer). Az események változatlanok maradtak.'];
        }
        $db->commit();

        return [true, 'A kép törölve. ' . count($ids) . ' esemény borító URL-je üres lett.'];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('events_eventpics_delete_with_clear: ' . $e->getMessage());

        return [false, 'Adatbázis vagy törlési hiba történt.'];
    }
}
