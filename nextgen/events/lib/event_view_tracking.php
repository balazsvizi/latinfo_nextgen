<?php
declare(strict_types=1);

const EVENTS_VIEW_METRIC_PAGE = 'page_view';
const EVENTS_VIEW_METRIC_CALENDAR_PREVIEW = 'calendar_preview';
const EVENTS_VIEW_METRIC_EXTERNAL_INFO = 'external_info_click';

const EVENTS_VIEW_SOURCE_DIRECT = 'direct';
const EVENTS_VIEW_SOURCE_CALENDAR = 'calendar';
const EVENTS_VIEW_SOURCE_CAL_PREVIEW = 'cal_preview';
const EVENTS_VIEW_SOURCE_LIST = 'list';

/**
 * @return list<string>
 */
function events_view_metric_types(): array
{
    return [
        EVENTS_VIEW_METRIC_PAGE,
        EVENTS_VIEW_METRIC_CALENDAR_PREVIEW,
        EVENTS_VIEW_METRIC_EXTERNAL_INFO,
    ];
}

/**
 * Naptár-előnézet és CTA: ezek továbbra is a ajax_event_metric.php-n mennek.
 * Az oldalmegtekintés külön, csak böngészős beacon (ajax_client_page_view.php).
 *
 * @return list<string>
 */
function events_view_metric_types_ajax(): array
{
    return [
        EVENTS_VIEW_METRIC_CALENDAR_PREVIEW,
        EVENTS_VIEW_METRIC_EXTERNAL_INFO,
    ];
}

function events_view_tracking_ip_hash(): ?string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    return $ip !== '' ? hash('sha256', $ip . '|' . SITE_NAME) : null;
}

/**
 * Bejelentkezett regisztrált (latinfo) felhasználó ID — admin/partner nem számít.
 */
function events_view_tracking_current_user_id(): int
{
    if (!function_exists('user_current_id')) {
        $auth = dirname(__DIR__, 2) . '/user/includes/auth.php';
        if (is_file($auth)) {
            require_once $auth;
        }
    }
    if (!function_exists('user_is_logged_in') || !function_exists('user_current_id')) {
        return 0;
    }
    if (!user_is_logged_in()) {
        return 0;
    }

    return max(0, user_current_id());
}

/**
 * Kereső, AI-crawler, unfurler, monitor és prefetch.
 * Minden stat (buli, nyilvános oldal, CMS, modul, értékelés, értesítő, mobilapp) ezt hívja.
 * Élő kérésnél a spekulatív fejléceket is nézi; explicit UA-nál csak a szöveget.
 */
function events_view_tracking_detect_bot(?string $userAgent = null): bool
{
    $ua = trim($userAgent ?? (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($ua === '') {
        return true;
    }

    if ($userAgent === null && events_view_tracking_request_is_speculative()) {
        return true;
    }

    return events_view_tracking_ua_is_bot($ua);
}

/**
 * Chrome prefetch / prerender és link-preview kérés: nincs valódi megtekintés.
 */
function events_view_tracking_request_is_speculative(): bool
{
    $values = [
        (string) ($_SERVER['HTTP_SEC_PURPOSE'] ?? ''),
        (string) ($_SERVER['HTTP_PURPOSE'] ?? ''),
        (string) ($_SERVER['HTTP_X_PURPOSE'] ?? ''),
        (string) ($_SERVER['HTTP_X_MOZ'] ?? ''),
    ];
    foreach ($values as $value) {
        if ($value !== '' && preg_match('/\b(?:prefetch|prerender|preview)\b/i', $value) === 1) {
            return true;
        }
    }

    return false;
}

function events_view_tracking_ua_is_bot(string $ua): bool
{
    if (preg_match('/\+https?:\/\//i', $ua) === 1) {
        return true;
    }

    // (compatible; …) crawler-konvenció. A régi IE „compatible; MSIE” ember marad.
    if (
        preg_match('/\(compatible\s*;/i', $ua) === 1
        && preg_match('/\b(?:msie|trident)\b/i', $ua) !== 1
    ) {
        return true;
    }

    // Unfurler rövid ügynök. Az alkalmazás böngészője (Mozilla + WhatsApp/Pinterest/…) ember.
    if (
        preg_match('/\b(?:whatsapp|tumblr|pinterest|flipboard)\b/i', $ua) === 1
        && preg_match('/mozilla/i', $ua) !== 1
    ) {
        return true;
    }

    // *bot token (Googlebot, UptimeRobot, …). A Cubot telefonmárka nem crawler.
    if (preg_match_all('/(?<![a-z0-9])([a-z0-9_-]*bot)(?![a-z0-9])/i', $ua, $matches) > 0) {
        foreach ($matches[1] as $token) {
            if (strcasecmp((string) $token, 'cubot') !== 0) {
                return true;
            }
        }
    }

    static $pattern = null;
    if ($pattern === null) {
        $pattern = '/'
            . 'googleother|google-inspectiontool|google-read-aloud|googleproducer|duplexweb-google'
            . '|google-safety|google-site-verification|google-favicon|google-extended|feedfetcher'
            . '|mediapartners-google|apis-google|adsbot-google'
            . '|bingpreview|microsoftpreview|msnbot|adidxbot|slurp'
            . '|baiduspider|bytespider|yandex(?:images|render)|duckassist'
            . '|sogou(?:\s+web)?\s*spider|ia_archiver|archive\.org_bot'
            . '|ahrefs|semrush|screaming\s*frog|serpstat|mj12|dataforseo|barkrowler|zoominfo'
            . '|seekport|mojeek|qwantify|majestic'
            . '|chatgpt-user|oai-searchbot|claude-user|anthropic-ai|perplexity-user|cohere-ai|imagesift'
            . '|facebookexternalhit|meta-external|facebookcatalog|embedly|iframely'
            . '|quora\s*link\s*preview|outbrain'
            . '|crawler|spider|scrapy|archiver|scraper|wget|curl|python-requests|python-urllib'
            . '|aiohttp|httpx\/|okhttp|go-http-client|java\/|apache-httpclient|libwww|httpclient'
            . '|node-fetch|undici|axios\/|guzzlehttp|fasthttp|colly|mechanize|httrack'
            . '|headless|phantomjs|selenium|puppeteer|playwright'
            . '|lighthouse|ptst\/|gtmetrix|pingdom|statuscake|site24x7|netcraft|censys|shodan'
            . '|zgrab|masscan|nuclei|nikto|sqlmap'
            . '/i';
    }

    return preg_match($pattern, $ua) === 1;
}

function events_view_tracking_bot_column_ready(PDO $db, bool $refresh = false): bool
{
    static $ready = null;
    if ($refresh) {
        $ready = null;
    }
    if ($ready !== null) {
        return $ready;
    }

    try {
        $stmt = $db->query("SHOW COLUMNS FROM `events_calendar_event_views` LIKE 'is_bot'");
        $ready = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        $ready = false;
    }

    return $ready;
}

/**
 * is_bot oszlop létrehozása, ha hiányzik.
 */
function events_view_tracking_ensure_bot_column(PDO $db): bool
{
    if (events_view_tracking_bot_column_ready($db)) {
        return true;
    }

    try {
        $db->exec(
            'ALTER TABLE `events_calendar_event_views`
             ADD COLUMN `is_bot` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `source`'
        );
        try {
            $db->exec(
                'ALTER TABLE `events_calendar_event_views`
                 ADD INDEX `idx_event_metric_bot` (`esemény_id`, `metric_type`, `is_bot`)'
            );
        } catch (Throwable) {
            // Index opcionális / már létezhet.
        }
    } catch (Throwable $ex) {
        error_log('events_view_tracking_ensure_bot_column: ' . $ex->getMessage());

        return false;
    }

    return events_view_tracking_bot_column_ready($db, true);
}

function events_view_tracking_user_id_column_ready(PDO $db, bool $refresh = false): bool
{
    static $ready = null;
    if ($refresh) {
        $ready = null;
    }
    if ($ready !== null) {
        return $ready;
    }

    try {
        $stmt = $db->query("SHOW COLUMNS FROM `events_calendar_event_views` LIKE 'user_id'");
        $ready = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        $ready = false;
    }

    return $ready;
}

/**
 * user_id oszlop létrehozása, ha hiányzik (regisztrált látogató).
 */
function events_view_tracking_ensure_user_id_column(PDO $db): bool
{
    if (events_view_tracking_user_id_column_ready($db)) {
        return true;
    }

    try {
        $db->exec(
            'ALTER TABLE `events_calendar_event_views`
             ADD COLUMN `user_id` INT UNSIGNED NULL DEFAULT NULL AFTER `ip_hash`'
        );
        try {
            $db->exec(
                'ALTER TABLE `events_calendar_event_views`
                 ADD INDEX `idx_views_user_id` (`user_id`, `létrehozva`)'
            );
        } catch (Throwable) {
            // Index opcionális / már létezhet.
        }
    } catch (Throwable $ex) {
        error_log('events_view_tracking_ensure_user_id_column: ' . $ex->getMessage());

        return false;
    }

    return events_view_tracking_user_id_column_ready($db, true);
}

/**
 * Korrelált COUNT SQL egy metrikára (emberi / bot / össz).
 *
 * @return array{human: string, bot: string, total: string}
 */
function events_view_metric_count_selects(
    string $metricType,
    bool $botColumnReady,
    string $eventIdExpr = 'e.id',
    string $tableAlias = 'm'
): array {
    $base = "FROM `events_calendar_event_views` {$tableAlias}"
        . " WHERE {$tableAlias}.`esemény_id` = {$eventIdExpr}"
        . " AND {$tableAlias}.`metric_type` = "
        . "'" . str_replace("'", "''", $metricType) . "'";

    $total = "(SELECT COUNT(*) {$base})";
    if (!$botColumnReady) {
        return [
            'human' => $total,
            'bot' => '0',
            'total' => $total,
        ];
    }

    return [
        'human' => "(SELECT COUNT(*) {$base} AND {$tableAlias}.`is_bot` = 0)",
        'bot' => "(SELECT COUNT(*) {$base} AND {$tableAlias}.`is_bot` = 1)",
        'total' => $total,
    ];
}

/**
 * @return array{human: int, bot: int, total: int}
 */
function events_view_metric_counts_from_row(array $row, string $prefix): array
{
    $total = (int) ($row[$prefix] ?? 0);
    $human = array_key_exists($prefix . '_human', $row) ? (int) $row[$prefix . '_human'] : $total;
    $bot = array_key_exists($prefix . '_bot', $row) ? (int) $row[$prefix . '_bot'] : max(0, $total - $human);

    return [
        'human' => $human,
        'bot' => $bot,
        'total' => $total > 0 ? $total : ($human + $bot),
    ];
}

function events_view_tracking_append_ref(string $url, string $ref): string
{
    $url = trim($url);
    $ref = trim($ref);
    if ($url === '' || $url === '#' || $ref === '') {
        return $url;
    }

    $separator = str_contains($url, '?') ? '&' : '?';

    return $url . $separator . 'ref=' . rawurlencode($ref);
}

function events_view_tracking_resolve_page_source(string $ref): string
{
    return match (trim($ref)) {
        EVENTS_VIEW_SOURCE_CAL_PREVIEW => EVENTS_VIEW_SOURCE_CAL_PREVIEW,
        EVENTS_VIEW_SOURCE_CALENDAR => EVENTS_VIEW_SOURCE_CALENDAR,
        EVENTS_VIEW_SOURCE_LIST => EVENTS_VIEW_SOURCE_LIST,
        default => EVENTS_VIEW_SOURCE_DIRECT,
    };
}

function events_view_tracking_is_published_event(PDO $db, int $eventId): bool
{
    if ($eventId <= 0) {
        return false;
    }

    $statuses = events_publicly_visible_post_statuses();
    $ph = implode(',', array_fill(0, count($statuses), '?'));
    $stmt = $db->prepare("SELECT 1 FROM `events_calendar_events` WHERE `id` = ? AND `event_status` IN ({$ph}) LIMIT 1");
    $stmt->execute(array_merge([$eventId], $statuses));

    return (bool) $stmt->fetchColumn();
}

/**
 * A saját oldal JS-éből induló beacon (sendBeacon / fetch).
 * A HTML-t csak letöltő crawler ezt nem tudja küldeni — ugyanaz a feltétel, mint a GA4-nél.
 */
function events_view_tracking_is_same_origin_beacon(): bool
{
    $site = strtolower(trim((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
    if ($site === 'same-origin') {
        return true;
    }
    if ($site !== '') {
        return false;
    }

    $referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
    $refHost = strtolower((string) (parse_url($referer, PHP_URL_HOST) ?? ''));
    $own = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $own = (string) preg_replace('/:\d+$/', '', $own);

    return $refHost !== '' && $own !== '' && $refHost === $own;
}

/**
 * Admin vagy partnerportál munkamenetben nem rögzítünk megtekintést (saját számláló).
 */
function events_view_tracking_should_record(): bool
{
    if (function_exists('events_public_visitor_metrics_allowed')) {
        return events_public_visitor_metrics_allowed();
    }

    if (function_exists('isLoggedIn') && isLoggedIn()) {
        return false;
    }
    if (function_exists('partner_is_logged_in') && partner_is_logged_in()) {
        return false;
    }

    return empty($_SESSION['partner_id']);
}

function events_track_event_view(PDO $db, int $eventId, string $metricType, ?string $source = null): void
{
    if (!events_view_tracking_should_record()) {
        return;
    }

    if ($eventId <= 0 || !in_array($metricType, events_view_metric_types(), true)) {
        return;
    }

    if ($metricType === EVENTS_VIEW_METRIC_PAGE) {
        $source = events_view_tracking_resolve_page_source((string) $source);
    } elseif ($metricType === EVENTS_VIEW_METRIC_EXTERNAL_INFO) {
        $source = EVENTS_VIEW_SOURCE_DIRECT;
    } elseif ($source === null || $source === '') {
        $source = EVENTS_VIEW_SOURCE_CALENDAR;
    }

    $isBot = events_view_tracking_detect_bot() ? 1 : 0;
    $botColumnReady = events_view_tracking_ensure_bot_column($db);
    $userIdReady = events_view_tracking_ensure_user_id_column($db);
    $userId = events_view_tracking_current_user_id();
    $userIdParam = $userId > 0 ? $userId : null;
    $ipHash = events_view_tracking_ip_hash();

    try {
        if ($botColumnReady && $userIdReady) {
            $stmt = $db->prepare(
                'INSERT INTO `events_calendar_event_views`
                    (`esemény_id`, `ip_hash`, `user_id`, `metric_type`, `source`, `is_bot`)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$eventId, $ipHash, $userIdParam, $metricType, $source, $isBot]);
        } elseif ($botColumnReady) {
            $stmt = $db->prepare(
                'INSERT INTO `events_calendar_event_views`
                    (`esemény_id`, `ip_hash`, `metric_type`, `source`, `is_bot`)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$eventId, $ipHash, $metricType, $source, $isBot]);
        } elseif ($userIdReady) {
            $stmt = $db->prepare(
                'INSERT INTO `events_calendar_event_views`
                    (`esemény_id`, `ip_hash`, `user_id`, `metric_type`, `source`)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$eventId, $ipHash, $userIdParam, $metricType, $source]);
        } else {
            $stmt = $db->prepare(
                'INSERT INTO `events_calendar_event_views`
                    (`esemény_id`, `ip_hash`, `metric_type`, `source`)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$eventId, $ipHash, $metricType, $source]);
        }
    } catch (Throwable) {
        // Opcionális napló – ne törjük a megjelenítést.
    }
}
