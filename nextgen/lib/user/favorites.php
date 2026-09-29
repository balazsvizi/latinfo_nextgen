<?php
declare(strict_types=1);

/**
 * Publikus kedvencek (szívecskék): esemény, szervező, helyszín, DJ.
 */

const LATINFO_FAVORITE_TYPE_EVENT = 'event';
const LATINFO_FAVORITE_TYPE_ORGANIZER = 'organizer';
const LATINFO_FAVORITE_TYPE_VENUE = 'venue';
const LATINFO_FAVORITE_TYPE_DJ = 'dj';
const LATINFO_FAVORITE_TYPE_ZENEKAR = 'zenekar';

const LATINFO_FAVORITES_VISITOR_COOKIE = 'latinfo_fav_vid';
const EVENTS_APP_SETTING_PUBLIC_HEARTS = 'public_hearts_enabled';

/**
 * @return list<string>
 */
function latinfo_favorite_entity_types(): array
{
    return [
        LATINFO_FAVORITE_TYPE_EVENT,
        LATINFO_FAVORITE_TYPE_ORGANIZER,
        LATINFO_FAVORITE_TYPE_VENUE,
        LATINFO_FAVORITE_TYPE_DJ,
        LATINFO_FAVORITE_TYPE_ZENEKAR,
    ];
}

function latinfo_favorites_entity_group_label(string $type, string $lang = 'hu'): string
{
    $type = latinfo_favorites_normalize_type($type) ?? '';
    $isEn = $lang === 'en';

    return match ($type) {
        LATINFO_FAVORITE_TYPE_EVENT => $isEn ? 'Event' : 'Esemény',
        LATINFO_FAVORITE_TYPE_ORGANIZER => $isEn ? 'Organizer' : 'Szervező',
        LATINFO_FAVORITE_TYPE_VENUE => $isEn ? 'Venue' : 'Helyszín',
        LATINFO_FAVORITE_TYPE_DJ => 'DJ',
        LATINFO_FAVORITE_TYPE_ZENEKAR => $isEn ? 'Band' : 'Zenekar',
        default => '',
    };
}

/**
 * Eseményhez kapcsolódó kedvenc-választó sorok.
 *
 * @param list<array{id?:int,name?:string}> $organizers
 * @param array{id?:int,label?:string}|null $venue
 * @param list<array{id?:int,name?:string}> $djs
 * @param list<array{id?:int,name?:string}> $bands
 * @return array{items:list<array{type:string,id:int,groupLabel:string,label:string,active:bool}>,active:bool,count:int}
 */
function latinfo_favorites_build_event_picker(
    PDO $db,
    int $eventId,
    string $eventName,
    string $lang,
    array $organizers = [],
    ?array $venue = null,
    array $djs = [],
    array $bands = []
): array {
    $lang = $lang === 'en' ? 'en' : 'hu';
    $pairs = [['type' => LATINFO_FAVORITE_TYPE_EVENT, 'id' => $eventId]];
    foreach ($organizers as $org) {
        $oid = (int) ($org['id'] ?? 0);
        if ($oid > 0) {
            $pairs[] = ['type' => LATINFO_FAVORITE_TYPE_ORGANIZER, 'id' => $oid];
        }
    }
    $venueId = (int) ($venue['id'] ?? 0);
    if ($venueId > 0) {
        $pairs[] = ['type' => LATINFO_FAVORITE_TYPE_VENUE, 'id' => $venueId];
    }
    foreach ($djs as $dj) {
        $did = (int) ($dj['id'] ?? 0);
        if ($did > 0) {
            $pairs[] = ['type' => LATINFO_FAVORITE_TYPE_DJ, 'id' => $did];
        }
    }
    foreach ($bands as $band) {
        $bid = (int) ($band['id'] ?? 0);
        if ($bid > 0) {
            $pairs[] = ['type' => LATINFO_FAVORITE_TYPE_ZENEKAR, 'id' => $bid];
        }
    }
    $activeSet = latinfo_favorites_active_set_for_actor($db, latinfo_favorites_current_actor_key(), $pairs);
    $eventState = latinfo_favorites_state($db, LATINFO_FAVORITE_TYPE_EVENT, $eventId);
    $items = [[
        'type' => LATINFO_FAVORITE_TYPE_EVENT,
        'id' => $eventId,
        'groupLabel' => latinfo_favorites_entity_group_label(LATINFO_FAVORITE_TYPE_EVENT, $lang),
        'label' => $eventName,
        'active' => isset($activeSet[LATINFO_FAVORITE_TYPE_EVENT . ':' . $eventId]),
    ]];
    foreach ($organizers as $org) {
        $oid = (int) ($org['id'] ?? 0);
        if ($oid <= 0) {
            continue;
        }
        $items[] = [
            'type' => LATINFO_FAVORITE_TYPE_ORGANIZER,
            'id' => $oid,
            'groupLabel' => latinfo_favorites_entity_group_label(LATINFO_FAVORITE_TYPE_ORGANIZER, $lang),
            'label' => (string) ($org['name'] ?? ''),
            'active' => isset($activeSet[LATINFO_FAVORITE_TYPE_ORGANIZER . ':' . $oid]),
        ];
    }
    if ($venueId > 0) {
        $items[] = [
            'type' => LATINFO_FAVORITE_TYPE_VENUE,
            'id' => $venueId,
            'groupLabel' => latinfo_favorites_entity_group_label(LATINFO_FAVORITE_TYPE_VENUE, $lang),
            'label' => (string) ($venue['label'] ?? ''),
            'active' => isset($activeSet[LATINFO_FAVORITE_TYPE_VENUE . ':' . $venueId]),
        ];
    }
    foreach ($djs as $dj) {
        $did = (int) ($dj['id'] ?? 0);
        if ($did <= 0) {
            continue;
        }
        $items[] = [
            'type' => LATINFO_FAVORITE_TYPE_DJ,
            'id' => $did,
            'groupLabel' => latinfo_favorites_entity_group_label(LATINFO_FAVORITE_TYPE_DJ, $lang),
            'label' => (string) ($dj['name'] ?? ''),
            'active' => isset($activeSet[LATINFO_FAVORITE_TYPE_DJ . ':' . $did]),
        ];
    }
    foreach ($bands as $band) {
        $bid = (int) ($band['id'] ?? 0);
        if ($bid <= 0) {
            continue;
        }
        $items[] = [
            'type' => LATINFO_FAVORITE_TYPE_ZENEKAR,
            'id' => $bid,
            'groupLabel' => latinfo_favorites_entity_group_label(LATINFO_FAVORITE_TYPE_ZENEKAR, $lang),
            'label' => (string) ($band['name'] ?? ''),
            'active' => isset($activeSet[LATINFO_FAVORITE_TYPE_ZENEKAR . ':' . $bid]),
        ];
    }
    $anyActive = false;
    foreach ($items as $item) {
        if (!empty($item['active'])) {
            $anyActive = true;
            break;
        }
    }

    return [
        'items' => $items,
        'active' => $anyActive,
        'count' => $eventState['count'],
    ];
}

/**
 * @param list<array{type:string,id:int}> $pairs
 * @return array<string, true>
 */
function latinfo_favorites_active_set_for_actor(PDO $db, ?string $actorKey, array $pairs): array
{
    if ($actorKey === null || $actorKey === '' || !latinfo_favorites_table_ready($db) || $pairs === []) {
        return [];
    }
    $byType = [];
    foreach ($pairs as $pair) {
        $type = latinfo_favorites_normalize_type((string) ($pair['type'] ?? ''));
        $id = (int) ($pair['id'] ?? 0);
        if ($type === null || $id <= 0) {
            continue;
        }
        $byType[$type][$id] = true;
    }
    if ($byType === []) {
        return [];
    }
    $out = [];
    try {
        foreach ($byType as $type => $idsMap) {
            $ids = array_keys($idsMap);
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $st = $db->prepare("
                SELECT `entity_id`
                FROM `latinfo_favorites`
                WHERE `actor_key` = ? AND `entity_type` = ? AND `entity_id` IN ({$ph})
            ");
            $st->execute(array_merge([$actorKey, $type], $ids));
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $eid) {
                $out[$type . ':' . (int) $eid] = true;
            }
        }
    } catch (Throwable $ex) {
        error_log('latinfo_favorites_active_set_for_actor: ' . $ex->getMessage());
    }

    return $out;
}

function latinfo_favorites_table_ready(PDO $db, bool $forceRefresh = false): bool
{
    static $cached = null;
    if (!$forceRefresh && $cached !== null) {
        return $cached;
    }
    try {
        $db->query('SELECT 1 FROM `latinfo_favorites` LIMIT 1');
        $cached = true;
    } catch (Throwable) {
        $cached = false;
    }

    return $cached;
}

function latinfo_favorites_ensure_schema(PDO $db): bool
{
    static $done = false;
    if ($done && latinfo_favorites_table_ready($db)) {
        return true;
    }

    try {
        if (!function_exists('events_slug_redirects_ensure_schema')) {
            require_once dirname(__DIR__, 2) . '/events/lib/slug_redirects.php';
        }
        if (!function_exists('latinfo_users_ensure_schema')) {
            require_once __DIR__ . '/users.php';
        }
        events_slug_redirects_ensure_schema($db);
        latinfo_users_ensure_schema($db);
        latinfo_users_ensure_notification_email_column($db);

        $db->exec("
            CREATE TABLE IF NOT EXISTS `latinfo_favorites` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `entity_type` VARCHAR(16) NOT NULL,
                `entity_id` INT UNSIGNED NOT NULL,
                `actor_key` VARCHAR(72) NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_latinfo_fav_actor_entity` (`entity_type`, `entity_id`, `actor_key`),
                KEY `idx_latinfo_fav_actor` (`actor_key`, `entity_type`),
                KEY `idx_latinfo_fav_entity` (`entity_type`, `entity_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $st = $db->prepare('
            INSERT IGNORE INTO `events_app_settings` (`setting_key`, `setting_value`)
            VALUES (?, ?)
        ');
        $st->execute([EVENTS_APP_SETTING_PUBLIC_HEARTS, '1']);

        $ready = latinfo_favorites_table_ready($db, true);
        if ($ready) {
            $done = true;
        }

        return $ready;
    } catch (Throwable $ex) {
        error_log('latinfo_favorites_ensure_schema: ' . $ex->getMessage());

        return false;
    }
}

function latinfo_users_ensure_notification_email_column(PDO $db): void
{
    if (!latinfo_users_table_ready($db)) {
        return;
    }
    try {
        $cols = $db->query("SHOW COLUMNS FROM `latinfo_users` LIKE 'notification_email'")->fetch(PDO::FETCH_ASSOC);
        if ($cols) {
            return;
        }
        $db->exec('
            ALTER TABLE `latinfo_users`
            ADD COLUMN `notification_email` VARCHAR(255) NULL DEFAULT NULL AFTER `email`
        ');
    } catch (Throwable $ex) {
        error_log('latinfo_users_ensure_notification_email_column: ' . $ex->getMessage());
    }
}

function latinfo_favorites_public_enabled(PDO $db): bool
{
    if (!events_slug_redirects_tables_available($db)) {
        return true;
    }
    try {
        $st = $db->prepare('SELECT `setting_value` FROM `events_app_settings` WHERE `setting_key` = ? LIMIT 1');
        $st->execute([EVENTS_APP_SETTING_PUBLIC_HEARTS]);
        $raw = $st->fetchColumn();
        if ($raw === false) {
            return true;
        }

        return (string) $raw === '1';
    } catch (Throwable) {
        return true;
    }
}

function latinfo_favorites_set_public_enabled(PDO $db, bool $enabled): void
{
    if (!events_slug_redirects_ensure_schema($db)) {
        throw new RuntimeException('Az alkalmazásbeállítások táblája nem érhető el.');
    }
    $st = $db->prepare('
        INSERT INTO `events_app_settings` (`setting_key`, `setting_value`)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)
    ');
    $st->execute([EVENTS_APP_SETTING_PUBLIC_HEARTS, $enabled ? '1' : '0']);
}

function latinfo_favorites_normalize_type(string $type): ?string
{
    $type = strtolower(trim($type));

    return in_array($type, latinfo_favorite_entity_types(), true) ? $type : null;
}

function latinfo_favorites_user_actor_key(int $userId): string
{
    return 'u:' . max(0, $userId);
}

function latinfo_favorites_visitor_actor_key(string $visitorToken): string
{
    $visitorToken = trim($visitorToken);
    if ($visitorToken === '' || !preg_match('/^[a-f0-9]{32,64}$/i', $visitorToken)) {
        return '';
    }

    return 'v:' . hash('sha256', $visitorToken);
}

function latinfo_favorites_issue_visitor_token(): string
{
    return bin2hex(random_bytes(32));
}

function latinfo_favorites_read_visitor_token_from_cookie(): string
{
    $raw = trim((string) ($_COOKIE[LATINFO_FAVORITES_VISITOR_COOKIE] ?? ''));

    return preg_match('/^[a-f0-9]{32,64}$/i', $raw) ? $raw : '';
}

function latinfo_favorites_send_visitor_cookie(string $token): void
{
    if (headers_sent()) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
    setcookie(LATINFO_FAVORITES_VISITOR_COOKIE, $token, [
        'expires' => time() + 86400 * 400,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[LATINFO_FAVORITES_VISITOR_COOKIE] = $token;
}

function latinfo_favorites_resolve_visitor_token(): string
{
    $token = latinfo_favorites_read_visitor_token_from_cookie();
    if ($token === '') {
        $token = latinfo_favorites_issue_visitor_token();
        latinfo_favorites_send_visitor_cookie($token);
    }

    return $token;
}

/**
 * Aktuális kedvenc „szereplő” (bejelentkezett user vagy vendég).
 */
function latinfo_favorites_current_actor_key(): ?string
{
    if (function_exists('user_is_logged_in') && user_is_logged_in()) {
        $uid = function_exists('user_current_id') ? user_current_id() : (int) ($_SESSION['user_id'] ?? 0);
        if ($uid > 0) {
            return latinfo_favorites_user_actor_key($uid);
        }
    }
    $visitorKey = latinfo_favorites_visitor_actor_key(latinfo_favorites_resolve_visitor_token());
    if ($visitorKey === '') {
        return null;
    }

    return $visitorKey;
}

function latinfo_favorites_entity_exists(PDO $db, string $type, int $entityId): bool
{
    if ($entityId <= 0) {
        return false;
    }
    try {
        return match ($type) {
            LATINFO_FAVORITE_TYPE_EVENT => (static function () use ($db, $entityId): bool {
                if (!function_exists('events_view_tracking_is_published_event')) {
                    require_once dirname(__DIR__, 2) . '/events/lib/event_view_tracking.php';
                }

                return events_view_tracking_is_published_event($db, $entityId);
            })(),
            LATINFO_FAVORITE_TYPE_ORGANIZER => (static function () use ($db, $entityId): bool {
                $st = $db->prepare('SELECT 1 FROM `events_organizers` WHERE `id` = ? LIMIT 1');
                $st->execute([$entityId]);

                return (bool) $st->fetchColumn();
            })(),
            LATINFO_FAVORITE_TYPE_VENUE => (static function () use ($db, $entityId): bool {
                $st = $db->prepare('SELECT 1 FROM `events_venues` WHERE `id` = ? LIMIT 1');
                $st->execute([$entityId]);

                return (bool) $st->fetchColumn();
            })(),
            LATINFO_FAVORITE_TYPE_DJ => (static function () use ($db, $entityId): bool {
                if (!function_exists('events_public_tag_has_type_code') || !events_tags_tables_available($db)) {
                    return false;
                }

                return events_public_tag_has_type_code($db, $entityId, 'dj');
            })(),
            LATINFO_FAVORITE_TYPE_ZENEKAR => (static function () use ($db, $entityId): bool {
                if (!function_exists('events_public_tag_has_type_code') || !events_tags_tables_available($db)) {
                    return false;
                }

                return events_public_tag_has_type_code($db, $entityId, 'zenekar');
            })(),
            default => false,
        };
    } catch (Throwable $ex) {
        error_log('latinfo_favorites_entity_exists: ' . $ex->getMessage());

        return false;
    }
}

function latinfo_favorites_count(PDO $db, string $type, int $entityId): int
{
    $type = latinfo_favorites_normalize_type($type) ?? '';
    if ($type === '' || $entityId <= 0 || !latinfo_favorites_table_ready($db)) {
        return 0;
    }
    try {
        $st = $db->prepare('
            SELECT COUNT(*) FROM `latinfo_favorites`
            WHERE `entity_type` = ? AND `entity_id` = ?
        ');
        $st->execute([$type, $entityId]);

        return (int) $st->fetchColumn();
    } catch (Throwable $ex) {
        error_log('latinfo_favorites_count: ' . $ex->getMessage());

        return 0;
    }
}

/**
 * @return array{active:bool,count:int}
 */
function latinfo_favorites_state(PDO $db, string $type, int $entityId, ?string $actorKey = null): array
{
    $type = latinfo_favorites_normalize_type($type) ?? '';
    if ($type === '' || $entityId <= 0) {
        return ['active' => false, 'count' => 0];
    }
    $actorKey = $actorKey ?? latinfo_favorites_current_actor_key();
    $active = false;
    if ($actorKey !== null && $actorKey !== '' && latinfo_favorites_table_ready($db)) {
        try {
            $st = $db->prepare('
                SELECT 1 FROM `latinfo_favorites`
                WHERE `entity_type` = ? AND `entity_id` = ? AND `actor_key` = ?
                LIMIT 1
            ');
            $st->execute([$type, $entityId, $actorKey]);
            $active = (bool) $st->fetchColumn();
        } catch (Throwable $ex) {
            error_log('latinfo_favorites_state: ' . $ex->getMessage());
        }
    }

    return [
        'active' => $active,
        'count' => latinfo_favorites_count($db, $type, $entityId),
    ];
}

/**
 * @return array{ok:bool,active:bool,count:int,error:string}
 */
function latinfo_favorites_set_active(PDO $db, string $type, int $entityId, bool $active, ?string $actorKey = null): array
{
    if (!latinfo_favorites_ensure_schema($db)) {
        return ['ok' => false, 'active' => false, 'count' => 0, 'error' => 'A kedvencek rendszer nem érhető el.'];
    }
    $type = latinfo_favorites_normalize_type($type);
    if ($type === null || $entityId <= 0) {
        return ['ok' => false, 'active' => false, 'count' => 0, 'error' => 'Érvénytelen kedvenc.'];
    }
    if (!latinfo_favorites_entity_exists($db, $type, $entityId)) {
        return ['ok' => false, 'active' => false, 'count' => 0, 'error' => 'Az entitás nem található.'];
    }
    $actorKey = $actorKey ?? latinfo_favorites_current_actor_key();
    if ($actorKey === null || $actorKey === '') {
        return ['ok' => false, 'active' => false, 'count' => 0, 'error' => 'Nem azonosítható látogató.'];
    }

    try {
        if ($active) {
            $ins = $db->prepare('
                INSERT IGNORE INTO `latinfo_favorites` (`entity_type`, `entity_id`, `actor_key`)
                VALUES (?, ?, ?)
            ');
            $ins->execute([$type, $entityId, $actorKey]);
        } else {
            $del = $db->prepare('
                DELETE FROM `latinfo_favorites`
                WHERE `entity_type` = ? AND `entity_id` = ? AND `actor_key` = ?
            ');
            $del->execute([$type, $entityId, $actorKey]);
        }
        $state = latinfo_favorites_state($db, $type, $entityId, $actorKey);

        return ['ok' => true, 'active' => $state['active'], 'count' => $state['count'], 'error' => ''];
    } catch (Throwable $ex) {
        error_log('latinfo_favorites_set_active: ' . $ex->getMessage());

        return ['ok' => false, 'active' => false, 'count' => 0, 'error' => 'Mentés sikertelen.'];
    }
}

/**
 * @return array{ok:bool,active:bool,count:int,error:string}
 */
function latinfo_favorites_toggle(PDO $db, string $type, int $entityId, ?string $actorKey = null): array
{
    $state = latinfo_favorites_state($db, $type, $entityId, $actorKey);

    return latinfo_favorites_set_active($db, $type, $entityId, !$state['active'], $actorKey);
}

/**
 * @param list<array{type:string,id:int}> $items
 * @return array{ok:bool,results:list<array{type:string,id:int,active:bool,count:int}>,error:string}
 */
function latinfo_favorites_apply_batch(PDO $db, array $items, bool $active, ?string $actorKey = null): array
{
    $results = [];
    foreach ($items as $item) {
        $type = latinfo_favorites_normalize_type((string) ($item['type'] ?? ''));
        $id = (int) ($item['id'] ?? 0);
        if ($type === null || $id <= 0) {
            continue;
        }
        $res = latinfo_favorites_set_active($db, $type, $id, $active, $actorKey);
        if (!$res['ok']) {
            return ['ok' => false, 'results' => $results, 'error' => $res['error']];
        }
        $results[] = [
            'type' => $type,
            'id' => $id,
            'active' => $res['active'],
            'count' => $res['count'],
        ];
    }

    return ['ok' => true, 'results' => $results, 'error' => ''];
}

function latinfo_favorites_merge_visitor_to_user(PDO $db, int $userId): void
{
    if ($userId <= 0 || !latinfo_favorites_table_ready($db)) {
        return;
    }
    $visitorToken = latinfo_favorites_read_visitor_token_from_cookie();
    if ($visitorToken === '') {
        return;
    }
    $visitorKey = latinfo_favorites_visitor_actor_key($visitorToken);
    $userKey = latinfo_favorites_user_actor_key($userId);
    if ($visitorKey === '' || $userKey === '') {
        return;
    }
    try {
        $sel = $db->prepare('
            SELECT `entity_type`, `entity_id`
            FROM `latinfo_favorites`
            WHERE `actor_key` = ?
        ');
        $sel->execute([$visitorKey]);
        $rows = $sel->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $type = latinfo_favorites_normalize_type((string) ($row['entity_type'] ?? ''));
            $eid = (int) ($row['entity_id'] ?? 0);
            if ($type === null || $eid <= 0) {
                continue;
            }
            latinfo_favorites_set_active($db, $type, $eid, true, $userKey);
        }
        $del = $db->prepare('DELETE FROM `latinfo_favorites` WHERE `actor_key` = ?');
        $del->execute([$visitorKey]);
    } catch (Throwable $ex) {
        error_log('latinfo_favorites_merge_visitor_to_user: ' . $ex->getMessage());
    }
}

/**
 * @return list<array{type:string,id:int,label:string,url:string,created_at:string}>
 */
function latinfo_favorites_list_for_user(PDO $db, int $userId, string $lang = 'hu'): array
{
    if ($userId <= 0 || !latinfo_favorites_table_ready($db)) {
        return [];
    }
    $actor = latinfo_favorites_user_actor_key($userId);
    $lang = $lang === 'en' ? 'en' : 'hu';
    try {
        $st = $db->prepare('
            SELECT `entity_type`, `entity_id`, `created_at`
            FROM `latinfo_favorites`
            WHERE `actor_key` = ?
            ORDER BY `created_at` DESC, `id` DESC
        ');
        $st->execute([$actor]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $type = (string) ($row['entity_type'] ?? '');
            $eid = (int) ($row['entity_id'] ?? 0);
            $meta = latinfo_favorites_entity_public_meta($db, $type, $eid, $lang);
            $out[] = [
                'type' => $type,
                'id' => $eid,
                'label' => $meta['label'] ?? ('#' . $eid),
                'url' => $meta['url'] ?? '#',
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        return $out;
    } catch (Throwable $ex) {
        error_log('latinfo_favorites_list_for_user: ' . $ex->getMessage());

        return [];
    }
}

/**
 * @return array{label:string,url:string}|null
 */
function latinfo_favorites_entity_public_meta(PDO $db, string $type, int $entityId, string $lang = 'hu'): ?array
{
    $type = latinfo_favorites_normalize_type($type) ?? '';
    if ($type === '' || $entityId <= 0) {
        return null;
    }
    $lang = $lang === 'en' ? 'en' : 'hu';

    try {
        return match ($type) {
            LATINFO_FAVORITE_TYPE_EVENT => (static function () use ($db, $entityId, $lang): ?array {
                $st = $db->prepare('
                    SELECT `event_name`, `event_slug`, `event_status`
                    FROM `events_calendar_events`
                    WHERE `id` = ?
                    LIMIT 1
                ');
                $st->execute([$entityId]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if (!$row || (string) ($row['event_status'] ?? '') !== events_public_post_status()) {
                    return null;
                }
                $slug = trim((string) ($row['event_slug'] ?? ''));
                if ($slug === '') {
                    return null;
                }

                return [
                    'label' => (string) ($row['event_name'] ?? ('#' . $entityId)),
                    'url' => events_public_event_page_url($slug, $lang),
                ];
            })(),
            LATINFO_FAVORITE_TYPE_ORGANIZER => (static function () use ($db, $entityId, $lang): ?array {
                $st = $db->prepare('SELECT `name` FROM `events_organizers` WHERE `id` = ? LIMIT 1');
                $st->execute([$entityId]);
                $name = $st->fetchColumn();
                if ($name === false) {
                    return null;
                }

                return [
                    'label' => (string) $name,
                    'url' => events_public_organizer_page_url($entityId, $lang),
                ];
            })(),
            LATINFO_FAVORITE_TYPE_VENUE => (static function () use ($db, $entityId, $lang): ?array {
                $st = $db->prepare('SELECT `name`, `slug` FROM `events_venues` WHERE `id` = ? LIMIT 1');
                $st->execute([$entityId]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    return null;
                }
                $slug = trim((string) ($row['slug'] ?? ''));
                if ($slug === '') {
                    return null;
                }

                return [
                    'label' => (string) ($row['name'] ?? ('#' . $entityId)),
                    'url' => events_public_venue_page_url($slug, $lang),
                ];
            })(),
            LATINFO_FAVORITE_TYPE_DJ => (static function () use ($db, $entityId, $lang): ?array {
                if (!events_tags_tables_available($db) || !events_public_tag_has_type_code($db, $entityId, 'dj')) {
                    return null;
                }
                $slugSelect = events_tags_slug_column_available($db) ? ', `slug`' : '';
                $st = $db->prepare('SELECT `name`' . $slugSelect . ' FROM `events_tags` WHERE `id` = ? LIMIT 1');
                $st->execute([$entityId]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    return null;
                }
                $slug = trim((string) ($row['slug'] ?? ''));
                $name = (string) ($row['name'] ?? ('#' . $entityId));
                if ($slug !== '') {
                    return ['label' => $name, 'url' => events_public_dj_page_url($slug, $lang)];
                }

                return ['label' => $name, 'url' => events_url('tag.php?id=') . $entityId];
            })(),
            LATINFO_FAVORITE_TYPE_ZENEKAR => (static function () use ($db, $entityId, $lang): ?array {
                if (!events_tags_tables_available($db) || !events_public_tag_has_type_code($db, $entityId, 'zenekar')) {
                    return null;
                }
                $st = $db->prepare('SELECT `name` FROM `events_tags` WHERE `id` = ? LIMIT 1');
                $st->execute([$entityId]);
                $name = $st->fetchColumn();
                if ($name === false) {
                    return null;
                }

                return [
                    'label' => (string) $name,
                    'url' => events_public_tag_page_url($entityId, $lang),
                ];
            })(),
            default => null,
        };
    } catch (Throwable $ex) {
        error_log('latinfo_favorites_entity_public_meta: ' . $ex->getMessage());

        return null;
    }
}

function latinfo_user_save_notification_email(PDO $db, int $userId, string $email): array
{
    latinfo_users_ensure_notification_email_column($db);
    $email = latinfo_users_normalize_email($email);
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Érvénytelen értesítési e-mail cím.'];
    }
    try {
        $stmt = $db->prepare('UPDATE `latinfo_users` SET `notification_email` = ? WHERE `id` = ?');
        $stmt->execute([$email !== '' ? $email : null, $userId]);

        return ['ok' => true, 'error' => ''];
    } catch (Throwable $ex) {
        error_log('latinfo_user_save_notification_email: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'Mentés sikertelen.'];
    }
}

function latinfo_user_notification_email(array $user): string
{
    $notify = trim((string) ($user['notification_email'] ?? ''));

    return $notify !== '' ? $notify : trim((string) ($user['email'] ?? ''));
}
