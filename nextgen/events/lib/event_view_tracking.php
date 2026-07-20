<?php
declare(strict_types=1);

const EVENTS_VIEW_METRIC_PAGE = 'page_view';
const EVENTS_VIEW_METRIC_CALENDAR_PREVIEW = 'calendar_preview';

const EVENTS_VIEW_SOURCE_DIRECT = 'direct';
const EVENTS_VIEW_SOURCE_CALENDAR = 'calendar';
const EVENTS_VIEW_SOURCE_CAL_PREVIEW = 'cal_preview';
const EVENTS_VIEW_SOURCE_LIST = 'list';

/**
 * @return list<string>
 */
function events_view_metric_types(): array
{
    return [EVENTS_VIEW_METRIC_PAGE, EVENTS_VIEW_METRIC_CALENDAR_PREVIEW];
}

function events_view_tracking_ip_hash(): ?string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    return $ip !== '' ? hash('sha256', $ip . '|' . SITE_NAME) : null;
}

/**
 * Kereső / AI / scraper User-Agent felismerés (UA alapú, nem 100%).
 */
function events_view_tracking_detect_bot(?string $userAgent = null): bool
{
    $ua = trim($userAgent ?? (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($ua === '') {
        return true;
    }

    static $pattern = null;
    if ($pattern === null) {
        $pattern = '/'
            . 'googlebot|google-extended|storebot-google|adsbot-google|apis-google|mediapartners-google|feedfetcher'
            . '|bingbot|bingpreview|msnbot|adidxbot'
            . '|slurp|duckduckbot|duckassistbot|baiduspider|yandex(?:bot|images)|sogou|exabot|seznambot|coccocbot'
            . '|applebot|petalbot|bytespider|amazonbot|ia_archiver|archive\.org_bot'
            . '|ahrefsbot|semrushbot|dotbot|mj12bot|rogerbot|screaming frog|serpstat|majestic'
            . '|gptbot|chatgpt-user|oai-searchbot|claudebot|anthropic|ccbot|perplexitybot|diffbot'
            . '|facebookexternalhit|facebot|twitterbot|linkedinbot|pinterest|redditbot|slackbot|discordbot'
            . '|whatsapp|telegrambot|embedly|quora\s*link\s*preview|outbrain|flipboard|tumblr|bitlybot'
            . '|crawler|spider|scrapy|wget|curl|python-requests|python-urllib|go-http-client|java\/|okhttp'
            . '|libwww-perl|httpclient|headlesschrome|phantomjs|selenium|puppeteer|playwright|httrack'
            . '/i';
    }

    return (bool) preg_match($pattern, $ua);
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

    $stmt = $db->prepare('SELECT 1 FROM `events_calendar_events` WHERE `id` = ? AND `event_status` = ? LIMIT 1');
    $stmt->execute([$eventId, events_public_post_status()]);

    return (bool) $stmt->fetchColumn();
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
    } elseif ($source === null || $source === '') {
        $source = EVENTS_VIEW_SOURCE_CALENDAR;
    }

    $isBot = events_view_tracking_detect_bot() ? 1 : 0;
    $botColumnReady = events_view_tracking_ensure_bot_column($db);

    try {
        if ($botColumnReady) {
            $stmt = $db->prepare(
                'INSERT INTO `events_calendar_event_views`
                    (`esemény_id`, `ip_hash`, `metric_type`, `source`, `is_bot`)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$eventId, events_view_tracking_ip_hash(), $metricType, $source, $isBot]);
        } else {
            $stmt = $db->prepare(
                'INSERT INTO `events_calendar_event_views`
                    (`esemény_id`, `ip_hash`, `metric_type`, `source`)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$eventId, events_view_tracking_ip_hash(), $metricType, $source]);
        }
    } catch (Throwable) {
        // Opcionális napló – ne törjük a megjelenítést.
    }
}
