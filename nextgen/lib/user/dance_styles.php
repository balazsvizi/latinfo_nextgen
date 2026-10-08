<?php
declare(strict_types=1);

/**
 * Felhasználói táncstílus-szintek (1–10; hiányzó / 0 = nem adom meg).
 * A stílusok törzse: events_styles.
 */

function latinfo_user_dance_styles_table_ready(PDO $db, bool $force = false): bool
{
    static $cached = null;
    if (!$force && $cached !== null) {
        return $cached;
    }
    try {
        $db->query('SELECT 1 FROM `latinfo_user_dance_styles` LIMIT 1');
        $cached = true;
    } catch (Throwable) {
        $cached = false;
    }

    return $cached;
}

function latinfo_user_dance_styles_ensure_schema(PDO $db): bool
{
    static $done = false;
    if ($done) {
        return latinfo_user_dance_styles_table_ready($db);
    }

    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `latinfo_user_dance_styles` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NOT NULL,
                `style_id` INT UNSIGNED NOT NULL,
                `level` TINYINT UNSIGNED NOT NULL,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_latinfo_user_dance_style` (`user_id`, `style_id`),
                KEY `idx_latinfo_user_dance_styles_user` (`user_id`),
                KEY `idx_latinfo_user_dance_styles_style` (`style_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        latinfo_user_dance_styles_table_ready($db, true);
        $done = true;

        return true;
    } catch (Throwable $ex) {
        error_log('latinfo_user_dance_styles_ensure_schema: ' . $ex->getMessage());

        return false;
    }
}

function latinfo_dance_style_level_label(int $level): string
{
    return match (true) {
        $level <= 0 => 'Nem adom meg',
        $level <= 3 => 'Kezdő',
        $level <= 6 => 'Középhaladó',
        $level <= 8 => 'Haladó',
        default => 'Profi',
    };
}

/**
 * @return int 0–10
 */
function latinfo_dance_style_normalize_level(mixed $raw): int
{
    if ($raw === null || $raw === '' || $raw === false) {
        return 0;
    }
    $level = (int) $raw;
    if ($level < 0) {
        return 0;
    }
    if ($level > 10) {
        return 10;
    }

    return $level;
}

/**
 * @return array<int, string> style_id => name
 */
function latinfo_dance_style_catalog(PDO $db): array
{
    try {
        $rows = $db->query('SELECT `id`, `name` FROM `events_styles` ORDER BY `name` ASC, `id` ASC')
            ->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return [];
    }

    $out = [];
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        $name = trim((string) ($row['name'] ?? ''));
        if ($id > 0 && $name !== '') {
            $out[$id] = $name;
        }
    }

    return $out;
}

/**
 * @return array<int, int> style_id => level (1–10)
 */
function latinfo_user_dance_styles_map(PDO $db, int $userId): array
{
    if ($userId <= 0 || !latinfo_user_dance_styles_ensure_schema($db)) {
        return [];
    }
    try {
        $stmt = $db->prepare('
            SELECT `style_id`, `level`
            FROM `latinfo_user_dance_styles`
            WHERE `user_id` = ?
        ');
        $stmt->execute([$userId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $styleId = (int) ($row['style_id'] ?? 0);
            $level = latinfo_dance_style_normalize_level($row['level'] ?? 0);
            if ($styleId > 0 && $level >= 1) {
                $out[$styleId] = $level;
            }
        }

        return $out;
    } catch (Throwable $ex) {
        error_log('latinfo_user_dance_styles_map: ' . $ex->getMessage());

        return [];
    }
}

/**
 * @param array<int|string, mixed> $levelsByStyleId style_id => level (0–10)
 * @return array{ok:bool,error:string,changed:int}
 */
function latinfo_user_dance_styles_save(PDO $db, int $userId, array $levelsByStyleId): array
{
    if ($userId <= 0) {
        return ['ok' => false, 'error' => 'Érvénytelen felhasználó.', 'changed' => 0];
    }
    if (!latinfo_user_dance_styles_ensure_schema($db)) {
        return ['ok' => false, 'error' => 'A mentés jelenleg nem elérhető.', 'changed' => 0];
    }

    $catalog = latinfo_dance_style_catalog($db);
    if ($catalog === []) {
        return ['ok' => false, 'error' => 'Nincs elérhető táncstílus.', 'changed' => 0];
    }

    $wanted = [];
    foreach ($levelsByStyleId as $styleKey => $rawLevel) {
        $styleId = (int) $styleKey;
        if ($styleId <= 0 || !isset($catalog[$styleId])) {
            continue;
        }
        $level = latinfo_dance_style_normalize_level($rawLevel);
        if ($level >= 1) {
            $wanted[$styleId] = $level;
        }
    }

    $current = latinfo_user_dance_styles_map($db, $userId);
    $changed = 0;

    try {
        $db->beginTransaction();

        $upsert = $db->prepare('
            INSERT INTO `latinfo_user_dance_styles` (`user_id`, `style_id`, `level`)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE `level` = VALUES(`level`)
        ');
        $delete = $db->prepare('
            DELETE FROM `latinfo_user_dance_styles`
            WHERE `user_id` = ? AND `style_id` = ?
        ');

        foreach ($wanted as $styleId => $level) {
            if (($current[$styleId] ?? null) === $level) {
                continue;
            }
            $upsert->execute([$userId, $styleId, $level]);
            $changed++;
        }

        foreach ($current as $styleId => $_level) {
            if (isset($wanted[$styleId])) {
                continue;
            }
            $delete->execute([$userId, $styleId]);
            $changed++;
        }

        $db->commit();

        return ['ok' => true, 'error' => '', 'changed' => $changed];
    } catch (Throwable $ex) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('latinfo_user_dance_styles_save: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'A mentés sikertelen.', 'changed' => 0];
    }
}
