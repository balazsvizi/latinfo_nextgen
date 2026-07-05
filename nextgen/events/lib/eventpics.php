<?php
declare(strict_types=1);

/**
 * Event borítókép mappa (megosztott képforrás).
 */
function events_eventpics_dir_path(): string {
    return BASE_PATH . '/nextgen/events/eventpics';
}

function events_eventpics_web_prefix(): string {
    return '/nextgen/events/eventpics/';
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
    $path = is_string($p) && $p !== '' ? $p : $u;
    $marker = events_eventpics_web_prefix();
    $pos = strpos($path, $marker);
    if ($pos === false) {
        return '';
    }
    $f = substr($path, $pos + strlen($marker));
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
          AND `event_featured_image_url` LIKE \'%/nextgen/events/eventpics/%\'
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
 * @return array<string, int> eventpics fájlnév => hány esemény használja
 */
function events_eventpics_usage_count_map(PDO $db): array {
    $st = $db->query('
        SELECT `event_featured_image_url`
        FROM `events_calendar_events`
        WHERE `event_featured_image_url` IS NOT NULL AND TRIM(`event_featured_image_url`) != \'\'
          AND `event_featured_image_url` LIKE \'%/nextgen/events/eventpics/%\'
    ');
    if ($st === false) {
        return [];
    }
    $map = [];
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $fn = events_eventpics_extract_selected_from_featured((string) ($row['event_featured_image_url'] ?? ''));
        if ($fn === '') {
            continue;
        }
        $map[$fn] = ($map[$fn] ?? 0) + 1;
    }

    return $map;
}

/**
 * Eventpics fájl törlése a lemezről, ha már egyetlen esemény sem használja.
 */
function events_eventpics_delete_file_if_unused(PDO $db, string $filename): bool {
    if (!events_eventpics_is_safe_filename($filename)) {
        return false;
    }
    if (events_events_using_eventpic($db, $filename) !== []) {
        return false;
    }
    $path = events_eventpics_dir_path() . '/' . $filename;
    if (!is_file($path)) {
        return true;
    }

    return @unlink($path);
}

/**
 * Eventpics fájl törlése a lemezről. Csak akkor engedélyezett, ha egyetlen esemény sem használja.
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
    if ($rows !== []) {
        $n = count($rows);

        return [false, 'A kép nem törölhető: ' . $n . ' esemény használja. Előbb állítsd át a borítót az érintett eseményeknél.'];
    }
    if (!@unlink($path)) {
        return [false, 'A fájl törlése nem sikerült (fájlrendszer).'];
    }

    return [true, 'A kép törölve.'];
}

/**
 * @param list<int|string> $filenames
 * @return array{ok: int, skipped: int, failed: int, messages: list<string>, cleared_events: int}
 */
function events_eventpics_bulk_delete_with_clear(PDO $db, array $filenames): array
{
    $seen = [];
    $ok = 0;
    $skipped = 0;
    $failed = 0;
    $messages = [];

    foreach ($filenames as $rawName) {
        $fn = trim((string) $rawName);
        if ($fn === '' || isset($seen[$fn])) {
            continue;
        }
        $seen[$fn] = true;

        if (!events_eventpics_is_safe_filename($fn)) {
            $skipped++;
            continue;
        }

        [$success, $msg] = events_eventpics_delete_with_clear($db, $fn);
        if ($success) {
            $ok++;
            $messages[] = $fn . ': ' . $msg;
        } else {
            if (str_contains($msg, 'nem törölhető')) {
                $skipped++;
            } else {
                $failed++;
            }
            $messages[] = $fn . ': ' . $msg;
        }
    }

    return [
        'ok' => $ok,
        'skipped' => $skipped,
        'failed' => $failed,
        'messages' => $messages,
        'cleared_events' => 0,
    ];
}

/**
 * Kiemelt kép admin listához: típus (URL / saját eventpics / nincs) és oszlopok.
 *
 * @return array{
 *   type: 'none'|'url'|'own',
 *   type_label: string,
 *   url: string,
 *   own_link: string,
 *   search_type: string,
 *   search_url: string,
 *   search_own: string
 * }
 */
function events_featured_image_list_meta(?string $featuredUrl): array
{
    $raw = trim(html_entity_decode(trim((string) $featuredUrl), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $raw = preg_replace('/^\x{FEFF}|\x{200B}/u', '', $raw) ?? $raw;
    if ($raw === '') {
        return [
            'type' => 'none',
            'type_label' => '—',
            'url' => '',
            'own_link' => '',
            'search_type' => 'none',
            'search_url' => '',
            'search_own' => '',
        ];
    }

    $eventpicFile = events_eventpics_extract_selected_from_featured($raw);
    if ($eventpicFile !== '') {
        $ownLink = events_eventpics_build_web_path($eventpicFile);

        return [
            'type' => 'own',
            'type_label' => 'Saját',
            'url' => '',
            'own_link' => $ownLink,
            'search_type' => 'own',
            'search_url' => '',
            'search_own' => mb_strtolower($ownLink . ' ' . $eventpicFile, 'UTF-8'),
        ];
    }

    return [
        'type' => 'url',
        'type_label' => 'URL',
        'url' => $raw,
        'own_link' => '',
        'search_type' => 'url',
        'search_url' => mb_strtolower($raw, 'UTF-8'),
        'search_own' => '',
    ];
}

/**
 * Összes esemény kiemelt kép adatai (admin áttekintés).
 *
 * @return list<array{
 *   id: int,
 *   event_name: string,
 *   event_status: string,
 *   featured_meta: array<string, string>
 * }>
 */
function events_featured_image_admin_all_events(PDO $db, ?int $listLimit = null): array
{
    require_once __DIR__ . '/admin_event_filters.php';
    $poolFrom = events_admin_table_pool_from_sql('events_calendar_events', 'e', $listLimit);

    try {
        $stmt = $db->query('
            SELECT e.`id`, e.`event_name`, e.`event_status`, e.`event_featured_image_url`
            FROM ' . $poolFrom . '
            ORDER BY e.`event_start` IS NULL, e.`event_start` DESC, e.`id` DESC
        ');
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        error_log('events_featured_image_admin_all_events: ' . $e->getMessage());

        return [];
    }

    $out = [];
    foreach ($rows as $row) {
        $meta = events_featured_image_list_meta((string) ($row['event_featured_image_url'] ?? ''));
        $out[] = [
            'id' => (int) ($row['id'] ?? 0),
            'event_name' => (string) ($row['event_name'] ?? ''),
            'event_status' => (string) ($row['event_status'] ?? ''),
            'featured_meta' => $meta,
        ];
    }

    return $out;
}

function events_eventpics_is_public_http_host(string $host): bool
{
    $host = strtolower(trim($host));
    if ($host === '' || strcasecmp($host, 'localhost') === 0) {
        return false;
    }
    if (str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
        return false;
    }

    $ips = @gethostbynamel($host);
    if ($ips === false || $ips === []) {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            return false;
        }
    }

    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
    }

    return true;
}

function events_eventpics_resolve_local_image_path(string $url): ?string
{
    $raw = trim(html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $raw = preg_replace('/^\x{FEFF}|\x{200B}/u', '', $raw) ?? $raw;
    if ($raw === '') {
        return null;
    }

    $path = parse_url($raw, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        $path = str_starts_with($raw, '/') ? $raw : null;
    }
    if (!is_string($path) || $path === '' || str_contains($path, '..')) {
        return null;
    }

    $full = BASE_PATH . $path;
    $real = realpath($full);
    $baseReal = realpath(BASE_PATH);
    if ($real === false || $baseReal === false || !str_starts_with($real, $baseReal . DIRECTORY_SEPARATOR)) {
        return null;
    }
    if (!is_file($real) || !is_readable($real)) {
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($real);
    if (!str_starts_with($mime, 'image/')) {
        return null;
    }

    return $real;
}

/**
 * @return array{0: string, 1: string, 2: int, 3: ?string} [tmpPath, origName, sizeBytes, error]
 */
function events_eventpics_download_http_to_temp(string $url): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'evpic_');
    if ($tmp === false) {
        return ['', '', 0, 'Ideiglenes fájl nem hozható létre.'];
    }

    $origName = basename((string) parse_url($url, PHP_URL_PATH)) ?: 'image.jpg';
    $size = 0;

    if (function_exists('curl_init')) {
        $fp = fopen($tmp, 'wb');
        if ($fp === false) {
            @unlink($tmp);

            return ['', '', 0, 'Ideiglenes fájl nem írható.'];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'AlatinfoEventpicsImporter/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FAILONERROR => true,
        ]);
        $ok = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        fclose($fp);
        $size = (int) (@filesize($tmp) ?: 0);
        if ($ok === false || $httpCode < 200 || $httpCode >= 400) {
            @unlink($tmp);

            return ['', '', 0, $curlErr !== '' ? 'Letöltési hiba: ' . $curlErr : 'Letöltési hiba (HTTP ' . $httpCode . ').'];
        }
    } else {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 45,
                'follow_location' => 1,
                'max_redirects' => 5,
                'user_agent' => 'AlatinfoEventpicsImporter/1.0',
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data === false || $data === '') {
            @unlink($tmp);

            return ['', '', 0, 'A kép letöltése nem sikerült.'];
        }
        if (strlen($data) > 8 * 1024 * 1024) {
            @unlink($tmp);

            return ['', '', 0, 'A borítókép maximum 8 MB lehet.'];
        }
        if (@file_put_contents($tmp, $data) === false) {
            @unlink($tmp);

            return ['', '', 0, 'Az ideiglenes fájl mentése nem sikerült.'];
        }
        $size = strlen($data);
    }

    if ($size <= 0 || $size > 8 * 1024 * 1024) {
        @unlink($tmp);

        return ['', '', 0, 'A letöltött kép üres vagy nagyobb mint 8 MB.'];
    }

    return [$tmp, $origName, $size, null];
}

/**
 * Kép bemásolása eventpics-be helyi útvonalról vagy HTTP(S) URL-ről.
 *
 * @return array{0:?string,1:?string} [webPath, error]
 */
function events_eventpics_import_image_source(string $sourceUrl, string $suggestedBaseName): array
{
    $local = events_eventpics_resolve_local_image_path($sourceUrl);
    if ($local !== null) {
        $size = (int) (@filesize($local) ?: 0);

        return events_eventpics_store_from_tmp($local, basename($local), $size, false);
    }

    $abs = events_absolute_url($sourceUrl);
    if (!preg_match('#^https?://#i', $abs) || !events_http_https_url_is_acceptable($abs)) {
        return [null, 'Érvénytelen vagy nem támogatott kép URL.'];
    }

    $host = (string) parse_url($abs, PHP_URL_HOST);
    $reqHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $reqHostBare = preg_replace('/:\d+$/', '', $reqHost) ?? $reqHost;
    $sameHost = $reqHostBare !== '' && strcasecmp($host, $reqHostBare) === 0;
    if (!$sameHost && !events_eventpics_is_public_http_host($host)) {
        return [null, 'A letöltés erről a hosztról nem engedélyezett.'];
    }

    [$tmp, $origName, $size, $dlErr] = events_eventpics_download_http_to_temp($abs);
    if ($dlErr !== null) {
        return [null, $dlErr];
    }

    $base = preg_replace('/[^a-z0-9]+/i', '-', strtolower($suggestedBaseName));
    $base = trim((string) $base, '-');
    if ($base === '') {
        $base = 'eventpic';
    }

    try {
        return events_eventpics_store_from_tmp($tmp, $base . '-' . $origName, $size, false);
    } finally {
        @unlink($tmp);
    }
}

/**
 * Egy esemény külső kiemelt kép URL-jét eventpics-re cseréli.
 *
 * @return array{0: bool, 1: string, 2: string} [success, message, status: ok|skip|error]
 */
function events_featured_image_migrate_url_to_own_for_event(PDO $db, int $eventId): array
{
    if ($eventId <= 0) {
        return [false, 'Érvénytelen esemény azonosító.', 'skip'];
    }

    try {
        $stmt = $db->prepare('SELECT `id`, `event_name`, `event_featured_image_url` FROM `events_calendar_events` WHERE `id` = ? LIMIT 1');
        $stmt->execute([$eventId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('events_featured_image_migrate_url_to_own_for_event: ' . $e->getMessage());

        return [false, 'Adatbázis hiba.', 'error'];
    }

    if (!$row) {
        return [false, 'Esemény nem található.', 'skip'];
    }

    $meta = events_featured_image_list_meta((string) ($row['event_featured_image_url'] ?? ''));
    if (($meta['type'] ?? '') === 'own') {
        return [false, 'Már saját kép.', 'skip'];
    }
    if (($meta['type'] ?? '') !== 'url' || ($meta['url'] ?? '') === '') {
        return [false, 'Nincs átvihető URL kép.', 'skip'];
    }

    $name = (string) ($row['event_name'] ?? '');
    $base = preg_replace('/[^a-z0-9]+/i', '-', strtolower($name));
    $base = trim((string) $base, '-');
    if ($base === '') {
        $base = 'event-' . $eventId;
    }

    [$webPath, $importErr] = events_eventpics_import_image_source((string) $meta['url'], $base);
    if ($importErr !== null || $webPath === null) {
        return [false, $importErr ?? 'Import hiba.', 'error'];
    }

    try {
        $upd = $db->prepare('UPDATE `events_calendar_events` SET `event_featured_image_url` = ? WHERE `id` = ?');
        $upd->execute([$webPath, $eventId]);
    } catch (Throwable $e) {
        error_log('events_featured_image_migrate_url_to_own update: ' . $e->getMessage());

        return [false, 'Az esemény frissítése nem sikerült.', 'error'];
    }

    return [true, 'Átállítva: ' . $webPath, 'ok'];
}

/**
 * @param list<int|string> $eventIds
 * @return array{ok: int, skipped: int, failed: int, messages: list<string>}
 */
function events_featured_image_bulk_url_to_own(PDO $db, array $eventIds): array
{
    $seen = [];
    $ok = 0;
    $skipped = 0;
    $failed = 0;
    $messages = [];

    foreach ($eventIds as $rawId) {
        $eventId = (int) $rawId;
        if ($eventId <= 0 || isset($seen[$eventId])) {
            continue;
        }
        $seen[$eventId] = true;

        [$success, $msg, $status] = events_featured_image_migrate_url_to_own_for_event($db, $eventId);
        $label = '#' . $eventId . ': ' . $msg;
        if ($status === 'ok' && $success) {
            $ok++;
            $messages[] = $label;
        } elseif ($status === 'skip') {
            $skipped++;
        } else {
            $failed++;
            $messages[] = $label;
        }
    }

    return [
        'ok' => $ok,
        'skipped' => $skipped,
        'failed' => $failed,
        'messages' => $messages,
    ];
}
