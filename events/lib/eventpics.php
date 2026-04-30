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
