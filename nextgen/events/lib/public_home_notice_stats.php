<?php
declare(strict_types=1);

require_once __DIR__ . '/event_view_tracking.php';

/**
 * Fejléc tip átkattintás-napló és szövegverzió-történet.
 */

function events_public_home_notice_stats_tables_ready(PDO $db, bool $refresh = false): bool
{
    static $ready = null;
    if ($refresh) {
        $ready = null;
    }
    if ($ready !== null) {
        return $ready;
    }

    try {
        $db->query('SELECT 1 FROM `events_public_home_notice_versions` LIMIT 1');
        $db->query('SELECT 1 FROM `events_public_home_notice_clicks` LIMIT 1');
        $ready = true;
    } catch (Throwable) {
        $ready = false;
    }

    return $ready;
}

function events_public_home_notice_stats_ensure_schema(PDO $db): bool
{
    if (events_public_home_notice_stats_tables_ready($db)) {
        return true;
    }

    try {
        $db->exec('
            CREATE TABLE IF NOT EXISTS `events_public_home_notice_versions` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `notice_text` VARCHAR(500) NOT NULL DEFAULT \'\',
                `notice_text_en` VARCHAR(500) NOT NULL DEFAULT \'\',
                `notice_url` VARCHAR(500) NOT NULL DEFAULT \'\',
                `started_at` DATETIME NOT NULL,
                `ended_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_notice_versions_ended` (`ended_at`),
                KEY `idx_notice_versions_started` (`started_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
        $db->exec('
            CREATE TABLE IF NOT EXISTS `events_public_home_notice_clicks` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `version_id` INT UNSIGNED NULL DEFAULT NULL,
                `clicked_at` DATETIME NOT NULL,
                `lang` CHAR(2) NOT NULL DEFAULT \'hu\',
                `notice_text` VARCHAR(500) NOT NULL DEFAULT \'\',
                `notice_url` VARCHAR(500) NOT NULL DEFAULT \'\',
                `ip_hash` CHAR(64) NULL DEFAULT NULL,
                `is_bot` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_notice_clicks_clicked` (`clicked_at`),
                KEY `idx_notice_clicks_version` (`version_id`, `clicked_at`),
                KEY `idx_notice_clicks_bot` (`is_bot`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    } catch (Throwable $e) {
        error_log('events_public_home_notice_stats_ensure_schema: ' . $e->getMessage());

        return false;
    }

    return events_public_home_notice_stats_tables_ready($db, true);
}

/**
 * @param array{notice_text?: string, notice_text_en?: string, notice_url?: string} $notice
 */
function events_public_home_notice_sync_version(PDO $db, array $notice): ?int
{
    if (!events_public_home_notice_stats_ensure_schema($db)) {
        return null;
    }

    $hu = mb_substr(trim((string) ($notice['notice_text'] ?? '')), 0, 500);
    $en = mb_substr(trim((string) ($notice['notice_text_en'] ?? '')), 0, 500);
    $url = mb_substr(trim((string) ($notice['notice_url'] ?? '')), 0, 500);

    try {
        $st = $db->query(
            'SELECT `id`, `notice_text`, `notice_text_en`, `notice_url`
             FROM `events_public_home_notice_versions`
             WHERE `ended_at` IS NULL
             ORDER BY `id` DESC
             LIMIT 1'
        );
        $current = $st ? $st->fetch(PDO::FETCH_ASSOC) : false;

        if (
            is_array($current)
            && (string) $current['notice_text'] === $hu
            && (string) $current['notice_text_en'] === $en
            && (string) $current['notice_url'] === $url
        ) {
            return (int) $current['id'];
        }

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        if (is_array($current)) {
            $close = $db->prepare(
                'UPDATE `events_public_home_notice_versions`
                 SET `ended_at` = ?
                 WHERE `id` = ? AND `ended_at` IS NULL'
            );
            $close->execute([$now, (int) $current['id']]);
        }

        $ins = $db->prepare(
            'INSERT INTO `events_public_home_notice_versions`
                (`notice_text`, `notice_text_en`, `notice_url`, `started_at`)
             VALUES (?, ?, ?, ?)'
        );
        $ins->execute([$hu, $en, $url, $now]);
        $id = (int) $db->lastInsertId();

        return $id > 0 ? $id : null;
    } catch (Throwable $e) {
        error_log('events_public_home_notice_sync_version: ' . $e->getMessage());

        return null;
    }
}

/**
 * @return array{id: int, notice_text: string, notice_text_en: string, notice_url: string}|null
 */
function events_public_home_notice_version_by_id(PDO $db, int $versionId): ?array
{
    if ($versionId <= 0 || !events_public_home_notice_stats_tables_ready($db)) {
        return null;
    }

    try {
        $st = $db->prepare(
            'SELECT `id`, `notice_text`, `notice_text_en`, `notice_url`
             FROM `events_public_home_notice_versions`
             WHERE `id` = ?
             LIMIT 1'
        );
        $st->execute([$versionId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'notice_text' => (string) $row['notice_text'],
            'notice_text_en' => (string) $row['notice_text_en'],
            'notice_url' => (string) $row['notice_url'],
        ];
    } catch (Throwable $e) {
        error_log('events_public_home_notice_version_by_id: ' . $e->getMessage());

        return null;
    }
}

function events_public_home_notice_track_click(PDO $db, int $versionId, string $lang): bool
{
    if (!events_view_tracking_should_record()) {
        return false;
    }
    if (!events_public_home_notice_stats_ensure_schema($db)) {
        return false;
    }

    $lang = $lang === 'en' ? 'en' : 'hu';
    $version = events_public_home_notice_version_by_id($db, $versionId);
    if ($version === null) {
        $st = $db->query(
            'SELECT `id`, `notice_text`, `notice_text_en`, `notice_url`
             FROM `events_public_home_notice_versions`
             WHERE `ended_at` IS NULL
             ORDER BY `id` DESC
             LIMIT 1'
        );
        $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : false;
        if (!is_array($row)) {
            return false;
        }
        $version = [
            'id' => (int) $row['id'],
            'notice_text' => (string) $row['notice_text'],
            'notice_text_en' => (string) $row['notice_text_en'],
            'notice_url' => (string) $row['notice_url'],
        ];
    }

    $url = trim($version['notice_url']);
    if ($url === '') {
        return false;
    }

    $displayed = $lang === 'en'
        ? (trim($version['notice_text_en']) !== '' ? $version['notice_text_en'] : $version['notice_text'])
        : (trim($version['notice_text']) !== '' ? $version['notice_text'] : $version['notice_text_en']);
    $displayed = mb_substr(trim($displayed), 0, 500);

    $isBot = events_view_tracking_detect_bot() ? 1 : 0;
    $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

    try {
        $ins = $db->prepare(
            'INSERT INTO `events_public_home_notice_clicks`
                (`version_id`, `clicked_at`, `lang`, `notice_text`, `notice_url`, `ip_hash`, `is_bot`)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $version['id'],
            $now,
            $lang,
            $displayed,
            mb_substr($url, 0, 500),
            events_view_tracking_ip_hash(),
            $isBot,
        ]);

        return true;
    } catch (Throwable $e) {
        error_log('events_public_home_notice_track_click: ' . $e->getMessage());

        return false;
    }
}

function events_public_home_notice_truncate(string $text, int $max = 72): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    if (mb_strlen($text) <= $max) {
        return $text;
    }

    return mb_substr($text, 0, max(1, $max - 1)) . '…';
}

function events_public_home_notice_format_dt(?string $dt): string
{
    $raw = trim((string) $dt);
    if ($raw === '') {
        return 'mostanáig';
    }
    try {
        return (new DateTimeImmutable($raw))->format('Y.m.d. H:i');
    } catch (Throwable) {
        return $raw;
    }
}

/**
 * @param array<string, mixed> $query
 * @return array{
 *   date_from: string,
 *   date_to: string,
 *   version_id: int,
 *   lang: string,
 *   visitor: string
 * }
 */
function events_public_home_notice_stats_params_from_request(array $query): array
{
    $base = function_exists('events_edit_stats_params_from_request')
        ? events_edit_stats_params_from_request($query)
        : [
            'date_from' => (new DateTimeImmutable('today'))->modify('-29 days')->format('Y-m-d'),
            'date_to' => (new DateTimeImmutable('today'))->format('Y-m-d'),
        ];

    $versionId = filter_var($query['notice_version'] ?? 0, FILTER_VALIDATE_INT);
    $lang = strtolower(trim((string) ($query['notice_lang'] ?? 'all')));
    if (!in_array($lang, ['all', 'hu', 'en'], true)) {
        $lang = 'all';
    }
    $visitor = strtolower(trim((string) ($query['notice_visitor'] ?? 'all')));
    if (!in_array($visitor, ['all', 'human', 'bot'], true)) {
        $visitor = 'all';
    }

    return [
        'date_from' => (string) $base['date_from'],
        'date_to' => (string) $base['date_to'],
        'version_id' => ($versionId === false || $versionId < 0) ? 0 : (int) $versionId,
        'lang' => $lang,
        'visitor' => $visitor,
    ];
}

/**
 * @param array{date_from: string, date_to: string, version_id: int, lang: string, visitor: string} $params
 * @return array{0: list<string>, 1: list<mixed>}
 */
function events_public_home_notice_stats_click_where(array $params): array
{
    $sql = ['c.`clicked_at` >= ?', 'c.`clicked_at` < ?'];
    $bind = [
        $params['date_from'] . ' 00:00:00',
        (new DateTimeImmutable($params['date_to']))->modify('+1 day')->format('Y-m-d') . ' 00:00:00',
    ];

    if ($params['version_id'] > 0) {
        $sql[] = 'c.`version_id` = ?';
        $bind[] = $params['version_id'];
    }
    if ($params['lang'] === 'hu' || $params['lang'] === 'en') {
        $sql[] = 'c.`lang` = ?';
        $bind[] = $params['lang'];
    }
    if ($params['visitor'] === 'human') {
        $sql[] = 'c.`is_bot` = 0';
    } elseif ($params['visitor'] === 'bot') {
        $sql[] = 'c.`is_bot` = 1';
    }

    return [implode(' AND ', $sql), $bind];
}

/**
 * @return list<array{
 *   id: int,
 *   notice_text: string,
 *   notice_text_en: string,
 *   notice_url: string,
 *   started_at: string,
 *   ended_at: ?string,
 *   is_current: bool
 * }>
 */
function events_public_home_notice_list_versions(PDO $db): array
{
    if (!events_public_home_notice_stats_tables_ready($db)) {
        return [];
    }

    try {
        $rows = $db->query(
            'SELECT `id`, `notice_text`, `notice_text_en`, `notice_url`, `started_at`, `ended_at`
             FROM `events_public_home_notice_versions`
             ORDER BY `started_at` DESC, `id` DESC'
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('events_public_home_notice_list_versions: ' . $e->getMessage());

        return [];
    }

    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $ended = $row['ended_at'] ?? null;
        $out[] = [
            'id' => (int) $row['id'],
            'notice_text' => (string) $row['notice_text'],
            'notice_text_en' => (string) $row['notice_text_en'],
            'notice_url' => (string) $row['notice_url'],
            'started_at' => (string) $row['started_at'],
            'ended_at' => $ended !== null && (string) $ended !== '' ? (string) $ended : null,
            'is_current' => $ended === null || (string) $ended === '',
        ];
    }

    return $out;
}

function events_public_home_notice_earliest_click_date(PDO $db): ?string
{
    if (!events_public_home_notice_stats_tables_ready($db)) {
        return null;
    }

    try {
        $val = $db->query('SELECT MIN(`clicked_at`) FROM `events_public_home_notice_clicks`')->fetchColumn();
        if (!is_string($val) || $val === '') {
            $val = $db->query('SELECT MIN(`started_at`) FROM `events_public_home_notice_versions`')->fetchColumn();
        }
        if (!is_string($val) || $val === '') {
            return null;
        }

        return (new DateTimeImmutable($val))->format('Y-m-d');
    } catch (Throwable) {
        return null;
    }
}

/**
 * @param array{date_from: string, date_to: string, version_id: int, lang: string, visitor: string} $params
 * @return array{
 *   table_ready: bool,
 *   totals: array{clicks: int, clicks_human: int, clicks_bot: int, unique_human: int, versions_in_period: int},
 *   chart: array{labels: list<string>, datasets: list<array{label: string, data: list<int>, color: string, total: int}>},
 *   version_chart: array{labels: list<string>, data: list<int>, ids: list<int>},
 *   versions: list<array{
 *     id: int,
 *     notice_text: string,
 *     notice_text_en: string,
 *     notice_url: string,
 *     started_at: string,
 *     ended_at: ?string,
 *     is_current: bool,
 *     clicks: int,
 *     clicks_human: int,
 *     clicks_bot: int
 *   }>
 * }
 */
function events_public_home_notice_stats(PDO $db, array $params): array
{
    $emptyChart = ['labels' => [], 'datasets' => []];
    $empty = [
        'table_ready' => false,
        'totals' => [
            'clicks' => 0,
            'clicks_human' => 0,
            'clicks_bot' => 0,
            'unique_human' => 0,
            'versions_in_period' => 0,
        ],
        'chart' => $emptyChart,
        'version_chart' => ['labels' => [], 'data' => [], 'ids' => []],
        'versions' => [],
    ];

    if (!events_public_home_notice_stats_ensure_schema($db)) {
        return $empty;
    }
    $empty['table_ready'] = true;

    try {
        $fromDt = new DateTimeImmutable($params['date_from']);
        $toDt = new DateTimeImmutable($params['date_to']);
    } catch (Throwable) {
        return $empty;
    }
    if ($fromDt > $toDt) {
        [$fromDt, $toDt] = [$toDt, $fromDt];
        $params['date_from'] = $fromDt->format('Y-m-d');
        $params['date_to'] = $toDt->format('Y-m-d');
    }

    [$whereSql, $whereBind] = events_public_home_notice_stats_click_where($params);

    $dayLabels = function_exists('events_edit_stats_date_labels')
        ? events_edit_stats_date_labels($params['date_from'], $params['date_to'])
        : [];
    $humanByDay = array_fill_keys($dayLabels, 0);
    $botByDay = array_fill_keys($dayLabels, 0);

    try {
        $st = $db->prepare(
            'SELECT DATE(c.`clicked_at`) AS `d`, c.`is_bot`, COUNT(*) AS `c`
             FROM `events_public_home_notice_clicks` c
             WHERE ' . $whereSql . '
             GROUP BY DATE(c.`clicked_at`), c.`is_bot`'
        );
        $st->execute($whereBind);
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $d = (string) ($row['d'] ?? '');
            $count = (int) ($row['c'] ?? 0);
            $isBot = (int) ($row['is_bot'] ?? 0) === 1;
            if ($isBot) {
                if (array_key_exists($d, $botByDay)) {
                    $botByDay[$d] = $count;
                }
            } elseif (array_key_exists($d, $humanByDay)) {
                $humanByDay[$d] = $count;
            }
        }

        $tot = $db->prepare(
            'SELECT
                COUNT(*) AS `clicks`,
                SUM(CASE WHEN c.`is_bot` = 0 THEN 1 ELSE 0 END) AS `clicks_human`,
                SUM(CASE WHEN c.`is_bot` = 1 THEN 1 ELSE 0 END) AS `clicks_bot`,
                COUNT(DISTINCT CASE WHEN c.`is_bot` = 0 THEN c.`ip_hash` END) AS `unique_human`
             FROM `events_public_home_notice_clicks` c
             WHERE ' . $whereSql
        );
        $tot->execute($whereBind);
        $totRow = $tot->fetch(PDO::FETCH_ASSOC) ?: [];

        $periodFrom = $params['date_from'] . ' 00:00:00';
        $periodToExclusive = $toDt->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
        $verSql = '
            SELECT
                v.`id`, v.`notice_text`, v.`notice_text_en`, v.`notice_url`,
                v.`started_at`, v.`ended_at`,
                COUNT(c.`id`) AS `clicks`,
                SUM(CASE WHEN c.`is_bot` = 0 THEN 1 ELSE 0 END) AS `clicks_human`,
                SUM(CASE WHEN c.`is_bot` = 1 THEN 1 ELSE 0 END) AS `clicks_bot`
            FROM `events_public_home_notice_versions` v
            LEFT JOIN `events_public_home_notice_clicks` c
              ON c.`version_id` = v.`id`
             AND ' . $whereSql . '
            WHERE v.`started_at` < ?
              AND (v.`ended_at` IS NULL OR v.`ended_at` >= ?)
        ';
        $verBind = array_merge($whereBind, [$periodToExclusive, $periodFrom]);
        if ($params['version_id'] > 0) {
            $verSql .= ' AND v.`id` = ?';
            $verBind[] = $params['version_id'];
        }
        $verSql .= ' GROUP BY v.`id`, v.`notice_text`, v.`notice_text_en`, v.`notice_url`, v.`started_at`, v.`ended_at`
                     ORDER BY v.`started_at` DESC, v.`id` DESC';
        $verSt = $db->prepare($verSql);
        $verSt->execute($verBind);
        $versionRows = [];
        $versionChartLabels = [];
        $versionChartData = [];
        $versionChartIds = [];
        while ($row = $verSt->fetch(PDO::FETCH_ASSOC)) {
            $ended = $row['ended_at'] ?? null;
            $id = (int) $row['id'];
            $hu = (string) $row['notice_text'];
            $labelSrc = $hu !== '' ? $hu : (string) $row['notice_text_en'];
            $label = events_public_home_notice_truncate($labelSrc !== '' ? $labelSrc : '(üres tip)', 42);
            $clicksHuman = (int) ($row['clicks_human'] ?? 0);
            $versionRows[] = [
                'id' => $id,
                'notice_text' => $hu,
                'notice_text_en' => (string) $row['notice_text_en'],
                'notice_url' => (string) $row['notice_url'],
                'started_at' => (string) $row['started_at'],
                'ended_at' => $ended !== null && (string) $ended !== '' ? (string) $ended : null,
                'is_current' => $ended === null || (string) $ended === '',
                'clicks' => (int) ($row['clicks'] ?? 0),
                'clicks_human' => $clicksHuman,
                'clicks_bot' => (int) ($row['clicks_bot'] ?? 0),
            ];
            $versionChartLabels[] = $label;
            $versionChartData[] = $params['visitor'] === 'bot'
                ? (int) ($row['clicks_bot'] ?? 0)
                : ($params['visitor'] === 'human' ? $clicksHuman : (int) ($row['clicks'] ?? 0));
            $versionChartIds[] = $id;
        }
    } catch (Throwable $e) {
        error_log('events_public_home_notice_stats: ' . $e->getMessage());

        return $empty;
    }

    $clicksHuman = (int) ($totRow['clicks_human'] ?? 0);
    $clicksBot = (int) ($totRow['clicks_bot'] ?? 0);
    $clicks = (int) ($totRow['clicks'] ?? ($clicksHuman + $clicksBot));

    $chartLabels = function_exists('events_edit_stats_chart_labels')
        ? events_edit_stats_chart_labels($dayLabels)
        : $dayLabels;
    $datasets = [];
    if ($params['visitor'] !== 'bot') {
        $datasets[] = [
            'label' => 'Emberi',
            'data' => array_values($humanByDay),
            'color' => '#3d6b4f',
            'total' => $clicksHuman,
        ];
    }
    if ($params['visitor'] !== 'human') {
        $datasets[] = [
            'label' => 'Bot',
            'data' => array_values($botByDay),
            'color' => '#9ca3af',
            'total' => $clicksBot,
        ];
    }

    return [
        'table_ready' => true,
        'totals' => [
            'clicks' => $clicks,
            'clicks_human' => $clicksHuman,
            'clicks_bot' => $clicksBot,
            'unique_human' => (int) ($totRow['unique_human'] ?? 0),
            'versions_in_period' => count($versionRows),
        ],
        'chart' => ($dayLabels !== [] && $clicks > 0)
            ? ['labels' => $chartLabels, 'datasets' => $datasets]
            : $emptyChart,
        'version_chart' => [
            'labels' => $versionChartLabels,
            'data' => $versionChartData,
            'ids' => $versionChartIds,
        ],
        'versions' => $versionRows,
    ];
}
