<?php
declare(strict_types=1);

/**
 * Mobilapp / PWA használat és telepítés statisztika.
 */

require_once dirname(__DIR__, 2) . '/events/lib/event_edit_stats.php';
require_once dirname(__DIR__, 2) . '/events/lib/public_traffic.php';
require_once __DIR__ . '/site_modules.php';
require_once dirname(__DIR__, 2) . '/includes/landingpage_table.php';

/** @return list<string> */
function latinfo_mobilapp_event_types(): array
{
    return [
        'page_view',
        'install_prompt',
        'install_accepted',
        'install_dismissed',
        'app_installed',
    ];
}

function latinfo_mobilapp_normalize_event(mixed $raw): string
{
    $key = strtolower(trim((string) $raw));

    return in_array($key, latinfo_mobilapp_event_types(), true) ? $key : '';
}

function latinfo_mobilapp_event_label(string $eventType): string
{
    return match ($eventType) {
        'page_view' => 'Mobilapp oldal megnyitás',
        'install_prompt' => 'Telepítő prompt',
        'install_accepted' => 'Telepítés elfogadva',
        'install_dismissed' => 'Telepítés elutasítva',
        'app_installed' => 'Telepítés kész',
        default => $eventType,
    };
}

function latinfo_mobilapp_stat_url(string $query = ''): string
{
    $base = nextgen_url('site/mobilapp_stat.php');

    return $query === '' ? $base : $base . '?' . ltrim($query, '?');
}

function latinfo_mobilapp_events_ensure_schema(PDO $db): bool
{
    static $ready = false;
    if ($ready) {
        return true;
    }

    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `latinfo_mobilapp_events` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `event_type` VARCHAR(32) NOT NULL,
                `device` VARCHAR(16) NOT NULL DEFAULT 'unknown',
                `ip_hash` CHAR(64) NULL DEFAULT NULL,
                `is_bot` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `user_agent` VARCHAR(512) NULL DEFAULT NULL,
                `occurred_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_ma_event_type_time` (`event_type`, `occurred_at`),
                KEY `idx_ma_event_bot` (`is_bot`, `occurred_at`),
                KEY `idx_ma_event_device` (`device`, `occurred_at`),
                KEY `idx_ma_event_ip` (`ip_hash`, `occurred_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $ready = true;

        return true;
    } catch (Throwable $e) {
        error_log('latinfo_mobilapp_events_ensure_schema: ' . $e->getMessage());

        return false;
    }
}

function latinfo_mobilapp_should_record(): bool
{
    if (function_exists('events_view_tracking_should_record')) {
        return events_view_tracking_should_record();
    }

    return !(function_exists('isLoggedIn') && isLoggedIn());
}

/**
 * @return array{ok: bool, recorded: bool}
 */
function latinfo_mobilapp_track_event(PDO $db, string $eventType): array
{
    $eventType = latinfo_mobilapp_normalize_event($eventType);
    if ($eventType === '') {
        return ['ok' => false, 'recorded' => false];
    }

    if (!latinfo_mobilapp_events_ensure_schema($db)) {
        return ['ok' => false, 'recorded' => false];
    }

    if (!latinfo_mobilapp_should_record()) {
        return ['ok' => true, 'recorded' => false];
    }

    $isBot = (function_exists('events_view_tracking_detect_bot') && events_view_tracking_detect_bot()) ? 1 : 0;
    $device = function_exists('events_public_traffic_detect_device')
        ? events_public_traffic_detect_device()
        : 'unknown';
    $ipHash = function_exists('events_view_tracking_ip_hash')
        ? events_view_tracking_ip_hash()
        : null;
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (strlen($ua) > 512) {
        $ua = substr($ua, 0, 512);
    }

    try {
        // Ugyanarra a napra / IP-re ne számoljuk tízszer a telepítést vagy a promptot.
        if (in_array($eventType, ['app_installed', 'install_prompt', 'page_view'], true) && $ipHash !== null && $ipHash !== '') {
            $stDup = $db->prepare('
                SELECT 1
                FROM `latinfo_mobilapp_events`
                WHERE `event_type` = ?
                  AND `ip_hash` = ?
                  AND `occurred_at` >= CURDATE()
                LIMIT 1
            ');
            $stDup->execute([$eventType, $ipHash]);
            if ($stDup->fetchColumn()) {
                return ['ok' => true, 'recorded' => false];
            }
        }

        $st = $db->prepare('
            INSERT INTO `latinfo_mobilapp_events`
                (`event_type`, `device`, `ip_hash`, `is_bot`, `user_agent`)
            VALUES (?, ?, ?, ?, ?)
        ');
        $st->execute([
            $eventType,
            $device,
            $ipHash !== '' ? $ipHash : null,
            $isBot,
            $ua !== '' ? $ua : null,
        ]);

        return ['ok' => true, 'recorded' => true];
    } catch (Throwable $e) {
        error_log('latinfo_mobilapp_track_event: ' . $e->getMessage());

        return ['ok' => false, 'recorded' => false];
    }
}

/**
 * @return array{date_from: string, date_to: string, visitor: string, device: string}
 */
function latinfo_mobilapp_stats_params_from_request(array $query): array
{
    $base = events_edit_stats_params_from_request($query);
    $visitor = strtolower(trim((string) ($query['visitor'] ?? 'human')));
    if (!in_array($visitor, ['all', 'human', 'bot'], true)) {
        $visitor = 'human';
    }
    $device = strtolower(trim((string) ($query['device'] ?? 'all')));
    if (!in_array($device, ['all', 'desktop', 'mobile', 'tablet', 'unknown'], true)) {
        $device = 'all';
    }

    return [
        'date_from' => (string) $base['date_from'],
        'date_to' => (string) $base['date_to'],
        'visitor' => $visitor,
        'device' => $device,
    ];
}

/**
 * @param array{date_from: string, date_to: string, visitor?: string, device?: string} $params
 * @return array{sql: string, bind: list<mixed>}
 */
function latinfo_mobilapp_events_where(array $params, string $alias = 'e'): array
{
    $sql = ["{$alias}.`occurred_at` >= ?", "{$alias}.`occurred_at` < DATE_ADD(?, INTERVAL 1 DAY)"];
    $bind = [$params['date_from'], $params['date_to']];

    $visitor = (string) ($params['visitor'] ?? 'human');
    if ($visitor === 'human') {
        $sql[] = "{$alias}.`is_bot` = 0";
    } elseif ($visitor === 'bot') {
        $sql[] = "{$alias}.`is_bot` = 1";
    }

    $device = (string) ($params['device'] ?? 'all');
    if ($device !== 'all') {
        $sql[] = "{$alias}.`device` = ?";
        $bind[] = $device;
    }

    return ['sql' => implode(' AND ', $sql), 'bind' => $bind];
}

function latinfo_mobilapp_stats_earliest_date(PDO $db): ?string
{
    $candidates = [];
    try {
        if (latinfo_mobilapp_events_ensure_schema($db)) {
            $raw = $db->query('SELECT MIN(`occurred_at`) FROM `latinfo_mobilapp_events`')->fetchColumn();
            if (is_string($raw) && $raw !== '') {
                $candidates[] = substr($raw, 0, 10);
            }
        }
    } catch (Throwable $e) {
        // ignore
    }

    try {
        if (latinfo_home_modules_ensure_schema($db)) {
            $st = $db->query("SELECT MIN(`occurred_at`) FROM `latinfo_home_module_clicks` WHERE `surface` = 'app'");
            $raw = $st ? $st->fetchColumn() : false;
            if (is_string($raw) && $raw !== '') {
                $candidates[] = substr($raw, 0, 10);
            }
        }
    } catch (Throwable $e) {
        // ignore
    }

    try {
        ensure_landingpage_table($db);
        $st = $db->query("
            SELECT MIN(`létrehozva`) FROM `nextgen_landing_feedback`
            WHERE `forras` IN ('mobilapp', 'mobileapp')
        ");
        $raw = $st ? $st->fetchColumn() : false;
        if (is_string($raw) && $raw !== '') {
            $candidates[] = substr($raw, 0, 10);
        }
    } catch (Throwable $e) {
        // ignore
    }

    if ($candidates === []) {
        return null;
    }
    sort($candidates);

    return $candidates[0];
}

/**
 * @param array{date_from: string, date_to: string, visitor: string, device: string} $params
 * @return array<string, mixed>
 */
function latinfo_mobilapp_overview_stats(PDO $db, array $params): array
{
    $empty = [
        'table_ready' => false,
        'funnel' => [
            'page_views' => 0,
            'page_unique' => 0,
            'install_prompt' => 0,
            'install_accepted' => 0,
            'install_dismissed' => 0,
            'app_installed' => 0,
            'install_unique' => 0,
        ],
        'usage' => [
            'clicks_human' => 0,
            'unique_human' => 0,
            'modules_hit' => 0,
        ],
        'modules' => [],
        'devices_install' => [],
        'devices_usage' => [],
        'feedback' => [
            'count' => 0,
            'with_device' => 0,
            'devices' => [],
            'recent' => [],
        ],
        'chart' => ['labels' => [], 'datasets' => []],
        'granularity' => 'day',
        'notes' => [
            'Az „Telepítés kész” főleg Android Chrome / Edge PWA telepítéseket számol; iOS Safari „Hozzáadás a Főképernyőhöz” gyakran nem jelez eseményt.',
            'A kezdőoldali app-használat a telepített PWA / ?source=mobilapp felületen mért modul-kattintásokból jön.',
        ],
    ];

    $eventsReady = latinfo_mobilapp_events_ensure_schema($db);
    $modulesReady = latinfo_home_modules_ensure_schema($db);
    $empty['table_ready'] = $eventsReady || $modulesReady;

    $from = new DateTimeImmutable($params['date_from']);
    $to = new DateTimeImmutable($params['date_to']);
    $days = max(1, (int) $from->diff($to)->days + 1);
    $granularity = $days > 120 ? 'month' : ($days > 45 ? 'week' : 'day');
    $empty['granularity'] = $granularity;

    // --- Funnel / install events ---
    if ($eventsReady) {
        try {
            $where = latinfo_mobilapp_events_where($params, 'e');
            $st = $db->prepare("
                SELECT
                    e.`event_type`,
                    COUNT(*) AS cnt,
                    COUNT(DISTINCT CASE WHEN e.`ip_hash` IS NOT NULL THEN e.`ip_hash` END) AS uniq
                FROM `latinfo_mobilapp_events` e
                WHERE {$where['sql']}
                GROUP BY e.`event_type`
            ");
            $st->execute($where['bind']);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $type = (string) ($row['event_type'] ?? '');
                $cnt = (int) ($row['cnt'] ?? 0);
                $uniq = (int) ($row['uniq'] ?? 0);
                if ($type === 'page_view') {
                    $empty['funnel']['page_views'] = $cnt;
                    $empty['funnel']['page_unique'] = $uniq;
                } elseif ($type === 'install_prompt') {
                    $empty['funnel']['install_prompt'] = $cnt;
                } elseif ($type === 'install_accepted') {
                    $empty['funnel']['install_accepted'] = $cnt;
                } elseif ($type === 'install_dismissed') {
                    $empty['funnel']['install_dismissed'] = $cnt;
                } elseif ($type === 'app_installed') {
                    $empty['funnel']['app_installed'] = $cnt;
                    $empty['funnel']['install_unique'] = $uniq;
                }
            }

            $stDev = $db->prepare("
                SELECT e.`device`, COUNT(*) AS cnt
                FROM `latinfo_mobilapp_events` e
                WHERE {$where['sql']} AND e.`event_type` = 'app_installed'
                GROUP BY e.`device`
                ORDER BY cnt DESC
            ");
            $stDev->execute($where['bind']);
            foreach ($stDev->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $empty['devices_install'][] = [
                    'device' => (string) ($row['device'] ?? 'unknown'),
                    'count' => (int) ($row['cnt'] ?? 0),
                ];
            }
        } catch (Throwable $e) {
            error_log('latinfo_mobilapp_overview_stats events: ' . $e->getMessage());
        }
    }

    // --- App surface module usage ---
    if ($modulesReady) {
        try {
            $moduleParams = [
                'date_from' => $params['date_from'],
                'date_to' => $params['date_to'],
                'module' => 'all',
                'visitor' => $params['visitor'],
                'lang' => 'all',
                'device' => $params['device'],
                'surface' => 'app',
            ];
            $modStats = latinfo_home_module_overview_stats($db, $moduleParams);
            $empty['usage'] = [
                'clicks_human' => (int) ($modStats['totals']['clicks_human'] ?? 0),
                'unique_human' => (int) ($modStats['totals']['unique_human'] ?? 0),
                'modules_hit' => (int) ($modStats['totals']['modules_hit'] ?? 0),
            ];
            $empty['modules'] = is_array($modStats['modules'] ?? null) ? $modStats['modules'] : [];

            $whereMod = latinfo_home_module_stats_where($moduleParams);
            $stDevU = $db->prepare("
                SELECT c.`device`,
                    SUM(CASE WHEN c.`is_bot` = 0 THEN 1 ELSE 0 END) AS clicks_human
                FROM `latinfo_home_module_clicks` c
                WHERE {$whereMod['sql']}
                GROUP BY c.`device`
                ORDER BY clicks_human DESC
            ");
            $stDevU->execute($whereMod['bind']);
            foreach ($stDevU->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $empty['devices_usage'][] = [
                    'device' => (string) ($row['device'] ?? 'unknown'),
                    'count' => (int) ($row['clicks_human'] ?? 0),
                ];
            }
        } catch (Throwable $e) {
            error_log('latinfo_mobilapp_overview_stats usage: ' . $e->getMessage());
        }
    }

    // --- Feedback ---
    try {
        ensure_landingpage_table($db);
        $fbSql = '
            SELECT COUNT(*) AS cnt,
                SUM(CASE WHEN `eszkoz` IS NOT NULL AND TRIM(`eszkoz`) <> \'\' THEN 1 ELSE 0 END) AS with_device
            FROM `nextgen_landing_feedback`
            WHERE `forras` IN (\'mobilapp\', \'mobileapp\')
              AND `létrehozva` >= ?
              AND `létrehozva` < DATE_ADD(?, INTERVAL 1 DAY)
        ';
        $stFb = $db->prepare($fbSql);
        $stFb->execute([$params['date_from'], $params['date_to']]);
        $fbTot = $stFb->fetch(PDO::FETCH_ASSOC) ?: [];
        $empty['feedback']['count'] = (int) ($fbTot['cnt'] ?? 0);
        $empty['feedback']['with_device'] = (int) ($fbTot['with_device'] ?? 0);

        $stFbDev = $db->prepare('
            SELECT TRIM(`eszkoz`) AS eszkoz, COUNT(*) AS cnt
            FROM `nextgen_landing_feedback`
            WHERE `forras` IN (\'mobilapp\', \'mobileapp\')
              AND `létrehozva` >= ?
              AND `létrehozva` < DATE_ADD(?, INTERVAL 1 DAY)
              AND `eszkoz` IS NOT NULL AND TRIM(`eszkoz`) <> \'\'
            GROUP BY TRIM(`eszkoz`)
            ORDER BY cnt DESC
            LIMIT 20
        ');
        $stFbDev->execute([$params['date_from'], $params['date_to']]);
        foreach ($stFbDev->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $empty['feedback']['devices'][] = [
                'label' => (string) ($row['eszkoz'] ?? ''),
                'count' => (int) ($row['cnt'] ?? 0),
            ];
        }

        $stFbRecent = $db->prepare('
            SELECT id, ilyen_legyen, ilyen_ne_legyen, egyeb_uzenet, eszkoz, létrehozva
            FROM `nextgen_landing_feedback`
            WHERE `forras` IN (\'mobilapp\', \'mobileapp\')
              AND `létrehozva` >= ?
              AND `létrehozva` < DATE_ADD(?, INTERVAL 1 DAY)
            ORDER BY `létrehozva` DESC
            LIMIT 15
        ');
        $stFbRecent->execute([$params['date_from'], $params['date_to']]);
        foreach ($stFbRecent->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $empty['feedback']['recent'][] = [
                'id' => (int) ($row['id'] ?? 0),
                'ilyen' => (string) ($row['ilyen_legyen'] ?? ''),
                'ne' => (string) ($row['ilyen_ne_legyen'] ?? ''),
                'egyeb' => (string) ($row['egyeb_uzenet'] ?? ''),
                'eszkoz' => (string) ($row['eszkoz'] ?? ''),
                'created' => (string) ($row['létrehozva'] ?? ''),
            ];
        }
    } catch (Throwable $e) {
        error_log('latinfo_mobilapp_overview_stats feedback: ' . $e->getMessage());
    }

    // --- Combined chart: installs + app usage ---
    try {
        $bucketExprEvents = match ($granularity) {
            'month' => "DATE_FORMAT(e.`occurred_at`, '%Y-%m-01')",
            'week' => 'DATE(DATE_SUB(e.`occurred_at`, INTERVAL WEEKDAY(e.`occurred_at`) DAY))',
            default => 'DATE(e.`occurred_at`)',
        };
        $bucketExprClicks = match ($granularity) {
            'month' => "DATE_FORMAT(c.`occurred_at`, '%Y-%m-01')",
            'week' => 'DATE(DATE_SUB(c.`occurred_at`, INTERVAL WEEKDAY(c.`occurred_at`) DAY))',
            default => 'DATE(c.`occurred_at`)',
        };

        $installByBucket = [];
        $usageByBucket = [];

        if ($eventsReady) {
            $where = latinfo_mobilapp_events_where($params, 'e');
            $stCh = $db->prepare("
                SELECT {$bucketExprEvents} AS bucket, COUNT(*) AS cnt
                FROM `latinfo_mobilapp_events` e
                WHERE {$where['sql']} AND e.`event_type` = 'app_installed'
                GROUP BY bucket
                ORDER BY bucket
            ");
            $stCh->execute($where['bind']);
            foreach ($stCh->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $b = (string) ($row['bucket'] ?? '');
                if ($b !== '') {
                    $installByBucket[$b] = (int) ($row['cnt'] ?? 0);
                }
            }
        }

        if ($modulesReady) {
            $moduleParams = [
                'date_from' => $params['date_from'],
                'date_to' => $params['date_to'],
                'module' => 'all',
                'visitor' => $params['visitor'],
                'lang' => 'all',
                'device' => $params['device'],
                'surface' => 'app',
            ];
            $whereMod = latinfo_home_module_stats_where($moduleParams);
            $stCh2 = $db->prepare("
                SELECT {$bucketExprClicks} AS bucket,
                    SUM(CASE WHEN c.`is_bot` = 0 THEN 1 ELSE 0 END) AS cnt
                FROM `latinfo_home_module_clicks` c
                WHERE {$whereMod['sql']}
                GROUP BY bucket
                ORDER BY bucket
            ");
            $stCh2->execute($whereMod['bind']);
            foreach ($stCh2->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $b = (string) ($row['bucket'] ?? '');
                if ($b !== '') {
                    $usageByBucket[$b] = (int) ($row['cnt'] ?? 0);
                }
            }
        }

        $labelSet = array_unique(array_merge(array_keys($installByBucket), array_keys($usageByBucket)));
        sort($labelSet);
        $labels = array_values($labelSet);
        $installData = [];
        $usageData = [];
        foreach ($labels as $label) {
            $installData[] = (int) ($installByBucket[$label] ?? 0);
            $usageData[] = (int) ($usageByBucket[$label] ?? 0);
        }

        $empty['chart'] = [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Telepítések',
                    'data' => $installData,
                    'color' => '#6d8f63',
                ],
                [
                    'label' => 'App modul-kattintás',
                    'data' => $usageData,
                    'color' => '#d4a054',
                ],
            ],
        ];
    } catch (Throwable $e) {
        error_log('latinfo_mobilapp_overview_stats chart: ' . $e->getMessage());
    }

    return $empty;
}
