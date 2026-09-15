<?php
declare(strict_types=1);

require_once __DIR__ . '/event_view_tracking.php';

/**
 * Fejléc tip átkattintás-napló és szövegverzió-történet.
 */

function events_public_home_notice_has_column(PDO $db, string $table, string $column, bool $refresh = false): bool
{
    static $cache = [];
    $allowed = [
        'events_public_home' => true,
        'events_public_home_notices' => true,
        'events_public_home_notice_versions' => true,
        'events_public_home_notice_clicks' => true,
        'events_public_home_notice_impressions' => true,
    ];
    if (!isset($allowed[$table])) {
        return false;
    }
    $key = $table . '.' . $column;
    if ($refresh) {
        unset($cache[$key]);
    }
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $st = $db->query('SHOW COLUMNS FROM `' . $table . '` LIKE ' . $db->quote($column));
        $cache[$key] = is_object($st) && $st->fetch(PDO::FETCH_ASSOC) !== false;
    } catch (Throwable) {
        $cache[$key] = false;
    }

    return $cache[$key];
}

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
    static $done = false;
    if ($done) {
        return events_public_home_notice_stats_tables_ready($db);
    }

    $coreReady = events_public_home_notice_stats_tables_ready($db);

    try {
        if (!$coreReady) {
            $db->exec('
                CREATE TABLE IF NOT EXISTS `events_public_home_notice_versions` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `notice_id` INT UNSIGNED NULL DEFAULT NULL,
                    `notice_text` VARCHAR(500) NOT NULL DEFAULT \'\',
                    `notice_text_en` VARCHAR(500) NOT NULL DEFAULT \'\',
                    `notice_url` VARCHAR(500) NOT NULL DEFAULT \'\',
                    `started_at` DATETIME NOT NULL,
                    `ended_at` DATETIME NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `idx_notice_versions_ended` (`ended_at`),
                    KEY `idx_notice_versions_started` (`started_at`),
                    KEY `idx_notice_versions_notice` (`notice_id`, `ended_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ');
            $db->exec('
                CREATE TABLE IF NOT EXISTS `events_public_home_notice_clicks` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `version_id` INT UNSIGNED NULL DEFAULT NULL,
                    `notice_id` INT UNSIGNED NULL DEFAULT NULL,
                    `clicked_at` DATETIME NOT NULL,
                    `lang` CHAR(2) NOT NULL DEFAULT \'hu\',
                    `notice_text` VARCHAR(500) NOT NULL DEFAULT \'\',
                    `notice_url` VARCHAR(500) NOT NULL DEFAULT \'\',
                    `ip_hash` CHAR(64) NULL DEFAULT NULL,
                    `is_bot` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                    PRIMARY KEY (`id`),
                    KEY `idx_notice_clicks_clicked` (`clicked_at`),
                    KEY `idx_notice_clicks_version` (`version_id`, `clicked_at`),
                    KEY `idx_notice_clicks_notice` (`notice_id`, `clicked_at`),
                    KEY `idx_notice_clicks_bot` (`is_bot`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ');
        }

        if (!events_public_home_notice_has_column($db, 'events_public_home_notice_versions', 'notice_id', true)) {
            $db->exec(
                'ALTER TABLE `events_public_home_notice_versions`
                 ADD COLUMN `notice_id` INT UNSIGNED NULL DEFAULT NULL AFTER `id`'
            );
            events_public_home_notice_has_column($db, 'events_public_home_notice_versions', 'notice_id', true);
            try {
                $db->exec('ALTER TABLE `events_public_home_notice_versions` ADD KEY `idx_notice_versions_notice` (`notice_id`, `ended_at`)');
            } catch (Throwable) {
                // A kulcs már létezhet.
            }
        }
        if (!events_public_home_notice_has_column($db, 'events_public_home_notice_clicks', 'notice_id', true)) {
            $db->exec(
                'ALTER TABLE `events_public_home_notice_clicks`
                 ADD COLUMN `notice_id` INT UNSIGNED NULL DEFAULT NULL AFTER `version_id`'
            );
            events_public_home_notice_has_column($db, 'events_public_home_notice_clicks', 'notice_id', true);
            try {
                $db->exec('ALTER TABLE `events_public_home_notice_clicks` ADD KEY `idx_notice_clicks_notice` (`notice_id`, `clicked_at`)');
            } catch (Throwable) {
                // A kulcs már létezhet.
            }
        }

        $db->exec('
            CREATE TABLE IF NOT EXISTS `events_public_home_notice_impressions` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `notice_id` INT UNSIGNED NOT NULL,
                `version_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `impression_date` DATE NOT NULL,
                `lang` CHAR(2) NOT NULL DEFAULT \'hu\',
                `is_bot` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `impressions` INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `idx_notice_impr_day` (`notice_id`, `version_id`, `impression_date`, `lang`, `is_bot`),
                KEY `idx_notice_impr_date` (`impression_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    } catch (Throwable $e) {
        error_log('events_public_home_notice_stats_ensure_schema: ' . $e->getMessage());

        return events_public_home_notice_stats_tables_ready($db, true);
    }

    $ok = events_public_home_notice_stats_tables_ready($db, true);
    if ($ok) {
        $done = true;
    }

    return $ok;
}

/**
 * @param array{id?: int, notice_id?: int, notice_text?: string, notice_text_en?: string, notice_url?: string} $notice
 */
function events_public_home_notice_sync_version(PDO $db, array $notice): ?int
{
    if (!events_public_home_notice_stats_ensure_schema($db)) {
        return null;
    }

    $noticeId = (int) ($notice['id'] ?? $notice['notice_id'] ?? 0);
    if ($noticeId <= 0) {
        return null;
    }

    $hu = mb_substr(trim((string) ($notice['notice_text'] ?? '')), 0, 500);
    $en = mb_substr(trim((string) ($notice['notice_text_en'] ?? '')), 0, 500);
    $url = mb_substr(trim((string) ($notice['notice_url'] ?? '')), 0, 500);

    try {
        $st = $db->prepare(
            'SELECT `id`, `notice_text`, `notice_text_en`, `notice_url`
             FROM `events_public_home_notice_versions`
             WHERE `notice_id` = ? AND `ended_at` IS NULL
             ORDER BY `id` DESC
             LIMIT 1'
        );
        $st->execute([$noticeId]);
        $current = $st->fetch(PDO::FETCH_ASSOC);

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
                (`notice_id`, `notice_text`, `notice_text_en`, `notice_url`, `started_at`)
             VALUES (?, ?, ?, ?, ?)'
        );
        $ins->execute([$noticeId, $hu, $en, $url, $now]);
        $id = (int) $db->lastInsertId();

        return $id > 0 ? $id : null;
    } catch (Throwable $e) {
        error_log('events_public_home_notice_sync_version: ' . $e->getMessage());

        return null;
    }
}

function events_public_home_notice_current_version_id(PDO $db, int $noticeId): ?int
{
    if ($noticeId <= 0 || !events_public_home_notice_stats_tables_ready($db)) {
        return null;
    }

    try {
        $st = $db->prepare(
            'SELECT `id` FROM `events_public_home_notice_versions`
             WHERE `notice_id` = ? AND `ended_at` IS NULL
             ORDER BY `id` DESC
             LIMIT 1'
        );
        $st->execute([$noticeId]);
        $id = (int) $st->fetchColumn();

        return $id > 0 ? $id : null;
    } catch (Throwable $e) {
        error_log('events_public_home_notice_current_version_id: ' . $e->getMessage());

        return null;
    }
}

/**
 * @return array{id: int, notice_id: int, notice_text: string, notice_text_en: string, notice_url: string}|null
 */
function events_public_home_notice_version_by_id(PDO $db, int $versionId): ?array
{
    if ($versionId <= 0 || !events_public_home_notice_stats_tables_ready($db)) {
        return null;
    }

    try {
        $st = $db->prepare(
            'SELECT `id`, `notice_id`, `notice_text`, `notice_text_en`, `notice_url`
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
            'notice_id' => (int) ($row['notice_id'] ?? 0),
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
        return false;
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
    $noticeId = (int) ($version['notice_id'] ?? 0);

    try {
        $ins = $db->prepare(
            'INSERT INTO `events_public_home_notice_clicks`
                (`version_id`, `notice_id`, `clicked_at`, `lang`, `notice_text`, `notice_url`, `ip_hash`, `is_bot`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $version['id'],
            $noticeId > 0 ? $noticeId : null,
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

function events_public_home_notice_track_impression(PDO $db, int $noticeId, int $versionId, string $lang): bool
{
    if ($noticeId <= 0 || !events_view_tracking_should_record()) {
        return false;
    }
    if (!events_public_home_notice_stats_ensure_schema($db)) {
        return false;
    }

    $lang = $lang === 'en' ? 'en' : 'hu';
    $isBot = events_view_tracking_detect_bot() ? 1 : 0;
    $day = (new DateTimeImmutable('today'))->format('Y-m-d');
    $versionId = max(0, $versionId);

    try {
        $st = $db->prepare(
            'INSERT INTO `events_public_home_notice_impressions`
                (`notice_id`, `version_id`, `impression_date`, `lang`, `is_bot`, `impressions`)
             VALUES (?, ?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE `impressions` = `impressions` + 1'
        );
        $st->execute([$noticeId, $versionId, $day, $lang, $isBot]);

        return true;
    } catch (Throwable $e) {
        error_log('events_public_home_notice_track_impression: ' . $e->getMessage());

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

/**
 * @param array<string, mixed> $query
 * @return array{
 *   date_from: string,
 *   date_to: string,
 *   notice_id: int,
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

    $noticeId = filter_var($query['notice_tip'] ?? ($query['notice_id'] ?? 0), FILTER_VALIDATE_INT);
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
        'notice_id' => ($noticeId === false || $noticeId < 0) ? 0 : (int) $noticeId,
        'version_id' => 0,
        'lang' => $lang,
        'visitor' => $visitor,
    ];
}

/**
 * @param array{date_from: string, date_to: string, notice_id: int, lang: string, visitor: string} $params
 * @return array{0: list<string>, 1: list<mixed>}
 */
function events_public_home_notice_stats_click_where(array $params): array
{
    $sql = ['c.`clicked_at` >= ?', 'c.`clicked_at` < ?'];
    $bind = [
        $params['date_from'] . ' 00:00:00',
        (new DateTimeImmutable($params['date_to']))->modify('+1 day')->format('Y-m-d') . ' 00:00:00',
    ];

    if (($params['notice_id'] ?? 0) > 0) {
        $sql[] = 'c.`notice_id` = ?';
        $bind[] = $params['notice_id'];
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
 * @param array{date_from: string, date_to: string, notice_id: int, lang: string, visitor: string} $params
 * @return array{0: list<string>, 1: list<mixed>}
 */
function events_public_home_notice_stats_impression_where(array $params): array
{
    $sql = ['i.`impression_date` >= ?', 'i.`impression_date` <= ?'];
    $bind = [$params['date_from'], $params['date_to']];

    if (($params['notice_id'] ?? 0) > 0) {
        $sql[] = 'i.`notice_id` = ?';
        $bind[] = $params['notice_id'];
    }
    if ($params['lang'] === 'hu' || $params['lang'] === 'en') {
        $sql[] = 'i.`lang` = ?';
        $bind[] = $params['lang'];
    }
    if ($params['visitor'] === 'human') {
        $sql[] = 'i.`is_bot` = 0';
    } elseif ($params['visitor'] === 'bot') {
        $sql[] = 'i.`is_bot` = 1';
    }

    return [implode(' AND ', $sql), $bind];
}

/**
 * @return list<array{
 *   id: int,
 *   notice_text: string,
 *   notice_text_en: string,
 *   notice_url: string,
 *   is_active: bool,
 *   is_deleted: bool
 * }>
 */
function events_public_home_notice_list_for_filter(PDO $db): array
{
    $out = [];
    $seen = [];

    try {
        $db->query('SELECT 1 FROM `events_public_home_notices` LIMIT 1');
        $rows = $db->query(
            'SELECT `id`, `notice_text`, `notice_text_en`, `notice_url`, `is_active`
             FROM `events_public_home_notices`
             ORDER BY `is_active` DESC, `sort_order` ASC, `id` ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) $row['id'];
            if ($id <= 0) {
                continue;
            }
            $seen[$id] = true;
            $out[] = [
                'id' => $id,
                'notice_text' => (string) $row['notice_text'],
                'notice_text_en' => (string) $row['notice_text_en'],
                'notice_url' => (string) $row['notice_url'],
                'is_active' => (int) ($row['is_active'] ?? 0) === 1,
                'is_deleted' => false,
            ];
        }
    } catch (Throwable) {
        // A tip tábla még nincs kész.
    }

    if (!events_public_home_notice_stats_tables_ready($db)) {
        return $out;
    }

    try {
        $orphan = $db->query(
            'SELECT v.`notice_id`, v.`notice_text`, v.`notice_text_en`, v.`notice_url`
             FROM `events_public_home_notice_versions` v
             INNER JOIN (
                SELECT `notice_id`, MAX(`id`) AS `max_id`
                FROM `events_public_home_notice_versions`
                WHERE `notice_id` IS NOT NULL AND `notice_id` > 0
                GROUP BY `notice_id`
             ) lastv ON lastv.`max_id` = v.`id`'
        );
        if ($orphan) {
            while ($row = $orphan->fetch(PDO::FETCH_ASSOC)) {
                $id = (int) ($row['notice_id'] ?? 0);
                if ($id <= 0 || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $out[] = [
                    'id' => $id,
                    'notice_text' => (string) ($row['notice_text'] ?? ''),
                    'notice_text_en' => (string) ($row['notice_text_en'] ?? ''),
                    'notice_url' => (string) ($row['notice_url'] ?? ''),
                    'is_active' => false,
                    'is_deleted' => true,
                ];
            }
        }
    } catch (Throwable $e) {
        error_log('events_public_home_notice_list_for_filter: ' . $e->getMessage());
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
 * @param array{date_from: string, date_to: string, notice_id: int, lang: string, visitor: string} $params
 * @return array{
 *   table_ready: bool,
 *   totals: array{
 *     clicks: int,
 *     clicks_human: int,
 *     clicks_bot: int,
 *     unique_human: int,
 *     versions_in_period: int,
 *     impressions: int,
 *     impressions_human: int,
 *     impressions_bot: int
 *   },
 *   chart: array{labels: list<string>, datasets: list<array{label: string, data: list<int>, color: string, total: int}>},
 *   version_chart: array{labels: list<string>, data: list<int>, ids: list<int>},
 *   versions: list<array<string, mixed>>
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
            'impressions' => 0,
            'impressions_human' => 0,
            'impressions_bot' => 0,
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

    $totRow = [];
    $imprTot = [
        'impressions' => 0,
        'impressions_human' => 0,
        'impressions_bot' => 0,
    ];
    $clickByNotice = [];
    $imprByNotice = [];

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

        $byNotice = $db->prepare(
            'SELECT
                COALESCE(c.`notice_id`, 0) AS `notice_id`,
                COUNT(*) AS `clicks`,
                SUM(CASE WHEN c.`is_bot` = 0 THEN 1 ELSE 0 END) AS `clicks_human`,
                SUM(CASE WHEN c.`is_bot` = 1 THEN 1 ELSE 0 END) AS `clicks_bot`
             FROM `events_public_home_notice_clicks` c
             WHERE ' . $whereSql . '
             GROUP BY COALESCE(c.`notice_id`, 0)'
        );
        $byNotice->execute($whereBind);
        while ($row = $byNotice->fetch(PDO::FETCH_ASSOC)) {
            $nid = (int) ($row['notice_id'] ?? 0);
            $clickByNotice[$nid] = [
                'clicks' => (int) ($row['clicks'] ?? 0),
                'clicks_human' => (int) ($row['clicks_human'] ?? 0),
                'clicks_bot' => (int) ($row['clicks_bot'] ?? 0),
            ];
        }

        [$imprSql, $imprBind] = events_public_home_notice_stats_impression_where($params);
        $imprSt = $db->prepare(
            'SELECT
                SUM(i.`impressions`) AS `impressions`,
                SUM(CASE WHEN i.`is_bot` = 0 THEN i.`impressions` ELSE 0 END) AS `impressions_human`,
                SUM(CASE WHEN i.`is_bot` = 1 THEN i.`impressions` ELSE 0 END) AS `impressions_bot`
             FROM `events_public_home_notice_impressions` i
             WHERE ' . $imprSql
        );
        $imprSt->execute($imprBind);
        $imprTotRow = $imprSt->fetch(PDO::FETCH_ASSOC) ?: [];
        $imprTot = [
            'impressions' => (int) ($imprTotRow['impressions'] ?? 0),
            'impressions_human' => (int) ($imprTotRow['impressions_human'] ?? 0),
            'impressions_bot' => (int) ($imprTotRow['impressions_bot'] ?? 0),
        ];

        $imprBy = $db->prepare(
            'SELECT
                i.`notice_id`,
                SUM(i.`impressions`) AS `impressions`,
                SUM(CASE WHEN i.`is_bot` = 0 THEN i.`impressions` ELSE 0 END) AS `impressions_human`,
                SUM(CASE WHEN i.`is_bot` = 1 THEN i.`impressions` ELSE 0 END) AS `impressions_bot`
             FROM `events_public_home_notice_impressions` i
             WHERE ' . $imprSql . '
             GROUP BY i.`notice_id`'
        );
        $imprBy->execute($imprBind);
        while ($row = $imprBy->fetch(PDO::FETCH_ASSOC)) {
            $nid = (int) ($row['notice_id'] ?? 0);
            $imprByNotice[$nid] = [
                'impressions' => (int) ($row['impressions'] ?? 0),
                'impressions_human' => (int) ($row['impressions_human'] ?? 0),
                'impressions_bot' => (int) ($row['impressions_bot'] ?? 0),
            ];
        }
    } catch (Throwable $e) {
        error_log('events_public_home_notice_stats: ' . $e->getMessage());

        return $empty;
    }

    $metaById = [];
    foreach (events_public_home_notice_list_for_filter($db) as $opt) {
        $metaById[(int) $opt['id']] = $opt;
    }

    $noticeIds = [];
    foreach (array_keys($metaById) as $nid) {
        $noticeIds[] = (int) $nid;
    }
    foreach (array_keys($clickByNotice) as $nid) {
        $noticeIds[] = (int) $nid;
    }
    foreach (array_keys($imprByNotice) as $nid) {
        $noticeIds[] = (int) $nid;
    }
    $noticeIds = array_values(array_unique($noticeIds));
    if (($params['notice_id'] ?? 0) > 0) {
        $noticeIds = array_values(array_filter(
            $noticeIds,
            static fn (int $id): bool => $id === (int) $params['notice_id']
        ));
        if ($noticeIds === []) {
            $noticeIds = [(int) $params['notice_id']];
        }
    }

    usort($noticeIds, static function (int $a, int $b) use ($metaById, $clickByNotice): int {
        $aActive = !empty($metaById[$a]['is_active']);
        $bActive = !empty($metaById[$b]['is_active']);
        if ($aActive !== $bActive) {
            return $aActive ? -1 : 1;
        }
        $aClicks = (int) ($clickByNotice[$a]['clicks'] ?? 0);
        $bClicks = (int) ($clickByNotice[$b]['clicks'] ?? 0);
        if ($aClicks !== $bClicks) {
            return $bClicks <=> $aClicks;
        }

        return $a <=> $b;
    });

    $versionRows = [];
    $versionChartLabels = [];
    $versionChartData = [];
    $versionChartIds = [];

    foreach ($noticeIds as $nid) {
        $meta = $metaById[$nid] ?? null;
        $clicksRow = $clickByNotice[$nid] ?? ['clicks' => 0, 'clicks_human' => 0, 'clicks_bot' => 0];
        $imprRow = $imprByNotice[$nid] ?? ['impressions' => 0, 'impressions_human' => 0, 'impressions_bot' => 0];
        $hu = is_array($meta) ? (string) $meta['notice_text'] : '';
        $en = is_array($meta) ? (string) $meta['notice_text_en'] : '';
        if ($hu === '' && $en === '' && $nid > 0) {
            $fallback = events_public_home_notice_latest_version_text($db, $nid);
            $hu = $fallback['notice_text'];
            $en = $fallback['notice_text_en'];
        }
        $labelSrc = $hu !== '' ? $hu : $en;
        if ($nid <= 0) {
            $labelSrc = $labelSrc !== '' ? $labelSrc : 'Korábbi (nem besorolt)';
        }
        $label = events_public_home_notice_truncate($labelSrc !== '' ? $labelSrc : '(üres tip)', 42);
        $isActive = is_array($meta) && !empty($meta['is_active']);
        $isDeleted = $nid > 0 && ($meta === null || !empty($meta['is_deleted']));
        $impressionsHuman = (int) $imprRow['impressions_human'];
        $clicksHuman = (int) $clicksRow['clicks_human'];
        $ctr = $impressionsHuman > 0
            ? round(($clicksHuman / $impressionsHuman) * 100, 1)
            : null;

        $versionRows[] = [
            'id' => $nid,
            'notice_text' => $hu,
            'notice_text_en' => $en,
            'notice_url' => is_array($meta) ? (string) $meta['notice_url'] : '',
            'started_at' => '',
            'ended_at' => null,
            'is_current' => $isActive,
            'is_active' => $isActive,
            'is_deleted' => $isDeleted,
            'clicks' => (int) $clicksRow['clicks'],
            'clicks_human' => $clicksHuman,
            'clicks_bot' => (int) $clicksRow['clicks_bot'],
            'impressions' => (int) $imprRow['impressions'],
            'impressions_human' => $impressionsHuman,
            'impressions_bot' => (int) $imprRow['impressions_bot'],
            'ctr_human' => $ctr,
        ];
        $chartValue = $params['visitor'] === 'bot'
            ? (int) $clicksRow['clicks_bot']
            : ($params['visitor'] === 'human' ? $clicksHuman : (int) $clicksRow['clicks']);
        $versionChartLabels[] = $label;
        $versionChartData[] = $chartValue;
        $versionChartIds[] = $nid;
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
            'impressions' => $imprTot['impressions'],
            'impressions_human' => $imprTot['impressions_human'],
            'impressions_bot' => $imprTot['impressions_bot'],
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

/**
 * @return array{notice_text: string, notice_text_en: string, notice_url: string}
 */
function events_public_home_notice_latest_version_text(PDO $db, int $noticeId): array
{
    $empty = ['notice_text' => '', 'notice_text_en' => '', 'notice_url' => ''];
    if ($noticeId <= 0 || !events_public_home_notice_stats_tables_ready($db)) {
        return $empty;
    }

    try {
        $st = $db->prepare(
            'SELECT `notice_text`, `notice_text_en`, `notice_url`
             FROM `events_public_home_notice_versions`
             WHERE `notice_id` = ?
             ORDER BY `id` DESC
             LIMIT 1'
        );
        $st->execute([$noticeId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return $empty;
        }

        return [
            'notice_text' => (string) $row['notice_text'],
            'notice_text_en' => (string) $row['notice_text_en'],
            'notice_url' => (string) $row['notice_url'],
        ];
    } catch (Throwable) {
        return $empty;
    }
}
