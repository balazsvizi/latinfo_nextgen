<?php
declare(strict_types=1);

/**
 * Publikus Latinfo felhasználók (latinfo_users) – admin/partner identity-től elkülönítve.
 */

function latinfo_users_table_ready(PDO $db): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    try {
        $db->query('SELECT 1 FROM `latinfo_users` LIMIT 1');
        $cached = true;
    } catch (Throwable) {
        $cached = false;
    }

    return $cached;
}

function latinfo_user_oauth_table_ready(PDO $db): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    try {
        $db->query('SELECT 1 FROM `latinfo_user_oauth` LIMIT 1');
        $cached = true;
    } catch (Throwable) {
        $cached = false;
    }

    return $cached;
}

function latinfo_users_ensure_schema(PDO $db): bool
{
    static $done = false;
    if ($done) {
        return latinfo_users_table_ready($db) && latinfo_user_oauth_table_ready($db);
    }

    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `latinfo_users` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `email` VARCHAR(255) NOT NULL,
                `name` VARCHAR(160) NOT NULL DEFAULT '',
                `avatar_url` VARCHAR(500) NULL DEFAULT NULL,
                `password_hash` VARCHAR(255) NULL DEFAULT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `last_login_at` DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_latinfo_users_email` (`email`),
                KEY `idx_latinfo_users_active` (`is_active`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS `latinfo_user_oauth` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NOT NULL,
                `provider` VARCHAR(32) NOT NULL,
                `provider_user_id` VARCHAR(191) NOT NULL,
                `email` VARCHAR(255) NULL DEFAULT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_latinfo_oauth_provider` (`provider`, `provider_user_id`),
                KEY `idx_latinfo_oauth_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $done = true;

        return true;
    } catch (Throwable $ex) {
        error_log('latinfo_users_ensure_schema: ' . $ex->getMessage());

        return false;
    }
}

function latinfo_users_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

/**
 * @return array<string, mixed>|null
 */
function latinfo_user_by_id(PDO $db, int $id): ?array
{
    if ($id <= 0 || !latinfo_users_table_ready($db)) {
        return null;
    }
    try {
        $stmt = $db->prepare('SELECT * FROM `latinfo_users` WHERE `id` = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (Throwable $ex) {
        error_log('latinfo_user_by_id: ' . $ex->getMessage());

        return null;
    }
}

/**
 * @return array<string, mixed>|null
 */
function latinfo_user_by_email(PDO $db, string $email): ?array
{
    $email = latinfo_users_normalize_email($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !latinfo_users_table_ready($db)) {
        return null;
    }
    try {
        $stmt = $db->prepare('SELECT * FROM `latinfo_users` WHERE `email` = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (Throwable $ex) {
        error_log('latinfo_user_by_email: ' . $ex->getMessage());

        return null;
    }
}

/**
 * @return array<string, mixed>|null
 */
function latinfo_user_by_oauth(PDO $db, string $provider, string $providerUserId): ?array
{
    $provider = strtolower(trim($provider));
    $providerUserId = trim($providerUserId);
    if ($provider === '' || $providerUserId === '' || !latinfo_user_oauth_table_ready($db)) {
        return null;
    }
    try {
        $stmt = $db->prepare('
            SELECT u.*
            FROM `latinfo_user_oauth` o
            INNER JOIN `latinfo_users` u ON u.`id` = o.`user_id`
            WHERE o.`provider` = ? AND o.`provider_user_id` = ?
            LIMIT 1
        ');
        $stmt->execute([$provider, $providerUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (Throwable $ex) {
        error_log('latinfo_user_by_oauth: ' . $ex->getMessage());

        return null;
    }
}

/**
 * @return array{ok:bool,user:?array<string,mixed>,error:string}
 */
function latinfo_user_register(PDO $db, string $email, string $name, string $password): array
{
    if (!latinfo_users_ensure_schema($db)) {
        return ['ok' => false, 'user' => null, 'error' => 'A felhasználói rendszer még nincs beállítva.'];
    }

    $email = latinfo_users_normalize_email($email);
    $name = trim($name);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'user' => null, 'error' => 'Érvénytelen e-mail cím.'];
    }
    if ($name === '' || mb_strlen($name) > 160) {
        return ['ok' => false, 'user' => null, 'error' => 'Add meg a neved (max. 160 karakter).'];
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'user' => null, 'error' => 'A jelszó legalább 8 karakter legyen.'];
    }
    if (latinfo_user_by_email($db, $email) !== null) {
        return ['ok' => false, 'user' => null, 'error' => 'Ehhez az e-mail címhez már tartozik fiók.'];
    }

    try {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare('
            INSERT INTO `latinfo_users` (`email`, `name`, `password_hash`, `is_active`)
            VALUES (?, ?, ?, 1)
        ');
        $stmt->execute([$email, $name, $hash]);
        $id = (int) $db->lastInsertId();
        $user = latinfo_user_by_id($db, $id);

        return ['ok' => true, 'user' => $user, 'error' => ''];
    } catch (Throwable $ex) {
        error_log('latinfo_user_register: ' . $ex->getMessage());

        return ['ok' => false, 'user' => null, 'error' => 'A regisztráció sikertelen. Próbáld újra később.'];
    }
}

/**
 * @param array{provider:string,provider_user_id:string,email:?string,name:?string,avatar_url:?string} $identity
 * @return array{ok:bool,user:?array<string,mixed>,error:string,created:bool}
 */
function latinfo_user_upsert_from_oauth(PDO $db, array $identity): array
{
    if (!latinfo_users_ensure_schema($db)) {
        return ['ok' => false, 'user' => null, 'error' => 'A felhasználói rendszer még nincs beállítva.', 'created' => false];
    }

    $provider = strtolower(trim((string) ($identity['provider'] ?? '')));
    $providerUserId = trim((string) ($identity['provider_user_id'] ?? ''));
    $email = latinfo_users_normalize_email((string) ($identity['email'] ?? ''));
    $name = trim((string) ($identity['name'] ?? ''));
    $avatar = trim((string) ($identity['avatar_url'] ?? ''));

    if ($provider === '' || $providerUserId === '') {
        return ['ok' => false, 'user' => null, 'error' => 'Hiányzó OAuth azonosító.', 'created' => false];
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'user' => null, 'error' => 'A szolgáltató nem adott vissza érvényes e-mail címet. Engedélyezd az e-mail megosztását, majd próbáld újra.', 'created' => false];
    }
    if ($name === '') {
        $name = explode('@', $email)[0] ?: 'Felhasználó';
    }
    if (mb_strlen($name) > 160) {
        $name = mb_substr($name, 0, 160);
    }
    if ($avatar !== '' && !preg_match('#^https?://#i', $avatar)) {
        $avatar = '';
    }

    try {
        $existing = latinfo_user_by_oauth($db, $provider, $providerUserId);
        if ($existing !== null) {
            if (empty($existing['is_active'])) {
                return ['ok' => false, 'user' => null, 'error' => 'Ez a fiók le van tiltva.', 'created' => false];
            }
            $updates = [];
            $params = [];
            if ($name !== '' && (string) ($existing['name'] ?? '') === '') {
                $updates[] = '`name` = ?';
                $params[] = $name;
            }
            if ($avatar !== '' && empty($existing['avatar_url'])) {
                $updates[] = '`avatar_url` = ?';
                $params[] = $avatar;
            }
            if ($updates !== []) {
                $params[] = (int) $existing['id'];
                $db->prepare('UPDATE `latinfo_users` SET ' . implode(', ', $updates) . ' WHERE `id` = ?')->execute($params);
                $existing = latinfo_user_by_id($db, (int) $existing['id']) ?? $existing;
            }

            return ['ok' => true, 'user' => $existing, 'error' => '', 'created' => false];
        }

        $byEmail = latinfo_user_by_email($db, $email);
        if ($byEmail !== null) {
            if (empty($byEmail['is_active'])) {
                return ['ok' => false, 'user' => null, 'error' => 'Ez a fiók le van tiltva.', 'created' => false];
            }
            $userId = (int) $byEmail['id'];
            $link = $db->prepare('
                INSERT INTO `latinfo_user_oauth` (`user_id`, `provider`, `provider_user_id`, `email`)
                VALUES (?, ?, ?, ?)
            ');
            $link->execute([$userId, $provider, $providerUserId, $email]);
            if ($avatar !== '' && empty($byEmail['avatar_url'])) {
                $db->prepare('UPDATE `latinfo_users` SET `avatar_url` = ? WHERE `id` = ?')->execute([$avatar, $userId]);
            }
            $user = latinfo_user_by_id($db, $userId);

            return ['ok' => true, 'user' => $user, 'error' => '', 'created' => false];
        }

        $db->beginTransaction();
        $ins = $db->prepare('
            INSERT INTO `latinfo_users` (`email`, `name`, `avatar_url`, `password_hash`, `is_active`)
            VALUES (?, ?, ?, NULL, 1)
        ');
        $ins->execute([$email, $name, $avatar !== '' ? $avatar : null]);
        $userId = (int) $db->lastInsertId();
        $link = $db->prepare('
            INSERT INTO `latinfo_user_oauth` (`user_id`, `provider`, `provider_user_id`, `email`)
            VALUES (?, ?, ?, ?)
        ');
        $link->execute([$userId, $provider, $providerUserId, $email]);
        $db->commit();

        $user = latinfo_user_by_id($db, $userId);

        return ['ok' => true, 'user' => $user, 'error' => '', 'created' => true];
    } catch (Throwable $ex) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('latinfo_user_upsert_from_oauth: ' . $ex->getMessage());

        return ['ok' => false, 'user' => null, 'error' => 'A belépés sikertelen. Próbáld újra később.', 'created' => false];
    }
}

function latinfo_user_touch_login(PDO $db, int $userId): void
{
    if ($userId <= 0 || !latinfo_users_table_ready($db)) {
        return;
    }
    try {
        $db->prepare('UPDATE `latinfo_users` SET `last_login_at` = NOW() WHERE `id` = ?')->execute([$userId]);
    } catch (Throwable $ex) {
        error_log('latinfo_user_touch_login: ' . $ex->getMessage());
    }
}

/**
 * @return list<array<string, mixed>>
 */
function latinfo_users_list(PDO $db, ?string $search = null, string $order = 'created_at', string $dir = 'desc'): array
{
    if (!latinfo_users_table_ready($db)) {
        return [];
    }
    $allowedOrder = [
        'id' => '`id`',
        'name' => '`name`',
        'email' => '`email`',
        'is_active' => '`is_active`',
        'created_at' => '`created_at`',
        'last_login_at' => '`last_login_at`',
    ];
    $orderSql = $allowedOrder[$order] ?? '`created_at`';
    $dirSql = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';
    $where = '';
    $params = [];
    if ($search !== null && trim($search) !== '') {
        $where = 'WHERE `name` LIKE ? OR `email` LIKE ?';
        $p = '%' . trim($search) . '%';
        $params = [$p, $p];
    }
    try {
        $stmt = $db->prepare("
            SELECT u.*,
                (SELECT GROUP_CONCAT(o.`provider` ORDER BY o.`provider` SEPARATOR ',')
                 FROM `latinfo_user_oauth` o WHERE o.`user_id` = u.`id`) AS `oauth_providers`
            FROM `latinfo_users` u
            {$where}
            ORDER BY {$orderSql} {$dirSql}
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $ex) {
        error_log('latinfo_users_list: ' . $ex->getMessage());

        return [];
    }
}

function latinfo_user_set_active(PDO $db, int $userId, bool $active): bool
{
    if ($userId <= 0 || !latinfo_users_table_ready($db)) {
        return false;
    }
    try {
        $stmt = $db->prepare('UPDATE `latinfo_users` SET `is_active` = ? WHERE `id` = ?');
        $stmt->execute([$active ? 1 : 0, $userId]);

        return $stmt->rowCount() > 0;
    } catch (Throwable $ex) {
        error_log('latinfo_user_set_active: ' . $ex->getMessage());

        return false;
    }
}

/**
 * @return list<string>
 */
function latinfo_user_oauth_providers(PDO $db, int $userId): array
{
    if ($userId <= 0 || !latinfo_user_oauth_table_ready($db)) {
        return [];
    }
    try {
        $stmt = $db->prepare('SELECT `provider` FROM `latinfo_user_oauth` WHERE `user_id` = ? ORDER BY `provider`');
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_filter(array_map('strval', $rows ?: [])));
    } catch (Throwable) {
        return [];
    }
}
