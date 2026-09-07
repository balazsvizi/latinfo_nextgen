<?php
declare(strict_types=1);

function nextgen_partner_media_value_trials_table_ready(PDO $db): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    try {
        $db->query('SELECT 1 FROM `nextgen_partner_media_value_trials` LIMIT 1');
        $cached = true;
    } catch (Throwable) {
        $cached = false;
    }

    return $cached;
}

function nextgen_partner_ensure_media_value_trials_schema(PDO $db): bool
{
    if (!nextgen_partners_table_ready($db)) {
        return false;
    }
    if (nextgen_partner_media_value_trials_table_ready($db)) {
        return true;
    }
    try {
        $db->exec('
            CREATE TABLE IF NOT EXISTS `nextgen_partner_media_value_trials` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `partner_id` INT UNSIGNED NOT NULL,
                `page_unit_ft` INT UNSIGNED NOT NULL,
                `click_unit_ft` INT UNSIGNED NOT NULL,
                `page_views_human` INT UNSIGNED NOT NULL DEFAULT 0,
                `external_clicks_human` INT UNSIGNED NOT NULL DEFAULT 0,
                `page_value_ft` INT UNSIGNED NOT NULL DEFAULT 0,
                `click_value_ft` INT UNSIGNED NOT NULL DEFAULT 0,
                `total_ft` INT UNSIGNED NOT NULL DEFAULT 0,
                `date_from` DATE NOT NULL,
                `date_to` DATE NOT NULL,
                `stat_mode` VARCHAR(16) NOT NULL DEFAULT \'smart\',
                `context_label` VARCHAR(255) NULL DEFAULT NULL,
                `létrehozva` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_partner_media_trials_partner` (`partner_id`, `létrehozva`),
                KEY `idx_partner_media_trials_created` (`létrehozva`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        return nextgen_partner_media_value_trials_table_ready($db);
    } catch (Throwable $ex) {
        error_log('nextgen_partner_ensure_media_value_trials_schema: ' . $ex->getMessage());

        return false;
    }
}

/**
 * @param array{
 *   partner_id: int,
 *   page_unit_ft: int,
 *   click_unit_ft: int,
 *   page_views_human?: int,
 *   external_clicks_human?: int,
 *   page_value_ft?: int,
 *   click_value_ft?: int,
 *   total_ft?: int,
 *   date_from: string,
 *   date_to: string,
 *   stat_mode?: string,
 *   context_label?: string|null
 * } $payload
 */
function nextgen_partner_media_value_trial_save(PDO $db, array $payload): bool
{
    if (!nextgen_partner_ensure_media_value_trials_schema($db)) {
        return false;
    }

    $partnerId = (int) ($payload['partner_id'] ?? 0);
    $pageUnit = max(0, (int) ($payload['page_unit_ft'] ?? 0));
    $clickUnit = max(0, (int) ($payload['click_unit_ft'] ?? 0));
    $dateFrom = (string) ($payload['date_from'] ?? '');
    $dateTo = (string) ($payload['date_to'] ?? '');
    if (
        $partnerId <= 0
        || preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) !== 1
        || preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) !== 1
    ) {
        return false;
    }

    $pageViews = max(0, (int) ($payload['page_views_human'] ?? 0));
    $clicks = max(0, (int) ($payload['external_clicks_human'] ?? 0));
    $pageValue = max(0, (int) ($payload['page_value_ft'] ?? ($pageViews * $pageUnit)));
    $clickValue = max(0, (int) ($payload['click_value_ft'] ?? ($clicks * $clickUnit)));
    $total = max(0, (int) ($payload['total_ft'] ?? ($pageValue + $clickValue)));
    $mode = ((string) ($payload['stat_mode'] ?? 'smart')) === 'all' ? 'all' : 'smart';
    $contextLabel = trim((string) ($payload['context_label'] ?? ''));
    if ($contextLabel === '') {
        $contextLabel = null;
    } elseif (mb_strlen($contextLabel) > 255) {
        $contextLabel = mb_substr($contextLabel, 0, 255);
    }

    try {
        $lastStmt = $db->prepare('
            SELECT `page_unit_ft`, `click_unit_ft`, `date_from`, `date_to`, `stat_mode`, `context_label`
            FROM `nextgen_partner_media_value_trials`
            WHERE `partner_id` = ?
            ORDER BY `id` DESC
            LIMIT 1
        ');
        $lastStmt->execute([$partnerId]);
        $last = $lastStmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($last)
            && (int) ($last['page_unit_ft'] ?? -1) === $pageUnit
            && (int) ($last['click_unit_ft'] ?? -1) === $clickUnit
            && (string) ($last['date_from'] ?? '') === $dateFrom
            && (string) ($last['date_to'] ?? '') === $dateTo
            && (string) ($last['stat_mode'] ?? '') === $mode
            && (string) ($last['context_label'] ?? '') === (string) ($contextLabel ?? '')
        ) {
            return true;
        }

        $stmt = $db->prepare('
            INSERT INTO `nextgen_partner_media_value_trials` (
                `partner_id`, `page_unit_ft`, `click_unit_ft`,
                `page_views_human`, `external_clicks_human`,
                `page_value_ft`, `click_value_ft`, `total_ft`,
                `date_from`, `date_to`, `stat_mode`, `context_label`
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $partnerId,
            $pageUnit,
            $clickUnit,
            $pageViews,
            $clicks,
            $pageValue,
            $clickValue,
            $total,
            $dateFrom,
            $dateTo,
            $mode,
            $contextLabel,
        ]);

        return true;
    } catch (Throwable $ex) {
        error_log('nextgen_partner_media_value_trial_save: ' . $ex->getMessage());

        return false;
    }
}

/**
 * @return list<array<string, mixed>>
 */
function nextgen_partner_media_value_trials_list(
    PDO $db,
    ?int $partnerId = null,
    int $limit = 200
): array {
    if (!nextgen_partner_ensure_media_value_trials_schema($db)) {
        return [];
    }

    $limit = max(1, min(1000, $limit));
    try {
        if ($partnerId !== null && $partnerId > 0) {
            $stmt = $db->prepare('
                SELECT t.*, p.`név` AS partner_nev
                FROM `nextgen_partner_media_value_trials` t
                LEFT JOIN `nextgen_partners` p ON p.`id` = t.`partner_id`
                WHERE t.`partner_id` = ?
                ORDER BY t.`id` DESC
                LIMIT ' . $limit . '
            ');
            $stmt->execute([$partnerId]);
        } else {
            $stmt = $db->query('
                SELECT t.*, p.`név` AS partner_nev
                FROM `nextgen_partner_media_value_trials` t
                LEFT JOIN `nextgen_partners` p ON p.`id` = t.`partner_id`
                ORDER BY t.`id` DESC
                LIMIT ' . $limit . '
            ');
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    } catch (Throwable $ex) {
        error_log('nextgen_partner_media_value_trials_list: ' . $ex->getMessage());

        return [];
    }
}

function nextgen_media_value_rates_builtin_page_ft(): int
{
    return 80;
}

function nextgen_media_value_rates_builtin_click_ft(): int
{
    return 70;
}

function nextgen_media_value_rates_table_ready(PDO $db): bool
{
    static $cached = null;
    if ($cached === true) {
        return true;
    }
    try {
        $db->query('SELECT 1 FROM `nextgen_media_value_rates` LIMIT 1');
        $cached = true;
    } catch (Throwable) {
        $cached = false;
    }

    return $cached;
}

function nextgen_media_value_rates_ensure_schema(PDO $db): bool
{
    if (nextgen_media_value_rates_table_ready($db)) {
        return true;
    }
    try {
        $db->exec('
            CREATE TABLE IF NOT EXISTS `nextgen_media_value_rates` (
                `id` TINYINT UNSIGNED NOT NULL,
                `page_unit_ft` INT UNSIGNED NOT NULL,
                `click_unit_ft` INT UNSIGNED NOT NULL,
                `frissítve` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
        $ins = $db->prepare('
            INSERT IGNORE INTO `nextgen_media_value_rates` (`id`, `page_unit_ft`, `click_unit_ft`)
            VALUES (1, ?, ?)
        ');
        $ins->execute([
            nextgen_media_value_rates_builtin_page_ft(),
            nextgen_media_value_rates_builtin_click_ft(),
        ]);
        // Invalidate negative cache from the failed pre-check.
        $db->query('SELECT 1 FROM `nextgen_media_value_rates` LIMIT 1');

        return true;
    } catch (Throwable $ex) {
        error_log('nextgen_media_value_rates_ensure_schema: ' . $ex->getMessage());

        return false;
    }
}

/**
 * @return array{page_unit_ft: int, click_unit_ft: int}
 */
function nextgen_media_value_rates_get(?PDO $db = null): array
{
    if (isset($GLOBALS['__nextgen_media_value_rates_cache']) && is_array($GLOBALS['__nextgen_media_value_rates_cache'])) {
        /** @var array{page_unit_ft: int, click_unit_ft: int} $cached */
        $cached = $GLOBALS['__nextgen_media_value_rates_cache'];

        return $cached;
    }

    $fallback = [
        'page_unit_ft' => nextgen_media_value_rates_builtin_page_ft(),
        'click_unit_ft' => nextgen_media_value_rates_builtin_click_ft(),
    ];

    if ($db === null && function_exists('getDb')) {
        try {
            $db = getDb();
        } catch (Throwable) {
            $db = null;
        }
    }
    if (!$db instanceof PDO) {
        return $fallback;
    }
    if (!nextgen_media_value_rates_ensure_schema($db)) {
        return $fallback;
    }

    try {
        $row = $db->query('
            SELECT `page_unit_ft`, `click_unit_ft`
            FROM `nextgen_media_value_rates`
            WHERE `id` = 1
            LIMIT 1
        ')->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return $fallback;
        }
        $rates = [
            'page_unit_ft' => max(0, min(1_000_000, (int) ($row['page_unit_ft'] ?? $fallback['page_unit_ft']))),
            'click_unit_ft' => max(0, min(1_000_000, (int) ($row['click_unit_ft'] ?? $fallback['click_unit_ft']))),
        ];
        $GLOBALS['__nextgen_media_value_rates_cache'] = $rates;

        return $rates;
    } catch (Throwable $ex) {
        error_log('nextgen_media_value_rates_get: ' . $ex->getMessage());

        return $fallback;
    }
}

function nextgen_media_value_rates_save(PDO $db, int $pageUnitFt, int $clickUnitFt): bool
{
    if (!nextgen_media_value_rates_ensure_schema($db)) {
        return false;
    }
    $pageUnitFt = max(0, min(1_000_000, $pageUnitFt));
    $clickUnitFt = max(0, min(1_000_000, $clickUnitFt));
    try {
        $stmt = $db->prepare('
            INSERT INTO `nextgen_media_value_rates` (`id`, `page_unit_ft`, `click_unit_ft`)
            VALUES (1, ?, ?)
            ON DUPLICATE KEY UPDATE
                `page_unit_ft` = VALUES(`page_unit_ft`),
                `click_unit_ft` = VALUES(`click_unit_ft`)
        ');
        $stmt->execute([$pageUnitFt, $clickUnitFt]);
        $GLOBALS['__nextgen_media_value_rates_cache'] = [
            'page_unit_ft' => $pageUnitFt,
            'click_unit_ft' => $clickUnitFt,
        ];

        return true;
    } catch (Throwable $ex) {
        error_log('nextgen_media_value_rates_save: ' . $ex->getMessage());

        return false;
    }
}
