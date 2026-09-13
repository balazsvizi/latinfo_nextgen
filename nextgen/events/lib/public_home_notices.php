<?php
declare(strict_types=1);

require_once __DIR__ . '/public_home_content.php';
require_once __DIR__ . '/public_home_notice_stats.php';

const EVENTS_HOME_NOTICE_SESSION_KEY = 'events_home_notice_id';
const EVENTS_HOME_NOTICE_SEEN_COOKIE = 'events_home_notice_seen';
const EVENTS_HOME_NOTICE_SORT_STEP = 10;

function events_public_home_notices_table_available(PDO $db): bool
{
    try {
        $db->query('SELECT 1 FROM `events_public_home_notices` LIMIT 1');

        return true;
    } catch (Throwable) {
        return false;
    }
}

function events_public_home_notices_ensure_schema(PDO $db): bool
{
    static $done = false;
    if ($done) {
        return true;
    }

    events_public_home_notice_stats_ensure_schema($db);

    try {
        if (!events_public_home_notices_table_available($db)) {
            $db->exec('
                CREATE TABLE IF NOT EXISTS `events_public_home_notices` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
                    `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
                    `notice_text` VARCHAR(500) NOT NULL DEFAULT \'\',
                    `notice_text_en` VARCHAR(500) NOT NULL DEFAULT \'\',
                    `notice_url` VARCHAR(500) NOT NULL DEFAULT \'\',
                    `notice_url_new_tab` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                    `notice_color_scheme` VARCHAR(32) NOT NULL DEFAULT \'neon_green\',
                    `notice_custom_color` CHAR(7) NOT NULL DEFAULT \'#39FF14\',
                    `created_at` DATETIME NOT NULL,
                    `updated_at` DATETIME NOT NULL,
                    PRIMARY KEY (`id`),
                    KEY `idx_home_notices_active` (`is_active`, `sort_order`, `id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ');
        }
    } catch (Throwable $e) {
        error_log('events_public_home_notices_ensure_schema: ' . $e->getMessage());

        return false;
    }

    if (!events_public_home_notices_table_available($db)) {
        return false;
    }

    events_public_home_notices_seed_from_legacy($db);
    $done = true;

    return true;
}

function events_public_home_notices_seed_from_legacy(PDO $db): void
{
    try {
        $count = (int) $db->query('SELECT COUNT(*) FROM `events_public_home_notices`')->fetchColumn();
        if ($count > 0) {
            return;
        }
        if (!events_public_home_table_available($db) || !events_public_home_ensure_notice_schema($db)) {
            return;
        }

        $row = $db->query(
            'SELECT `notice_text`, `notice_text_en`, `notice_url`, `notice_url_new_tab`,
                    `notice_color_scheme`, `notice_custom_color`
             FROM `events_public_home` WHERE `id` = 1 LIMIT 1'
        )->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return;
        }

        $hu = mb_substr(trim((string) ($row['notice_text'] ?? '')), 0, 500);
        $en = mb_substr(trim((string) ($row['notice_text_en'] ?? '')), 0, 500);
        $url = mb_substr(trim((string) ($row['notice_url'] ?? '')), 0, 500);
        if ($hu === '' && $en === '') {
            return;
        }

        $defaults = events_public_home_notice_defaults();
        $scheme = (string) ($row['notice_color_scheme'] ?? $defaults['notice_color_scheme']);
        $presets = events_public_home_notice_color_presets();
        if ($scheme !== 'custom' && !isset($presets[$scheme])) {
            $scheme = $defaults['notice_color_scheme'];
        }
        $custom = events_public_home_normalize_hex_color((string) ($row['notice_custom_color'] ?? ''))
            ?? $defaults['notice_custom_color'];
        $newTab = events_public_home_notice_new_tab_enabled($row['notice_url_new_tab'] ?? false) ? 1 : 0;
        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $ins = $db->prepare('
            INSERT INTO `events_public_home_notices` (
                `sort_order`, `is_active`, `notice_text`, `notice_text_en`, `notice_url`,
                `notice_url_new_tab`, `notice_color_scheme`, `notice_custom_color`,
                `created_at`, `updated_at`
            ) VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $ins->execute([EVENTS_HOME_NOTICE_SORT_STEP, $hu, $en, $url, $newTab, $scheme, $custom, $now, $now]);
        $id = (int) $db->lastInsertId();
        if ($id <= 0) {
            return;
        }

        if (events_public_home_notice_has_column($db, 'events_public_home_notice_versions', 'notice_id')) {
            $db->prepare(
                'UPDATE `events_public_home_notice_versions` SET `notice_id` = ? WHERE `notice_id` IS NULL OR `notice_id` = 0'
            )->execute([$id]);
        }
        if (events_public_home_notice_has_column($db, 'events_public_home_notice_clicks', 'notice_id')) {
            $db->prepare(
                'UPDATE `events_public_home_notice_clicks` SET `notice_id` = ? WHERE `notice_id` IS NULL OR `notice_id` = 0'
            )->execute([$id]);
        }

        events_public_home_notice_sync_version($db, [
            'id' => $id,
            'notice_text' => $hu,
            'notice_text_en' => $en,
            'notice_url' => $url,
        ]);
    } catch (Throwable $e) {
        error_log('events_public_home_notices_seed_from_legacy: ' . $e->getMessage());
    }
}

/**
 * @return list<array<string, mixed>>
 */
function events_public_home_notices_all(PDO $db): array
{
    if (!events_public_home_notices_ensure_schema($db)) {
        return [];
    }

    try {
        $rows = $db->query(
            'SELECT * FROM `events_public_home_notices` ORDER BY `sort_order` ASC, `id` ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('events_public_home_notices_all: ' . $e->getMessage());

        return [];
    }

    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $out[] = events_public_home_notices_normalize_row($row);
    }

    return $out;
}

/**
 * Aktív, megjeleníthető tipeket adja vissza (van HU vagy EN szöveg).
 *
 * @return list<array<string, mixed>>
 */
function events_public_home_notices_active_for_display(PDO $db): array
{
    $out = [];
    foreach (events_public_home_notices_all($db) as $row) {
        if ((int) ($row['is_active'] ?? 0) !== 1) {
            continue;
        }
        $hu = trim((string) ($row['notice_text'] ?? ''));
        $en = trim((string) ($row['notice_text_en'] ?? ''));
        if ($hu === '' && $en === '') {
            continue;
        }
        $out[] = $row;
    }

    return $out;
}

/**
 * @return array<string, mixed>|null
 */
function events_public_home_notices_find(PDO $db, int $id): ?array
{
    if ($id <= 0 || !events_public_home_notices_ensure_schema($db)) {
        return null;
    }

    try {
        $st = $db->prepare('SELECT * FROM `events_public_home_notices` WHERE `id` = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('events_public_home_notices_find: ' . $e->getMessage());

        return null;
    }

    return is_array($row) ? events_public_home_notices_normalize_row($row) : null;
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function events_public_home_notices_normalize_row(array $row): array
{
    $defaults = events_public_home_notice_defaults();
    $scheme = (string) ($row['notice_color_scheme'] ?? $defaults['notice_color_scheme']);
    $presets = events_public_home_notice_color_presets();
    if ($scheme !== 'custom' && !isset($presets[$scheme])) {
        $scheme = $defaults['notice_color_scheme'];
    }
    $custom = events_public_home_normalize_hex_color((string) ($row['notice_custom_color'] ?? ''))
        ?? $defaults['notice_custom_color'];

    return [
        'id' => (int) ($row['id'] ?? 0),
        'sort_order' => (int) ($row['sort_order'] ?? 0),
        'is_active' => (int) ($row['is_active'] ?? 0) === 1,
        'notice_text' => (string) ($row['notice_text'] ?? ''),
        'notice_text_en' => (string) ($row['notice_text_en'] ?? ''),
        'notice_url' => (string) ($row['notice_url'] ?? ''),
        'notice_url_new_tab' => events_public_home_notice_new_tab_enabled($row['notice_url_new_tab'] ?? false),
        'notice_color_scheme' => $scheme,
        'notice_custom_color' => $custom,
        'created_at' => (string) ($row['created_at'] ?? ''),
        'updated_at' => (string) ($row['updated_at'] ?? ''),
    ];
}

function events_public_home_notices_create(PDO $db): int
{
    if (!events_public_home_notices_ensure_schema($db)) {
        throw new RuntimeException('A tip tábla nem érhető el.');
    }

    $defaults = events_public_home_notice_defaults();
    $next = (int) ($db->query('SELECT COALESCE(MAX(`sort_order`), 0) FROM `events_public_home_notices`')?->fetchColumn() ?: 0);
    $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

    $st = $db->prepare('
        INSERT INTO `events_public_home_notices` (
            `sort_order`, `is_active`, `notice_text`, `notice_text_en`, `notice_url`,
            `notice_url_new_tab`, `notice_color_scheme`, `notice_custom_color`,
            `created_at`, `updated_at`
        ) VALUES (?, 1, \'\', \'\', ?, 0, ?, ?, ?, ?)
    ');
    $st->execute([
        $next + EVENTS_HOME_NOTICE_SORT_STEP,
        $defaults['notice_url'],
        $defaults['notice_color_scheme'],
        $defaults['notice_custom_color'],
        $now,
        $now,
    ]);
    $id = (int) $db->lastInsertId();
    if ($id <= 0) {
        throw new RuntimeException('A tip létrehozása nem sikerült.');
    }

    events_public_home_notice_sync_version($db, [
        'id' => $id,
        'notice_text' => '',
        'notice_text_en' => '',
        'notice_url' => $defaults['notice_url'],
    ]);

    return $id;
}

/**
 * @param array<string, mixed> $input
 */
function events_public_home_notices_save(PDO $db, int $id, array $input): void
{
    $current = events_public_home_notices_find($db, $id);
    if ($current === null) {
        throw new InvalidArgumentException('A tip nem található.');
    }

    $parsed = events_public_home_notices_parse_input($input);
    $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

    $st = $db->prepare('
        UPDATE `events_public_home_notices`
        SET `is_active` = ?, `notice_text` = ?, `notice_text_en` = ?, `notice_url` = ?,
            `notice_url_new_tab` = ?, `notice_color_scheme` = ?, `notice_custom_color` = ?,
            `updated_at` = ?
        WHERE `id` = ?
    ');
    $st->execute([
        $parsed['is_active'] ? 1 : 0,
        $parsed['notice_text'],
        $parsed['notice_text_en'],
        $parsed['notice_url'],
        $parsed['notice_url_new_tab'] ? 1 : 0,
        $parsed['notice_color_scheme'],
        $parsed['notice_custom_color'],
        $now,
        $id,
    ]);

    events_public_home_notice_sync_version($db, [
        'id' => $id,
        'notice_text' => $parsed['notice_text'],
        'notice_text_en' => $parsed['notice_text_en'],
        'notice_url' => $parsed['notice_url'],
    ]);
}

function events_public_home_notices_set_active(PDO $db, int $id, bool $active): void
{
    $current = events_public_home_notices_find($db, $id);
    if ($current === null) {
        throw new InvalidArgumentException('A tip nem található.');
    }

    $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    $st = $db->prepare(
        'UPDATE `events_public_home_notices` SET `is_active` = ?, `updated_at` = ? WHERE `id` = ?'
    );
    $st->execute([$active ? 1 : 0, $now, $id]);
}

function events_public_home_notices_delete(PDO $db, int $id): void
{
    if ($id <= 0) {
        throw new InvalidArgumentException('A tip nem található.');
    }
    $st = $db->prepare('DELETE FROM `events_public_home_notices` WHERE `id` = ?');
    $st->execute([$id]);
}

/**
 * @param array<string, mixed> $input
 * @return array{
 *   is_active: bool,
 *   notice_text: string,
 *   notice_text_en: string,
 *   notice_url: string,
 *   notice_url_new_tab: bool,
 *   notice_color_scheme: string,
 *   notice_custom_color: string
 * }
 */
function events_public_home_notices_parse_input(array $input): array
{
    $defaults = events_public_home_notice_defaults();
    $url = events_public_home_sanitize_notice_url((string) ($input['notice_url'] ?? ''));
    if ($url === null) {
        throw new InvalidArgumentException('Érvénytelen átkattintás URL.');
    }
    $scheme = trim((string) ($input['notice_color_scheme'] ?? $defaults['notice_color_scheme']));
    $presets = events_public_home_notice_color_presets();
    if ($scheme !== 'custom' && !isset($presets[$scheme])) {
        $scheme = $defaults['notice_color_scheme'];
    }
    $custom = events_public_home_normalize_hex_color((string) ($input['notice_custom_color'] ?? ''))
        ?? $defaults['notice_custom_color'];

    return [
        'is_active' => events_public_home_notice_new_tab_enabled($input['is_active'] ?? false),
        'notice_text' => mb_substr(trim((string) ($input['notice_text'] ?? '')), 0, 500),
        'notice_text_en' => mb_substr(trim((string) ($input['notice_text_en'] ?? '')), 0, 500),
        'notice_url' => $url,
        'notice_url_new_tab' => events_public_home_notice_new_tab_enabled($input['notice_url_new_tab'] ?? false),
        'notice_color_scheme' => $scheme,
        'notice_custom_color' => $custom,
    ];
}

function events_public_home_notices_has_displayable_text(array $notice): bool
{
    return trim((string) ($notice['notice_text'] ?? '')) !== ''
        || trim((string) ($notice['notice_text_en'] ?? '')) !== '';
}

/**
 * Látogatónként: munkamenetben ugyanaz a tip, következő alkalommal másik (ha van).
 *
 * @param array<string, string> $langStrings
 * @return array{visible: bool, text: string, aria: string, url: string, open_new_tab: bool, style: string, version_id: int, notice_id: int, lang: string}|null
 */
function events_public_home_notice_pick_for_visitor(PDO $db, string $lang, array $langStrings = []): ?array
{
    if (!events_public_home_notices_ensure_schema($db)) {
        return null;
    }

    $active = events_public_home_notices_active_for_display($db);
    if ($active === []) {
        return null;
    }

    $byId = [];
    $activeIds = [];
    foreach ($active as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $byId[$id] = $row;
        $activeIds[] = $id;
    }
    if ($activeIds === []) {
        return null;
    }

    $sessionId = (int) ($_SESSION[EVENTS_HOME_NOTICE_SESSION_KEY] ?? 0);
    $seen = events_public_home_notice_seen_ids_from_cookie();
    $choice = events_public_home_notice_choose_id($activeIds, $seen, $sessionId);
    $chosenId = (int) $choice['id'];
    if ($chosenId <= 0 || !isset($byId[$chosenId])) {
        return null;
    }

    if (!empty($choice['rotated'])) {
        $_SESSION[EVENTS_HOME_NOTICE_SESSION_KEY] = $chosenId;
        events_public_home_notice_write_seen_cookie($choice['seen']);
    } elseif ($sessionId !== $chosenId) {
        $_SESSION[EVENTS_HOME_NOTICE_SESSION_KEY] = $chosenId;
    }

    $notice = $byId[$chosenId];
    $versionId = events_public_home_notice_current_version_id($db, $chosenId);
    if ($versionId === null || $versionId <= 0) {
        $versionId = events_public_home_notice_sync_version($db, $notice) ?? 0;
    }

    $displayLang = $lang === 'en' ? 'en' : 'hu';
    events_public_home_notice_track_impression($db, $chosenId, $versionId, $displayLang);

    $display = events_public_home_notice_for_display($notice, $lang, $langStrings);
    if ($display === null) {
        return null;
    }
    $display['version_id'] = $versionId;
    $display['notice_id'] = $chosenId;

    return $display;
}

/**
 * @param list<int> $activeIds
 * @param list<int> $seenIds
 * @return array{id: int, seen: list<int>, rotated: bool}
 */
function events_public_home_notice_choose_id(array $activeIds, array $seenIds, int $sessionCurrentId): array
{
    $activeIds = array_values(array_unique(array_filter(
        array_map('intval', $activeIds),
        static fn (int $id): bool => $id > 0
    )));
    if ($activeIds === []) {
        return ['id' => 0, 'seen' => [], 'rotated' => false];
    }
    $activeSet = array_fill_keys($activeIds, true);

    if ($sessionCurrentId > 0 && isset($activeSet[$sessionCurrentId])) {
        return ['id' => $sessionCurrentId, 'seen' => $seenIds, 'rotated' => false];
    }

    $seen = array_values(array_filter(
        array_map('intval', $seenIds),
        static fn (int $id): bool => isset($activeSet[$id])
    ));
    $unseen = array_values(array_filter(
        $activeIds,
        static fn (int $id): bool => !in_array($id, $seen, true)
    ));

    if ($unseen === []) {
        $last = $seen !== [] ? (int) $seen[count($seen) - 1] : 0;
        $unseen = $activeIds;
        if (count($unseen) > 1 && $last > 0) {
            $withoutLast = array_values(array_filter($unseen, static fn (int $id): bool => $id !== $last));
            if ($withoutLast !== []) {
                $unseen = $withoutLast;
            }
        }
        $seen = [];
    }

    $chosen = $unseen[random_int(0, count($unseen) - 1)];
    $seen[] = $chosen;

    return [
        'id' => $chosen,
        'seen' => array_values(array_unique($seen)),
        'rotated' => true,
    ];
}

/**
 * @return list<int>
 */
function events_public_home_notice_seen_ids_from_cookie(): array
{
    $raw = trim((string) ($_COOKIE[EVENTS_HOME_NOTICE_SEEN_COOKIE] ?? ''));
    if ($raw === '') {
        return [];
    }
    $ids = [];
    foreach (explode(',', $raw) as $part) {
        $id = (int) trim($part);
        if ($id > 0) {
            $ids[] = $id;
        }
        if (count($ids) >= 40) {
            break;
        }
    }

    return array_values(array_unique($ids));
}

/**
 * @param list<int> $ids
 */
function events_public_home_notice_write_seen_cookie(array $ids): void
{
    $clean = [];
    foreach ($ids as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $clean[] = $id;
        }
        if (count($clean) >= 40) {
            break;
        }
    }
    $value = implode(',', array_values(array_unique($clean)));
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);

    setcookie(EVENTS_HOME_NOTICE_SEEN_COOKIE, $value, [
        'expires' => time() + 180 * 86400,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[EVENTS_HOME_NOTICE_SEEN_COOKIE] = $value;
}
