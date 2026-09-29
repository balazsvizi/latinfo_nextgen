<?php
declare(strict_types=1);

require_once __DIR__ . '/event_view_tracking.php';
require_once __DIR__ . '/event_edit_stats.php';
require_once __DIR__ . '/public_traffic.php';
require_once __DIR__ . '/public_home_notice_stats.php';

const EVENTS_REALTIME_WINDOW_MINUTES = 30;
const EVENTS_REALTIME_TOP_EVENTS = 10;
const EVENTS_REALTIME_TOP_PAGES = 8;
const EVENTS_REALTIME_TOP_NAV = 8;
const EVENTS_REALTIME_RECENT_LIMIT = 40;
const EVENTS_REALTIME_FEED_POOL = 120;
const EVENTS_REALTIME_PRESENCE_LIMIT = 18;

/**
 * @return 'human'|'all'|'bot'
 */
function events_realtime_normalize_visitor(mixed $raw): string
{
    $v = strtolower(trim((string) $raw));

    return in_array($v, ['human', 'all', 'bot'], true) ? $v : 'human';
}

/**
 * SQL feltétel is_bot szerint. A $visitor már normalizált érték.
 */
function events_realtime_bot_and(string $visitor, bool $botReady, string $columnExpr = '`is_bot`'): string
{
    if (!$botReady) {
        return $visitor === 'bot' ? ' AND 1 = 0' : '';
    }

    return match ($visitor) {
        'human' => " AND {$columnExpr} = 0",
        'bot' => " AND {$columnExpr} = 1",
        default => '',
    };
}

/**
 * Index a 30 perces ablak lekérdezéseihez (idempotens).
 */
function events_realtime_ensure_indexes(PDO $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $stmt = $db->query("SHOW INDEX FROM `events_calendar_event_views` WHERE Key_name = 'idx_views_created_metric'");
        if ($stmt && $stmt->fetch(PDO::FETCH_ASSOC)) {
            return;
        }
        $db->exec(
            'ALTER TABLE `events_calendar_event_views`
             ADD INDEX `idx_views_created_metric` (`létrehozva`, `metric_type`)'
        );
    } catch (Throwable $ex) {
        error_log('events_realtime_ensure_indexes: ' . $ex->getMessage());
    }
}

/**
 * @return array{start: string, end: string, minutes: list<string>}
 */
function events_realtime_window(): array
{
    $end = new DateTimeImmutable('now');
    // Align to current minute floor for stable buckets.
    $endMinute = $end->setTime((int) $end->format('H'), (int) $end->format('i'), 0);
    $start = $endMinute->modify('-' . (EVENTS_REALTIME_WINDOW_MINUTES - 1) . ' minutes');

    $minutes = [];
    $cur = $start;
    for ($i = 0; $i < EVENTS_REALTIME_WINDOW_MINUTES; $i++) {
        $minutes[] = $cur->format('Y-m-d H:i:00');
        $cur = $cur->modify('+1 minute');
    }

    return [
        'start' => $start->format('Y-m-d H:i:s'),
        'end' => $end->format('Y-m-d H:i:s'),
        'minutes' => $minutes,
    ];
}

function events_realtime_source_label(string $source): string
{
    return match ($source) {
        EVENTS_VIEW_SOURCE_DIRECT => 'Közvetlen',
        EVENTS_VIEW_SOURCE_CALENDAR => 'Naptár',
        EVENTS_VIEW_SOURCE_CAL_PREVIEW => 'Naptár előnézet',
        EVENTS_VIEW_SOURCE_LIST => 'Lista',
        default => $source !== '' ? $source : 'Ismeretlen',
    };
}

function events_realtime_metric_label(string $metric): string
{
    return match ($metric) {
        EVENTS_VIEW_METRIC_PAGE => 'Oldal',
        EVENTS_VIEW_METRIC_CALENDAR_PREVIEW => 'Előnézet',
        EVENTS_VIEW_METRIC_EXTERNAL_INFO => 'További info',
        'hub_page' => 'Statikus oldal',
        'nav_click' => 'Menü',
        'notice_click' => 'Értesítő',
        default => $metric !== '' ? $metric : '—',
    };
}

function events_realtime_kind_label(string $kind): string
{
    return match ($kind) {
        'party' => 'Buli',
        'preview' => 'Előnézet',
        'external' => 'További info',
        'hub' => 'Statikus oldal',
        'nav' => 'Menü',
        'notice' => 'Értesítő',
        default => $kind !== '' ? $kind : '—',
    };
}

function events_realtime_device_label(string $device): string
{
    return match ($device) {
        'mobile' => 'mobil',
        'tablet' => 'tablet',
        'desktop' => 'asztali',
        default => '',
    };
}

/**
 * Stabil látogatójelölés: regisztrált usernél a név, egyébként ip_hash alapú álnév.
 * A teljes hash nem kerül a kliensre.
 *
 * @return array{
 *   key: string,
 *   label: string,
 *   color: string,
 *   emoji: string,
 *   code: string,
 *   is_bot: bool,
 *   user_id: int,
 *   is_registered: bool,
 *   profile_url: string
 * }
 */
function events_realtime_visitor_mark(?string $ipHash, bool $isBot, int $userId = 0, string $userName = ''): array
{
    $animals = [
        ['emoji' => '🦊', 'name' => 'róka'],
        ['emoji' => '🦉', 'name' => 'bagoly'],
        ['emoji' => '🐬', 'name' => 'delfin'],
        ['emoji' => '🐰', 'name' => 'nyúl'],
        ['emoji' => '🐻', 'name' => 'medve'],
        ['emoji' => '🐼', 'name' => 'panda'],
        ['emoji' => '🐯', 'name' => 'tigris'],
        ['emoji' => '🐧', 'name' => 'pingvin'],
        ['emoji' => '🐨', 'name' => 'koala'],
        ['emoji' => '🐺', 'name' => 'farkas'],
        ['emoji' => '🐱', 'name' => 'macska'],
        ['emoji' => '🐶', 'name' => 'kutya'],
        ['emoji' => '🦔', 'name' => 'sün'],
        ['emoji' => '🐢', 'name' => 'teknős'],
        ['emoji' => '🦜', 'name' => 'papagáj'],
        ['emoji' => '🦁', 'name' => 'oroszlán'],
        ['emoji' => '🐸', 'name' => 'béka'],
        ['emoji' => '🦄', 'name' => 'unikornis'],
        ['emoji' => '🐝', 'name' => 'méh'],
        ['emoji' => '🦋', 'name' => 'pillangó'],
    ];
    $colors = [
        ['name' => 'Korall', 'hex' => '#d4534a'],
        ['name' => 'Narancs', 'hex' => '#e07a2f'],
        ['name' => 'Arany', 'hex' => '#b5891f'],
        ['name' => 'Olív', 'hex' => '#6f8f2e'],
        ['name' => 'Zöld', 'hex' => '#2f8f62'],
        ['name' => 'Türkiz', 'hex' => '#1f8a86'],
        ['name' => 'Kék', 'hex' => '#2f6faf'],
        ['name' => 'Indigó', 'hex' => '#5558b0'],
        ['name' => 'Lila', 'hex' => '#8a4eab'],
        ['name' => 'Málna', 'hex' => '#c4477a'],
        ['name' => 'Bordó', 'hex' => '#a33b4a'],
        ['name' => 'Barna', 'hex' => '#8a5a3a'],
    ];

    if ($userId > 0) {
        $seed = hash('sha256', 'uid:' . $userId);
        $color = $colors[hexdec(substr($seed, 2, 2)) % count($colors)];
        $label = trim($userName);
        if ($label === '') {
            $label = 'Felhasználó #' . $userId;
        }

        return [
            'key' => 'u' . $userId,
            'label' => $label,
            'color' => $color['hex'],
            'emoji' => '',
            'code' => '',
            'is_bot' => $isBot,
            'user_id' => $userId,
            'is_registered' => true,
            'profile_url' => events_realtime_user_profile_url($userId),
        ];
    }

    $hash = strtolower(trim((string) $ipHash));
    $unknown = [
        'key' => '',
        'label' => $isBot ? 'Ismeretlen bot' : 'Ismeretlen',
        'color' => '#8b9198',
        'emoji' => '•',
        'code' => '',
        'is_bot' => $isBot,
        'user_id' => 0,
        'is_registered' => false,
        'profile_url' => '',
    ];
    if ($hash === '' || preg_match('/^[0-9a-f]{8,}$/', $hash) !== 1) {
        return $unknown;
    }

    $animal = $animals[hexdec(substr($hash, 0, 2)) % count($animals)];
    $color = $colors[hexdec(substr($hash, 2, 2)) % count($colors)];

    return [
        'key' => substr($hash, 0, 12),
        'label' => $color['name'] . ' ' . $animal['name'],
        'color' => $color['hex'],
        'emoji' => $animal['emoji'],
        'code' => strtoupper(substr($hash, 0, 4)),
        'is_bot' => $isBot,
        'user_id' => 0,
        'is_registered' => false,
        'profile_url' => '',
    ];
}

function events_realtime_user_profile_url(int $userId): string
{
    if ($userId <= 0 || !function_exists('nextgen_url')) {
        return '';
    }

    return nextgen_url('admin/users/profil.php?id=' . $userId);
}

/**
 * @param list<int> $userIds
 * @return array<int, string>
 */
function events_realtime_user_names(PDO $db, array $userIds): array
{
    $ids = [];
    foreach ($userIds as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $ids[$id] = true;
        }
    }
    if ($ids === []) {
        return [];
    }

    $idList = array_keys($ids);
    $placeholders = implode(',', array_fill(0, count($idList), '?'));

    try {
        if (!function_exists('latinfo_users_table_ready')) {
            $usersLib = dirname(__DIR__, 2) . '/lib/user/users.php';
            if (is_file($usersLib)) {
                require_once $usersLib;
            }
        }
        if (function_exists('latinfo_users_table_ready') && !latinfo_users_table_ready($db)) {
            return [];
        }

        $stmt = $db->prepare(
            "SELECT `id`, `name` FROM `latinfo_users` WHERE `id` IN ({$placeholders})"
        );
        $stmt->execute($idList);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[$id] = trim((string) ($row['name'] ?? ''));
        }

        return $out;
    } catch (Throwable $ex) {
        error_log('events_realtime_user_names: ' . $ex->getMessage());

        return [];
    }
}

/**
 * @param list<array<string, mixed>> $items
 * @return list<array<string, mixed>>
 */
function events_realtime_apply_user_names(PDO $db, array $items): array
{
    $ids = [];
    foreach ($items as $row) {
        $uid = (int) ($row['user_id'] ?? 0);
        if ($uid > 0) {
            $ids[$uid] = true;
        }
    }
    if ($ids === []) {
        return $items;
    }

    $names = events_realtime_user_names($db, array_keys($ids));
    foreach ($items as &$row) {
        $uid = (int) ($row['user_id'] ?? 0);
        if ($uid <= 0) {
            continue;
        }
        $row['visitor'] = events_realtime_visitor_mark(
            null,
            !empty($row['is_bot']),
            $uid,
            $names[$uid] ?? ''
        );
    }
    unset($row);

    return $items;
}

/**
 * @param array<string, mixed> $item
 * @return array<string, mixed>
 */
function events_realtime_with_visitor(array $item, mixed $ipHash, bool $isBot, string $device = '', int $userId = 0): array
{
    $normalized = events_public_traffic_normalize_device($device);
    $userId = max(0, $userId);
    $item['is_bot'] = $isBot;
    $item['device'] = $normalized;
    $item['device_label'] = events_realtime_device_label($normalized);
    $item['user_id'] = $userId;
    $item['visitor'] = events_realtime_visitor_mark(is_string($ipHash) ? $ipHash : null, $isBot, $userId);

    return $item;
}

/**
 * Legutóbbi hely szerint egyedi látogatók. Az $items newest-first.
 *
 * @param list<array<string, mixed>> $items
 * @return list<array{
 *   key: string,
 *   label: string,
 *   color: string,
 *   emoji: string,
 *   code: string,
 *   is_bot: bool,
 *   user_id: int,
 *   hits: int,
 *   last_at: string,
 *   last_target: string,
 *   last_kind: string,
 *   device_label: string
 * }>
 */
function events_realtime_presence_from_items(array $items): array
{
    $byKey = [];
    foreach ($items as $row) {
        $mark = $row['visitor'] ?? null;
        if (!is_array($mark)) {
            continue;
        }
        $key = (string) ($mark['key'] ?? '');
        if ($key === '') {
            continue;
        }
        if (!isset($byKey[$key])) {
            $byKey[$key] = [
                'key' => $key,
                'label' => (string) ($mark['label'] ?? ''),
                'color' => (string) ($mark['color'] ?? '#8b9198'),
                'emoji' => (string) ($mark['emoji'] ?? '•'),
                'code' => (string) ($mark['code'] ?? ''),
                'is_bot' => !empty($mark['is_bot']),
                'user_id' => (int) ($mark['user_id'] ?? $row['user_id'] ?? 0),
                'is_registered' => !empty($mark['is_registered']),
                'profile_url' => (string) ($mark['profile_url'] ?? ''),
                'hits' => 0,
                'last_at' => (string) ($row['at'] ?? ''),
                'last_target' => (string) ($row['target'] ?? ''),
                'last_kind' => (string) ($row['kind_label'] ?? ''),
                'device_label' => (string) ($row['device_label'] ?? ''),
            ];
        }
        $byKey[$key]['hits']++;
        if ($byKey[$key]['device_label'] === '' && (string) ($row['device_label'] ?? '') !== '') {
            $byKey[$key]['device_label'] = (string) $row['device_label'];
        }
    }

    return array_values($byKey);
}

/**
 * @return array{
 *   users_30m: int,
 *   page_hits_30m: int,
 *   party_hits_30m: int,
 *   hub_hits_30m: int,
 *   nav_hits_30m: int,
 *   preview_hits_30m: int,
 *   external_hits_30m: int,
 *   notice_hits_30m: int,
 *   bot_hits_30m: int,
 *   visitor: 'human'|'all'|'bot',
 *   window_start: string,
 *   window_end: string,
 *   per_minute: list<array{t: string, label: string, users: int, party: int, hub: int, nav: int, preview: int, external: int}>,
 *   top_events: list<array{id: int, name: string, slug: string, unique: int, page: int, preview: int, external: int}>,
 *   top_pages: list<array{key: string, label: string, count: int}>,
 *   top_nav: list<array{key: string, label: string, count: int}>,
 *   by_source: list<array{source: string, label: string, count: int}>,
 *   recent: list<array{
 *     at: string,
 *     kind: string,
 *     kind_label: string,
 *     target: string,
 *     href: string,
 *     detail: string,
 *     event_id: int,
 *     name: string,
 *     event_date: string,
 *     metric: string,
 *     metric_label: string,
 *     source: string,
 *     source_label: string,
 *     is_bot: bool,
 *     device: string,
 *     device_label: string,
 *     visitor: array{key: string, label: string, color: string, emoji: string, code: string, is_bot: bool}
 *   }>,
 *   presence: list<array{
 *     key: string,
 *     label: string,
 *     color: string,
 *     emoji: string,
 *     code: string,
 *     is_bot: bool,
 *     hits: int,
 *     last_at: string,
 *     last_target: string,
 *     last_kind: string,
 *     device_label: string
 *   }>
 * }
 */
function events_realtime_snapshot(PDO $db, string $visitor = 'human'): array
{
    $visitor = events_realtime_normalize_visitor($visitor);

    events_view_tracking_ensure_bot_column($db);
    events_view_tracking_ensure_user_id_column($db);
    events_realtime_ensure_indexes($db);
    events_public_traffic_ensure_schema($db);
    events_public_home_notice_stats_ensure_schema($db);

    $window = events_realtime_window();
    $start = $window['start'];
    $botReady = events_view_tracking_bot_column_ready($db);
    $tableReady = events_edit_stats_table_ready($db);
    $trafficReady = events_public_traffic_tables_ready($db);
    $noticeReady = events_public_home_notice_stats_tables_ready($db);

    $emptyMinute = static function (string $minute): array {
        return [
            't' => $minute,
            'label' => substr($minute, 11, 5),
            'users' => 0,
            'party' => 0,
            'hub' => 0,
            'nav' => 0,
            'preview' => 0,
            'external' => 0,
        ];
    };

    $empty = [
        'users_30m' => 0,
        'page_hits_30m' => 0,
        'party_hits_30m' => 0,
        'hub_hits_30m' => 0,
        'nav_hits_30m' => 0,
        'preview_hits_30m' => 0,
        'external_hits_30m' => 0,
        'notice_hits_30m' => 0,
        'bot_hits_30m' => 0,
        'visitor' => $visitor,
        'window_start' => $start,
        'window_end' => $window['end'],
        'per_minute' => [],
        'top_events' => [],
        'top_pages' => [],
        'top_nav' => [],
        'by_source' => [],
        'recent' => [],
        'presence' => [],
    ];

    foreach ($window['minutes'] as $minute) {
        $empty['per_minute'][] = $emptyMinute($minute);
    }

    try {
        $partyHits = events_realtime_count_hits($db, $start, EVENTS_VIEW_METRIC_PAGE, $visitor, $botReady, $tableReady);
        $previewHits = $tableReady
            ? events_realtime_count_hits($db, $start, EVENTS_VIEW_METRIC_CALENDAR_PREVIEW, $visitor, $botReady, $tableReady)
            : 0;
        $externalHits = $tableReady
            ? events_realtime_count_hits($db, $start, EVENTS_VIEW_METRIC_EXTERNAL_INFO, $visitor, $botReady, $tableReady)
            : 0;
        $hubHits = $trafficReady
            ? events_realtime_count_traffic($db, $start, EVENTS_PUBLIC_TRAFFIC_PAGE_VIEW, $visitor)
            : 0;
        $navHits = $trafficReady
            ? events_realtime_count_traffic($db, $start, EVENTS_PUBLIC_TRAFFIC_NAV_CLICK, $visitor)
            : 0;
        $noticeHits = $noticeReady
            ? events_realtime_count_notice_clicks($db, $start, $visitor)
            : 0;

        $users30 = events_realtime_count_unique_users_all($db, $start, $visitor, $botReady, $tableReady, $trafficReady);
        $botHits = events_realtime_count_bot_hits_all($db, $start, $botReady, $tableReady, $trafficReady, $noticeReady);

        $perMinuteMap = [];
        foreach ($empty['per_minute'] as $row) {
            $perMinuteMap[$row['t']] = $row;
        }
        events_realtime_fill_per_minute($db, $start, $visitor, $botReady, $tableReady, $trafficReady, $perMinuteMap);

        $perMinute = [];
        foreach ($window['minutes'] as $minute) {
            $perMinute[] = $perMinuteMap[$minute] ?? $emptyMinute($minute);
        }

        $feed = events_realtime_recent_all(
            $db,
            $start,
            $visitor,
            $botReady,
            $tableReady,
            $trafficReady,
            $noticeReady
        );

        return [
            'users_30m' => $users30,
            // Legacy alias: buli oldalmegtekintések (régi UI / kliens kompatibilitás).
            'page_hits_30m' => $partyHits,
            'party_hits_30m' => $partyHits,
            'hub_hits_30m' => $hubHits,
            'nav_hits_30m' => $navHits,
            'preview_hits_30m' => $previewHits,
            'external_hits_30m' => $externalHits,
            'notice_hits_30m' => $noticeHits,
            'bot_hits_30m' => $botHits,
            'visitor' => $visitor,
            'window_start' => $start,
            'window_end' => $window['end'],
            'per_minute' => $perMinute,
            'top_events' => events_realtime_top_events($db, $start, $visitor, $botReady, $tableReady),
            'top_pages' => $trafficReady ? events_realtime_top_pages($db, $start, $visitor) : [],
            'top_nav' => $trafficReady ? events_realtime_top_nav($db, $start, $visitor) : [],
            'by_source' => events_realtime_by_source($db, $start, $visitor, $botReady, $tableReady),
            'recent' => $feed['recent'],
            'presence' => $feed['presence'],
        ];
    } catch (Throwable $ex) {
        error_log('events_realtime_snapshot: ' . $ex->getMessage());

        return $empty;
    }
}

function events_realtime_count_unique_users(PDO $db, string $start, bool $botReady, bool $tableReady): int
{
    return events_realtime_count_unique_users_all($db, $start, 'human', $botReady, $tableReady, false);
}

/**
 * Egyedi látogatók a buli- és a hub-/menü-forgalomból együtt.
 */
function events_realtime_count_unique_users_all(
    PDO $db,
    string $start,
    string $visitor,
    bool $botReady,
    bool $tableReady,
    bool $trafficReady
): int
{
    $visitor = events_realtime_normalize_visitor($visitor);
    $parts = [];
    $params = [];

    $metricAnd = $tableReady ? ' AND `metric_type` = ?' : '';
    $botAnd = events_realtime_bot_and($visitor, $botReady);
    $parts[] = "SELECT `ip_hash`
                FROM `events_calendar_event_views`
                WHERE `létrehozva` >= ?
                  AND `ip_hash` IS NOT NULL AND `ip_hash` <> ''
                  {$botAnd}{$metricAnd}";
    $params[] = $start;
    if ($tableReady) {
        $params[] = EVENTS_VIEW_METRIC_PAGE;
    }

    if ($trafficReady) {
        $trafficBotAnd = events_realtime_bot_and($visitor, true);
        $parts[] = "SELECT `ip_hash`
                    FROM `events_public_traffic`
                    WHERE `occurred_at` >= ?
                      AND `event_type` = ?
                      AND `ip_hash` IS NOT NULL AND `ip_hash` <> ''
                      {$trafficBotAnd}";
        $params[] = $start;
        $params[] = EVENTS_PUBLIC_TRAFFIC_PAGE_VIEW;
    }

    if ($parts === []) {
        return 0;
    }

    $sql = 'SELECT COUNT(DISTINCT `ip_hash`) FROM (' . implode(' UNION ALL ', $parts) . ') u';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function events_realtime_count_hits(
    PDO $db,
    string $start,
    string $metricType,
    string $visitor,
    bool $botReady,
    bool $tableReady
): int {
    $visitor = events_realtime_normalize_visitor($visitor);
    $metricAnd = $tableReady ? ' AND `metric_type` = ?' : '';
    $botAnd = events_realtime_bot_and($visitor, $botReady);

    $sql = "SELECT COUNT(*) FROM `events_calendar_event_views`
            WHERE `létrehozva` >= ?{$botAnd}{$metricAnd}";
    $params = [$start];
    if ($tableReady) {
        $params[] = $metricType;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function events_realtime_count_traffic(PDO $db, string $start, string $eventType, string $visitor): int
{
    $visitor = events_realtime_normalize_visitor($visitor);
    $botAnd = events_realtime_bot_and($visitor, true);
    $sql = "SELECT COUNT(*) FROM `events_public_traffic`
            WHERE `occurred_at` >= ? AND `event_type` = ?{$botAnd}";
    $stmt = $db->prepare($sql);
    $stmt->execute([$start, $eventType]);

    return (int) $stmt->fetchColumn();
}

function events_realtime_count_notice_clicks(PDO $db, string $start, string $visitor): int
{
    $visitor = events_realtime_normalize_visitor($visitor);
    $botAnd = events_realtime_bot_and($visitor, true);
    $sql = "SELECT COUNT(*) FROM `events_public_home_notice_clicks`
            WHERE `clicked_at` >= ?{$botAnd}";
    $stmt = $db->prepare($sql);
    $stmt->execute([$start]);

    return (int) $stmt->fetchColumn();
}

function events_realtime_count_bot_hits(PDO $db, string $start, bool $tableReady): int
{
    $metricAnd = $tableReady ? ' AND `metric_type` IN (?, ?, ?)' : '';
    $sql = "SELECT COUNT(*) FROM `events_calendar_event_views`
            WHERE `létrehozva` >= ? AND `is_bot` = 1{$metricAnd}";
    $params = [$start];
    if ($tableReady) {
        $params[] = EVENTS_VIEW_METRIC_PAGE;
        $params[] = EVENTS_VIEW_METRIC_CALENDAR_PREVIEW;
        $params[] = EVENTS_VIEW_METRIC_EXTERNAL_INFO;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function events_realtime_count_bot_hits_all(
    PDO $db,
    string $start,
    bool $botReady,
    bool $tableReady,
    bool $trafficReady,
    bool $noticeReady
): int {
    $total = 0;
    if ($botReady) {
        $total += events_realtime_count_bot_hits($db, $start, $tableReady);
    }
    if ($trafficReady) {
        $total += events_realtime_count_traffic($db, $start, EVENTS_PUBLIC_TRAFFIC_PAGE_VIEW, 'bot');
        $total += events_realtime_count_traffic($db, $start, EVENTS_PUBLIC_TRAFFIC_NAV_CLICK, 'bot');
    }
    if ($noticeReady) {
        $total += events_realtime_count_notice_clicks($db, $start, 'bot');
    }

    return $total;
}

/**
 * @param array<string, array{t: string, label: string, users: int, party: int, hub: int, nav: int, preview: int, external: int}> $perMinuteMap
 */
function events_realtime_fill_per_minute(
    PDO $db,
    string $start,
    string $visitor,
    bool $botReady,
    bool $tableReady,
    bool $trafficReady,
    array &$perMinuteMap
): void {
    $visitor = events_realtime_normalize_visitor($visitor);
    $botAnd = events_realtime_bot_and($visitor, $botReady);
    $metricSelect = $tableReady ? '`metric_type`' : "'" . EVENTS_VIEW_METRIC_PAGE . "' AS `metric_type`";

    $sqlHits = "SELECT DATE_FORMAT(`létrehozva`, '%Y-%m-%d %H:%i:00') AS bucket,
                       {$metricSelect},
                       COUNT(*) AS cnt
                FROM `events_calendar_event_views`
                WHERE `létrehozva` >= ?{$botAnd}
                GROUP BY bucket" . ($tableReady ? ', `metric_type`' : '');
    $stmt = $db->prepare($sqlHits);
    $stmt->execute([$start]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $bucket = (string) ($row['bucket'] ?? '');
        if (!isset($perMinuteMap[$bucket])) {
            continue;
        }
        $metric = (string) ($row['metric_type'] ?? EVENTS_VIEW_METRIC_PAGE);
        $cnt = (int) ($row['cnt'] ?? 0);
        if ($metric === EVENTS_VIEW_METRIC_CALENDAR_PREVIEW) {
            $perMinuteMap[$bucket]['preview'] += $cnt;
        } elseif ($metric === EVENTS_VIEW_METRIC_EXTERNAL_INFO) {
            $perMinuteMap[$bucket]['external'] += $cnt;
        } elseif ($metric === EVENTS_VIEW_METRIC_PAGE) {
            $perMinuteMap[$bucket]['party'] += $cnt;
        }
    }

    if ($trafficReady) {
        $trafficBotAnd = events_realtime_bot_and($visitor, true);
        $sqlTraffic = "SELECT DATE_FORMAT(`occurred_at`, '%Y-%m-%d %H:%i:00') AS bucket,
                              `event_type`,
                              COUNT(*) AS cnt
                       FROM `events_public_traffic`
                       WHERE `occurred_at` >= ?{$trafficBotAnd}
                       GROUP BY bucket, `event_type`";
        $stmt = $db->prepare($sqlTraffic);
        $stmt->execute([$start]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $bucket = (string) ($row['bucket'] ?? '');
            if (!isset($perMinuteMap[$bucket])) {
                continue;
            }
            $type = (string) ($row['event_type'] ?? '');
            $cnt = (int) ($row['cnt'] ?? 0);
            if ($type === EVENTS_PUBLIC_TRAFFIC_PAGE_VIEW) {
                $perMinuteMap[$bucket]['hub'] += $cnt;
            } elseif ($type === EVENTS_PUBLIC_TRAFFIC_NAV_CLICK) {
                $perMinuteMap[$bucket]['nav'] += $cnt;
            }
        }
    }

    // Unique users per minute (buli + hub oldal).
    $parts = [];
    $params = [];
    $metricAnd = $tableReady ? ' AND `metric_type` = ?' : '';
    $parts[] = "SELECT DATE_FORMAT(`létrehozva`, '%Y-%m-%d %H:%i:00') AS bucket, `ip_hash`
                FROM `events_calendar_event_views`
                WHERE `létrehozva` >= ?
                  AND `ip_hash` IS NOT NULL AND `ip_hash` <> ''
                  {$botAnd}{$metricAnd}";
    $params[] = $start;
    if ($tableReady) {
        $params[] = EVENTS_VIEW_METRIC_PAGE;
    }
    if ($trafficReady) {
        $trafficBotAnd = events_realtime_bot_and($visitor, true);
        $parts[] = "SELECT DATE_FORMAT(`occurred_at`, '%Y-%m-%d %H:%i:00') AS bucket, `ip_hash`
                    FROM `events_public_traffic`
                    WHERE `occurred_at` >= ?
                      AND `event_type` = ?
                      AND `ip_hash` IS NOT NULL AND `ip_hash` <> ''
                      {$trafficBotAnd}";
        $params[] = $start;
        $params[] = EVENTS_PUBLIC_TRAFFIC_PAGE_VIEW;
    }

    $sqlUsers = 'SELECT bucket, COUNT(DISTINCT `ip_hash`) AS cnt
                 FROM (' . implode(' UNION ALL ', $parts) . ') x
                 GROUP BY bucket';
    $stmt = $db->prepare($sqlUsers);
    $stmt->execute($params);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $bucket = (string) ($row['bucket'] ?? '');
        if (!isset($perMinuteMap[$bucket])) {
            continue;
        }
        $perMinuteMap[$bucket]['users'] = (int) ($row['cnt'] ?? 0);
    }
}

/**
 * @return list<array{id: int, name: string, slug: string, unique: int, page: int, preview: int, external: int}>
 */
function events_realtime_top_events(PDO $db, string $start, string $visitor, bool $botReady, bool $tableReady): array
{
    $visitor = events_realtime_normalize_visitor($visitor);
    $botAnd = events_realtime_bot_and($visitor, $botReady, 'v.`is_bot`');
    $pageMetricAnd = $tableReady ? ' AND v.`metric_type` = ?' : '';
    $previewMetricAnd = $tableReady ? ' AND v.`metric_type` = ?' : ' AND 1 = 0';
    $externalMetricAnd = $tableReady ? ' AND v.`metric_type` = ?' : ' AND 1 = 0';

    $sql = "
        SELECT e.`id`, e.`event_name`, e.`event_slug`,
            COALESCE(u.unique_cnt, 0) AS unique_cnt,
            COALESCE(p.page_cnt, 0) AS page_cnt,
            COALESCE(pr.preview_cnt, 0) AS preview_cnt,
            COALESCE(ex.external_cnt, 0) AS external_cnt
        FROM `events_calendar_events` e
        INNER JOIN (
            SELECT v.`esemény_id` AS event_id, COUNT(*) AS page_cnt
            FROM `events_calendar_event_views` v
            WHERE v.`létrehozva` >= ?{$botAnd}{$pageMetricAnd}
            GROUP BY v.`esemény_id`
        ) p ON p.event_id = e.`id`
        LEFT JOIN (
            SELECT v.`esemény_id` AS event_id, COUNT(DISTINCT v.`ip_hash`) AS unique_cnt
            FROM `events_calendar_event_views` v
            WHERE v.`létrehozva` >= ?
              AND v.`ip_hash` IS NOT NULL AND v.`ip_hash` <> ''
              {$botAnd}{$pageMetricAnd}
            GROUP BY v.`esemény_id`
        ) u ON u.event_id = e.`id`
        LEFT JOIN (
            SELECT v.`esemény_id` AS event_id, COUNT(*) AS preview_cnt
            FROM `events_calendar_event_views` v
            WHERE v.`létrehozva` >= ?{$botAnd}{$previewMetricAnd}
            GROUP BY v.`esemény_id`
        ) pr ON pr.event_id = e.`id`
        LEFT JOIN (
            SELECT v.`esemény_id` AS event_id, COUNT(*) AS external_cnt
            FROM `events_calendar_event_views` v
            WHERE v.`létrehozva` >= ?{$botAnd}{$externalMetricAnd}
            GROUP BY v.`esemény_id`
        ) ex ON ex.event_id = e.`id`
        ORDER BY page_cnt DESC, unique_cnt DESC, e.`id` DESC
        LIMIT " . (int) EVENTS_REALTIME_TOP_EVENTS;

    $params = [$start];
    if ($tableReady) {
        $params[] = EVENTS_VIEW_METRIC_PAGE;
    }
    $params[] = $start;
    if ($tableReady) {
        $params[] = EVENTS_VIEW_METRIC_PAGE;
    }
    $params[] = $start;
    if ($tableReady) {
        $params[] = EVENTS_VIEW_METRIC_CALENDAR_PREVIEW;
    }
    $params[] = $start;
    if ($tableReady) {
        $params[] = EVENTS_VIEW_METRIC_EXTERNAL_INFO;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['event_name'] ?? ''),
            'slug' => (string) ($row['event_slug'] ?? ''),
            'unique' => (int) ($row['unique_cnt'] ?? 0),
            'page' => (int) ($row['page_cnt'] ?? 0),
            'preview' => (int) ($row['preview_cnt'] ?? 0),
            'external' => (int) ($row['external_cnt'] ?? 0),
        ];
    }

    return $out;
}

/**
 * @return list<array{key: string, label: string, count: int}>
 */
function events_realtime_top_pages(PDO $db, string $start, string $visitor = 'human'): array
{
    $visitor = events_realtime_normalize_visitor($visitor);
    $botAnd = events_realtime_bot_and($visitor, true);
    $sql = "SELECT `page_key`, COUNT(*) AS cnt
            FROM `events_public_traffic`
            WHERE `occurred_at` >= ?
              AND `event_type` = ?
              AND `page_key` <> ''
              {$botAnd}
            GROUP BY `page_key`
            ORDER BY cnt DESC
            LIMIT " . (int) EVENTS_REALTIME_TOP_PAGES;
    $stmt = $db->prepare($sql);
    $stmt->execute([$start, EVENTS_PUBLIC_TRAFFIC_PAGE_VIEW]);

    $out = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $key = (string) ($row['page_key'] ?? '');
        $out[] = [
            'key' => $key,
            'label' => events_public_traffic_page_label($key),
            'count' => (int) ($row['cnt'] ?? 0),
        ];
    }

    return $out;
}

/**
 * @return list<array{key: string, label: string, count: int}>
 */
function events_realtime_top_nav(PDO $db, string $start, string $visitor = 'human'): array
{
    $visitor = events_realtime_normalize_visitor($visitor);
    $botAnd = events_realtime_bot_and($visitor, true);
    $sql = "SELECT `nav_key`, COUNT(*) AS cnt
            FROM `events_public_traffic`
            WHERE `occurred_at` >= ?
              AND `event_type` = ?
              AND `nav_key` <> ''
              {$botAnd}
            GROUP BY `nav_key`
            ORDER BY cnt DESC
            LIMIT " . (int) EVENTS_REALTIME_TOP_NAV;
    $stmt = $db->prepare($sql);
    $stmt->execute([$start, EVENTS_PUBLIC_TRAFFIC_NAV_CLICK]);

    $out = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $key = (string) ($row['nav_key'] ?? '');
        $out[] = [
            'key' => $key,
            'label' => events_public_traffic_nav_label($key, $db),
            'count' => (int) ($row['cnt'] ?? 0),
        ];
    }

    return $out;
}

/**
 * @return list<array{source: string, label: string, count: int}>
 */
function events_realtime_by_source(PDO $db, string $start, string $visitor, bool $botReady, bool $tableReady): array
{
    $visitor = events_realtime_normalize_visitor($visitor);
    $botAnd = events_realtime_bot_and($visitor, $botReady);
    $metricAnd = $tableReady ? ' AND `metric_type` = ?' : '';
    $sql = "SELECT COALESCE(NULLIF(TRIM(`source`), ''), 'direct') AS src, COUNT(*) AS cnt
            FROM `events_calendar_event_views`
            WHERE `létrehozva` >= ?{$botAnd}{$metricAnd}
            GROUP BY src
            ORDER BY cnt DESC";
    $params = [$start];
    if ($tableReady) {
        $params[] = EVENTS_VIEW_METRIC_PAGE;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $out = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $source = (string) ($row['src'] ?? EVENTS_VIEW_SOURCE_DIRECT);
        $out[] = [
            'source' => $source,
            'label' => events_realtime_source_label($source),
            'count' => (int) ($row['cnt'] ?? 0),
        ];
    }

    return $out;
}

/**
 * @return array{
 *   recent: list<array<string, mixed>>,
 *   presence: list<array<string, mixed>>
 * }
 */
function events_realtime_recent_all(
    PDO $db,
    string $start,
    string $visitor,
    bool $botReady,
    bool $tableReady,
    bool $trafficReady,
    bool $noticeReady
): array {
    if (!function_exists('events_admin_format_datum_cell')) {
        require_once __DIR__ . '/admin_event_filters.php';
    }

    $visitor = events_realtime_normalize_visitor($visitor);
    $fetchLimit = (int) EVENTS_REALTIME_RECENT_LIMIT;
    $poolLimit = max($fetchLimit, (int) EVENTS_REALTIME_FEED_POOL);
    $items = [];

    $botSelect = $botReady ? 'v.`is_bot`' : '0 AS `is_bot`';
    $metricSelect = $tableReady ? 'v.`metric_type`' : "'" . EVENTS_VIEW_METRIC_PAGE . "' AS `metric_type`";
    $userIdReady = events_view_tracking_user_id_column_ready($db);
    $userSelect = $userIdReady ? 'v.`user_id`' : '0 AS `user_id`';
    $eventsBotAnd = events_realtime_bot_and($visitor, $botReady, 'v.`is_bot`');
    $sqlEvents = "SELECT v.`létrehozva` AS at_ts, v.`esemény_id` AS event_id,
                         e.`event_name`, e.`event_start`, e.`event_end`, e.`event_allday`,
                         {$metricSelect}, v.`source`, {$botSelect}, v.`ip_hash`, {$userSelect}
                  FROM `events_calendar_event_views` v
                  LEFT JOIN `events_calendar_events` e ON e.`id` = v.`esemény_id`
                  WHERE v.`létrehozva` >= ?{$eventsBotAnd}
                  ORDER BY v.`létrehozva` DESC
                  LIMIT {$poolLimit}";
    $stmt = $db->prepare($sqlEvents);
    $stmt->execute([$start]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $metric = (string) ($row['metric_type'] ?? EVENTS_VIEW_METRIC_PAGE);
        $source = trim((string) ($row['source'] ?? ''));
        if ($source === '') {
            $source = EVENTS_VIEW_SOURCE_DIRECT;
        }
        $eventId = (int) ($row['event_id'] ?? 0);
        $name = (string) ($row['event_name'] ?? ($eventId > 0 ? '#' . $eventId : 'Ismeretlen buli'));
        $kind = match ($metric) {
            EVENTS_VIEW_METRIC_CALENDAR_PREVIEW => 'preview',
            EVENTS_VIEW_METRIC_EXTERNAL_INFO => 'external',
            default => 'party',
        };
        $isBot = (int) ($row['is_bot'] ?? 0) === 1;
        $items[] = events_realtime_with_visitor([
            'at' => (string) ($row['at_ts'] ?? ''),
            'kind' => $kind,
            'kind_label' => events_realtime_kind_label($kind),
            'target' => $name,
            'href' => $eventId > 0 ? 'event:' . $eventId : '',
            'detail' => events_realtime_source_label($source),
            'event_id' => $eventId,
            'name' => $name,
            'event_date' => events_admin_format_datum_cell($row),
            'metric' => $metric,
            'metric_label' => events_realtime_metric_label($metric),
            'source' => $source,
            'source_label' => events_realtime_source_label($source),
        ], $row['ip_hash'] ?? null, $isBot, '', (int) ($row['user_id'] ?? 0));
    }

    if ($trafficReady) {
        $trafficBotAnd = events_realtime_bot_and($visitor, true);
        $trafficUserReady = events_public_traffic_user_id_column_ready($db);
        $trafficUserSelect = $trafficUserReady ? '`user_id`' : '0 AS `user_id`';
        $sqlTraffic = "SELECT `occurred_at` AS at_ts, `event_type`, `page_key`, `nav_key`,
                              `entity_label`, `lang`, `device`, `is_bot`, `ip_hash`, {$trafficUserSelect}
                       FROM `events_public_traffic`
                       WHERE `occurred_at` >= ?{$trafficBotAnd}
                       ORDER BY `occurred_at` DESC
                       LIMIT {$poolLimit}";
        $stmt = $db->prepare($sqlTraffic);
        $stmt->execute([$start]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $type = (string) ($row['event_type'] ?? '');
            $pageKey = (string) ($row['page_key'] ?? '');
            $navKey = (string) ($row['nav_key'] ?? '');
            $lang = strtoupper((string) ($row['lang'] ?? 'hu'));
            $entityLabel = trim((string) ($row['entity_label'] ?? ''));

            if ($type === EVENTS_PUBLIC_TRAFFIC_NAV_CLICK) {
                $target = events_public_traffic_nav_label($navKey, $db);
                $detailParts = ['Menükattintás', $lang];
                if ($pageKey !== '') {
                    $detailParts[] = 'ról: ' . events_public_traffic_page_label($pageKey);
                }
                $isBot = (int) ($row['is_bot'] ?? 0) === 1;
                $items[] = events_realtime_with_visitor([
                    'at' => (string) ($row['at_ts'] ?? ''),
                    'kind' => 'nav',
                    'kind_label' => events_realtime_kind_label('nav'),
                    'target' => $target,
                    'href' => '',
                    'detail' => implode(' · ', $detailParts),
                    'event_id' => 0,
                    'name' => $target,
                    'event_date' => '–',
                    'metric' => 'nav_click',
                    'metric_label' => events_realtime_metric_label('nav_click'),
                    'source' => $navKey,
                    'source_label' => $target,
                ], $row['ip_hash'] ?? null, $isBot, (string) ($row['device'] ?? ''), (int) ($row['user_id'] ?? 0));
            } else {
                $target = events_public_traffic_page_label($pageKey);
                if ($entityLabel !== '') {
                    $target .= ' · ' . $entityLabel;
                }
                $isBot = (int) ($row['is_bot'] ?? 0) === 1;
                $items[] = events_realtime_with_visitor([
                    'at' => (string) ($row['at_ts'] ?? ''),
                    'kind' => 'hub',
                    'kind_label' => events_realtime_kind_label('hub'),
                    'target' => $target,
                    'href' => '',
                    'detail' => 'Oldalmegtekintés · ' . $lang,
                    'event_id' => 0,
                    'name' => $target,
                    'event_date' => '–',
                    'metric' => 'hub_page',
                    'metric_label' => events_realtime_metric_label('hub_page'),
                    'source' => $pageKey,
                    'source_label' => events_public_traffic_page_label($pageKey),
                ], $row['ip_hash'] ?? null, $isBot, (string) ($row['device'] ?? ''), (int) ($row['user_id'] ?? 0));
            }
        }
    }

    if ($noticeReady) {
        $noticeBotAnd = events_realtime_bot_and($visitor, true);
        $noticeUserReady = events_public_home_notice_has_column($db, 'events_public_home_notice_clicks', 'user_id');
        $noticeUserSelect = $noticeUserReady ? '`user_id`' : '0 AS `user_id`';
        $sqlNotice = "SELECT `clicked_at` AS at_ts, `notice_text`, `notice_url`, `lang`, `is_bot`, `ip_hash`, {$noticeUserSelect}
                      FROM `events_public_home_notice_clicks`
                      WHERE `clicked_at` >= ?{$noticeBotAnd}
                      ORDER BY `clicked_at` DESC
                      LIMIT {$poolLimit}";
        $stmt = $db->prepare($sqlNotice);
        $stmt->execute([$start]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $text = trim((string) ($row['notice_text'] ?? ''));
            if ($text === '') {
                $text = 'Értesítő';
            }
            $text = events_public_home_notice_truncate($text, 64);
            $lang = strtoupper((string) ($row['lang'] ?? 'hu'));
            $isBot = (int) ($row['is_bot'] ?? 0) === 1;
            $items[] = events_realtime_with_visitor([
                'at' => (string) ($row['at_ts'] ?? ''),
                'kind' => 'notice',
                'kind_label' => events_realtime_kind_label('notice'),
                'target' => $text,
                'href' => '',
                'detail' => 'Értesítő kattintás · ' . $lang,
                'event_id' => 0,
                'name' => $text,
                'event_date' => '–',
                'metric' => 'notice_click',
                'metric_label' => events_realtime_metric_label('notice_click'),
                'source' => 'notice',
                'source_label' => 'Értesítő',
            ], $row['ip_hash'] ?? null, $isBot, '', (int) ($row['user_id'] ?? 0));
        }
    }

    usort(
        $items,
        static function (array $a, array $b): int {
            return strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? ''));
        }
    );

    $items = events_realtime_apply_user_names($db, $items);

    return [
        'recent' => array_slice($items, 0, $fetchLimit),
        'presence' => array_slice(
            events_realtime_presence_from_items($items),
            0,
            (int) EVENTS_REALTIME_PRESENCE_LIMIT
        ),
    ];
}

/**
 * @return list<array{at: string, event_id: int, name: string, event_date: string, metric: string, metric_label: string, source: string, source_label: string, is_bot: bool}>
 * @deprecated Use events_realtime_recent_all()
 */
function events_realtime_recent(PDO $db, string $start, bool $botReady, bool $tableReady): array
{
    $feed = events_realtime_recent_all(
        $db,
        $start,
        'human',
        $botReady,
        $tableReady,
        events_public_traffic_tables_ready($db),
        events_public_home_notice_stats_tables_ready($db)
    );

    return $feed['recent'];
}
