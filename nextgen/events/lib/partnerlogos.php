<?php
declare(strict_types=1);

/**
 * Partner logók mappa (nextgen/events/partnerlogos).
 * A szerkesztő űrlapjával együtt érkező feltöltést kezeli; külső URL is megadható helyette.
 */

const EVENTS_PARTNERLOGOS_MAX_BYTES = 4 * 1024 * 1024;

function events_partnerlogos_dir_path(): string {
    return BASE_PATH . '/nextgen/events/partnerlogos';
}

function events_partnerlogos_web_prefix(): string {
    return '/nextgen/events/partnerlogos/';
}

function events_partnerlogos_ensure_dir(): bool {
    $dir = events_partnerlogos_dir_path();
    if (is_dir($dir)) {
        return true;
    }

    return @mkdir($dir, 0775, true) || is_dir($dir);
}

function events_partnerlogos_build_web_path(string $filename): string {
    return events_partnerlogos_web_prefix() . ltrim($filename, '/');
}

/**
 * Feltöltött logó mentése.
 *
 * @param array<string, mixed>|null $file $_FILES egy eleme
 * @return array{0:?string,1:?string} [webPath, error]
 */
function events_partnerlogos_handle_upload(?array $file): array {
    if (!is_array($file) || !isset($file['error'])) {
        return [null, null];
    }
    $err = (int) $file['error'];
    if ($err === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        return [null, 'A logó túl nagy (maximum 4 MB).'];
    }
    if ($err !== UPLOAD_ERR_OK) {
        return [null, 'A logó feltöltése sikertelen.'];
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp) || !is_readable($tmp)) {
        return [null, 'A logó feltöltése érvénytelen.'];
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > EVENTS_PARTNERLOGOS_MAX_BYTES) {
        return [null, 'A logó maximum 4 MB lehet.'];
    }
    if (!events_partnerlogos_ensure_dir()) {
        return [null, 'A partnerlogos mappa nem létrehozható.'];
    }

    $origName = (string) ($file['name'] ?? 'logo');
    $origExt = strtolower((string) pathinfo($origName, PATHINFO_EXTENSION));
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmp);
    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/svg+xml' => 'svg',
    ];
    $ext = $map[$mime] ?? '';
    if ($ext === '' && in_array($origExt, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        $ext = $origExt === 'jpeg' ? 'jpg' : $origExt;
    }
    // Néhány rendszer az SVG-t text/xml vagy text/plain néven adja vissza.
    if ($ext === '' && $origExt === 'svg' && in_array($mime, ['text/xml', 'application/xml', 'text/plain'], true)) {
        $ext = 'svg';
    }
    if ($ext === '') {
        return [null, 'Csak JPG, PNG, WEBP, GIF vagy SVG logó tölthető fel.'];
    }
    if ($ext === 'svg' && !events_partnerlogos_svg_is_safe($tmp)) {
        return [null, 'Az SVG logó szkriptet vagy külső hivatkozást tartalmaz, ezért nem fogadható el.'];
    }

    $base = preg_replace('/[^a-z0-9]+/i', '-', strtolower((string) pathinfo($origName, PATHINFO_FILENAME)));
    $base = trim((string) $base, '-');
    if ($base === '') {
        $base = 'partner';
    }
    $base = mb_substr($base, 0, 60);
    $name = $base . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;

    if (!@move_uploaded_file($tmp, events_partnerlogos_dir_path() . '/' . $name)) {
        return [null, 'A logó mentése nem sikerült.'];
    }

    return [events_partnerlogos_build_web_path($name), null];
}

/**
 * SVG csak akkor engedélyezett, ha nincs benne szkript, event handler vagy külső betöltés.
 */
function events_partnerlogos_svg_is_safe(string $path): bool {
    $svg = (string) @file_get_contents($path, false, null, 0, 512 * 1024);
    if ($svg === '' || stripos($svg, '<svg') === false) {
        return false;
    }
    $lower = strtolower($svg);
    foreach (['<script', '<foreignobject', '<iframe', '<embed', '<object', 'javascript:', '<!entity', '<use'] as $needle) {
        if (str_contains($lower, $needle)) {
            return false;
        }
    }

    return !(bool) preg_match('/\son[a-z]+\s*=/i', $svg);
}

/**
 * Feltöltött (nem külső) logó törlése a lemezről.
 */
function events_partnerlogos_delete_if_local(string $webPath): void {
    $prefix = events_partnerlogos_web_prefix();
    if ($webPath === '' || !str_starts_with($webPath, $prefix)) {
        return;
    }
    $name = basename($webPath);
    if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,190}$/', $name)) {
        return;
    }
    $full = events_partnerlogos_dir_path() . '/' . $name;
    if (is_file($full)) {
        @unlink($full);
    }
}
