<?php
declare(strict_types=1);

/**
 * Publikus user hozzájárulások (adatkezelés, hírlevél, …).
 */

const LATINFO_CONSENT_PRIVACY = 'privacy_policy';
const LATINFO_CONSENT_NEWSLETTER = 'newsletter_general';

/** Aktuális adatkezelési tájékoztató verzió (semver-szerű). */
const LATINFO_PRIVACY_POLICY_VERSION = '1.0';

/** Hatálybalépés dátuma (Y-m-d). */
const LATINFO_PRIVACY_POLICY_EFFECTIVE = '2026-04-06';

function latinfo_privacy_policy_url(): string
{
    return site_url('adatkezeles/');
}

function latinfo_privacy_policy_version(): string
{
    return LATINFO_PRIVACY_POLICY_VERSION;
}

function latinfo_privacy_controller_name(): string
{
    $name = trim((string) (defined('PRIVACY_CONTROLLER_NAME') ? PRIVACY_CONTROLLER_NAME : ''));

    return $name !== '' ? $name : (defined('SITE_NAME') ? (string) SITE_NAME : 'Latinfo.hu');
}

function latinfo_privacy_controller_email(): string
{
    $email = trim((string) (defined('PRIVACY_CONTROLLER_EMAIL') ? PRIVACY_CONTROLLER_EMAIL : ''));

    return $email !== '' ? $email : 'adatvedelem@latinfo.hu';
}

function latinfo_privacy_controller_address(): string
{
    return trim((string) (defined('PRIVACY_CONTROLLER_ADDRESS') ? PRIVACY_CONTROLLER_ADDRESS : ''));
}

function latinfo_user_consents_table_ready(PDO $db): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    try {
        $db->query('SELECT 1 FROM `latinfo_user_consents` LIMIT 1');
        $cached = true;
    } catch (Throwable) {
        $cached = false;
    }

    return $cached;
}

function latinfo_user_consents_ensure_schema(PDO $db): bool
{
    static $done = false;
    if ($done) {
        return latinfo_user_consents_table_ready($db);
    }

    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `latinfo_user_consents` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` INT UNSIGNED NOT NULL,
                `consent_type` VARCHAR(64) NOT NULL,
                `version` VARCHAR(32) NOT NULL DEFAULT '',
                `granted` TINYINT(1) NOT NULL DEFAULT 1,
                `source` VARCHAR(64) NOT NULL DEFAULT '',
                `ip_hash` CHAR(64) NULL DEFAULT NULL,
                `user_agent` VARCHAR(255) NULL DEFAULT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_latinfo_consents_user_type` (`user_id`, `consent_type`, `created_at`),
                KEY `idx_latinfo_consents_type_granted` (`consent_type`, `granted`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $done = true;

        return true;
    } catch (Throwable $ex) {
        error_log('latinfo_user_consents_ensure_schema: ' . $ex->getMessage());

        return false;
    }
}

/**
 * @return array{source?:string,ip?:string,user_agent?:string}
 */
function latinfo_user_consent_request_meta(): array
{
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    $ua = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if (mb_strlen($ua) > 255) {
        $ua = mb_substr($ua, 0, 255);
    }

    return [
        'ip' => $ip,
        'user_agent' => $ua,
    ];
}

function latinfo_user_consent_hash_ip(string $ip): ?string
{
    $ip = trim($ip);
    if ($ip === '') {
        return null;
    }

    return hash('sha256', $ip);
}

/**
 * @param array{source?:string,ip?:string,user_agent?:string} $meta
 */
function latinfo_user_consent_record(
    PDO $db,
    int $userId,
    string $consentType,
    bool $granted,
    string $version = '',
    array $meta = []
): bool {
    if ($userId <= 0 || $consentType === '') {
        return false;
    }
    if (!latinfo_user_consents_ensure_schema($db)) {
        return false;
    }

    $consentType = strtolower(trim($consentType));
    $version = trim($version);
    if ($consentType === LATINFO_CONSENT_PRIVACY && $version === '') {
        $version = latinfo_privacy_policy_version();
    }
    $source = trim((string) ($meta['source'] ?? ''));
    if (mb_strlen($source) > 64) {
        $source = mb_substr($source, 0, 64);
    }
    $ua = trim((string) ($meta['user_agent'] ?? ''));
    if (mb_strlen($ua) > 255) {
        $ua = mb_substr($ua, 0, 255);
    }
    $ipHash = latinfo_user_consent_hash_ip((string) ($meta['ip'] ?? ''));

    try {
        $stmt = $db->prepare('
            INSERT INTO `latinfo_user_consents`
                (`user_id`, `consent_type`, `version`, `granted`, `source`, `ip_hash`, `user_agent`)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $userId,
            $consentType,
            $version,
            $granted ? 1 : 0,
            $source,
            $ipHash,
            $ua !== '' ? $ua : null,
        ]);

        return true;
    } catch (Throwable $ex) {
        error_log('latinfo_user_consent_record: ' . $ex->getMessage());

        return false;
    }
}

/**
 * @return array<string, mixed>|null
 */
function latinfo_user_consent_latest(PDO $db, int $userId, string $consentType): ?array
{
    if ($userId <= 0 || $consentType === '' || !latinfo_user_consents_table_ready($db)) {
        return null;
    }
    try {
        $stmt = $db->prepare('
            SELECT *
            FROM `latinfo_user_consents`
            WHERE `user_id` = ? AND `consent_type` = ?
            ORDER BY `id` DESC
            LIMIT 1
        ');
        $stmt->execute([$userId, strtolower(trim($consentType))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (Throwable $ex) {
        error_log('latinfo_user_consent_latest: ' . $ex->getMessage());

        return null;
    }
}

function latinfo_user_consent_is_granted(PDO $db, int $userId, string $consentType, ?string $requiredVersion = null): bool
{
    $row = latinfo_user_consent_latest($db, $userId, $consentType);
    if ($row === null || empty($row['granted'])) {
        return false;
    }
    if ($requiredVersion !== null && $requiredVersion !== '') {
        return (string) ($row['version'] ?? '') === $requiredVersion;
    }

    return true;
}

function latinfo_user_has_current_privacy_consent(PDO $db, int $userId): bool
{
    return latinfo_user_consent_is_granted(
        $db,
        $userId,
        LATINFO_CONSENT_PRIVACY,
        latinfo_privacy_policy_version()
    );
}

/**
 * @return list<array<string, mixed>>
 */
function latinfo_user_consents_list(PDO $db, int $userId, int $limit = 50): array
{
    if ($userId <= 0 || !latinfo_user_consents_table_ready($db)) {
        return [];
    }
    $limit = max(1, min(200, $limit));
    try {
        $stmt = $db->prepare(
            'SELECT *
            FROM `latinfo_user_consents`
            WHERE `user_id` = ?
            ORDER BY `id` DESC
            LIMIT ' . (int) $limit
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $ex) {
        error_log('latinfo_user_consents_list: ' . $ex->getMessage());

        return [];
    }
}

function latinfo_user_consent_type_label(string $type): string
{
    return match (strtolower(trim($type))) {
        LATINFO_CONSENT_PRIVACY => 'Adatkezelési tájékoztató',
        LATINFO_CONSENT_NEWSLETTER => 'Általános hírlevél / értesítések',
        default => $type,
    };
}
