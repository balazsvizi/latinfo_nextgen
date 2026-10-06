<?php
declare(strict_types=1);

/**
 * Publikus e-mail listák és feliratkozások (több téma).
 */

const LATINFO_MAILING_STATUS_ACTIVE = 'active';
const LATINFO_MAILING_STATUS_UNSUBSCRIBED = 'unsubscribed';

/**
 * Beépített lista-katalógus (seed).
 * soon_label = „hamarosan” jelvény (állítható); coming_soon csak legacy (ne zároljon).
 *
 * @return list<array{
 *   slug:string,
 *   parent:?string,
 *   name:string,
 *   description:string,
 *   sort:int,
 *   coming_soon:bool,
 *   soon_label:bool,
 *   is_group:bool
 * }>
 */
function latinfo_mailing_catalog(): array
{
    return [
        [
            'slug' => 'latinfo_news',
            'parent' => null,
            'name' => 'Latinfo.hu hírek',
            'description' => 'Általános hírek a Latinfo.hu-ról.',
            'sort' => 10,
            'coming_soon' => false,
            'soon_label' => false,
            'is_group' => false,
        ],
        [
            'slug' => 'parties',
            'parent' => null,
            'name' => 'Bulik',
            'description' => 'Események / bulik értesítései.',
            'sort' => 20,
            'coming_soon' => false,
            'soon_label' => false,
            'is_group' => true,
        ],
        [
            'slug' => 'parties_favorites',
            'parent' => 'parties',
            'name' => 'A te általad bejelölt kedvencek / preferenciák',
            'description' => 'Csak a kedvenceidhez kapcsolódó bulik.',
            'sort' => 21,
            'coming_soon' => false,
            'soon_label' => false,
            'is_group' => false,
        ],
        [
            'slug' => 'parties_all',
            'parent' => 'parties',
            'name' => 'Mind',
            'description' => 'Minden buli / esemény híre.',
            'sort' => 22,
            'coming_soon' => false,
            'soon_label' => false,
            'is_group' => false,
        ],
        [
            'slug' => 'workshops',
            'parent' => null,
            'name' => 'Hírek workshopokról',
            'description' => 'Workshop értesítések.',
            'sort' => 30,
            'coming_soon' => false,
            'soon_label' => false,
            'is_group' => true,
        ],
        [
            'slug' => 'workshops_yours',
            'parent' => 'workshops',
            'name' => 'A te általad bejelölt preferenciák csak',
            'description' => 'Preferencia-alapú szűrés.',
            'sort' => 31,
            'coming_soon' => false,
            'soon_label' => true,
            'is_group' => false,
        ],
        [
            'slug' => 'workshops_all',
            'parent' => 'workshops',
            'name' => 'Mind',
            'description' => 'Minden workshop hír.',
            'sort' => 32,
            'coming_soon' => false,
            'soon_label' => true,
            'is_group' => false,
        ],
        [
            'slug' => 'dance_schools',
            'parent' => null,
            'name' => 'Hírek tánciskolákról',
            'description' => 'Tánciskola értesítések.',
            'sort' => 40,
            'coming_soon' => false,
            'soon_label' => false,
            'is_group' => true,
        ],
        [
            'slug' => 'dance_schools_prefs',
            'parent' => 'dance_schools',
            'name' => 'A te általad bejelölt preferenciák csak',
            'description' => 'Preferencia-alapú szűrés.',
            'sort' => 41,
            'coming_soon' => false,
            'soon_label' => true,
            'is_group' => false,
        ],
        [
            'slug' => 'dance_schools_all',
            'parent' => 'dance_schools',
            'name' => 'Mind',
            'description' => 'Minden tánciskola hír.',
            'sort' => 42,
            'coming_soon' => false,
            'soon_label' => true,
            'is_group' => false,
        ],
        [
            'slug' => 'dance_weekends',
            'parent' => null,
            'name' => 'Táncos hétvégék / táborok',
            'description' => 'Hétvégék és táborok értesítései.',
            'sort' => 50,
            'coming_soon' => false,
            'soon_label' => false,
            'is_group' => true,
        ],
        [
            'slug' => 'dance_weekends_prefs',
            'parent' => 'dance_weekends',
            'name' => 'A te általad bejelölt preferenciák csak',
            'description' => 'Preferencia-alapú szűrés.',
            'sort' => 51,
            'coming_soon' => false,
            'soon_label' => true,
            'is_group' => false,
        ],
        [
            'slug' => 'dance_weekends_all',
            'parent' => 'dance_weekends',
            'name' => 'Mind',
            'description' => 'Minden hétvége / tábor hír.',
            'sort' => 52,
            'coming_soon' => false,
            'soon_label' => true,
            'is_group' => false,
        ],
    ];
}

/**
 * Mind (all) slug → preferenciás slug. Mind bekapcsolása kikapcsolja a preferenciát.
 *
 * @return array<string, string>
 */
function latinfo_mailing_exclusive_pairs(): array
{
    return [
        'parties_all' => 'parties_favorites',
        'workshops_all' => 'workshops_yours',
        'dance_schools_all' => 'dance_schools_prefs',
        'dance_weekends_all' => 'dance_weekends_prefs',
    ];
}

/** Signup „értesítések” checkbox alapértelmezett listái. */
function latinfo_mailing_default_signup_slugs(): array
{
    return ['latinfo_news', 'parties_favorites'];
}

function latinfo_mailing_lists_table_ready(PDO $db, bool $reset = false): bool
{
    static $cached = null;
    if ($reset) {
        $cached = null;
    }
    if ($cached !== null) {
        return $cached;
    }
    try {
        $db->query('SELECT 1 FROM `latinfo_mailing_lists` LIMIT 1');
        $cached = true;
    } catch (Throwable) {
        $cached = false;
    }

    return $cached;
}

function latinfo_mailing_subscriptions_table_ready(PDO $db, bool $reset = false): bool
{
    static $cached = null;
    if ($reset) {
        $cached = null;
    }
    if ($cached !== null) {
        return $cached;
    }
    try {
        $db->query('SELECT 1 FROM `latinfo_mailing_subscriptions` LIMIT 1');
        $cached = true;
    } catch (Throwable) {
        $cached = false;
    }

    return $cached;
}

function latinfo_mailing_ensure_schema(PDO $db): bool
{
    static $done = false;
    static $seeded = false;

    $finish = static function (PDO $db) use (&$seeded): bool {
        if (!$seeded) {
            latinfo_mailing_seed_lists($db);
            $seeded = true;
        }

        return true;
    };

    if ($done) {
        if (latinfo_mailing_lists_table_ready($db) && latinfo_mailing_subscriptions_table_ready($db)) {
            return $finish($db);
        }

        return false;
    }

    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `latinfo_mailing_lists` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `slug` VARCHAR(64) NOT NULL,
                `parent_id` INT UNSIGNED NULL DEFAULT NULL,
                `name` VARCHAR(160) NOT NULL,
                `description` VARCHAR(400) NOT NULL DEFAULT '',
                `sort_order` INT NOT NULL DEFAULT 0,
                `is_group` TINYINT(1) NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `is_coming_soon` TINYINT(1) NOT NULL DEFAULT 0,
                `show_soon_label` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_latinfo_mailing_slug` (`slug`),
                KEY `idx_latinfo_mailing_parent` (`parent_id`, `sort_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS `latinfo_mailing_subscriptions` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `list_id` INT UNSIGNED NOT NULL,
                `user_id` INT UNSIGNED NOT NULL,
                `email` VARCHAR(255) NOT NULL DEFAULT '',
                `status` VARCHAR(24) NOT NULL DEFAULT 'active',
                `unsubscribe_token` CHAR(64) NOT NULL,
                `source` VARCHAR(64) NOT NULL DEFAULT '',
                `subscribed_at` DATETIME NULL DEFAULT NULL,
                `unsubscribed_at` DATETIME NULL DEFAULT NULL,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_latinfo_mail_sub_user_list` (`user_id`, `list_id`),
                UNIQUE KEY `uq_latinfo_mail_sub_token` (`unsubscribe_token`),
                KEY `idx_latinfo_mail_sub_list_status` (`list_id`, `status`),
                KEY `idx_latinfo_mail_sub_email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        latinfo_mailing_lists_table_ready($db, true);
        latinfo_mailing_subscriptions_table_ready($db, true);
        try {
            $db->query('SELECT `show_soon_label` FROM `latinfo_mailing_lists` LIMIT 1');
        } catch (Throwable) {
            $db->exec('ALTER TABLE `latinfo_mailing_lists` ADD COLUMN `show_soon_label` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_coming_soon`');
        }
        $done = true;

        return $finish($db);
    } catch (Throwable $ex) {
        error_log('latinfo_mailing_ensure_schema: ' . $ex->getMessage());

        return false;
    }
}

function latinfo_mailing_seed_lists(PDO $db): void
{
    try {
        $db->query('SELECT 1 FROM `latinfo_mailing_lists` LIMIT 1');
    } catch (Throwable) {
        return;
    }

    $bySlug = [];
    try {
        $rows = $db->query('SELECT `id`, `slug` FROM `latinfo_mailing_lists`')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $bySlug[(string) $row['slug']] = (int) $row['id'];
        }
    } catch (Throwable) {
        return;
    }

    $upsert = $db->prepare('
        INSERT INTO `latinfo_mailing_lists`
            (`slug`, `parent_id`, `name`, `description`, `sort_order`, `is_group`, `is_active`, `is_coming_soon`, `show_soon_label`)
        VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)
        ON DUPLICATE KEY UPDATE
            `parent_id` = VALUES(`parent_id`),
            `name` = VALUES(`name`),
            `description` = VALUES(`description`),
            `sort_order` = VALUES(`sort_order`),
            `is_group` = VALUES(`is_group`),
            `is_coming_soon` = VALUES(`is_coming_soon`),
            `show_soon_label` = VALUES(`show_soon_label`),
            `is_active` = 1
    ');

    $catalog = latinfo_mailing_catalog();
    usort($catalog, static function (array $a, array $b): int {
        $ap = $a['parent'] === null ? 0 : 1;
        $bp = $b['parent'] === null ? 0 : 1;
        if ($ap !== $bp) {
            return $ap <=> $bp;
        }

        return ((int) $a['sort']) <=> ((int) $b['sort']);
    });

    foreach ($catalog as $item) {
        $parentId = null;
        $parentSlug = $item['parent'];
        if ($parentSlug !== null && $parentSlug !== '') {
            if (!isset($bySlug[$parentSlug])) {
                continue;
            }
            $parentId = $bySlug[$parentSlug];
        }
        $upsert->execute([
            $item['slug'],
            $parentId,
            $item['name'],
            $item['description'],
            (int) $item['sort'],
            !empty($item['is_group']) ? 1 : 0,
            !empty($item['coming_soon']) ? 1 : 0,
            !empty($item['soon_label']) ? 1 : 0,
        ]);
        if (!isset($bySlug[$item['slug']])) {
            $newId = (int) $db->lastInsertId();
            if ($newId <= 0) {
                $stmt = $db->prepare('SELECT `id` FROM `latinfo_mailing_lists` WHERE `slug` = ? LIMIT 1');
                $stmt->execute([$item['slug']]);
                $newId = (int) $stmt->fetchColumn();
            }
            if ($newId > 0) {
                $bySlug[$item['slug']] = $newId;
            }
        }
    }
}

/**
 * @return list<array<string, mixed>>
 */
function latinfo_mailing_lists_all(PDO $db): array
{
    if (!latinfo_mailing_ensure_schema($db)) {
        return [];
    }
    try {
        $stmt = $db->query('
            SELECT *
            FROM `latinfo_mailing_lists`
            WHERE `is_active` = 1
            ORDER BY `sort_order` ASC, `id` ASC
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $ex) {
        error_log('latinfo_mailing_lists_all: ' . $ex->getMessage());

        return [];
    }
}

/**
 * @return array<string, mixed>|null
 */
function latinfo_mailing_list_by_slug(PDO $db, string $slug): ?array
{
    $slug = trim($slug);
    if ($slug === '' || !latinfo_mailing_ensure_schema($db)) {
        return null;
    }
    try {
        $stmt = $db->prepare('SELECT * FROM `latinfo_mailing_lists` WHERE `slug` = ? LIMIT 1');
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (Throwable) {
        return null;
    }
}

/**
 * Hierarchia a fiók UI-hoz: gyökér csoportok + gyerek levelek; standalone levelek külön.
 *
 * @return list<array{list:array<string,mixed>,children:list<array<string,mixed>>}>
 */
function latinfo_mailing_lists_tree(PDO $db): array
{
    $all = latinfo_mailing_lists_all($db);
    if ($all === []) {
        return [];
    }
    $byId = [];
    foreach ($all as $row) {
        $byId[(int) $row['id']] = $row;
    }
    $tree = [];
    foreach ($all as $row) {
        $parentId = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
        if ($parentId !== null) {
            continue;
        }
        $children = [];
        $id = (int) $row['id'];
        foreach ($all as $child) {
            if ((int) ($child['parent_id'] ?? 0) === $id) {
                $children[] = $child;
            }
        }
        $tree[] = [
            'list' => $row,
            'children' => $children,
        ];
    }

    return $tree;
}

/**
 * @return array<int, bool> list_id => subscribed
 */
function latinfo_mailing_user_active_map(PDO $db, int $userId): array
{
    if ($userId <= 0 || !latinfo_mailing_subscriptions_table_ready($db)) {
        if ($userId > 0) {
            latinfo_mailing_ensure_schema($db);
        }
        if ($userId <= 0 || !latinfo_mailing_subscriptions_table_ready($db)) {
            return [];
        }
    }
    try {
        $stmt = $db->prepare('
            SELECT `list_id`
            FROM `latinfo_mailing_subscriptions`
            WHERE `user_id` = ? AND `status` = ?
        ');
        $stmt->execute([$userId, LATINFO_MAILING_STATUS_ACTIVE]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $listId) {
            $map[(int) $listId] = true;
        }

        return $map;
    } catch (Throwable $ex) {
        error_log('latinfo_mailing_user_active_map: ' . $ex->getMessage());

        return [];
    }
}

function latinfo_mailing_new_token(): string
{
    return bin2hex(random_bytes(32));
}

/**
 * @return array{ok:bool,error:string}
 */
function latinfo_mailing_set_subscription(
    PDO $db,
    int $userId,
    int $listId,
    bool $subscribe,
    string $email = '',
    string $source = 'account'
): array {
    if ($userId <= 0 || $listId <= 0) {
        return ['ok' => false, 'error' => 'Érvénytelen kérés.'];
    }
    if (!latinfo_mailing_ensure_schema($db)) {
        return ['ok' => false, 'error' => 'A listák még nincsenek beállítva.'];
    }

    try {
        $stmt = $db->prepare('SELECT * FROM `latinfo_mailing_lists` WHERE `id` = ? LIMIT 1');
        $stmt->execute([$listId]);
        $list = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$list || empty($list['is_active'])) {
            return ['ok' => false, 'error' => 'Ismeretlen lista.'];
        }
        if (!empty($list['is_group'])) {
            return ['ok' => false, 'error' => 'Erre a csoportra nem lehet közvetlenül feliratkozni.'];
        }

        $email = latinfo_users_normalize_email($email);
        if ($email === '' && function_exists('latinfo_user_by_id')) {
            $user = latinfo_user_by_id($db, $userId);
            $email = latinfo_users_normalize_email((string) ($user['email'] ?? ''));
            if ($email === '' && !empty($user['notification_email'])) {
                $email = latinfo_users_normalize_email((string) $user['notification_email']);
            }
        }

        $existing = $db->prepare('
            SELECT * FROM `latinfo_mailing_subscriptions`
            WHERE `user_id` = ? AND `list_id` = ?
            LIMIT 1
        ');
        $existing->execute([$userId, $listId]);
        $row = $existing->fetch(PDO::FETCH_ASSOC);

        if ($subscribe) {
            if ($row) {
                $upd = $db->prepare('
                    UPDATE `latinfo_mailing_subscriptions`
                    SET `status` = ?, `email` = ?, `source` = ?,
                        `subscribed_at` = COALESCE(`subscribed_at`, NOW()),
                        `unsubscribed_at` = NULL
                    WHERE `id` = ?
                ');
                $upd->execute([
                    LATINFO_MAILING_STATUS_ACTIVE,
                    $email,
                    $source,
                    (int) $row['id'],
                ]);
            } else {
                $ins = $db->prepare('
                    INSERT INTO `latinfo_mailing_subscriptions`
                        (`list_id`, `user_id`, `email`, `status`, `unsubscribe_token`, `source`, `subscribed_at`)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ');
                $ins->execute([
                    $listId,
                    $userId,
                    $email,
                    LATINFO_MAILING_STATUS_ACTIVE,
                    latinfo_mailing_new_token(),
                    $source,
                ]);
            }
        } else {
            if ($row) {
                $upd = $db->prepare('
                    UPDATE `latinfo_mailing_subscriptions`
                    SET `status` = ?, `unsubscribed_at` = NOW()
                    WHERE `id` = ?
                ');
                $upd->execute([LATINFO_MAILING_STATUS_UNSUBSCRIBED, (int) $row['id']]);
            }
        }

        return ['ok' => true, 'error' => ''];
    } catch (Throwable $ex) {
        error_log('latinfo_mailing_set_subscription: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'A mentés sikertelen. Próbáld újra később.'];
    }
}

/**
 * Több lista egyszerre (fiók form).
 *
 * @param array<int, bool> $wantedByListId list_id => checked
 * @return array{ok:bool,error:string,changed:int}
 */
function latinfo_mailing_sync_user_lists(PDO $db, int $userId, array $wantedByListId, string $source = 'account'): array
{
    if ($userId <= 0) {
        return ['ok' => false, 'error' => 'Érvénytelen felhasználó.', 'changed' => 0];
    }
    if (!latinfo_mailing_ensure_schema($db)) {
        return ['ok' => false, 'error' => 'A listák még nincsenek beállítva.', 'changed' => 0];
    }

    $tree = latinfo_mailing_lists_tree($db);
    $subscribable = [];
    foreach ($tree as $node) {
        $list = $node['list'];
        if (empty($list['is_group'])) {
            $subscribable[(int) $list['id']] = $list;
        }
        foreach ($node['children'] as $child) {
            $subscribable[(int) $child['id']] = $child;
        }
    }

    $current = latinfo_mailing_user_active_map($db, $userId);
    $changed = 0;
    $email = '';
    if (function_exists('latinfo_user_by_id')) {
        $user = latinfo_user_by_id($db, $userId);
        $email = latinfo_users_normalize_email((string) ($user['notification_email'] ?? ''));
        if ($email === '') {
            $email = latinfo_users_normalize_email((string) ($user['email'] ?? ''));
        }
    }

    // slug → id a kizáró párokhoz
    $slugToId = [];
    foreach ($subscribable as $listId => $list) {
        $slug = (string) ($list['slug'] ?? '');
        if ($slug !== '') {
            $slugToId[$slug] = (int) $listId;
        }
    }
    foreach (latinfo_mailing_exclusive_pairs() as $allSlug => $prefsSlug) {
        $allId = $slugToId[$allSlug] ?? 0;
        $prefsId = $slugToId[$prefsSlug] ?? 0;
        if ($allId > 0 && !empty($wantedByListId[$allId]) && $prefsId > 0) {
            unset($wantedByListId[$prefsId]);
        }
    }

    foreach ($subscribable as $listId => $list) {
        $want = !empty($wantedByListId[$listId]);
        $have = !empty($current[$listId]);
        if ($want === $have) {
            continue;
        }
        $res = latinfo_mailing_set_subscription($db, $userId, $listId, $want, $email, $source);
        if (!$res['ok']) {
            return ['ok' => false, 'error' => $res['error'], 'changed' => $changed];
        }
        $changed++;
    }

    return ['ok' => true, 'error' => '', 'changed' => $changed];
}

/**
 * Signup / OAuth: alapértelmezett listák felvétele.
 *
 * @param list<string>|null $slugs
 */
function latinfo_mailing_subscribe_defaults(PDO $db, int $userId, ?array $slugs = null, string $source = 'signup'): bool
{
    if ($userId <= 0 || !latinfo_mailing_ensure_schema($db)) {
        return false;
    }
    $slugs = $slugs ?? latinfo_mailing_default_signup_slugs();
    $ok = true;
    foreach ($slugs as $slug) {
        $list = latinfo_mailing_list_by_slug($db, (string) $slug);
        if ($list === null || !empty($list['is_group'])) {
            continue;
        }
        $res = latinfo_mailing_set_subscription($db, $userId, (int) $list['id'], true, '', $source);
        if (!$res['ok']) {
            $ok = false;
        }
    }

    return $ok;
}

/**
 * Van-e bármilyen (aktív vagy leiratkozott) feliratkozási sor a usernél.
 */
function latinfo_mailing_user_has_any_row(PDO $db, int $userId): bool
{
    if ($userId <= 0 || !latinfo_mailing_subscriptions_table_ready($db)) {
        return false;
    }
    try {
        $stmt = $db->prepare('SELECT 1 FROM `latinfo_mailing_subscriptions` WHERE `user_id` = ? LIMIT 1');
        $stmt->execute([$userId]);

        return (bool) $stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

/**
 * @return list<array<string, mixed>>
 */
function latinfo_mailing_user_subscriptions(PDO $db, int $userId): array
{
    if ($userId <= 0 || !latinfo_mailing_ensure_schema($db)) {
        return [];
    }
    try {
        $stmt = $db->prepare('
            SELECT s.*, l.`slug`, l.`name`, l.`parent_id`
            FROM `latinfo_mailing_subscriptions` s
            INNER JOIN `latinfo_mailing_lists` l ON l.`id` = s.`list_id`
            WHERE s.`user_id` = ?
            ORDER BY l.`sort_order` ASC, s.`id` DESC
        ');
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $ex) {
        error_log('latinfo_mailing_user_subscriptions: ' . $ex->getMessage());

        return [];
    }
}
