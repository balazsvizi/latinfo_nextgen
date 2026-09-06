<?php
/**
 * Segédfüggvények
 */
if (!defined('NEXTGEN_WEB')) {
    require_once dirname(__DIR__, 2) . '/nextgen/core/config.php';
}

/**
 * Rendszer log írása
 */
function rendszer_log(string $entitás, ?int $entitás_id, string $művelet, ?string $részletek = null): void {
    $db = getDb();
    $admin_id = $_SESSION['admin_id'] ?? null;
    $stmt = $db->prepare('INSERT INTO nextgen_system_log (entitás, entitás_id, művelet, részletek, admin_id) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$entitás, $entitás_id, $művelet, $részletek, $admin_id]);
}

/**
 * Log entitás megtekintési URL-je (ahol releváns), egyébként null
 */
function log_entity_url(string $entitás, ?int $entitás_id): ?string {
    if ($entitás_id === null) {
        return null;
    }
    $p = NEXTGEN_WEB;
    $urls = [
        'szervező' => $p . '/organizers/megtekint.php?id=',
        'kontakt' => $p . '/contacts/megtekint.php?id=',
        'számla' => $p . '/finance/szamlak/szerkeszt.php?id=',
        'számlázandó' => $p . '/finance/szamlazando/szerkeszt.php?id=',
        'számlázási_cím' => $p . '/finance/cimek/szerkeszt.php?id=',
        'admin' => $p . '/admin/adminok/szerkeszt.php?id=',
        'email_config' => $p . '/admin/email/szerkeszt.php?id=',
        'levélsablon' => $p . '/config/levelsablonok/szerkeszt.php?id=',
        'esemény' => nextgen_url('events/szerkeszt.php?id='),
        'helyszín' => nextgen_url('events/venue_szerkeszt.php?id='),
        'tag' => nextgen_url('events/tags.php?open_tag='),
        'spec_tag' => nextgen_url('events/tags.php?edit_special='),
        'dj' => nextgen_url('events/tags.php?open_tag='),
        'stílus' => nextgen_url('events/styles.php?open_style='),
        'címke' => nextgen_url('events/tags.php?open_tag='),
        'kontakt_típus' => null, // csak lista, nincs egy tétel oldal
        'partner' => nextgen_url('admin/partnerek/szerkeszt.php?id='),
    ];
    $base = $urls[$entitás] ?? null;
    return $base !== null ? $base . $entitás_id : null;
}

/**
 * Átirányítás
 */
function redirect(string $url, int $code = 302): void {
    if (!headers_sent()) {
        header('Location: ' . $url, true, $code);
        exit;
    }

    // Fallback when HTML output has already started.
    $safeUrl = h($url);
    echo '<script>window.location.href="' . $safeUrl . '";</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . $safeUrl . '"></noscript>';
    exit;
}

/**
 * HTML escape
 */
function h(?string $s): string {
    return $s === null ? '' : htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/**
 * Alkalmazás verzió (lábléc, napló).
 */
function nextgen_app_version(): string
{
    return defined('APP_VERSION') ? (string) APP_VERSION : '0.0.0';
}

/**
 * Szolid verziójel a láblécben.
 */
function nextgen_footer_version_markup(): string
{
    return '<span class="app-version" title="Verzió">v' . h(nextgen_app_version()) . '</span>';
}

/**
 * Nyilvános alap URL (séma + host + opcionális alkönyvtár), e-mailekhez és abszolút linkekhez.
 */
function ng_public_base_url(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    if (defined('APP_PUBLIC_URL')) {
        $configured = rtrim((string) APP_PUBLIC_URL, '/');
        if ($configured !== '' && preg_match('#^https?://#i', $configured)) {
            $cached = $configured;

            return $cached;
        }
    }

    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $basePath = '';

    if (defined('BASE_URL')) {
        $baseUrl = (string) BASE_URL;
        if (preg_match('#^https?://[^/]+(/.*)$#i', $baseUrl, $m)) {
            $basePath = rtrim($m[1], '/');
        } elseif ($baseUrl !== '' && str_starts_with($baseUrl, '/') && !str_starts_with($baseUrl, '//')) {
            $basePath = rtrim($baseUrl, '/');
        }
    }

    $cached = $scheme . '://' . $host . $basePath;

    return $cached;
}

/**
 * Teljes abszolút URL (e-mail linkekhez): https://domain/útvonal
 */
function ng_absolute_url(string $pathOrUrl): string
{
    $t = trim($pathOrUrl);
    if ($t === '') {
        return '';
    }

    if (str_starts_with($t, '//')) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https:' : 'http:';

        return $scheme . $t;
    }

    $path = $t;
    $query = '';
    if (preg_match('#^https?://[^/]+(/[^?]*)(\?.*)?$#i', $t, $m)) {
        $path = $m[1] !== '' ? $m[1] : '/';
        $query = $m[2] ?? '';
    } elseif (!str_starts_with($t, '/')) {
        $path = '/' . $t;
    } elseif (($qpos = strpos($t, '?')) !== false) {
        $path = substr($t, 0, $qpos);
        $query = substr($t, $qpos);
    }

    return rtrim(ng_public_base_url(), '/') . $path . $query;
}

/**
 * Backoffice navigációs zóna: nextgen (hub, config, admin, jelszó), finance (CRM), events.
 */
function ng_nav_app_zone(): string {
    $s = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (strpos($s, '/nextgen/events/') !== false) {
        return 'events';
    }
    if (strpos($s, '/nextgen/config/') !== false || strpos($s, '/nextgen/admin/') !== false) {
        return 'nextgen';
    }
    if (preg_match('#/nextgen/(apps|jelszo)\.php$#', $s)) {
        return 'nextgen';
    }
    if (strpos($s, '/nextgen/') !== false) {
        return 'finance';
    }
    return 'finance';
}

/**
 * Backoffice UI terület a kérés útvonala alapján (nem a SITE_NAME része).
 * Alapértelmezés: Finance (szervezők, pénzügy, kontaktok, kezdőlap).
 */
function app_backoffice_area(): string {
    $s = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (strpos($s, '/nextgen/events/') !== false) {
        return 'Event Admin';
    }
    if (strpos($s, '/nextgen/apps.php') !== false || str_ends_with($s, '/apps.php')) {
        return 'Alkalmazások';
    }
    if (strpos($s, '/config/') !== false) {
        return 'Config';
    }
    if (strpos($s, '/admin/pm/') !== false) {
        return 'PM';
    }
    if (strpos($s, '/admin/') !== false) {
        return 'Admin';
    }
    if (preg_match('#/nextgen/jelszo\.php$#', $s)) {
        return 'Jelszó';
    }
    return 'Finance';
}

/**
 * Logó / böngésző cím előtagja: SITE_NAME + szóköz + terület (Finance|Admin|Config|Event Admin|Alkalmazások).
 */
function app_backoffice_brand_line(): string {
    return trim(SITE_NAME . ' ' . app_backoffice_area());
}

/**
 * Hex szín normalizálása (#RRGGBB), fallbackkel.
 */
function normalize_hex_color(?string $value, string $default = '#64748B'): string {
    $value = strtoupper(trim((string) $value));
    if (preg_match('/^#[0-9A-F]{6}$/', $value)) {
        return $value;
    }
    return strtoupper($default);
}

/**
 * Kontrasztos szövegszín adott háttérhez.
 */
function contrast_text_color(string $hexColor): string {
    $hex = ltrim(normalize_hex_color($hexColor), '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $luminance = (0.299 * $r) + (0.587 * $g) + (0.114 * $b);
    return $luminance > 160 ? '#111827' : '#FFFFFF';
}

/**
 * Visszaadja, hogy a címkék táblában elérhető-e a szín oszlop.
 */
function cimkek_has_szin(PDO $db): bool {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    try {
        $stmt = $db->query("SHOW COLUMNS FROM finance_tags LIKE 'szín'");
        $cached = (bool) $stmt->fetch();
    } catch (Throwable $e) {
        $cached = false;
    }
    return $cached;
}

/**
 * Megmondja, hogy egy tábla létezik-e.
 */
function db_table_exists(PDO $db, string $table): bool {
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        $cache[$table] = false;

        return false;
    }
    try {
        $stmt = $db->prepare('
            SELECT 1 FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
            LIMIT 1
        ');
        $stmt->execute([$table]);
        $cache[$table] = (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        try {
            $q = '`' . str_replace('`', '``', $table) . '`';
            $db->query('SELECT 1 FROM ' . $q . ' LIMIT 1');
            $cache[$table] = true;
        } catch (Throwable) {
            $cache[$table] = false;
        }
    }

    return $cache[$table];
}

/**
 * Első létező táblanév kiválasztása kompatibilitáshoz.
 */
function db_resolve_table(PDO $db, array $candidates, string $default): string {
    foreach ($candidates as $candidate) {
        if (db_table_exists($db, $candidate)) {
            return $candidate;
        }
    }
    return $default;
}

/**
 * Form érték visszaadása
 */
function old(string $key, string $default = ''): string {
    return h($_SESSION['_old'][$key] ?? $default);
}

function clearOld(): void {
    unset($_SESSION['_old']);
}

function setOld(array $data): void {
    $_SESSION['_old'] = $data;
}

/**
 * Flash üzenet
 */
function flash(string $key, ?string $msg = null): ?string {
    if ($msg !== null) {
        $_SESSION['_flash'][$key] = $msg;
        return null;
    }
    $m = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $m;
}

/**
 * CSRF token kezelők.
 */
function csrf_token(string $scope = 'default'): string {
    if (!isset($_SESSION['_csrf']) || !is_array($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = [];
    }
    if (empty($_SESSION['_csrf'][$scope]) || !is_string($_SESSION['_csrf'][$scope])) {
        $_SESSION['_csrf'][$scope] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'][$scope];
}

function csrf_input(string $scope = 'default', string $field = '_csrf'): string {
    return '<input type="hidden" name="' . h($field) . '" value="' . h(csrf_token($scope)) . '">';
}

function csrf_validate(string $scope = 'default', string $field = '_csrf'): bool {
    $token = (string) ($_POST[$field] ?? '');
    $expected = (string) ($_SESSION['_csrf'][$scope] ?? '');
    if ($token === '' || $expected === '') {
        return false;
    }

    return hash_equals($expected, $token);
}

/**
 * CSRF kötelező POST műveleteknél — érvénytelen tokennél flash + redirect.
 */
function csrf_require(string $scope = 'default', string $field = '_csrf', ?string $redirectUrl = null): void
{
    if (csrf_validate($scope, $field)) {
        return;
    }
    flash('error', 'Érvénytelen biztonsági token. Próbáld újra.');
    if ($redirectUrl !== null && $redirectUrl !== '') {
        redirect($redirectUrl);
    }
    $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    if ($ref !== '' && str_starts_with($ref, (string) (defined('BASE_URL') ? rtrim(BASE_URL, '/') : ''))) {
        redirect($ref);
    }
    redirect(nextgen_url('apps.php'));
}

/**
 * Rate limit tároló könyvtár.
 */
function rate_limit_dir(): string
{
    return dirname(__DIR__) . '/data/rate_limit';
}

/**
 * Bucket név biztonságosítása (fájlnév).
 */
function rate_limit_sanitize_bucket(string $bucket): string
{
    $clean = preg_replace('/[^a-zA-Z0-9._-]/', '_', $bucket);

    return ($clean !== null && $clean !== '') ? $clean : 'default';
}

/**
 * Egyszerű fájl-alapú rate limit (login, publikus AJAX).
 * true = engedélyezett, false = túl sok próbálkozás.
 */
function rate_limit_allow(string $bucket, int $maxAttempts, int $windowSeconds): bool
{
    $bucket = rate_limit_sanitize_bucket($bucket);
    $dir = rate_limit_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $path = $dir . '/' . $bucket . '.json';
    $now = time();
    $fp = @fopen($path, 'c+');
    if ($fp === false) {
        return true;
    }
    try {
        if (!flock($fp, LOCK_EX)) {
            return true;
        }
        $raw = stream_get_contents($fp);
        $hits = [];
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $ts) {
                    $t = (int) $ts;
                    if ($t >= $now - $windowSeconds) {
                        $hits[] = $t;
                    }
                }
            }
        }
        if (count($hits) >= $maxAttempts) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($hits));
            fflush($fp);
            flock($fp, LOCK_UN);

            return false;
        }
        $hits[] = $now;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($hits));
        fflush($fp);
        flock($fp, LOCK_UN);

        return true;
    } finally {
        fclose($fp);
    }
}

function rate_limit_client_key(string $prefix): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

    return $prefix . '_' . hash('sha256', $ip);
}

/**
 * Egy rate-limit bucket törlése (nullázás).
 */
function rate_limit_reset(string $bucket): bool
{
    $bucket = rate_limit_sanitize_bucket($bucket);
    $path = rate_limit_dir() . '/' . $bucket . '.json';
    if (!is_file($path)) {
        return true;
    }

    return @unlink($path);
}

/**
 * Összes rate-limit bucket törlése.
 *
 * @return int Törölt fájlok száma
 */
function rate_limit_reset_all(): int
{
    $dir = rate_limit_dir();
    if (!is_dir($dir)) {
        return 0;
    }
    $deleted = 0;
    foreach (glob($dir . '/*.json') ?: [] as $path) {
        if (is_file($path) && @unlink($path)) {
            $deleted++;
        }
    }

    return $deleted;
}

/**
 * Aktív rate-limit bucketek listája (admin UI).
 *
 * @return list<array{bucket: string, hits: int, oldest: int|null, newest: int|null}>
 */
function rate_limit_list(): array
{
    $dir = rate_limit_dir();
    if (!is_dir($dir)) {
        return [];
    }
    $items = [];
    foreach (glob($dir . '/*.json') ?: [] as $path) {
        if (!is_file($path)) {
            continue;
        }
        $bucket = basename($path, '.json');
        $raw = @file_get_contents($path);
        $hits = [];
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $ts) {
                    $t = (int) $ts;
                    if ($t > 0) {
                        $hits[] = $t;
                    }
                }
            }
        }
        sort($hits);
        $items[] = [
            'bucket' => $bucket,
            'hits' => count($hits),
            'oldest' => $hits[0] ?? null,
            'newest' => $hits !== [] ? $hits[array_key_last($hits)] : null,
        ];
    }
    usort($items, static fn(array $a, array $b): int => ($b['newest'] ?? 0) <=> ($a['newest'] ?? 0));

    return $items;
}

/**
 * Engedélyezett számlafájl-kiterjesztések.
 *
 * @return list<string>
 */
function finance_invoice_allowed_extensions(): array
{
    return ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'doc', 'docx'];
}

/**
 * @return array{ok: bool, ext: string, error: string}
 */
function finance_invoice_validate_upload(string $tmpPath, string $originalName): array
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION) ?: '');
    $allowed = finance_invoice_allowed_extensions();
    if ($ext === '' || !in_array($ext, $allowed, true)) {
        return ['ok' => false, 'ext' => $ext, 'error' => 'Nem engedélyezett fájltípus.'];
    }
    if ($tmpPath === '' || !is_file($tmpPath)) {
        return ['ok' => false, 'ext' => $ext, 'error' => 'Érvénytelen feltöltés.'];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmpPath);
    $mimeMap = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'doc' => ['application/msword', 'application/octet-stream'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/octet-stream',
        ],
    ];
    $okMimes = $mimeMap[$ext] ?? [];
    if ($okMimes !== [] && !in_array($mime, $okMimes, true)) {
        return ['ok' => false, 'ext' => $ext, 'error' => 'A fájl tartalma nem egyezik a kiterjesztéssel.'];
    }
    $maxBytes = 15 * 1024 * 1024;
    $size = (int) filesize($tmpPath);
    if ($size <= 0 || $size > $maxBytes) {
        return ['ok' => false, 'ext' => $ext, 'error' => 'A fájl mérete érvénytelen (max. 15 MB).'];
    }

    return ['ok' => true, 'ext' => $ext, 'error' => ''];
}

/**
 * Számla csatolmány mentése (whitelist + biztonságos fájlnév).
 * Visszatér: sikeresen feltöltött fájlok száma.
 */
function finance_store_invoice_uploads(PDO $db, int $szamlaId, array $fajlAdat): int
{
    if ($szamlaId <= 0) {
        return 0;
    }
    if (!is_dir(UPLOAD_PATH)) {
        @mkdir(UPLOAD_PATH, 0755, true);
    }
    $uploadDir = UPLOAD_PATH . '/' . $szamlaId;
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $names = is_array($fajlAdat['name'] ?? null) ? $fajlAdat['name'] : [($fajlAdat['name'] ?? '')];
    $tmp = is_array($fajlAdat['tmp_name'] ?? null) ? $fajlAdat['tmp_name'] : [($fajlAdat['tmp_name'] ?? '')];
    $errors = is_array($fajlAdat['error'] ?? null) ? $fajlAdat['error'] : [($fajlAdat['error'] ?? UPLOAD_ERR_NO_FILE)];

    $feltoltve = 0;
    $ins = $db->prepare('INSERT INTO finance_invoice_files (számla_id, eredeti_név, fájl_útvonal) VALUES (?, ?, ?)');
    for ($i = 0, $n = count($names); $i < $n; $i++) {
        if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($names[$i])) {
            continue;
        }
        $name = (string) $names[$i];
        $tmpPath = (string) ($tmp[$i] ?? '');
        $check = finance_invoice_validate_upload($tmpPath, $name);
        if (!$check['ok']) {
            continue;
        }
        $safeBase = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($name)) ?: ('file_' . $i);
        $safeBase = preg_replace('/\.[^.]+$/', '', $safeBase) ?: ('file_' . $i);
        $ujnev = $safeBase . '_' . bin2hex(random_bytes(4)) . '.' . $check['ext'];
        $cel = $uploadDir . '/' . $ujnev;
        if (!move_uploaded_file($tmpPath, $cel)) {
            continue;
        }
        @chmod($cel, 0644);
        $ins->execute([$szamlaId, $name, $szamlaId . '/' . $ujnev]);
        $feltoltve++;
    }

    return $feltoltve;
}

/**
 * Számla státusz feliratok
 */
function szamla_statusz_label(string $s): string {
    $labels = [
        'generált'   => 'Generált',
        'kiküldve'   => 'Kiküldve',
        'kiegyenlítve' => 'Kiegyenlítve',
        'egyéb'      => 'Egyéb',
        'KP'         => 'KP',
        'sztornó'    => 'Sztornó',
    ];
    return $labels[$s] ?? $s;
}

/**
 * Hónap neve
 */
function honap_nev(int $honap): string {
    $nevek = [
        1 => 'Január', 2 => 'Február', 3 => 'Március', 4 => 'Április',
        5 => 'Május', 6 => 'Június', 7 => 'Július', 8 => 'Augusztus',
        9 => 'Szeptember', 10 => 'Október', 11 => 'November', 12 => 'December',
    ];
    return $nevek[$honap] ?? (string) $honap;
}

/**
 * Rendezési link URL (listákhoz): meglévő GET paramétereket megtartja, order/dir-t beállítja
 */
function sort_url(array $params, string $order_col, string $current_order, string $current_dir): string {
    $dir = ($current_order === $order_col && $current_dir === 'asc') ? 'desc' : 'asc';
    $params['order'] = $order_col;
    $params['dir'] = $dir;
    return '?' . http_build_query(array_filter($params, function ($v) { return $v !== '' && $v !== null; }));
}

/**
 * Rendezési fejléc HTML (kattintható, nyíl jelzi az irányt)
 */
function sort_th(string $label, string $order_col, string $current_order, string $current_dir, array $params): string {
    $url = sort_url($params, $order_col, $current_order, $current_dir);
    $arrow = '';
    if ($current_order === $order_col) {
        $arrow = $current_dir === 'asc' ? ' <span class="sort-arrow" aria-hidden="true">↑</span>' : ' <span class="sort-arrow" aria-hidden="true">↓</span>';
    }
    return '<a href="' . h($url) . '" class="th-sort">' . h($label) . $arrow . '</a>';
}
