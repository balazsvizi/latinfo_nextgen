<?php
declare(strict_types=1);

/**
 * DJ profilfotók mappa (nextgen/events/djpics).
 */

function events_djpics_dir_path(): string {
    return BASE_PATH . '/nextgen/events/djpics';
}

function events_djpics_web_prefix(): string {
    return '/nextgen/events/djpics/';
}

function events_djpics_ensure_dir(): bool {
    $dir = events_djpics_dir_path();
    if (is_dir($dir)) {
        return true;
    }

    return @mkdir($dir, 0775, true) || is_dir($dir);
}

function events_djpics_is_safe_filename(string $name): bool {
    return (bool) preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,190}$/', $name);
}

function events_djpics_build_web_path(string $filename): string {
    return events_djpics_web_prefix() . ltrim($filename, '/');
}

/**
 * @return array{0:?string,1:?string} [webPath, error]
 */
function events_djpics_normalize_selected(?string $selected): array {
    $file = trim((string) $selected);
    if ($file === '') {
        return [null, null];
    }
    if (!events_djpics_is_safe_filename($file)) {
        return [null, 'A kiválasztott DJ fotó fájlnév érvénytelen.'];
    }
    $path = events_djpics_dir_path() . '/' . $file;
    if (!is_file($path)) {
        return [null, 'A kiválasztott DJ fotó nem található.'];
    }

    return [events_djpics_build_web_path($file), null];
}

/**
 * @return array{0:?string,1:?string} [webPath, error]
 */
function events_djpics_store_from_tmp(string $tmpPath, string $origName, int $sizeBytes, bool $useMoveUploadedFile): array {
    if ($tmpPath === '' || !is_readable($tmpPath)) {
        return [null, 'A feltöltött fájl nem olvasható.'];
    }
    if ($sizeBytes <= 0 || $sizeBytes > 8 * 1024 * 1024) {
        return [null, 'A DJ fotó maximum 8 MB lehet.'];
    }
    if (!events_djpics_ensure_dir()) {
        return [null, 'A djpics mappa nem létrehozható.'];
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
        $base = 'dj';
    }
    $name = $base . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    $target = events_djpics_dir_path() . '/' . $name;
    if ($useMoveUploadedFile) {
        if (!@move_uploaded_file($tmpPath, $target)) {
            return [null, 'A DJ fotó mentése nem sikerült.'];
        }
    } elseif (!@copy($tmpPath, $target)) {
        return [null, 'A DJ fotó mentése nem sikerült.'];
    }

    return [events_djpics_build_web_path($name), null];
}

/**
 * @param array<string, mixed>|null $file
 * @return array{0:?string,1:?string} [webPath, error]
 */
function events_djpics_handle_upload(?array $file): array {
    if (!is_array($file) || !isset($file['error'])) {
        return [null, null];
    }
    $err = (int) $file['error'];
    if ($err === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($err !== UPLOAD_ERR_OK) {
        return [null, 'A DJ fotó feltöltése sikertelen.'];
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return [null, 'A DJ fotó feltöltése érvénytelen.'];
    }

    return events_djpics_store_from_tmp($tmp, (string) ($file['name'] ?? ''), (int) ($file['size'] ?? 0), true);
}

function events_djpics_extract_selected_from_photo(?string $photoUrl): string {
    $u = trim((string) $photoUrl);
    if ($u === '') {
        return '';
    }
    $p = parse_url($u, PHP_URL_PATH);
    $path = is_string($p) && $p !== '' ? $p : $u;
    $marker = events_djpics_web_prefix();
    $pos = strpos($path, $marker);
    if ($pos === false) {
        return '';
    }
    $f = substr($path, $pos + strlen($marker));
    if ($f === '' || !events_djpics_is_safe_filename($f)) {
        return '';
    }

    return $f;
}

/**
 * Mentéshez: új feltöltés / pick / megtartás / törlés.
 *
 * @return array{0:?string,1:?string} [photo_url|null (null = törlés üresre), error]
 *         Ha nincs változás, a második null és az első a currentPhotoUrl (string, lehet '').
 */
function events_djpics_resolve_photo_for_save(string $currentPhotoUrl, bool $clearPhoto): array {
    if ($clearPhoto) {
        return ['', null];
    }

    $file = $_FILES['dj_photo_upload'] ?? null;
    if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        [$webPath, $err] = events_djpics_handle_upload($file);
        if ($err !== null) {
            return [null, $err];
        }
        if ($webPath !== null) {
            return [$webPath, null];
        }
    }

    $pick = trim((string) ($_POST['dj_photo_pick'] ?? ''));
    if ($pick !== '') {
        [$webPath, $err] = events_djpics_normalize_selected($pick);
        if ($err !== null) {
            return [null, $err];
        }
        if ($webPath !== null) {
            return [$webPath, null];
        }
    }

    return [$currentPhotoUrl, null];
}
