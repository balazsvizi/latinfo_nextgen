<?php
declare(strict_types=1);

function nextgen_partner_media_value_trials_table_ready(PDO $db, bool $refresh = false): bool
{
    static $cached = null;
    if ($refresh) {
        $cached = null;
    }
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

function nextgen_media_value_table_has_column(PDO $db, string $table, string $column): bool
{
    $table = str_replace('`', '', $table);
    $column = str_replace('`', '', $column);
    if (
        $table === ''
        || $column === ''
        || preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1
        || preg_match('/^[A-Za-z0-9_]+$/', $column) !== 1
    ) {
        return false;
    }
    try {
        $stmt = $db->prepare('
            SELECT 1
            FROM `information_schema`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = DATABASE()
              AND `TABLE_NAME` = ?
              AND `COLUMN_NAME` = ?
            LIMIT 1
        ');
        $stmt->execute([$table, $column]);

        return (bool) $stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

function nextgen_partner_ensure_media_value_trials_schema(PDO $db): bool
{
    if (!nextgen_partners_table_ready($db)) {
        return false;
    }
    try {
        if (!nextgen_partner_media_value_trials_table_ready($db)) {
            $db->exec('
                CREATE TABLE IF NOT EXISTS `nextgen_partner_media_value_trials` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `partner_id` INT UNSIGNED NOT NULL,
                    `page_unit_ft` INT UNSIGNED NOT NULL,
                    `click_unit_ft` INT UNSIGNED NOT NULL,
                    `preview_unit_ft` INT UNSIGNED NOT NULL DEFAULT 0,
                    `page_views_human` INT UNSIGNED NOT NULL DEFAULT 0,
                    `external_clicks_human` INT UNSIGNED NOT NULL DEFAULT 0,
                    `preview_human` INT UNSIGNED NOT NULL DEFAULT 0,
                    `page_value_ft` INT UNSIGNED NOT NULL DEFAULT 0,
                    `click_value_ft` INT UNSIGNED NOT NULL DEFAULT 0,
                    `preview_value_ft` INT UNSIGNED NOT NULL DEFAULT 0,
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
        }
        $alters = [
            'preview_unit_ft' => 'ADD COLUMN `preview_unit_ft` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `click_unit_ft`',
            'preview_human' => 'ADD COLUMN `preview_human` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `external_clicks_human`',
            'preview_value_ft' => 'ADD COLUMN `preview_value_ft` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `click_value_ft`',
        ];
        foreach ($alters as $col => $sql) {
            if (!nextgen_media_value_table_has_column($db, 'nextgen_partner_media_value_trials', $col)) {
                $db->exec('ALTER TABLE `nextgen_partner_media_value_trials` ' . $sql);
            }
        }

        return nextgen_partner_media_value_trials_table_ready($db, true);
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
 *   preview_unit_ft?: int,
 *   page_views_human?: int,
 *   external_clicks_human?: int,
 *   preview_human?: int,
 *   page_value_ft?: int,
 *   click_value_ft?: int,
 *   preview_value_ft?: int,
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
    $previewUnit = max(0, (int) ($payload['preview_unit_ft'] ?? 0));
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
    $previews = max(0, (int) ($payload['preview_human'] ?? 0));
    $pageValue = max(0, (int) ($payload['page_value_ft'] ?? ($pageViews * $pageUnit)));
    $clickValue = max(0, (int) ($payload['click_value_ft'] ?? ($clicks * $clickUnit)));
    $previewValue = max(0, (int) ($payload['preview_value_ft'] ?? ($previews * $previewUnit)));
    $total = max(0, (int) ($payload['total_ft'] ?? ($pageValue + $clickValue + $previewValue)));
    $mode = ((string) ($payload['stat_mode'] ?? 'smart')) === 'all' ? 'all' : 'smart';
    $contextLabel = trim((string) ($payload['context_label'] ?? ''));
    if ($contextLabel === '') {
        $contextLabel = null;
    } elseif (mb_strlen($contextLabel) > 255) {
        $contextLabel = mb_substr($contextLabel, 0, 255);
    }

    try {
        $lastStmt = $db->prepare('
            SELECT `page_unit_ft`, `click_unit_ft`, `preview_unit_ft`, `date_from`, `date_to`, `stat_mode`, `context_label`
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
            && (int) ($last['preview_unit_ft'] ?? -1) === $previewUnit
            && (string) ($last['date_from'] ?? '') === $dateFrom
            && (string) ($last['date_to'] ?? '') === $dateTo
            && (string) ($last['stat_mode'] ?? '') === $mode
            && (string) ($last['context_label'] ?? '') === (string) ($contextLabel ?? '')
        ) {
            return true;
        }

        $stmt = $db->prepare('
            INSERT INTO `nextgen_partner_media_value_trials` (
                `partner_id`, `page_unit_ft`, `click_unit_ft`, `preview_unit_ft`,
                `page_views_human`, `external_clicks_human`, `preview_human`,
                `page_value_ft`, `click_value_ft`, `preview_value_ft`, `total_ft`,
                `date_from`, `date_to`, `stat_mode`, `context_label`
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $partnerId,
            $pageUnit,
            $clickUnit,
            $previewUnit,
            $pageViews,
            $clicks,
            $previews,
            $pageValue,
            $clickValue,
            $previewValue,
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

function nextgen_media_value_rates_builtin_preview_ft(): int
{
    return 12;
}

function nextgen_media_value_rates_builtin_page_ft(): int
{
    return 110;
}

function nextgen_media_value_rates_builtin_click_ft(): int
{
    return 150;
}

function nextgen_media_value_rates_clamp_ft(int $amount): int
{
    return max(0, min(1_000_000, $amount));
}

function nextgen_media_value_rates_table_ready(PDO $db, bool $refresh = false): bool
{
    static $cached = null;
    if ($refresh) {
        $cached = null;
    }
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
    try {
        if (!nextgen_media_value_rates_table_ready($db)) {
            $db->exec('
                CREATE TABLE IF NOT EXISTS `nextgen_media_value_rates` (
                    `id` TINYINT UNSIGNED NOT NULL,
                    `page_unit_ft` INT UNSIGNED NOT NULL,
                    `click_unit_ft` INT UNSIGNED NOT NULL,
                    `preview_unit_ft` INT UNSIGNED NOT NULL,
                    `frissítve` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ');
            $ins = $db->prepare('
                INSERT IGNORE INTO `nextgen_media_value_rates` (`id`, `page_unit_ft`, `click_unit_ft`, `preview_unit_ft`)
                VALUES (1, ?, ?, ?)
            ');
            $ins->execute([
                nextgen_media_value_rates_builtin_page_ft(),
                nextgen_media_value_rates_builtin_click_ft(),
                nextgen_media_value_rates_builtin_preview_ft(),
            ]);
            nextgen_media_value_rates_table_ready($db, true);
        }
        if (!nextgen_media_value_table_has_column($db, 'nextgen_media_value_rates', 'preview_unit_ft')) {
            try {
                $db->exec('
                    ALTER TABLE `nextgen_media_value_rates`
                    ADD COLUMN `preview_unit_ft` INT UNSIGNED NOT NULL DEFAULT 12 AFTER `click_unit_ft`
                ');
            } catch (Throwable) {
                if (!nextgen_media_value_table_has_column($db, 'nextgen_media_value_rates', 'preview_unit_ft')) {
                    throw new RuntimeException('A preview_unit_ft oszlop hozzáadása nem sikerült.');
                }
            }
            $legacy = $db->query('
                SELECT `page_unit_ft`, `click_unit_ft`
                FROM `nextgen_media_value_rates`
                WHERE `id` = 1
                LIMIT 1
            ')->fetch(PDO::FETCH_ASSOC);
            if (
                is_array($legacy)
                && (int) ($legacy['page_unit_ft'] ?? 0) === 80
                && (int) ($legacy['click_unit_ft'] ?? 0) === 70
            ) {
                $upd = $db->prepare('
                    UPDATE `nextgen_media_value_rates`
                    SET `page_unit_ft` = ?, `click_unit_ft` = ?, `preview_unit_ft` = ?
                    WHERE `id` = 1
                ');
                $upd->execute([
                    nextgen_media_value_rates_builtin_page_ft(),
                    nextgen_media_value_rates_builtin_click_ft(),
                    nextgen_media_value_rates_builtin_preview_ft(),
                ]);
            }
        }

        return true;
    } catch (Throwable $ex) {
        error_log('nextgen_media_value_rates_ensure_schema: ' . $ex->getMessage());

        return false;
    }
}

/**
 * @return array{page_unit_ft: int, click_unit_ft: int, preview_unit_ft: int}
 */
function nextgen_media_value_rates_get(?PDO $db = null): array
{
    if (isset($GLOBALS['__nextgen_media_value_rates_cache']) && is_array($GLOBALS['__nextgen_media_value_rates_cache'])) {
        /** @var array{page_unit_ft: int, click_unit_ft: int, preview_unit_ft: int} $cached */
        $cached = $GLOBALS['__nextgen_media_value_rates_cache'];

        return $cached;
    }

    $fallback = [
        'page_unit_ft' => nextgen_media_value_rates_builtin_page_ft(),
        'click_unit_ft' => nextgen_media_value_rates_builtin_click_ft(),
        'preview_unit_ft' => nextgen_media_value_rates_builtin_preview_ft(),
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
    nextgen_media_value_rates_ensure_schema($db);

    try {
        $row = $db->query('
            SELECT `page_unit_ft`, `click_unit_ft`, `preview_unit_ft`
            FROM `nextgen_media_value_rates`
            WHERE `id` = 1
            LIMIT 1
        ')->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return $fallback;
        }
        $rates = [
            'page_unit_ft' => nextgen_media_value_rates_clamp_ft((int) ($row['page_unit_ft'] ?? $fallback['page_unit_ft'])),
            'click_unit_ft' => nextgen_media_value_rates_clamp_ft((int) ($row['click_unit_ft'] ?? $fallback['click_unit_ft'])),
            'preview_unit_ft' => nextgen_media_value_rates_clamp_ft((int) ($row['preview_unit_ft'] ?? $fallback['preview_unit_ft'])),
        ];
        $GLOBALS['__nextgen_media_value_rates_cache'] = $rates;

        return $rates;
    } catch (Throwable $ex) {
        error_log('nextgen_media_value_rates_get: ' . $ex->getMessage());

        return $fallback;
    }
}

function nextgen_media_value_rates_save(PDO $db, int $pageUnitFt, int $clickUnitFt, int $previewUnitFt = 12): bool
{
    if (!nextgen_media_value_rates_ensure_schema($db)) {
        return false;
    }
    $pageUnitFt = nextgen_media_value_rates_clamp_ft($pageUnitFt);
    $clickUnitFt = nextgen_media_value_rates_clamp_ft($clickUnitFt);
    $previewUnitFt = nextgen_media_value_rates_clamp_ft($previewUnitFt);
    try {
        $stmt = $db->prepare('
            INSERT INTO `nextgen_media_value_rates` (`id`, `page_unit_ft`, `click_unit_ft`, `preview_unit_ft`)
            VALUES (1, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                `page_unit_ft` = VALUES(`page_unit_ft`),
                `click_unit_ft` = VALUES(`click_unit_ft`),
                `preview_unit_ft` = VALUES(`preview_unit_ft`)
        ');
        $stmt->execute([$pageUnitFt, $clickUnitFt, $previewUnitFt]);
        $GLOBALS['__nextgen_media_value_rates_cache'] = [
            'page_unit_ft' => $pageUnitFt,
            'click_unit_ft' => $clickUnitFt,
            'preview_unit_ft' => $previewUnitFt,
        ];

        return true;
    } catch (Throwable $ex) {
        error_log('nextgen_media_value_rates_save: ' . $ex->getMessage());

        return false;
    }
}

function nextgen_media_value_methodology_pdf_url(): string
{
    return nextgen_url('mediaertek-modszertan.php');
}
