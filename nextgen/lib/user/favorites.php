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
        LATINFO_FAVORITE_TYPE_ZENEKAR => $isEn ? 'Artist' : 'Előadó',
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

    return latinfo_favorites_assemble_event_picker(
        $eventId,
        $eventName,
        $lang,
        $organizers,
        $venue,
        $djs,
        $bands,
        $activeSet,
        $eventState['count']
    );
}

/**
 * Kedvenc-választó összeállítása előre betöltött activeSet / count alapján (naptár batch).
 *
 * @param list<array{id?:int,name?:string}> $organizers
 * @param array{id?:int,label?:string}|null $venue
 * @param list<array{id?:int,name?:string}> $djs
 * @param list<array{id?:int,name?:string}> $bands
 * @param array<string, true> $activeSet
 * @return array{items:list<array{type:string,id:int,groupLabel:string,label:string,active:bool}>,active:bool,count:int}
 */
function latinfo_favorites_assemble_event_picker(
    int $eventId,
    string $eventName,
    string $lang,
    array $organizers,
    ?array $venue,
    array $djs,
    array $bands,
    array $activeSet,
    int $eventCount
): array {
    $lang = $lang === 'en' ? 'en' : 'hu';
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
    $venueId = (int) ($venue['id'] ?? 0);
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
        'count' => max(0, $eventCount),
    ];
}

/**
 * @param list<int> $entityIds
 * @return array<int, int> entity_id => count
 */
function latinfo_favorites_counts_for_type(PDO $db, string $type, array $entityIds): array
{
    $type = latinfo_favorites_normalize_type($type) ?? '';
    $entityIds = array_values(array_unique(array_filter(
        array_map(static fn ($id): int => (int) $id, $entityIds),
        static fn (int $id): bool => $id > 0
    )));
    if ($type === '' || $entityIds === [] || !latinfo_favorites_table_ready($db)) {
        return [];
    }
    $out = [];
    try {
        // Chunk, ha nagyon sok ID van (naptár hónap / lista).
        foreach (array_chunk($entityIds, 500) as $chunk) {
            $ph = implode(',', array_fill(0, count($chunk), '?'));
            $st = $db->prepare("
                SELECT `entity_id`, COUNT(*) AS `cnt`
                FROM `latinfo_favorites`
                WHERE `entity_type` = ? AND `entity_id` IN ({$ph})
                GROUP BY `entity_id`
            ");
            $st->execute(array_merge([$type], $chunk));
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $out[(int) $row['entity_id']] = (int) $row['cnt'];
            }
        }
    } catch (Throwable $ex) {
        error_log('latinfo_favorites_counts_for_type: ' . $ex->getMessage());
    }

    return $out;
}

/**
 * Van-e legalább egy kedvence az actor-nak (könnyű EXISTS).
 */
function latinfo_favorites_actor_has_any(PDO $db, ?string $actorKey = null): bool
{
    $actorKey = $actorKey ?? latinfo_favorites_current_actor_key();
    if ($actorKey === null || $actorKey === '' || !latinfo_favorites_table_ready($db)) {
        return false;
    }
    static $cache = [];
    if (array_key_exists($actorKey, $cache)) {
        return $cache[$actorKey];
    }
    try {
        $st = $db->prepare('SELECT 1 FROM `latinfo_favorites` WHERE `actor_key` = ? LIMIT 1');
        $st->execute([$actorKey]);
        $cache[$actorKey] = (bool) $st->fetchColumn();
    } catch (Throwable $ex) {
        error_log('latinfo_favorites_actor_has_any: ' . $ex->getMessage());
        $cache[$actorKey] = false;
    }

    return $cache[$actorKey];
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
            foreach (array_chunk($ids, 500) as $chunk) {
                $ph = implode(',', array_fill(0, count($chunk), '?'));
                $st = $db->prepare("
                    SELECT `entity_id`
                    FROM `latinfo_favorites`
                    WHERE `actor_key` = ? AND `entity_type` = ? AND `entity_id` IN ({$ph})
                ");
                $st->execute(array_merge([$actorKey, $type], $chunk));
                foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $eid) {
                    $out[$type . ':' . (int) $eid] = true;
                }
            }
        }
    } catch (Throwable $ex) {
        error_log('latinfo_favorites_active_set_for_actor: ' . $ex->getMessage());
    }

    return $out;
}

/**
 * Aktuális actor kedvencei típus szerint (eseményszűrőhöz).
 *
 * @return array{
 *   event: list<int>,
 *   organizer: list<int>,
 *   venue: list<int>,
 *   dj: list<int>,
 *   zenekar: list<int>
 * }
 */
function latinfo_favorites_ids_by_type_for_actor(PDO $db, ?string $actorKey = null): array
{
    $empty = [
        LATINFO_FAVORITE_TYPE_EVENT => [],
        LATINFO_FAVORITE_TYPE_ORGANIZER => [],
        LATINFO_FAVORITE_TYPE_VENUE => [],
        LATINFO_FAVORITE_TYPE_DJ => [],
        LATINFO_FAVORITE_TYPE_ZENEKAR => [],
    ];
    $actorKey = $actorKey ?? latinfo_favorites_current_actor_key();
    if ($actorKey === null || $actorKey === '' || !latinfo_favorites_table_ready($db)) {
        return $empty;
    }
    static $cache = [];
    if (isset($cache[$actorKey])) {
        return $cache[$actorKey];
    }
    $out = $empty;
    try {
        $st = $db->prepare('
            SELECT `entity_type`, `entity_id`
            FROM `latinfo_favorites`
            WHERE `actor_key` = ?
        ');
        $st->execute([$actorKey]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $type = latinfo_favorites_normalize_type((string) ($row['entity_type'] ?? ''));
            $id = (int) ($row['entity_id'] ?? 0);
            if ($type === null || $id <= 0 || !isset($out[$type])) {
                continue;
            }
            $out[$type][] = $id;
        }
        foreach ($out as $type => $ids) {
            $out[$type] = array_values(array_unique($ids));
        }
    } catch (Throwable $ex) {
        error_log('latinfo_favorites_ids_by_type_for_actor: ' . $ex->getMessage());
    }
    $cache[$actorKey] = $out;

    return $out;
}

/**
 * WHERE feltétel: az eseményben van az actor valamelyik kedvence
 * (maga az esemény, szervező, helyszín, DJ vagy előadó).
 *
 * @param array{
 *   event?: list<int>,
 *   organizer?: list<int>,
 *   venue?: list<int>,
 *   dj?: list<int>,
 *   zenekar?: list<int>
 * }|null $byTypePreloaded
 * @return array{sql: string, params: list<mixed>}
 */
function latinfo_favorites_event_contains_any_where(PDO $db, ?string $actorKey = null, ?array $byTypePreloaded = null): array
{
    $byType = $byTypePreloaded ?? latinfo_favorites_ids_by_type_for_actor($db, $actorKey);
    $parts = [];
    $params = [];

    $eventIds = $byType[LATINFO_FAVORITE_TYPE_EVENT] ?? [];
    if ($eventIds !== []) {
        $ph = implode(',', array_fill(0, count($eventIds), '?'));
        $parts[] = "e.`id` IN ({$ph})";
        array_push($params, ...$eventIds);
    }

    $venueIds = $byType[LATINFO_FAVORITE_TYPE_VENUE] ?? [];
    if ($venueIds !== []) {
        $ph = implode(',', array_fill(0, count($venueIds), '?'));
        $parts[] = "e.`venue_id` IN ({$ph})";
        array_push($params, ...$venueIds);
    }

    $orgIds = $byType[LATINFO_FAVORITE_TYPE_ORGANIZER] ?? [];
    if ($orgIds !== []) {
        $ph = implode(',', array_fill(0, count($orgIds), '?'));
        $parts[] = "EXISTS (
            SELECT 1 FROM `events_calendar_event_organizers` eo_fav
            WHERE eo_fav.`event_id` = e.`id` AND eo_fav.`organizer_id` IN ({$ph})
        )";
        array_push($params, ...$orgIds);
    }

    $tagIds = array_values(array_unique(array_merge(
        $byType[LATINFO_FAVORITE_TYPE_DJ] ?? [],
        $byType[LATINFO_FAVORITE_TYPE_ZENEKAR] ?? []
    )));
    if ($tagIds !== []) {
        $ph = implode(',', array_fill(0, count($tagIds), '?'));
        $parts[] = "EXISTS (
            SELECT 1 FROM `events_calendar_event_tags` et_fav
            WHERE et_fav.`event_id` = e.`id` AND et_fav.`tag_id` IN ({$ph})
        )";
        array_push($params, ...$tagIds);
    }

    if ($parts === []) {
        return ['sql' => '0 = 1', 'params' => []];
    }

    return [
        'sql' => '(' . implode(' OR ', $parts) . ')',
        'params' => $params,
    ];
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
    if ($done) {
        return true;
    }
    // Gyors út: ha a tábla már létezik, ne fusson CREATE / users schema minden requestnél.
    if (latinfo_favorites_table_ready($db)) {
        $done = true;

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
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    if (!events_slug_redirects_tables_available($db)) {
        $cached = true;

        return true;
    }
    try {
        $st = $db->prepare('SELECT `setting_value` FROM `events_app_settings` WHERE `setting_key` = ? LIMIT 1');
        $st->execute([EVENTS_APP_SETTING_PUBLIC_HEARTS]);
        $raw = $st->fetchColumn();
        if ($raw === false) {
            $cached = true;

            return true;
        }

        $cached = (string) $raw === '1';

        return $cached;
    } catch (Throwable) {
        $cached = true;

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

/**
 * Actor kulcsok megjelenítendő neve (u:id → felhasználónév, v:… → Vendég).
 *
 * @param list<string> $actorKeys
 * @return array<string, string>
 */
function latinfo_favorites_actor_labels(PDO $db, array $actorKeys): array
{
    $labels = [];
    $userIds = [];
    foreach ($actorKeys as $key) {
        $key = trim((string) $key);
        if ($key === '' || isset($labels[$key])) {
            continue;
        }
        if (str_starts_with($key, 'u:')) {
            $uid = (int) substr($key, 2);
            if ($uid > 0) {
                $userIds[$uid] = $key;
            } else {
                $labels[$key] = 'Felhasználó';
            }
            continue;
        }
        if (str_starts_with($key, 'v:')) {
            $labels[$key] = 'Vendég';
            continue;
        }
        $labels[$key] = 'Ismeretlen';
    }

    if ($userIds === []) {
        return $labels;
    }

    if (!function_exists('latinfo_users_table_ready')) {
        require_once __DIR__ . '/users.php';
    }
    if (!latinfo_users_table_ready($db)) {
        foreach ($userIds as $uid => $key) {
            $labels[$key] = 'Felhasználó #' . $uid;
        }

        return $labels;
    }

    try {
        $ids = array_keys($userIds);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = $db->prepare("
            SELECT `id`, `name`, `email`
            FROM `latinfo_users`
            WHERE `id` IN ({$ph})
        ");
        $st->execute($ids);
        $found = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $uid = (int) ($row['id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $email = trim((string) ($row['email'] ?? ''));
                $name = $email !== '' ? $email : ('Felhasználó #' . $uid);
            }
            $found[$uid] = $name;
        }
        foreach ($userIds as $uid => $key) {
            $labels[$key] = $found[$uid] ?? ('Felhasználó #' . $uid);
        }
    } catch (Throwable $ex) {
        error_log('latinfo_favorites_actor_labels: ' . $ex->getMessage());
        foreach ($userIds as $uid => $key) {
            if (!isset($labels[$key])) {
                $labels[$key] = 'Felhasználó #' . $uid;
            }
        }
    }

    return $labels;
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

/**
 * DJ / zenekar címke létezik-e a megadott típussal (AJAX-ban is betölti a tag libet).
 */
function latinfo_favorites_tag_entity_exists(PDO $db, int $tagId, string $typeCode): bool
{
    if ($tagId <= 0 || $typeCode === '') {
        return false;
    }
    if (!function_exists('events_tags_tables_available') || !events_tags_tables_available($db)) {
        return false;
    }
    if (!function_exists('events_load_tag_type_codes')) {
        $tagTypeLib = dirname(__DIR__, 2) . '/events/lib/tag_type.php';
        if (!is_file($tagTypeLib)) {
            return false;
        }
        require_once $tagTypeLib;
    }
    if (!function_exists('events_load_tag_type_codes')) {
        return false;
    }

    return in_array($typeCode, events_load_tag_type_codes($db, $tagId), true);
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
            LATINFO_FAVORITE_TYPE_DJ => latinfo_favorites_tag_entity_exists($db, $entityId, 'dj'),
            LATINFO_FAVORITE_TYPE_ZENEKAR => latinfo_favorites_tag_entity_exists($db, $entityId, 'zenekar'),
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
            $label = is_array($meta) ? trim((string) ($meta['label'] ?? '')) : '';
            $url = is_array($meta) ? trim((string) ($meta['url'] ?? '')) : '';
            if ($label === '') {
                $label = latinfo_favorites_entity_group_label($type, $lang);
                $label = $label !== '' ? ($label . ' #' . $eid) : ('#' . $eid);
            }
            $out[] = [
                'type' => $type,
                'id' => $eid,
                'label' => $label,
                'url' => $url !== '' ? $url : '#',
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

    // URL / név helper-ek: account és AJAX oldalakon is legyenek betöltve.
    $langLib = dirname(__DIR__, 2) . '/events/lib/event_public_lang.php';
    if (!function_exists('events_public_event_page_url') && is_file($langLib)) {
        require_once $langLib;
    }
    $djLib = dirname(__DIR__, 2) . '/events/lib/event_public_djs.php';
    if (!function_exists('events_public_tag_has_type_code') && is_file($djLib)) {
        require_once $djLib;
    }

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
                if (!$row) {
                    return null;
                }
                $label = trim((string) ($row['event_name'] ?? ''));
                if ($label === '') {
                    $label = 'Esemény #' . $entityId;
                }
                $slug = trim((string) ($row['event_slug'] ?? ''));
                $isPublic = function_exists('events_public_post_status')
                    && (string) ($row['event_status'] ?? '') === events_public_post_status();
                $url = ($isPublic && $slug !== '' && function_exists('events_public_event_page_url'))
                    ? events_public_event_page_url($slug, $lang)
                    : '#';

                return ['label' => $label, 'url' => $url];
            })(),
            LATINFO_FAVORITE_TYPE_ORGANIZER => (static function () use ($db, $entityId, $lang): ?array {
                $st = $db->prepare('SELECT `name` FROM `events_organizers` WHERE `id` = ? LIMIT 1');
                $st->execute([$entityId]);
                $name = $st->fetchColumn();
                if ($name === false) {
                    return null;
                }
                $label = trim((string) $name);
                if ($label === '') {
                    $label = 'Szervező #' . $entityId;
                }
                $url = function_exists('events_public_organizer_page_url')
                    ? events_public_organizer_page_url($entityId, $lang)
                    : '#';

                return ['label' => $label, 'url' => $url];
            })(),
            LATINFO_FAVORITE_TYPE_VENUE => (static function () use ($db, $entityId, $lang): ?array {
                $st = $db->prepare('SELECT `name`, `slug` FROM `events_venues` WHERE `id` = ? LIMIT 1');
                $st->execute([$entityId]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    return null;
                }
                $label = trim((string) ($row['name'] ?? ''));
                if ($label === '') {
                    $label = 'Helyszín #' . $entityId;
                }
                $slug = trim((string) ($row['slug'] ?? ''));
                $url = ($slug !== '' && function_exists('events_public_venue_page_url'))
                    ? events_public_venue_page_url($slug, $lang)
                    : '#';

                return ['label' => $label, 'url' => $url];
            })(),
            LATINFO_FAVORITE_TYPE_DJ => (static function () use ($db, $entityId, $lang): ?array {
                if (function_exists('events_tags_tables_available') && !events_tags_tables_available($db)) {
                    return null;
                }
                $slugSelect = (function_exists('events_tags_slug_column_available') && events_tags_slug_column_available($db))
                    ? ', `slug`'
                    : '';
                $st = $db->prepare('SELECT `name`' . $slugSelect . ' FROM `events_tags` WHERE `id` = ? LIMIT 1');
                $st->execute([$entityId]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    return null;
                }
                $label = trim((string) ($row['name'] ?? ''));
                if ($label === '') {
                    $label = 'DJ #' . $entityId;
                }
                $slug = trim((string) ($row['slug'] ?? ''));
                if ($slug !== '' && function_exists('events_public_dj_page_url')) {
                    return ['label' => $label, 'url' => events_public_dj_page_url($slug, $lang)];
                }
                if (function_exists('events_url')) {
                    return ['label' => $label, 'url' => events_url('tag.php?id=') . $entityId];
                }

                return ['label' => $label, 'url' => '#'];
            })(),
            LATINFO_FAVORITE_TYPE_ZENEKAR => (static function () use ($db, $entityId, $lang): ?array {
                if (function_exists('events_tags_tables_available') && !events_tags_tables_available($db)) {
                    return null;
                }
                $st = $db->prepare('SELECT `name` FROM `events_tags` WHERE `id` = ? LIMIT 1');
                $st->execute([$entityId]);
                $name = $st->fetchColumn();
                if ($name === false) {
                    return null;
                }
                $label = trim((string) $name);
                if ($label === '') {
                    $label = 'Zenekar #' . $entityId;
                }
                $url = function_exists('events_public_tag_page_url')
                    ? events_public_tag_page_url($entityId, $lang)
                    : '#';

                return ['label' => $label, 'url' => $url];
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
