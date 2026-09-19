<?php
declare(strict_types=1);

/**
 * Latinfo.hu kezdőoldal modulok: katalógus, sorrend, kattintás- és értékelés-stat.
 */

require_once __DIR__ . '/site_home.php';

if (!function_exists('events_view_tracking_detect_bot')) {
    require_once dirname(__DIR__, 2) . '/events/lib/event_view_tracking.php';
}
if (!function_exists('events_public_traffic_detect_device')) {
    require_once dirname(__DIR__, 2) . '/events/lib/public_traffic.php';
}
if (!function_exists('events_edit_stats_params_from_request')) {
    require_once dirname(__DIR__, 2) . '/events/lib/event_edit_stats.php';
}

/**
 * @return array<string, array{
 *   label: string,
 *   menu_label: string,
 *   editable: bool,
 *   has_item_stats: bool,
 *   column: string,
 *   default_order: int,
 *   default_enabled: bool
 * }>
 */
function latinfo_home_module_catalog(): array
{
    return [
        'announcements' => [
            'label' => 'Bejelentések',
            'menu_label' => 'Bejelentések',
            'editable' => true,
            'has_item_stats' => true,
            'column' => 'rail',
            'default_order' => 10,
            'default_enabled' => true,
        ],
        'today' => [
            'label' => 'Mai események',
            'menu_label' => 'Mai események',
            'editable' => false,
            'has_item_stats' => false,
            'column' => 'calendar',
            'default_order' => 20,
            'default_enabled' => true,
        ],
        'tomorrow' => [
            'label' => 'Holnapi események',
            'menu_label' => 'Holnapi események',
            'editable' => false,
            'has_item_stats' => false,
            'column' => 'calendar',
            'default_order' => 30,
            'default_enabled' => true,
        ],
        'dj_spotlight' => [
            'label' => 'DJ ajánló',
            'menu_label' => 'DJ ajánló',
            'editable' => true,
            'has_item_stats' => true,
            'column' => 'rail',
            'default_order' => 40,
            'default_enabled' => true,
        ],
        'rating' => [
            'label' => 'Értékelés',
            'menu_label' => 'Értékelés',
            'editable' => true,
            'has_item_stats' => true,
            'column' => 'rail',
            'default_order' => 50,
            'default_enabled' => true,
        ],
    ];
}

/**
 * @return list<string>
 */
function latinfo_home_module_keys(): array
{
    return array_keys(latinfo_home_module_catalog());
}

function latinfo_home_module_label(string $key): string
{
    $catalog = latinfo_home_module_catalog();

    return (string) ($catalog[$key]['label'] ?? $key);
}

function latinfo_home_module_is_valid(string $key): bool
{
    return isset(latinfo_home_module_catalog()[$key]);
}

function latinfo_home_module_edit_url(string $key, string $query = ''): string
{
    $map = [
        'announcements' => 'modul_bejelentesek.php',
        'dj_spotlight' => 'modul_dj.php',
        'rating' => 'modul_ertekeles.php',
    ];
    $file = $map[$key] ?? 'szerkeszt.php';
    $base = nextgen_url('site/' . $file);

    return $query === '' ? $base : $base . '?' . ltrim($query, '?');
}

function latinfo_home_modules_stat_url(string $query = ''): string
{
    $base = nextgen_url('site/stat.php');

    return $query === '' ? $base : $base . '?' . ltrim($query, '?');
}

function latinfo_home_modules_ensure_schema(PDO $db): bool
{
    static $done = false;
    if ($done) {
        return true;
    }
    if (!latinfo_home_ensure_schema($db)) {
        return false;
    }

    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `latinfo_home_modules` (
                `module_key` VARCHAR(32) NOT NULL PRIMARY KEY,
                `is_enabled` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
                `sort_order` INT NOT NULL DEFAULT 0,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS `latinfo_home_module_clicks` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `module_key` VARCHAR(32) NOT NULL,
                `item_key` VARCHAR(80) NOT NULL DEFAULT '',
                `item_label` VARCHAR(200) NOT NULL DEFAULT '',
                `lang` CHAR(2) NOT NULL DEFAULT 'hu',
                `device` VARCHAR(16) NOT NULL DEFAULT 'unknown',
                `ip_hash` CHAR(64) NULL DEFAULT NULL,
                `is_bot` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `occurred_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_lh_mod_click_mod_time` (`module_key`, `occurred_at`),
                KEY `idx_lh_mod_click_item` (`module_key`, `item_key`, `occurred_at`),
                KEY `idx_lh_mod_click_bot` (`is_bot`, `occurred_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS `latinfo_home_ratings` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `stars` TINYINT UNSIGNED NOT NULL,
                `lang` CHAR(2) NOT NULL DEFAULT 'hu',
                `device` VARCHAR(16) NOT NULL DEFAULT 'unknown',
                `ip_hash` CHAR(64) NULL DEFAULT NULL,
                `is_bot` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `occurred_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_lh_rating_time` (`occurred_at`),
                KEY `idx_lh_rating_bot` (`is_bot`, `occurred_at`),
                KEY `idx_lh_rating_ip_day` (`ip_hash`, `occurred_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        latinfo_home_modules_seed_defaults($db);
        $done = true;

        return true;
    } catch (Throwable $e) {
        error_log('latinfo_home_modules_ensure_schema: ' . $e->getMessage());

        return false;
    }
}

function latinfo_home_modules_seed_defaults(PDO $db): void
{
    $st = $db->prepare('
        INSERT IGNORE INTO `latinfo_home_modules` (`module_key`, `is_enabled`, `sort_order`)
        VALUES (?, ?, ?)
    ');
    foreach (latinfo_home_module_catalog() as $key => $meta) {
        $st->execute([
            $key,
            !empty($meta['default_enabled']) ? 1 : 0,
            (int) $meta['default_order'],
        ]);
    }
}

/**
 * @return list<array{module_key: string, is_enabled: bool, sort_order: int, label: string, editable: bool, has_item_stats: bool, column: string}>
 */
function latinfo_home_modules_all(PDO $db): array
{
    $catalog = latinfo_home_module_catalog();
    $rowsByKey = [];
    try {
        $rows = $db->query('SELECT `module_key`, `is_enabled`, `sort_order` FROM `latinfo_home_modules`')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $key = (string) ($row['module_key'] ?? '');
            if (!isset($catalog[$key])) {
                continue;
            }
            $rowsByKey[$key] = $row;
        }
    } catch (Throwable $e) {
        error_log('latinfo_home_modules_all: ' . $e->getMessage());
    }

    $out = [];
    foreach ($catalog as $key => $meta) {
        $row = $rowsByKey[$key] ?? null;
        $out[] = [
            'module_key' => $key,
            'is_enabled' => $row !== null ? ((int) ($row['is_enabled'] ?? 1) === 1) : !empty($meta['default_enabled']),
            'sort_order' => $row !== null ? (int) ($row['sort_order'] ?? $meta['default_order']) : (int) $meta['default_order'],
            'label' => (string) $meta['label'],
            'editable' => !empty($meta['editable']),
            'has_item_stats' => !empty($meta['has_item_stats']),
            'column' => (string) $meta['column'],
        ];
    }
    usort($out, static function (array $a, array $b): int {
        $cmp = $a['sort_order'] <=> $b['sort_order'];

        return $cmp !== 0 ? $cmp : strcmp($a['module_key'], $b['module_key']);
    });

    return $out;
}

/**
 * @return list<array{module_key: string, is_enabled: bool, sort_order: int, label: string, editable: bool, has_item_stats: bool, column: string}>
 */
function latinfo_home_modules_enabled(PDO $db): array
{
    return array_values(array_filter(
        latinfo_home_modules_all($db),
        static fn (array $m): bool => !empty($m['is_enabled'])
    ));
}

/**
 * @param list<array{module_key?: string, is_enabled?: mixed, sort_order?: mixed}> $rows
 */
function latinfo_home_modules_save_order(PDO $db, array $rows): void
{
    $catalog = latinfo_home_module_catalog();
    $st = $db->prepare('
        INSERT INTO `latinfo_home_modules` (`module_key`, `is_enabled`, `sort_order`)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
            `is_enabled` = VALUES(`is_enabled`),
            `sort_order` = VALUES(`sort_order`)
    ');
    $seen = [];
    foreach ($rows as $i => $row) {
        $key = trim((string) ($row['module_key'] ?? ''));
        if (!isset($catalog[$key]) || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $sort = filter_var($row['sort_order'] ?? (($i + 1) * 10), FILTER_VALIDATE_INT);
        $sortOrder = ($sort === false) ? (($i + 1) * 10) : (int) $sort;
        $enabled = !empty($row['is_enabled']) ? 1 : 0;
        $st->execute([$key, $enabled, $sortOrder]);
    }
    foreach ($catalog as $key => $meta) {
        if (isset($seen[$key])) {
            continue;
        }
        $st->execute([$key, !empty($meta['default_enabled']) ? 1 : 0, (int) $meta['default_order']]);
    }
}

function latinfo_home_module_normalize_item_key(mixed $raw): string
{
    $key = trim((string) $raw);
    $key = (string) preg_replace('/[^a-zA-Z0-9:_-]/', '', $key);
    if ($key === '' || strlen($key) > 80) {
        return '';
    }

    return $key;
}

function latinfo_home_module_click_should_record(): bool
{
    if (function_exists('events_view_tracking_should_record')) {
        return events_view_tracking_should_record();
    }

    return !(function_exists('isLoggedIn') && isLoggedIn());
}

/**
 * @return array{ok: bool, recorded: bool}
 */
function latinfo_home_module_track_click(
    PDO $db,
    string $moduleKey,
    string $itemKey = '',
    string $itemLabel = '',
    string $lang = 'hu'
): array {
    if (!latinfo_home_module_is_valid($moduleKey)) {
        return ['ok' => false, 'recorded' => false];
    }
    $itemKey = latinfo_home_module_normalize_item_key($itemKey);
    $itemLabel = latinfo_home_clamp($itemLabel, 200);
    $lang = strtolower(trim($lang)) === 'en' ? 'en' : 'hu';

    if (!latinfo_home_module_click_should_record()) {
        return ['ok' => true, 'recorded' => false];
    }

    $isBot = events_view_tracking_detect_bot() ? 1 : 0;
    $device = events_public_traffic_detect_device();
    $ipHash = events_view_tracking_ip_hash();

    try {
        $st = $db->prepare('
            INSERT INTO `latinfo_home_module_clicks`
                (`module_key`, `item_key`, `item_label`, `lang`, `device`, `ip_hash`, `is_bot`)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $st->execute([$moduleKey, $itemKey, $itemLabel, $lang, $device, $ipHash, $isBot]);

        return ['ok' => true, 'recorded' => true];
    } catch (Throwable $e) {
        error_log('latinfo_home_module_track_click: ' . $e->getMessage());

        return ['ok' => false, 'recorded' => false];
    }
}

/**
 * @return array{average: float, count: int, stars_dist: array<int, int>}
 */
function latinfo_home_rating_summary(PDO $db, bool $humansOnly = true): array
{
    $dist = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
    $empty = ['average' => 0.0, 'count' => 0, 'stars_dist' => $dist];
    try {
        $sql = '
            SELECT `stars`, COUNT(*) AS cnt
            FROM `latinfo_home_ratings`
        ';
        if ($humansOnly) {
            $sql .= ' WHERE `is_bot` = 0';
        }
        $sql .= ' GROUP BY `stars`';
        $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $sum = 0;
        $count = 0;
        foreach ($rows as $row) {
            $stars = (int) ($row['stars'] ?? 0);
            $cnt = (int) ($row['cnt'] ?? 0);
            if ($stars < 1 || $stars > 5 || $cnt <= 0) {
                continue;
            }
            $dist[$stars] = $cnt;
            $sum += $stars * $cnt;
            $count += $cnt;
        }
        if ($count <= 0) {
            return $empty;
        }

        return [
            'average' => round($sum / $count, 1),
            'count' => $count,
            'stars_dist' => $dist,
        ];
    } catch (Throwable $e) {
        error_log('latinfo_home_rating_summary: ' . $e->getMessage());

        return $empty;
    }
}

/**
 * @return array{ok: bool, recorded: bool, average: float, count: int, message: string}
 */
function latinfo_home_rating_submit(PDO $db, int $stars, string $lang = 'hu'): array
{
    $summary = latinfo_home_rating_summary($db, true);
    $base = [
        'ok' => true,
        'recorded' => false,
        'average' => (float) $summary['average'],
        'count' => (int) $summary['count'],
        'message' => '',
    ];

    if ($stars < 1 || $stars > 5) {
        return array_merge($base, ['ok' => false, 'message' => 'Érvénytelen értékelés.']);
    }

    $lang = strtolower(trim($lang)) === 'en' ? 'en' : 'hu';
    $isBot = events_view_tracking_detect_bot() ? 1 : 0;
    $device = events_public_traffic_detect_device();
    $ipHash = events_view_tracking_ip_hash();

    if (!latinfo_home_module_click_should_record() && $isBot === 0) {
        // Admin előnézet: ne torzítsa a statot, de adja vissza az átlagot.
        $base['message'] = 'Az értékelés admin munkamenetben nem kerül mentésre.';

        return $base;
    }

    try {
        if ($isBot === 0 && $ipHash !== null) {
            $dayStart = (new DateTimeImmutable('today'))->format('Y-m-d 00:00:00');
            $check = $db->prepare('
                SELECT `id` FROM `latinfo_home_ratings`
                WHERE `ip_hash` = ? AND `is_bot` = 0 AND `occurred_at` >= ?
                ORDER BY `id` DESC LIMIT 1
            ');
            $check->execute([$ipHash, $dayStart]);
            $existingId = (int) ($check->fetchColumn() ?: 0);
            if ($existingId > 0) {
                $upd = $db->prepare('
                    UPDATE `latinfo_home_ratings`
                    SET `stars` = ?, `lang` = ?, `device` = ?, `occurred_at` = NOW()
                    WHERE `id` = ?
                ');
                $upd->execute([$stars, $lang, $device, $existingId]);
            } else {
                $ins = $db->prepare('
                    INSERT INTO `latinfo_home_ratings`
                        (`stars`, `lang`, `device`, `ip_hash`, `is_bot`)
                    VALUES (?, ?, ?, ?, 0)
                ');
                $ins->execute([$stars, $lang, $device, $ipHash]);
            }
        } else {
            $ins = $db->prepare('
                INSERT INTO `latinfo_home_ratings`
                    (`stars`, `lang`, `device`, `ip_hash`, `is_bot`)
                VALUES (?, ?, ?, ?, ?)
            ');
            $ins->execute([$stars, $lang, $device, $ipHash, $isBot]);
        }

        latinfo_home_module_track_click($db, 'rating', 'stars:' . $stars, $stars . ' csillag', $lang);

        $summary = latinfo_home_rating_summary($db, true);

        return [
            'ok' => true,
            'recorded' => $isBot === 0,
            'average' => (float) $summary['average'],
            'count' => (int) $summary['count'],
            'message' => '',
        ];
    } catch (Throwable $e) {
        error_log('latinfo_home_rating_submit: ' . $e->getMessage());

        return array_merge($base, ['ok' => false, 'message' => 'Mentés sikertelen.']);
    }
}

/**
 * @return array{date_from: string, date_to: string, module: string, visitor: string, lang: string, device: string}
 */
function latinfo_home_module_stats_params_from_request(array $query): array
{
    $base = events_edit_stats_params_from_request($query);
    $module = trim((string) ($query['module'] ?? 'all'));
    if ($module !== 'all' && !latinfo_home_module_is_valid($module)) {
        $module = 'all';
    }
    $visitor = strtolower(trim((string) ($query['visitor'] ?? 'human')));
    if (!in_array($visitor, ['all', 'human', 'bot'], true)) {
        $visitor = 'human';
    }
    $lang = strtolower(trim((string) ($query['traf_lang'] ?? 'all')));
    if (!in_array($lang, ['all', 'hu', 'en'], true)) {
        $lang = 'all';
    }
    $device = strtolower(trim((string) ($query['device'] ?? 'all')));
    if (!in_array($device, ['all', 'desktop', 'mobile', 'tablet', 'unknown'], true)) {
        $device = 'all';
    }

    return [
        'date_from' => (string) $base['date_from'],
        'date_to' => (string) $base['date_to'],
        'module' => $module,
        'visitor' => $visitor,
        'lang' => $lang,
        'device' => $device,
    ];
}

/**
 * @param array{date_from: string, date_to: string, module?: string, visitor?: string, lang?: string, device?: string} $params
 * @return array{sql: string, bind: list<mixed>}
 */
function latinfo_home_module_stats_where(array $params, ?string $forceModule = null): array
{
    $sql = ['c.`occurred_at` >= ?', 'c.`occurred_at` < ?'];
    $bind = [
        $params['date_from'] . ' 00:00:00',
        (new DateTimeImmutable($params['date_to']))->modify('+1 day')->format('Y-m-d') . ' 00:00:00',
    ];

    $module = $forceModule ?? (string) ($params['module'] ?? 'all');
    if ($module !== 'all' && latinfo_home_module_is_valid($module)) {
        $sql[] = 'c.`module_key` = ?';
        $bind[] = $module;
    }

    $visitor = (string) ($params['visitor'] ?? 'all');
    if ($visitor === 'human') {
        $sql[] = 'c.`is_bot` = 0';
    } elseif ($visitor === 'bot') {
        $sql[] = 'c.`is_bot` = 1';
    }

    $lang = (string) ($params['lang'] ?? 'all');
    if ($lang === 'hu' || $lang === 'en') {
        $sql[] = 'c.`lang` = ?';
        $bind[] = $lang;
    }

    $device = (string) ($params['device'] ?? 'all');
    if (in_array($device, ['desktop', 'mobile', 'tablet', 'unknown'], true)) {
        $sql[] = 'c.`device` = ?';
        $bind[] = $device;
    }

    return ['sql' => implode(' AND ', $sql), 'bind' => $bind];
}

function latinfo_home_module_stats_earliest_date(PDO $db): ?string
{
    try {
        $raw = $db->query('SELECT MIN(`occurred_at`) FROM `latinfo_home_module_clicks`')->fetchColumn();
        $raw = is_string($raw) ? trim($raw) : '';
        if ($raw === '') {
            return null;
        }

        return (new DateTimeImmutable($raw))->format('Y-m-d');
    } catch (Throwable) {
        return null;
    }
}

/**
 * Kezdőoldal modul-kattintás áttekintő (mint a publikus forgalom-stat).
 *
 * @param array{date_from: string, date_to: string, module: string, visitor: string, lang: string, device: string} $params
 * @return array<string, mixed>
 */
function latinfo_home_module_overview_stats(PDO $db, array $params): array
{
    $empty = [
        'table_ready' => true,
        'totals' => [
            'clicks' => 0,
            'clicks_human' => 0,
            'clicks_bot' => 0,
            'unique_human' => 0,
            'modules_hit' => 0,
        ],
        'modules' => [],
        'chart' => ['labels' => [], 'datasets' => []],
        'granularity' => 'day',
    ];

    try {
        $where = latinfo_home_module_stats_where($params);
        $st = $db->prepare("
            SELECT
                COUNT(*) AS clicks,
                SUM(CASE WHEN c.`is_bot` = 0 THEN 1 ELSE 0 END) AS clicks_human,
                SUM(CASE WHEN c.`is_bot` = 1 THEN 1 ELSE 0 END) AS clicks_bot,
                COUNT(DISTINCT CASE WHEN c.`is_bot` = 0 AND c.`ip_hash` IS NOT NULL THEN c.`ip_hash` END) AS unique_human,
                COUNT(DISTINCT c.`module_key`) AS modules_hit
            FROM `latinfo_home_module_clicks` c
            WHERE {$where['sql']}
        ");
        $st->execute($where['bind']);
        $tot = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        $stMod = $db->prepare("
            SELECT
                c.`module_key`,
                COUNT(*) AS clicks,
                SUM(CASE WHEN c.`is_bot` = 0 THEN 1 ELSE 0 END) AS clicks_human,
                SUM(CASE WHEN c.`is_bot` = 1 THEN 1 ELSE 0 END) AS clicks_bot,
                COUNT(DISTINCT CASE WHEN c.`is_bot` = 0 AND c.`ip_hash` IS NOT NULL THEN c.`ip_hash` END) AS unique_human
            FROM `latinfo_home_module_clicks` c
            WHERE {$where['sql']}
            GROUP BY c.`module_key`
            ORDER BY clicks_human DESC, clicks DESC
        ");
        $stMod->execute($where['bind']);
        $moduleRows = [];
        foreach ($stMod->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $key = (string) ($row['module_key'] ?? '');
            $moduleRows[] = [
                'module_key' => $key,
                'label' => latinfo_home_module_label($key),
                'clicks' => (int) ($row['clicks'] ?? 0),
                'clicks_human' => (int) ($row['clicks_human'] ?? 0),
                'clicks_bot' => (int) ($row['clicks_bot'] ?? 0),
                'unique_human' => (int) ($row['unique_human'] ?? 0),
            ];
        }

        $from = new DateTimeImmutable($params['date_from']);
        $to = new DateTimeImmutable($params['date_to']);
        $days = max(1, (int) $from->diff($to)->days + 1);
        $granularity = $days > 120 ? 'month' : ($days > 45 ? 'week' : 'day');
        $bucketExpr = match ($granularity) {
            'month' => "DATE_FORMAT(c.`occurred_at`, '%Y-%m-01')",
            'week' => 'DATE(DATE_SUB(c.`occurred_at`, INTERVAL WEEKDAY(c.`occurred_at`) DAY))',
            default => 'DATE(c.`occurred_at`)',
        };

        $stChart = $db->prepare("
            SELECT {$bucketExpr} AS bucket, c.`module_key`, COUNT(*) AS cnt
            FROM `latinfo_home_module_clicks` c
            WHERE {$where['sql']}
            GROUP BY bucket, c.`module_key`
            ORDER BY bucket ASC
        ");
        $stChart->execute($where['bind']);
        $chartRows = $stChart->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $labels = [];
        $series = [];
        $colors = [
            'announcements' => '#3d6b4f',
            'today' => '#2f6f8f',
            'tomorrow' => '#5a8a6a',
            'dj_spotlight' => '#8b5a9e',
            'rating' => '#c45c26',
        ];
        foreach ($chartRows as $row) {
            $bucket = (string) ($row['bucket'] ?? '');
            $key = (string) ($row['module_key'] ?? '');
            if ($bucket === '' || $key === '') {
                continue;
            }
            if (!in_array($bucket, $labels, true)) {
                $labels[] = $bucket;
            }
            if (!isset($series[$key])) {
                $series[$key] = [];
            }
            $series[$key][$bucket] = (int) ($row['cnt'] ?? 0);
        }
        $datasets = [];
        foreach ($series as $key => $byBucket) {
            $data = [];
            foreach ($labels as $label) {
                $data[] = (int) ($byBucket[$label] ?? 0);
            }
            $datasets[] = [
                'label' => latinfo_home_module_label($key),
                'data' => $data,
                'color' => $colors[$key] ?? '#6b7280',
                'total' => array_sum($data),
            ];
        }

        return [
            'table_ready' => true,
            'totals' => [
                'clicks' => (int) ($tot['clicks'] ?? 0),
                'clicks_human' => (int) ($tot['clicks_human'] ?? 0),
                'clicks_bot' => (int) ($tot['clicks_bot'] ?? 0),
                'unique_human' => (int) ($tot['unique_human'] ?? 0),
                'modules_hit' => (int) ($tot['modules_hit'] ?? 0),
            ],
            'modules' => $moduleRows,
            'chart' => ['labels' => $labels, 'datasets' => $datasets],
            'granularity' => $granularity,
        ];
    } catch (Throwable $e) {
        error_log('latinfo_home_module_overview_stats: ' . $e->getMessage());
        $empty['table_ready'] = false;

        return $empty;
    }
}

/**
 * Egy modul elemeinek kattintás-statja (szerkesztő aljára).
 *
 * @param array{date_from: string, date_to: string, module?: string, visitor?: string, lang?: string, device?: string} $params
 * @return array<string, mixed>
 */
function latinfo_home_module_item_stats(PDO $db, string $moduleKey, array $params): array
{
    $empty = [
        'table_ready' => true,
        'totals' => [
            'clicks' => 0,
            'clicks_human' => 0,
            'clicks_bot' => 0,
            'unique_human' => 0,
            'items' => 0,
        ],
        'items' => [],
        'chart' => ['labels' => [], 'datasets' => []],
    ];
    if (!latinfo_home_module_is_valid($moduleKey)) {
        return $empty;
    }

    try {
        $where = latinfo_home_module_stats_where($params, $moduleKey);
        $st = $db->prepare("
            SELECT
                COUNT(*) AS clicks,
                SUM(CASE WHEN c.`is_bot` = 0 THEN 1 ELSE 0 END) AS clicks_human,
                SUM(CASE WHEN c.`is_bot` = 1 THEN 1 ELSE 0 END) AS clicks_bot,
                COUNT(DISTINCT CASE WHEN c.`is_bot` = 0 AND c.`ip_hash` IS NOT NULL THEN c.`ip_hash` END) AS unique_human,
                COUNT(DISTINCT NULLIF(c.`item_key`, '')) AS items
            FROM `latinfo_home_module_clicks` c
            WHERE {$where['sql']}
        ");
        $st->execute($where['bind']);
        $tot = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        $stItems = $db->prepare("
            SELECT
                c.`item_key`,
                MAX(NULLIF(c.`item_label`, '')) AS item_label,
                COUNT(*) AS clicks,
                SUM(CASE WHEN c.`is_bot` = 0 THEN 1 ELSE 0 END) AS clicks_human,
                SUM(CASE WHEN c.`is_bot` = 1 THEN 1 ELSE 0 END) AS clicks_bot,
                COUNT(DISTINCT CASE WHEN c.`is_bot` = 0 AND c.`ip_hash` IS NOT NULL THEN c.`ip_hash` END) AS unique_human
            FROM `latinfo_home_module_clicks` c
            WHERE {$where['sql']} AND c.`item_key` <> ''
            GROUP BY c.`item_key`
            ORDER BY clicks_human DESC, clicks DESC
            LIMIT 100
        ");
        $stItems->execute($where['bind']);
        $items = [];
        foreach ($stItems->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $itemKey = (string) ($row['item_key'] ?? '');
            $label = trim((string) ($row['item_label'] ?? ''));
            if ($label === '') {
                $label = $itemKey;
            }
            $items[] = [
                'item_key' => $itemKey,
                'label' => $label,
                'clicks' => (int) ($row['clicks'] ?? 0),
                'clicks_human' => (int) ($row['clicks_human'] ?? 0),
                'clicks_bot' => (int) ($row['clicks_bot'] ?? 0),
                'unique_human' => (int) ($row['unique_human'] ?? 0),
            ];
        }

        $stChart = $db->prepare("
            SELECT DATE(c.`occurred_at`) AS bucket, COUNT(*) AS cnt
            FROM `latinfo_home_module_clicks` c
            WHERE {$where['sql']}
            GROUP BY bucket
            ORDER BY bucket ASC
        ");
        $stChart->execute($where['bind']);
        $labels = [];
        $data = [];
        foreach ($stChart->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $labels[] = (string) ($row['bucket'] ?? '');
            $data[] = (int) ($row['cnt'] ?? 0);
        }

        return [
            'table_ready' => true,
            'totals' => [
                'clicks' => (int) ($tot['clicks'] ?? 0),
                'clicks_human' => (int) ($tot['clicks_human'] ?? 0),
                'clicks_bot' => (int) ($tot['clicks_bot'] ?? 0),
                'unique_human' => (int) ($tot['unique_human'] ?? 0),
                'items' => (int) ($tot['items'] ?? 0),
            ],
            'items' => $items,
            'chart' => [
                'labels' => $labels,
                'datasets' => [[
                    'label' => 'Emberi kattintások',
                    'data' => $data,
                    'color' => '#3d6b4f',
                    'total' => array_sum($data),
                ]],
            ],
        ];
    } catch (Throwable $e) {
        error_log('latinfo_home_module_item_stats: ' . $e->getMessage());
        $empty['table_ready'] = false;

        return $empty;
    }
}

/**
 * @return array{date_from: string, date_to: string, visitor: string}
 */
function latinfo_home_rating_stats_params_from_request(array $query): array
{
    $base = events_edit_stats_params_from_request($query);
    $visitor = strtolower(trim((string) ($query['visitor'] ?? 'human')));
    if (!in_array($visitor, ['all', 'human', 'bot'], true)) {
        $visitor = 'human';
    }

    return [
        'date_from' => (string) $base['date_from'],
        'date_to' => (string) $base['date_to'],
        'visitor' => $visitor,
    ];
}

/**
 * @param array{date_from: string, date_to: string, visitor: string} $params
 * @return array<string, mixed>
 */
function latinfo_home_rating_admin_stats(PDO $db, array $params): array
{
    $empty = [
        'table_ready' => true,
        'totals' => [
            'ratings' => 0,
            'ratings_human' => 0,
            'ratings_bot' => 0,
            'average_human' => 0.0,
            'unique_human' => 0,
        ],
        'stars' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
        'chart' => ['labels' => [], 'data' => []],
    ];

    try {
        $sql = ['r.`occurred_at` >= ?', 'r.`occurred_at` < ?'];
        $bind = [
            $params['date_from'] . ' 00:00:00',
            (new DateTimeImmutable($params['date_to']))->modify('+1 day')->format('Y-m-d') . ' 00:00:00',
        ];
        $visitor = (string) ($params['visitor'] ?? 'human');
        if ($visitor === 'human') {
            $sql[] = 'r.`is_bot` = 0';
        } elseif ($visitor === 'bot') {
            $sql[] = 'r.`is_bot` = 1';
        }
        $where = implode(' AND ', $sql);

        $st = $db->prepare("
            SELECT
                COUNT(*) AS ratings,
                SUM(CASE WHEN r.`is_bot` = 0 THEN 1 ELSE 0 END) AS ratings_human,
                SUM(CASE WHEN r.`is_bot` = 1 THEN 1 ELSE 0 END) AS ratings_bot,
                AVG(CASE WHEN r.`is_bot` = 0 THEN r.`stars` END) AS average_human,
                COUNT(DISTINCT CASE WHEN r.`is_bot` = 0 AND r.`ip_hash` IS NOT NULL THEN r.`ip_hash` END) AS unique_human
            FROM `latinfo_home_ratings` r
            WHERE {$where}
        ");
        $st->execute($bind);
        $tot = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        $stStars = $db->prepare("
            SELECT r.`stars`, COUNT(*) AS cnt
            FROM `latinfo_home_ratings` r
            WHERE {$where}
            GROUP BY r.`stars`
        ");
        $stStars->execute($bind);
        $stars = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($stStars->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $s = (int) ($row['stars'] ?? 0);
            if ($s >= 1 && $s <= 5) {
                $stars[$s] = (int) ($row['cnt'] ?? 0);
            }
        }

        $stChart = $db->prepare("
            SELECT DATE(r.`occurred_at`) AS bucket, COUNT(*) AS cnt
            FROM `latinfo_home_ratings` r
            WHERE {$where} AND r.`is_bot` = 0
            GROUP BY bucket
            ORDER BY bucket ASC
        ");
        $stChart->execute($bind);
        $labels = [];
        $data = [];
        foreach ($stChart->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $labels[] = (string) ($row['bucket'] ?? '');
            $data[] = (int) ($row['cnt'] ?? 0);
        }

        return [
            'table_ready' => true,
            'totals' => [
                'ratings' => (int) ($tot['ratings'] ?? 0),
                'ratings_human' => (int) ($tot['ratings_human'] ?? 0),
                'ratings_bot' => (int) ($tot['ratings_bot'] ?? 0),
                'average_human' => round((float) ($tot['average_human'] ?? 0), 1),
                'unique_human' => (int) ($tot['unique_human'] ?? 0),
            ],
            'stars' => $stars,
            'chart' => ['labels' => $labels, 'data' => $data],
        ];
    } catch (Throwable $e) {
        error_log('latinfo_home_rating_admin_stats: ' . $e->getMessage());
        $empty['table_ready'] = false;

        return $empty;
    }
}

/**
 * @return array<string, string>
 */
function latinfo_home_rating_strings(string $lang): array
{
    if ($lang === 'en') {
        return [
            'title' => 'Rate Latinfo.hu',
            'aria' => 'Rate Latinfo.hu with 1 to 5 stars',
            'star_aria' => 'Rate %d out of 5',
            'thanks' => 'Thanks for your rating!',
            'average' => 'Average: %s based on %d ratings',
            'prompt' => 'How do you like Latinfo.hu?',
        ];
    }

    return [
        'title' => 'Értékeld a Latinfo.hu-t',
        'aria' => 'Értékeld a Latinfo.hu oldalt 1–5 csillaggal',
        'star_aria' => '%d csillag az 5-ből',
        'thanks' => 'Köszönjük az értékelést!',
        'average' => 'Átlag: %s · %d értékelés alapján',
        'prompt' => 'Mennyire tetszik a Latinfo.hu?',
    ];
}
