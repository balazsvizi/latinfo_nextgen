<?php
declare(strict_types=1);

/**
 * CMS képtár – elkülönül az eventpics tárától.
 */

function cms_uploads_dir_path(): string
{
    return BASE_PATH . '/nextgen/cms/uploads';
}

function cms_uploads_web_prefix(): string
{
    return '/nextgen/cms/uploads/';
}

function cms_uploads_ensure_dir(): bool
{
    $dir = cms_uploads_dir_path();
    if (is_dir($dir)) {
        return true;
    }

    return @mkdir($dir, 0775, true) || is_dir($dir);
}

function cms_uploads_is_safe_filename(string $name): bool
{
    return (bool) preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,190}$/', $name);
}

function cms_uploads_build_web_path(string $filename): string
{
    return cms_uploads_web_prefix() . ltrim($filename, '/');
}

/**
 * @return list<string>
 */
function cms_uploads_list_files(): array
{
    $dir = cms_uploads_dir_path();
    if (!is_dir($dir)) {
        return [];
    }
    $items = @scandir($dir);
    if (!is_array($items)) {
        return [];
    }

    $out = [];
    foreach ($items as $f) {
        if ($f === '.' || $f === '..' || $f === '.gitkeep') {
            continue;
        }
        if (!cms_uploads_is_safe_filename($f)) {
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

/**
 * @return array{0:?string,1:?string} [webPath, error]
 */
function cms_uploads_handle_upload(?array $file): array
{
    if (!is_array($file) || !isset($file['error'])) {
        return [null, null];
    }
    $err = (int) $file['error'];
    if ($err === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($err !== UPLOAD_ERR_OK) {
        return [null, 'A kép feltöltése sikertelen.'];
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return [null, 'A kép feltöltése érvénytelen.'];
    }
    $size = (int) ($file['size'] ?? 0);

    return cms_uploads_store_from_tmp($tmp, (string) ($file['name'] ?? ''), $size);
}

/**
 * @return array{0:?string,1:?string}
 */
function cms_uploads_store_from_tmp(string $tmpPath, string $origName, int $sizeBytes): array
{
    if ($tmpPath === '' || !is_readable($tmpPath)) {
        return [null, 'A feltöltött fájl nem olvasható.'];
    }
    if ($sizeBytes <= 0 || $sizeBytes > 8 * 1024 * 1024) {
        return [null, 'A kép maximum 8 MB lehet.'];
    }
    if (!cms_uploads_ensure_dir()) {
        return [null, 'A CMS feltöltési mappa nem létrehozható.'];
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

    try {
        $filename = 'cms_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    } catch (Throwable $e) {
        return [null, 'Nem sikerült fájlnevet generálni.'];
    }

    $dest = cms_uploads_dir_path() . '/' . $filename;
    if (!@move_uploaded_file($tmpPath, $dest)) {
        return [null, 'A kép mentése sikertelen.'];
    }
    @chmod($dest, 0644);

    return [cms_uploads_build_web_path($filename), null];
}

function cms_absolute_url(string $webPath): string
{
    $webPath = trim($webPath);
    if ($webPath === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $webPath)) {
        return $webPath;
    }
    if (function_exists('site_url')) {
        return rtrim(site_url(ltrim($webPath, '/')), '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

    return $scheme . '://' . $host . (str_starts_with($webPath, '/') ? $webPath : '/' . $webPath);
}
