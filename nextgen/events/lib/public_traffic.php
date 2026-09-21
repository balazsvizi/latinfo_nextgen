<?php
declare(strict_types=1);

require_once __DIR__ . '/event_view_tracking.php';

const EVENTS_PUBLIC_TRAFFIC_PAGE_VIEW = 'page_view';
const EVENTS_PUBLIC_TRAFFIC_NAV_CLICK = 'nav_click';

/**
 * Beégetett / nyilvános oldal kulcsok.
 *
 * @return array<string, array{label: string, group: string, color: string}>
 */
function events_public_traffic_page_catalog(): array
{
    return [
        'home' => ['label' => 'Főoldal', 'group' => 'hub', 'color' => '#6d8f63'],
        'calendar' => ['label' => 'Naptár', 'group' => 'hub', 'color' => '#3d6b4f'],
        'list' => ['label' => 'Eseménylista', 'group' => 'hub', 'color' => '#2f6f8f'],
        'map' => ['label' => 'Térkép', 'group' => 'hub', 'color' => '#5a8a6a'],
        'djs' => ['label' => 'DJ lista', 'group' => 'hub', 'color' => '#8b5a9e'],
        'organizers' => ['label' => 'Szervezők lista', 'group' => 'hub', 'color' => '#c45c26'],
        'partners' => ['label' => 'Partnereink', 'group' => 'hub', 'color' => '#8a6d4f'],
    ];
}

/**
 * Menü / chrome kattintás kulcsok.
 *
 * @return array<string, string>
 */
function events_public_traffic_nav_catalog(): array
{
    return [
        'calendar' => 'Naptár',
        'calendar-month' => 'Havi naptár',
        'calendar-list' => 'Eseménylista',
        'djs' => 'DJ-k',
        'organizers' => 'Szervezők',
        'latinfo' => 'Latinfo.hu',
        'partners' => 'Partnereink',
        'view-cal' => 'Nézet: naptár',
        'view-list' => 'Nézet: lista',
        'view-map' => 'Nézet: térkép',
        'logo' => 'Logó',
        'lang-hu' => 'Nyelv: HU',
        'lang-en' => 'Nyelv: EN',
        'lang-mcal' => 'Mobil / klasszikus naptár',
        'home-calendar-cta' => 'Naptár gomb (kezdőoldal)',
    ];
}

/**
 * @return list<string>
 */
function events_public_traffic_page_keys(): array
{
    return array_keys(events_public_traffic_page_catalog());
}

/**
 * @return list<string>
 */
function events_public_traffic_nav_keys(): array
{
    return array_keys(events_public_traffic_nav_catalog());
}

function events_public_traffic_page_label(string $pageKey): string
{
    $catalog = events_public_traffic_page_catalog();

    return (string) ($catalog[$pageKey]['label'] ?? $pageKey);
}

function events_public_traffic_nav_label(string $navKey, ?PDO $db = null): string
{
    $catalog = events_public_traffic_nav_catalog();
    if (isset($catalog[$navKey])) {
        return $catalog[$navKey];
    }
    if ($db instanceof PDO) {
        try {
            $st = $db->prepare(
                'SELECT `label_hu` FROM `events_public_nav_items` WHERE `menu_key` = ? LIMIT 1'
            );
            $st->execute([$navKey]);
            $label = trim((string) $st->fetchColumn());
            if ($label !== '') {
                return $label;
            }
        } catch (Throwable) {
            // A menütábla opcionális.
        }
    }

    return $navKey;
}

function events_public_traffic_page_color(string $pageKey): string
{
    $catalog = events_public_traffic_page_catalog();

    return (string) ($catalog[$pageKey]['color'] ?? '#6b7280');
}

function events_public_traffic_normalize_page_key(mixed $raw): string
{
    $key = trim((string) $raw);

    return in_array($key, events_public_traffic_page_keys(), true) ? $key : '';
}

function events_public_traffic_normalize_nav_key(mixed $raw): string
{
    $key = strtolower(trim((string) $raw));
    $key = (string) preg_replace('/[^a-z0-9_-]/', '', $key);
    if ($key === '' || strlen($key) > 64) {
        return '';
    }

    return $key;
}

function events_public_traffic_normalize_lang(mixed $raw): string
{
    return strtolower(trim((string) $raw)) === 'en' ? 'en' : 'hu';
}

function events_public_traffic_normalize_device(mixed $raw): string
{
    $v = strtolower(trim((string) $raw));

    return in_array($v, ['desktop', 'mobile', 'tablet', 'unknown'], true) ? $v : 'unknown';
}

function events_public_traffic_detect_device(?string $userAgent = null): string
{
    $ua = trim($userAgent ?? (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($ua === '') {
        return 'unknown';
    }
    if (preg_match('/iPad|Tablet|PlayBook|Silk/i', $ua) === 1
        || (preg_match('/Android/i', $ua) === 1 && preg_match('/Mobile/i', $ua) !== 1)
    ) {
        return 'tablet';
    }
    if (preg_match('/Mobile|iPhone|Android|webOS|BlackBerry|IEMobile|Opera Mini/i', $ua) === 1) {
        return 'mobile';
    }

    return 'desktop';
}

function events_public_traffic_referrer_host(): string
{
    $ref = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
    if ($ref === '') {
        return '';
    }
    $host = strtolower((string) (parse_url($ref, PHP_URL_HOST) ?? ''));
    if ($host === '') {
        return '';
    }
    $own = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $own = (string) preg_replace('/:\d+$/', '', $own);
    if ($own !== '' && ($host === $own || str_ends_with($host, '.' . $own))) {
        return '';
    }
    if (function_exists('site_url')) {
        $siteHost = strtolower((string) (parse_url(site_url(''), PHP_URL_HOST) ?? ''));
        if ($siteHost !== '' && ($host === $siteHost || str_ends_with($host, '.' . $siteHost))) {
            return '';
        }
    }

    return mb_substr($host, 0, 191);
}

function events_public_traffic_tables_ready(PDO $db, bool $refresh = false): bool
{
    static $ready = null;
    if ($refresh) {
        $ready = null;
    }
    if ($ready !== null) {
        return $ready;
    }

    try {
        $db->query('SELECT 1 FROM `events_public_traffic` LIMIT 1');
        $ready = true;
    } catch (Throwable) {
        $ready = false;
    }

    return $ready;
}

function events_public_traffic_ensure_schema(PDO $db): bool
{
    static $done = false;
    if ($done) {
        return events_public_traffic_tables_ready($db);
    }

    if (events_public_traffic_tables_ready($db)) {
        $done = true;

        return true;
    }

    try {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS `events_public_traffic` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `occurred_at` DATETIME NOT NULL,
                `event_type` VARCHAR(16) NOT NULL,
                `page_key` VARCHAR(32) NOT NULL DEFAULT \'\',
                `nav_key` VARCHAR(64) NOT NULL DEFAULT \'\',
                `entity_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `entity_label` VARCHAR(191) NOT NULL DEFAULT \'\',
                `lang` CHAR(2) NOT NULL DEFAULT \'hu\',
                `view_mode` VARCHAR(16) NOT NULL DEFAULT \'\',
                `device` VARCHAR(16) NOT NULL DEFAULT \'unknown\',
                `referrer_host` VARCHAR(191) NOT NULL DEFAULT \'\',
                `ip_hash` CHAR(64) NULL DEFAULT NULL,
                `is_bot` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_traffic_occurred` (`occurred_at`),
                KEY `idx_traffic_type_page` (`event_type`, `page_key`, `occurred_at`),
                KEY `idx_traffic_nav` (`event_type`, `nav_key`, `occurred_at`),
                KEY `idx_traffic_bot` (`is_bot`, `occurred_at`),
                KEY `idx_traffic_lang` (`lang`, `occurred_at`),
                KEY `idx_traffic_device` (`device`, `occurred_at`),
                KEY `idx_traffic_entity` (`page_key`, `entity_id`, `occurred_at`),
                KEY `idx_traffic_ip` (`ip_hash`, `occurred_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    } catch (Throwable $e) {
        error_log('events_public_traffic_ensure_schema: ' . $e->getMessage());

        return events_public_traffic_tables_ready($db, true);
    }

    $ok = events_public_traffic_tables_ready($db, true);
    if ($ok) {
        $done = true;
    }

    return $ok;
}

/**
 * @param array{
 *   event_type: string,
 *   page_key?: string,
 *   nav_key?: string,
 *   entity_id?: int,
 *   entity_label?: string,
 *   lang?: string,
 *   view_mode?: string
 * } $row
 */
function events_public_traffic_record(PDO $db, array $row): void
{
    if (!events_view_tracking_should_record()) {
        return;
    }
    if (!events_public_traffic_ensure_schema($db)) {
        return;
    }

    $eventType = (string) ($row['event_type'] ?? '');
    if (!in_array($eventType, [EVENTS_PUBLIC_TRAFFIC_PAGE_VIEW, EVENTS_PUBLIC_TRAFFIC_NAV_CLICK], true)) {
        return;
    }

    $pageKey = events_public_traffic_normalize_page_key($row['page_key'] ?? '');
    $navKey = events_public_traffic_normalize_nav_key($row['nav_key'] ?? '');
    if ($eventType === EVENTS_PUBLIC_TRAFFIC_PAGE_VIEW && $pageKey === '') {
        return;
    }
    if ($eventType === EVENTS_PUBLIC_TRAFFIC_NAV_CLICK && $navKey === '') {
        return;
    }

    $viewMode = strtolower(trim((string) ($row['view_mode'] ?? '')));
    if (!in_array($viewMode, ['cal', 'mcal', 'list', 'map', ''], true)) {
        $viewMode = '';
    }

    $label = trim((string) ($row['entity_label'] ?? ''));
    if (function_exists('mb_substr')) {
        $label = mb_substr($label, 0, 191, 'UTF-8');
    } else {
        $label = substr($label, 0, 191);
    }

    try {
        $stmt = $db->prepare(
            'INSERT INTO `events_public_traffic`
                (`occurred_at`, `event_type`, `page_key`, `nav_key`, `entity_id`, `entity_label`,
                 `lang`, `view_mode`, `device`, `referrer_host`, `ip_hash`, `is_bot`)
             VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $eventType,
            $pageKey,
            $navKey,
            max(0, (int) ($row['entity_id'] ?? 0)),
            $label,
            events_public_traffic_normalize_lang($row['lang'] ?? 'hu'),
            $viewMode,
            events_public_traffic_detect_device(),
            events_public_traffic_referrer_host(),
            events_view_tracking_ip_hash(),
            events_view_tracking_detect_bot() ? 1 : 0,
        ]);
    } catch (Throwable) {
        // Ne törjük a nyilvános oldalt.
    }
}

/**
 * @param array{entity_id?: int, entity_label?: string, view_mode?: string} $extra
 */
function events_public_traffic_hit(PDO $db, string $pageKey, string $lang, array $extra = []): void
{
    events_public_traffic_record($db, [
        'event_type' => EVENTS_PUBLIC_TRAFFIC_PAGE_VIEW,
        'page_key' => $pageKey,
        'lang' => $lang,
        'entity_id' => (int) ($extra['entity_id'] ?? 0),
        'entity_label' => (string) ($extra['entity_label'] ?? ''),
        'view_mode' => (string) ($extra['view_mode'] ?? ''),
    ]);
}

function events_public_traffic_nav_click(PDO $db, string $navKey, string $lang, string $pageKey = ''): void
{
    events_public_traffic_record($db, [
        'event_type' => EVENTS_PUBLIC_TRAFFIC_NAV_CLICK,
        'nav_key' => $navKey,
        'page_key' => $pageKey,
        'lang' => $lang,
    ]);
}

/**
 * @param array<string, mixed> $query
 * @return array{
 *   date_from: string,
 *   date_to: string,
 *   page: string,
 *   lang: string,
 *   visitor: string,
 *   device: string,
 *   kind: string
 * }
 */
function events_public_traffic_params_from_request(array $query): array
{
    $base = events_edit_stats_params_from_request($query);
    $page = trim((string) ($query['page'] ?? 'all'));
    if ($page === 'hub' || $page === 'detail') {
        $page = 'all';
    }
    $allowedPage = array_merge(['all'], events_public_traffic_page_keys());
    if (!in_array($page, $allowedPage, true)) {
        $page = 'all';
    }

    $lang = strtolower(trim((string) ($query['traf_lang'] ?? 'all')));
    if (!in_array($lang, ['all', 'hu', 'en'], true)) {
        $lang = 'all';
    }

    $visitor = strtolower(trim((string) ($query['visitor'] ?? 'all')));
    if (!in_array($visitor, ['all', 'human', 'bot'], true)) {
        $visitor = 'all';
    }

    $device = strtolower(trim((string) ($query['device'] ?? 'all')));
    if (!in_array($device, ['all', 'desktop', 'mobile', 'tablet', 'unknown'], true)) {
        $device = 'all';
    }

    $kind = strtolower(trim((string) ($query['kind'] ?? 'all')));
    if (!in_array($kind, ['all', EVENTS_PUBLIC_TRAFFIC_PAGE_VIEW, EVENTS_PUBLIC_TRAFFIC_NAV_CLICK], true)) {
        $kind = 'all';
    }

    return [
        'date_from' => (string) $base['date_from'],
        'date_to' => (string) $base['date_to'],
        'page' => $page,
        'lang' => $lang,
        'visitor' => $visitor,
        'device' => $device,
        'kind' => $kind,
    ];
}

function events_public_traffic_earliest_date(PDO $db): ?string
{
    if (!events_public_traffic_tables_ready($db)) {
        return null;
    }

    try {
        $raw = $db->query('SELECT MIN(`occurred_at`) FROM `events_public_traffic`')->fetchColumn();
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
 * @param array{date_from: string, date_to: string, page: string, lang: string, visitor: string, device: string, kind: string} $params
 * @return array{sql: string, bind: list<mixed>}
 */
function events_public_traffic_where(array $params, bool $includeKind = true): array
{
    $sql = ['t.`occurred_at` >= ?', 't.`occurred_at` < ?'];
    $bind = [
        $params['date_from'] . ' 00:00:00',
        (new DateTimeImmutable($params['date_to']))->modify('+1 day')->format('Y-m-d') . ' 00:00:00',
    ];

    $page = (string) ($params['page'] ?? 'all');
    $hubKeys = events_public_traffic_page_keys();
    if ($page === 'all' || $page === 'hub') {
        if ($hubKeys !== []) {
            $ph = implode(',', array_fill(0, count($hubKeys), '?'));
            $sql[] = '(t.`event_type` = \'nav_click\' OR t.`page_key` IN (' . $ph . '))';
            foreach ($hubKeys as $key) {
                $bind[] = $key;
            }
        }
    } else {
        $sql[] = 't.`page_key` = ?';
        $bind[] = $page;
    }

    if (($params['lang'] ?? 'all') !== 'all') {
        $sql[] = 't.`lang` = ?';
        $bind[] = $params['lang'];
    }
    if (($params['visitor'] ?? 'all') === 'human') {
        $sql[] = 't.`is_bot` = 0';
    } elseif (($params['visitor'] ?? 'all') === 'bot') {
        $sql[] = 't.`is_bot` = 1';
    }
    if (($params['device'] ?? 'all') !== 'all') {
        $sql[] = 't.`device` = ?';
        $bind[] = $params['device'];
    }
    if ($includeKind && ($params['kind'] ?? 'all') !== 'all') {
        $sql[] = 't.`event_type` = ?';
        $bind[] = $params['kind'];
    }

    return [
        'sql' => implode(' AND ', $sql),
        'bind' => $bind,
    ];
}

function events_public_traffic_granularity(string $dateFrom, string $dateTo): string
{
    try {
        $days = (int) (new DateTimeImmutable($dateFrom))->diff(new DateTimeImmutable($dateTo))->days;
    } catch (Throwable) {
        return 'day';
    }
    // Egy nap (pl. „Ma”) → óránkénti trend.
    if ($days === 0) {
        return 'hour';
    }
    if ($days > 366) {
        return 'month';
    }
    if ($days > 90) {
        return 'week';
    }

    return 'day';
}

function events_public_traffic_bucket_expr(string $granularity): string
{
    return match ($granularity) {
        'hour' => "DATE_FORMAT(t.`occurred_at`, '%H')",
        'month' => "DATE_FORMAT(t.`occurred_at`, '%Y-%m-01')",
        'week' => 'DATE(DATE_SUB(t.`occurred_at`, INTERVAL WEEKDAY(t.`occurred_at`) DAY))',
        default => 'DATE(t.`occurred_at`)',
    };
}

/**
 * @return list<string>
 */
function events_public_traffic_bucket_labels(string $dateFrom, string $dateTo, string $granularity): array
{
    if ($granularity === 'hour') {
        $out = [];
        for ($h = 0; $h < 24; $h++) {
            $out[] = sprintf('%02d', $h);
        }

        return $out;
    }

    try {
        $from = new DateTimeImmutable($dateFrom);
        $to = new DateTimeImmutable($dateTo);
    } catch (Throwable) {
        return [];
    }

    $out = [];
    if ($granularity === 'month') {
        $cursor = $from->modify('first day of this month');
        $end = $to->modify('first day of this month');
        while ($cursor <= $end) {
            $out[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+1 month');
        }

        return $out;
    }
    if ($granularity === 'week') {
        $cursor = $from->modify('-' . ((int) $from->format('N') - 1) . ' days');
        $end = $to->modify('-' . ((int) $to->format('N') - 1) . ' days');
        while ($cursor <= $end) {
            $out[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+7 days');
        }

        return $out;
    }

    $cursor = $from;
    while ($cursor <= $to) {
        $out[] = $cursor->format('Y-m-d');
        $cursor = $cursor->modify('+1 day');
    }

    return $out;
}

function events_public_traffic_format_bucket_label(string $ymd, string $granularity): string
{
    if ($granularity === 'hour') {
        return sprintf('%02d:00', max(0, min(23, (int) $ymd)));
    }

    try {
        $dt = new DateTimeImmutable($ymd);
    } catch (Throwable) {
        return $ymd;
    }

    return match ($granularity) {
        'month' => $dt->format('Y. m.'),
        'week' => $dt->format('Y. m. d.') . ' hét',
        default => $dt->format('Y. m. d.'),
    };
}

/**
 * @param array{date_from: string, date_to: string, page: string, lang: string, visitor: string, device: string, kind: string} $params
 * @return array<string, mixed>
 */
function events_public_traffic_stats(PDO $db, array $params): array
{
    $empty = [
        'table_ready' => false,
        'granularity' => 'day',
        'totals' => [
            'page_views' => 0,
            'page_views_human' => 0,
            'page_views_bot' => 0,
            'nav_clicks' => 0,
            'nav_clicks_human' => 0,
            'nav_clicks_bot' => 0,
            'unique_human' => 0,
            'unique_bot' => 0,
            'lang_hu' => 0,
            'lang_en' => 0,
        ],
        'pages' => [],
        'nav' => [],
        'devices' => [],
        'hours' => array_fill(0, 24, 0),
        'referrers' => [],
        'chart' => ['labels' => [], 'datasets' => []],
        'nav_chart' => ['labels' => [], 'datasets' => []],
        'page_share' => ['labels' => [], 'data' => [], 'colors' => []],
        'device_share' => ['labels' => [], 'data' => [], 'colors' => []],
        'lang_share' => ['labels' => [], 'data' => [], 'colors' => []],
        'hour_chart' => ['labels' => [], 'data' => []],
        'visitor_chart' => ['labels' => [], 'datasets' => []],
    ];

    if (!events_public_traffic_ensure_schema($db)) {
        return $empty;
    }
    $empty['table_ready'] = true;

    $whereAll = events_public_traffic_where($params, true);
    $granularity = events_public_traffic_granularity($params['date_from'], $params['date_to']);
    $empty['granularity'] = $granularity;

    try {
        $totals = $empty['totals'];
        $st = $db->prepare(
            'SELECT
                SUM(CASE WHEN t.`event_type` = \'page_view\' THEN 1 ELSE 0 END) AS page_views,
                SUM(CASE WHEN t.`event_type` = \'page_view\' AND t.`is_bot` = 0 THEN 1 ELSE 0 END) AS page_views_human,
                SUM(CASE WHEN t.`event_type` = \'page_view\' AND t.`is_bot` = 1 THEN 1 ELSE 0 END) AS page_views_bot,
                SUM(CASE WHEN t.`event_type` = \'nav_click\' THEN 1 ELSE 0 END) AS nav_clicks,
                SUM(CASE WHEN t.`event_type` = \'nav_click\' AND t.`is_bot` = 0 THEN 1 ELSE 0 END) AS nav_clicks_human,
                SUM(CASE WHEN t.`event_type` = \'nav_click\' AND t.`is_bot` = 1 THEN 1 ELSE 0 END) AS nav_clicks_bot,
                COUNT(DISTINCT CASE WHEN t.`event_type` = \'page_view\' AND t.`is_bot` = 0 AND t.`ip_hash` IS NOT NULL THEN t.`ip_hash` END) AS unique_human,
                COUNT(DISTINCT CASE WHEN t.`event_type` = \'page_view\' AND t.`is_bot` = 1 AND t.`ip_hash` IS NOT NULL THEN t.`ip_hash` END) AS unique_bot,
                SUM(CASE WHEN t.`event_type` = \'page_view\' AND t.`lang` = \'hu\' THEN 1 ELSE 0 END) AS lang_hu,
                SUM(CASE WHEN t.`event_type` = \'page_view\' AND t.`lang` = \'en\' THEN 1 ELSE 0 END) AS lang_en
             FROM `events_public_traffic` t
             WHERE ' . $whereAll['sql']
        );
        $st->execute($whereAll['bind']);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            foreach ($totals as $key => $_) {
                $totals[$key] = (int) ($row[$key] ?? 0);
            }
        }
        $empty['totals'] = $totals;

        $empty['pages'] = events_public_traffic_group_rows(
            $db,
            $whereAll,
            't.`event_type` = \'page_view\'',
            't.`page_key`',
            static function (string $key): string {
                return events_public_traffic_page_label($key);
            }
        );
        $empty['nav'] = events_public_traffic_group_rows(
            $db,
            $whereAll,
            't.`event_type` = \'nav_click\'',
            't.`nav_key`',
            static function (string $key) use ($db): string {
                return events_public_traffic_nav_label($key, $db);
            }
        );
        $empty['devices'] = events_public_traffic_group_rows(
            $db,
            $whereAll,
            't.`event_type` = \'page_view\'',
            't.`device`',
            static function (string $key): string {
                return match ($key) {
                    'desktop' => 'Asztali',
                    'mobile' => 'Mobil',
                    'tablet' => 'Tablet',
                    default => 'Ismeretlen',
                };
            }
        );

        $hourSql = $db->prepare(
            'SELECT HOUR(t.`occurred_at`) AS h, COUNT(*) AS c
             FROM `events_public_traffic` t
             WHERE ' . $whereAll['sql'] . ' AND t.`event_type` = \'page_view\'
             GROUP BY h'
        );
        $hourSql->execute($whereAll['bind']);
        $hours = array_fill(0, 24, 0);
        while ($hRow = $hourSql->fetch(PDO::FETCH_ASSOC)) {
            $h = max(0, min(23, (int) ($hRow['h'] ?? 0)));
            $hours[$h] = (int) ($hRow['c'] ?? 0);
        }
        $empty['hours'] = $hours;
        $hourLabels = [];
        for ($i = 0; $i < 24; $i++) {
            $hourLabels[] = sprintf('%02d:00', $i);
        }
        $empty['hour_chart'] = ['labels' => $hourLabels, 'data' => array_values($hours)];

        $refSql = $db->prepare(
            'SELECT
                CASE WHEN t.`referrer_host` = \'\' THEN \'(közvetlen)\' ELSE t.`referrer_host` END AS host,
                COUNT(*) AS total,
                SUM(CASE WHEN t.`is_bot` = 0 THEN 1 ELSE 0 END) AS human_count,
                SUM(CASE WHEN t.`is_bot` = 1 THEN 1 ELSE 0 END) AS bot_count
             FROM `events_public_traffic` t
             WHERE ' . $whereAll['sql'] . ' AND t.`event_type` = \'page_view\'
             GROUP BY host
             ORDER BY total DESC
             LIMIT 20'
        );
        $refSql->execute($whereAll['bind']);
        $empty['referrers'] = $refSql->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $bucketExpr = events_public_traffic_bucket_expr($granularity);
        $bucketKeys = events_public_traffic_bucket_labels($params['date_from'], $params['date_to'], $granularity);
        $displayLabels = array_map(
            static fn (string $ymd): string => events_public_traffic_format_bucket_label($ymd, $granularity),
            $bucketKeys
        );

        $pageSeriesSql = $db->prepare(
            'SELECT ' . $bucketExpr . ' AS bucket, t.`page_key`,
                    SUM(CASE WHEN t.`is_bot` = 0 THEN 1 ELSE 0 END) AS human_count,
                    COUNT(*) AS total
             FROM `events_public_traffic` t
             WHERE ' . $whereAll['sql'] . ' AND t.`event_type` = \'page_view\'
             GROUP BY bucket, t.`page_key`'
        );
        $pageSeriesSql->execute($whereAll['bind']);
        $byPage = [];
        $visitorHuman = array_fill_keys($bucketKeys, 0);
        $visitorBot = array_fill_keys($bucketKeys, 0);
        while ($pRow = $pageSeriesSql->fetch(PDO::FETCH_ASSOC)) {
            $bucket = (string) ($pRow['bucket'] ?? '');
            $pKey = (string) ($pRow['page_key'] ?? '');
            if ($bucket === '' || $pKey === '') {
                continue;
            }
            if (!isset($byPage[$pKey])) {
                $byPage[$pKey] = array_fill_keys($bucketKeys, 0);
            }
            if (array_key_exists($bucket, $byPage[$pKey])) {
                $byPage[$pKey][$bucket] = (int) ($pRow['human_count'] ?? 0);
            }
        }

        $visitorSql = $db->prepare(
            'SELECT ' . $bucketExpr . ' AS bucket,
                    SUM(CASE WHEN t.`is_bot` = 0 THEN 1 ELSE 0 END) AS human_count,
                    SUM(CASE WHEN t.`is_bot` = 1 THEN 1 ELSE 0 END) AS bot_count
             FROM `events_public_traffic` t
             WHERE ' . $whereAll['sql'] . ' AND t.`event_type` = \'page_view\'
             GROUP BY bucket'
        );
        $visitorSql->execute($whereAll['bind']);
        while ($vRow = $visitorSql->fetch(PDO::FETCH_ASSOC)) {
            $bucket = (string) ($vRow['bucket'] ?? '');
            if (!array_key_exists($bucket, $visitorHuman)) {
                continue;
            }
            $visitorHuman[$bucket] = (int) ($vRow['human_count'] ?? 0);
            $visitorBot[$bucket] = (int) ($vRow['bot_count'] ?? 0);
        }

        $datasets = [];
        foreach (events_public_traffic_page_catalog() as $pKey => $meta) {
            if (!isset($byPage[$pKey])) {
                continue;
            }
            $data = [];
            foreach ($bucketKeys as $bk) {
                $data[] = (int) ($byPage[$pKey][$bk] ?? 0);
            }
            if (array_sum($data) === 0) {
                continue;
            }
            $datasets[] = [
                'label' => (string) $meta['label'],
                'data' => $data,
                'color' => (string) $meta['color'],
                'key' => $pKey,
            ];
        }
        $empty['chart'] = [
            'labels' => $displayLabels,
            'datasets' => $datasets,
        ];
        $empty['visitor_chart'] = [
            'labels' => $displayLabels,
            'datasets' => [
                ['label' => 'Ember', 'data' => array_values($visitorHuman), 'color' => '#3d6b4f'],
                ['label' => 'Bot', 'data' => array_values($visitorBot), 'color' => '#9ca3af'],
            ],
        ];

        $navSeriesSql = $db->prepare(
            'SELECT ' . $bucketExpr . ' AS bucket, t.`nav_key`, COUNT(*) AS total
             FROM `events_public_traffic` t
             WHERE ' . $whereAll['sql'] . ' AND t.`event_type` = \'nav_click\'
             GROUP BY bucket, t.`nav_key`'
        );
        $navSeriesSql->execute($whereAll['bind']);
        $byNav = [];
        while ($nRow = $navSeriesSql->fetch(PDO::FETCH_ASSOC)) {
            $bucket = (string) ($nRow['bucket'] ?? '');
            $nKey = (string) ($nRow['nav_key'] ?? '');
            if ($bucket === '' || $nKey === '') {
                continue;
            }
            if (!isset($byNav[$nKey])) {
                $byNav[$nKey] = array_fill_keys($bucketKeys, 0);
            }
            if (array_key_exists($bucket, $byNav[$nKey])) {
                $byNav[$nKey][$bucket] = (int) ($nRow['total'] ?? 0);
            }
        }
        $navPalette = ['#c45c26', '#2f6f8f', '#8b5a9e', '#3d6b4f', '#c4a35a', '#4f6d8a', '#d2691e', '#6d5a8a', '#5a8a6a'];
        $navDatasets = [];
        $navIdx = 0;
        foreach ($byNav as $nKey => $series) {
            $data = [];
            foreach ($bucketKeys as $bk) {
                $data[] = (int) ($series[$bk] ?? 0);
            }
            if (array_sum($data) === 0) {
                continue;
            }
            $navDatasets[] = [
                'label' => events_public_traffic_nav_label((string) $nKey, $db),
                'data' => $data,
                'color' => $navPalette[$navIdx % count($navPalette)],
                'key' => (string) $nKey,
            ];
            $navIdx++;
        }
        $empty['nav_chart'] = [
            'labels' => $displayLabels,
            'datasets' => $navDatasets,
        ];

        $pageShareLabels = [];
        $pageShareData = [];
        $pageShareColors = [];
        foreach ($empty['pages'] as $pRow) {
            $cnt = (int) ($pRow['human_count'] ?? 0);
            if ($cnt <= 0) {
                continue;
            }
            $pageShareLabels[] = (string) ($pRow['label'] ?? '');
            $pageShareData[] = $cnt;
            $pageShareColors[] = events_public_traffic_page_color((string) ($pRow['key'] ?? ''));
        }
        $empty['page_share'] = [
            'labels' => $pageShareLabels,
            'data' => $pageShareData,
            'colors' => $pageShareColors,
        ];

        $deviceColors = [
            'desktop' => '#3d6b4f',
            'mobile' => '#2f6f8f',
            'tablet' => '#c4a35a',
            'unknown' => '#9ca3af',
        ];
        $deviceShareLabels = [];
        $deviceShareData = [];
        $deviceShareColors = [];
        foreach ($empty['devices'] as $dRow) {
            $cnt = (int) ($dRow['total'] ?? 0);
            if ($cnt <= 0) {
                continue;
            }
            $deviceShareLabels[] = (string) ($dRow['label'] ?? '');
            $deviceShareData[] = $cnt;
            $deviceShareColors[] = $deviceColors[(string) ($dRow['key'] ?? '')] ?? '#6b7280';
        }
        $empty['device_share'] = [
            'labels' => $deviceShareLabels,
            'data' => $deviceShareData,
            'colors' => $deviceShareColors,
        ];
        $empty['lang_share'] = [
            'labels' => ['Magyar', 'Angol'],
            'data' => [(int) $totals['lang_hu'], (int) $totals['lang_en']],
            'colors' => ['#3d6b4f', '#2f6f8f'],
        ];
    } catch (Throwable $e) {
        error_log('events_public_traffic_stats: ' . $e->getMessage());
    }

    return $empty;
}

/**
 * @param array{sql: string, bind: list<mixed>} $where
 * @return list<array{key: string, label: string, total: int, human_count: int, bot_count: int, unique_human: int}>
 */
function events_public_traffic_group_rows(
    PDO $db,
    array $where,
    string $extraSql,
    string $groupExpr,
    callable $labelFn
): array {
    $st = $db->prepare(
        'SELECT ' . $groupExpr . ' AS grp,
                COUNT(*) AS total,
                SUM(CASE WHEN t.`is_bot` = 0 THEN 1 ELSE 0 END) AS human_count,
                SUM(CASE WHEN t.`is_bot` = 1 THEN 1 ELSE 0 END) AS bot_count,
                COUNT(DISTINCT CASE WHEN t.`is_bot` = 0 AND t.`ip_hash` IS NOT NULL THEN t.`ip_hash` END) AS unique_human
         FROM `events_public_traffic` t
         WHERE ' . $where['sql'] . ' AND ' . $extraSql . '
         GROUP BY grp
         ORDER BY total DESC'
    );
    $st->execute($where['bind']);
    $out = [];
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $key = trim((string) ($row['grp'] ?? ''));
        if ($key === '') {
            continue;
        }
        $out[] = [
            'key' => $key,
            'label' => (string) $labelFn($key),
            'total' => (int) ($row['total'] ?? 0),
            'human_count' => (int) ($row['human_count'] ?? 0),
            'bot_count' => (int) ($row['bot_count'] ?? 0),
            'unique_human' => (int) ($row['unique_human'] ?? 0),
        ];
    }

    return $out;
}
