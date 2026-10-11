<?php
declare(strict_types=1);

/**
 * Felhasználói táncstílus-preferenciák.
 * - level: tudásszint 1–10 (0 = nem adom meg)
 * - liking: mennyire kedveli 1–10 (0 = nem adom meg)
 * - not_interested: nem érdekel → értékek figyelmen kívül
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

function latinfo_user_dance_styles_column_exists(PDO $db, string $column): bool
{
    try {
        $stmt = $db->query('SHOW COLUMNS FROM `latinfo_user_dance_styles` LIKE ' . $db->quote($column));

        return $stmt !== false && $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    } catch (Throwable) {
        return false;
    }
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
                `level` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `liking` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `not_interested` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_latinfo_user_dance_style` (`user_id`, `style_id`),
                KEY `idx_latinfo_user_dance_styles_user` (`user_id`),
                KEY `idx_latinfo_user_dance_styles_style` (`style_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        if (!latinfo_user_dance_styles_column_exists($db, 'liking')) {
            $db->exec('
                ALTER TABLE `latinfo_user_dance_styles`
                ADD COLUMN `liking` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `level`
            ');
        }
        if (!latinfo_user_dance_styles_column_exists($db, 'not_interested')) {
            $db->exec('
                ALTER TABLE `latinfo_user_dance_styles`
                ADD COLUMN `not_interested` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `liking`
            ');
        }

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

function latinfo_dance_style_liking_label(int $liking): string
{
    return match ($liking) {
        1 => 'Egyáltalán nem',
        2 => 'Alig',
        3 => 'Kicsit',
        4 => 'Közepesen',
        5 => 'Eléggé',
        6 => 'Szeretem',
        7 => 'Nagyon',
        8 => 'Nagyon szeretem',
        9 => 'Imádom',
        10 => 'A kedvencem',
        default => 'Nem adom meg',
    };
}

/**
 * Stílus megjelenítési név: nagybetűvel kezdődik; Társastánc → Zouk.
 */
function latinfo_dance_style_format_name(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '';
    }

    $folded = function_exists('mb_strtolower')
        ? mb_strtolower($name, 'UTF-8')
        : strtolower($name);

    if ($folded === 'társastánc' || $folded === 'tarsastanc') {
        return 'Zouk';
    }

    if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
        $first = mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
        $rest = mb_substr($name, 1, null, 'UTF-8');

        return $first . $rest;
    }

    return ucfirst($name);
}

/**
 * @return int 0–10
 */
function latinfo_dance_style_normalize_score(mixed $raw): int
{
    if ($raw === null || $raw === '' || $raw === false) {
        return 0;
    }
    $score = (int) $raw;
    if ($score < 0) {
        return 0;
    }
    if ($score > 10) {
        return 10;
    }

    return $score;
}

/**
 * @deprecated Use latinfo_dance_style_normalize_score()
 * @return int 0–10
 */
function latinfo_dance_style_normalize_level(mixed $raw): int
{
    return latinfo_dance_style_normalize_score($raw);
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
    $zoukId = 0;
    $tarsaIds = [];

    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        $rawName = trim((string) ($row['name'] ?? ''));
        if ($id <= 0 || $rawName === '') {
            continue;
        }

        $folded = function_exists('mb_strtolower')
            ? mb_strtolower($rawName, 'UTF-8')
            : strtolower($rawName);

        if ($folded === 'zouk') {
            $zoukId = $id;
        }
        if ($folded === 'társastánc' || $folded === 'tarsastanc') {
            $tarsaIds[] = $id;
        }

        $out[$id] = latinfo_dance_style_format_name($rawName);
    }

    // Társastánc helyett Zouk: ha már van Zouk, a Társastánc sort kihagyjuk.
    if ($zoukId > 0) {
        foreach ($tarsaIds as $tarsaId) {
            unset($out[$tarsaId]);
        }
    }

    asort($out, SORT_FLAG_CASE | SORT_NATURAL);

    return $out;
}

/**
 * @return array{level:int,liking:int,not_interested:bool}
 */
function latinfo_dance_style_empty_prefs(): array
{
    return [
        'level' => 0,
        'liking' => 0,
        'not_interested' => false,
    ];
}

/**
 * @return array<int, array{level:int,liking:int,not_interested:bool}>
 */
function latinfo_user_dance_styles_map(PDO $db, int $userId): array
{
    if ($userId <= 0 || !latinfo_user_dance_styles_ensure_schema($db)) {
        return [];
    }
    try {
        $stmt = $db->prepare('
            SELECT `style_id`, `level`, `liking`, `not_interested`
            FROM `latinfo_user_dance_styles`
            WHERE `user_id` = ?
        ');
        $stmt->execute([$userId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $styleId = (int) ($row['style_id'] ?? 0);
            if ($styleId <= 0) {
                continue;
            }
            $notInterested = (int) ($row['not_interested'] ?? 0) === 1;
            $level = $notInterested ? 0 : latinfo_dance_style_normalize_score($row['level'] ?? 0);
            $liking = $notInterested ? 0 : latinfo_dance_style_normalize_score($row['liking'] ?? 0);
            if (!$notInterested && $level < 1 && $liking < 1) {
                continue;
            }
            $out[$styleId] = [
                'level' => $level,
                'liking' => $liking,
                'not_interested' => $notInterested,
            ];
        }

        return $out;
    } catch (Throwable $ex) {
        error_log('latinfo_user_dance_styles_map: ' . $ex->getMessage());

        return [];
    }
}

/**
 * @param array<int|string, mixed> $prefsByStyleId style_id => level|array prefs
 * @return array{ok:bool,error:string,changed:int}
 */
function latinfo_user_dance_styles_save(PDO $db, int $userId, array $prefsByStyleId): array
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
    foreach ($prefsByStyleId as $styleKey => $rawPrefs) {
        $styleId = (int) $styleKey;
        if ($styleId <= 0 || !isset($catalog[$styleId])) {
            continue;
        }

        $notInterested = false;
        $level = 0;
        $liking = 0;

        if (is_array($rawPrefs)) {
            $notInterested = !empty($rawPrefs['not_interested'])
                && (string) $rawPrefs['not_interested'] !== '0';
            if (!$notInterested) {
                $level = latinfo_dance_style_normalize_score($rawPrefs['level'] ?? 0);
                $liking = latinfo_dance_style_normalize_score($rawPrefs['liking'] ?? 0);
            }
        } else {
            // Régi forma: styles[id] = level
            $level = latinfo_dance_style_normalize_score($rawPrefs);
        }

        if ($notInterested) {
            $wanted[$styleId] = [
                'level' => 0,
                'liking' => 0,
                'not_interested' => true,
            ];
            continue;
        }

        if ($level >= 1 || $liking >= 1) {
            $wanted[$styleId] = [
                'level' => $level,
                'liking' => $liking,
                'not_interested' => false,
            ];
        }
    }

    $current = latinfo_user_dance_styles_map($db, $userId);
    $changed = 0;

    try {
        $db->beginTransaction();

        $upsert = $db->prepare('
            INSERT INTO `latinfo_user_dance_styles`
                (`user_id`, `style_id`, `level`, `liking`, `not_interested`)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                `level` = VALUES(`level`),
                `liking` = VALUES(`liking`),
                `not_interested` = VALUES(`not_interested`)
        ');
        $delete = $db->prepare('
            DELETE FROM `latinfo_user_dance_styles`
            WHERE `user_id` = ? AND `style_id` = ?
        ');

        foreach ($wanted as $styleId => $prefs) {
            $prev = $current[$styleId] ?? null;
            if (
                $prev !== null
                && (int) $prev['level'] === (int) $prefs['level']
                && (int) $prev['liking'] === (int) $prefs['liking']
                && (bool) $prev['not_interested'] === (bool) $prefs['not_interested']
            ) {
                continue;
            }
            $upsert->execute([
                $userId,
                $styleId,
                (int) $prefs['level'],
                (int) $prefs['liking'],
                $prefs['not_interested'] ? 1 : 0,
            ]);
            $changed++;
        }

        foreach ($current as $styleId => $_prefs) {
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
