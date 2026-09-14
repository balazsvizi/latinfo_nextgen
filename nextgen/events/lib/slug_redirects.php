<?php
declare(strict_types=1);

const EVENTS_SLUG_SAVE_DELAY_DEFAULT = 60;
const EVENTS_SLUG_SAVE_DELAY_MIN = 0;
const EVENTS_SLUG_SAVE_DELAY_MAX = 10080;
const EVENTS_SLUG_SETTING_DELAY_KEY = 'slug_save_delay_minutes';

function events_slug_redirects_ensure_schema(PDO $db): bool
{
    static $done = false;
    if ($done) {
        return true;
    }

    try {
        if ($db->inTransaction()) {
            return events_slug_redirects_tables_available($db)
                && events_slug_published_at_column_available($db);
        }

        $db->exec("
            CREATE TABLE IF NOT EXISTS `events_app_settings` (
                `setting_key` VARCHAR(64) NOT NULL,
                `setting_value` VARCHAR(255) NOT NULL,
                `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`setting_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS `events_slug_redirects` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `event_id` INT UNSIGNED NOT NULL,
                `old_slug` VARCHAR(255) NOT NULL,
                `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_events_slug_redirects_old` (`old_slug`),
                KEY `idx_events_slug_redirects_event` (`event_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        try {
            $db->exec('
                ALTER TABLE `events_slug_redirects`
                ADD CONSTRAINT `fk_events_slug_redirects_event`
                FOREIGN KEY (`event_id`) REFERENCES `events_calendar_events` (`id`) ON DELETE CASCADE
            ');
        } catch (PDOException) {
            // FK már létezik, vagy a motor nem támogatja
        }

        if (!events_slug_published_at_column_available($db)) {
            $db->exec('
                ALTER TABLE `events_calendar_events`
                ADD COLUMN `event_published_at` DATETIME NULL DEFAULT NULL AFTER `event_status`
            ');
            events_slug_published_at_column_available($db, true);
            $db->exec("
                UPDATE `events_calendar_events`
                SET `event_published_at` = `created`
                WHERE `event_status` = 'publish'
                  AND `event_published_at` IS NULL
            ");
        }

        $st = $db->prepare('
            INSERT IGNORE INTO `events_app_settings` (`setting_key`, `setting_value`)
            VALUES (?, ?)
        ');
        $st->execute([EVENTS_SLUG_SETTING_DELAY_KEY, (string) EVENTS_SLUG_SAVE_DELAY_DEFAULT]);
        events_slug_redirects_tables_available($db, true);
    } catch (Throwable $e) {
        error_log('events_slug_redirects_ensure_schema: ' . $e->getMessage());

        return false;
    }

    if (!events_slug_redirects_tables_available($db, true) || !events_slug_published_at_column_available($db, true)) {
        return false;
    }

    $done = true;

    return true;
}

function events_slug_redirects_tables_available(PDO $db, bool $forceRefresh = false): bool
{
    static $cached = null;
    if (!$forceRefresh && $cached !== null) {
        return $cached;
    }
    try {
        $db->query('SELECT 1 FROM `events_slug_redirects` LIMIT 1');
        $db->query('SELECT 1 FROM `events_app_settings` LIMIT 1');
        $cached = true;
    } catch (Throwable) {
        $cached = false;
    }

    return $cached;
}

function events_slug_published_at_column_available(PDO $db, bool $forceRefresh = false): bool
{
    static $cached = null;
    if (!$forceRefresh && $cached !== null) {
        return $cached;
    }
    try {
        $st = $db->query("
            SELECT COUNT(*)
            FROM `information_schema`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = DATABASE()
              AND `TABLE_NAME` = 'events_calendar_events'
              AND `COLUMN_NAME` = 'event_published_at'
        ");
        $cached = ((int) $st->fetchColumn()) > 0;
    } catch (PDOException) {
        $cached = false;
    }

    return $cached;
}

function events_slug_save_delay_minutes(PDO $db): int
{
    $fallback = EVENTS_SLUG_SAVE_DELAY_DEFAULT;
    if (!events_slug_redirects_tables_available($db)) {
        return $fallback;
    }
    try {
        $st = $db->prepare('SELECT `setting_value` FROM `events_app_settings` WHERE `setting_key` = ? LIMIT 1');
        $st->execute([EVENTS_SLUG_SETTING_DELAY_KEY]);
        $raw = $st->fetchColumn();
        if ($raw === false) {
            return $fallback;
        }

        return events_slug_normalize_delay_minutes((int) $raw);
    } catch (Throwable) {
        return $fallback;
    }
}

function events_slug_normalize_delay_minutes(int $minutes): int
{
    if ($minutes < EVENTS_SLUG_SAVE_DELAY_MIN) {
        return EVENTS_SLUG_SAVE_DELAY_MIN;
    }
    if ($minutes > EVENTS_SLUG_SAVE_DELAY_MAX) {
        return EVENTS_SLUG_SAVE_DELAY_MAX;
    }

    return $minutes;
}

function events_slug_save_delay_minutes_save(PDO $db, int $minutes): void
{
    if (!events_slug_redirects_ensure_schema($db)) {
        throw new RuntimeException('A slug beállítások táblája nem érhető el.');
    }
    $minutes = events_slug_normalize_delay_minutes($minutes);
    $st = $db->prepare('
        INSERT INTO `events_app_settings` (`setting_key`, `setting_value`)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)
    ');
    $st->execute([EVENTS_SLUG_SETTING_DELAY_KEY, (string) $minutes]);
}

function events_slug_published_at_for_save(?string $existingPublishedAt, string $newStatus): ?string
{
    $current = $existingPublishedAt !== null ? trim($existingPublishedAt) : '';
    $current = $current !== '' ? $current : null;
    if ($newStatus === events_public_post_status()) {
        return $current ?? date('Y-m-d H:i:s');
    }

    return $current;
}

/**
 * A slug akkor rögzül, ha az esemény már publikálva volt, és a késleltetés letelt.
 *
 * @param array<string, mixed> $event DB sor (event_published_at)
 */
function events_slug_is_locked(PDO $db, array $event): bool
{
    $publishedAt = trim((string) ($event['event_published_at'] ?? ''));
    if ($publishedAt === '') {
        return false;
    }
    $ts = strtotime($publishedAt);
    if ($ts === false) {
        return false;
    }

    return time() >= ($ts + events_slug_save_delay_minutes($db) * 60);
}

/**
 * @param array<string, mixed> $event
 * @return array{locked: bool, published: bool, delay_minutes: int, remaining_minutes: ?int}
 */
function events_slug_lock_info(PDO $db, array $event): array
{
    $delay = events_slug_save_delay_minutes($db);
    $publishedAt = trim((string) ($event['event_published_at'] ?? ''));
    $published = $publishedAt !== '';
    if (!$published) {
        return [
            'locked' => false,
            'published' => false,
            'delay_minutes' => $delay,
            'remaining_minutes' => null,
        ];
    }
    $ts = strtotime($publishedAt);
    if ($ts === false) {
        return [
            'locked' => false,
            'published' => true,
            'delay_minutes' => $delay,
            'remaining_minutes' => $delay,
        ];
    }
    $unlocksAt = $ts + $delay * 60;
    $remaining = (int) ceil(($unlocksAt - time()) / 60);
    $locked = $remaining <= 0;

    return [
        'locked' => $locked,
        'published' => true,
        'delay_minutes' => $delay,
        'remaining_minutes' => $locked ? 0 : max(1, $remaining),
    ];
}

function events_slug_redirect_taken(PDO $db, string $slug, ?int $excludeEventId): bool
{
    if ($slug === '' || !events_slug_redirects_tables_available($db)) {
        return false;
    }
    $sql = 'SELECT 1 FROM `events_slug_redirects` WHERE `old_slug` = ?';
    $params = [$slug];
    if ($excludeEventId !== null) {
        $sql .= ' AND `event_id` != ?';
        $params[] = $excludeEventId;
    }
    try {
        $st = $db->prepare($sql . ' LIMIT 1');
        $st->execute($params);

        return (bool) $st->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

function events_slug_redirect_record(PDO $db, int $eventId, string $oldSlug, string $newSlug): bool
{
    if ($eventId <= 0 || !events_slug_redirects_ensure_schema($db)) {
        return false;
    }
    $oldSlug = events_slugify($oldSlug);
    $newSlug = events_slugify($newSlug);
    if ($oldSlug === '' || $newSlug === '' || $oldSlug === $newSlug) {
        return false;
    }

    $delBack = $db->prepare('DELETE FROM `events_slug_redirects` WHERE `event_id` = ? AND `old_slug` = ?');
    $delBack->execute([$eventId, $newSlug]);

    $chk = $db->prepare('SELECT `id`, `event_id` FROM `events_slug_redirects` WHERE `old_slug` = ? LIMIT 1');
    $chk->execute([$oldSlug]);
    $existing = $chk->fetch(PDO::FETCH_ASSOC);
    if (is_array($existing)) {
        return (int) ($existing['event_id'] ?? 0) === $eventId;
    }

    $ins = $db->prepare('INSERT INTO `events_slug_redirects` (`event_id`, `old_slug`) VALUES (?, ?)');
    $ins->execute([$eventId, $oldSlug]);

    return true;
}

function events_slug_redirect_target(PDO $db, string $slug): ?string
{
    $slug = trim($slug);
    if ($slug === '' || !events_slug_redirects_ensure_schema($db)) {
        return null;
    }
    $st = $db->prepare('
        SELECT e.`event_slug`
        FROM `events_slug_redirects` r
        INNER JOIN `events_calendar_events` e ON e.`id` = r.`event_id`
        WHERE r.`old_slug` = ? AND e.`event_status` = ?
        LIMIT 1
    ');
    $st->execute([$slug, events_public_post_status()]);
    $target = $st->fetchColumn();
    if (!is_string($target) || $target === '' || $target === $slug) {
        return null;
    }

    return $target;
}

/**
 * @return list<array<string, mixed>>
 */
function events_slug_redirects_list(PDO $db): array
{
    if (!events_slug_redirects_ensure_schema($db)) {
        return [];
    }
    $rows = $db->query('
        SELECT r.`id`, r.`event_id`, r.`old_slug`, r.`created`,
               e.`event_name`, e.`event_slug` AS `new_slug`, e.`event_status`
        FROM `events_slug_redirects` r
        LEFT JOIN `events_calendar_events` e ON e.`id` = r.`event_id`
        ORDER BY r.`created` DESC, r.`id` DESC
    ')->fetchAll(PDO::FETCH_ASSOC);

    return is_array($rows) ? $rows : [];
}

function events_slug_redirect_count_for_event(PDO $db, int $eventId): int
{
    if ($eventId <= 0 || !events_slug_redirects_tables_available($db)) {
        return 0;
    }
    try {
        $st = $db->prepare('SELECT COUNT(*) FROM `events_slug_redirects` WHERE `event_id` = ?');
        $st->execute([$eventId]);

        return (int) $st->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function events_slug_redirect_delete(PDO $db, int $id): bool
{
    if ($id <= 0 || !events_slug_redirects_ensure_schema($db)) {
        return false;
    }
    $st = $db->prepare('DELETE FROM `events_slug_redirects` WHERE `id` = ?');
    $st->execute([$id]);

    return $st->rowCount() > 0;
}

function events_slug_redirects_delete_for_event(PDO $db, int $eventId): void
{
    if ($eventId <= 0 || !events_slug_redirects_tables_available($db)) {
        return;
    }
    try {
        $db->prepare('DELETE FROM `events_slug_redirects` WHERE `event_id` = ?')->execute([$eventId]);
    } catch (Throwable) {
        // tábla még nincs
    }
}
