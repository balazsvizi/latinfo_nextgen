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
        'esemény' => site_url('events/szerkeszt.php?id='),
        'helyszín' => site_url('events/venue_szerkeszt.php?id='),
        'címke' => null, // csak lista, nincs egy tétel oldal
        'kontakt_típus' => null, // csak lista, nincs egy tétel oldal
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
 * Backoffice navigációs zóna: nextgen (hub, config, admin, jelszó), finance (CRM), events.
 */
function ng_nav_app_zone(): string {
    $s = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (strpos($s, '/events/') !== false) {
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
    if (strpos($s, '/events/') !== false) {
        return 'Event Admin';
    }
    if (strpos($s, '/nextgen/apps.php') !== false || str_ends_with($s, '/apps.php')) {
        return 'Alkalmazások';
    }
    if (strpos($s, '/config/') !== false) {
        return 'Config';
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
    try {
        $stmt = $db->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        $cache[$table] = (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        $cache[$table] = false;
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
