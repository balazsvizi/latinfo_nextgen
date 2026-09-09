<?php
declare(strict_types=1);

require_once __DIR__ . '/slug.php';
require_once __DIR__ . '/html_security.php';
require_once __DIR__ . '/event_request.php';

/**
 * DJ / címke profilmezők (events_tags nullable oszlopok).
 */

/** @return list<string> */
function events_tag_profile_column_names(): array {
    return [
        'description',
        'photo_url',
        'website_url',
        'facebook_url',
        'instagram_url',
        'soundcloud_url',
        'email',
        'phone',
    ];
}

function events_tags_profile_columns_available(PDO $db, bool $forceRefresh = false): bool {
    static $cached = null;
    if (!$forceRefresh && $cached !== null) {
        return $cached;
    }
    try {
        $st = $db->query("
            SELECT COUNT(*)
            FROM `information_schema`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = DATABASE()
              AND `TABLE_NAME` = 'events_tags'
              AND `COLUMN_NAME` IN ('description','photo_url','website_url','email')
        ");
        $cached = ((int) $st->fetchColumn()) >= 4;
    } catch (PDOException) {
        $cached = false;
    }

    return $cached;
}

/**
 * Profil oszlopok létrehozása futás közben (migráció nélkül).
 */
function events_tags_ensure_profile_columns(PDO $db): void {
    if (!events_tags_tables_available($db)) {
        return;
    }
    if (events_tags_profile_columns_available($db)) {
        return;
    }
    $alters = [
        'description' => 'ALTER TABLE `events_tags` ADD COLUMN `description` TEXT NULL DEFAULT NULL',
        'photo_url' => 'ALTER TABLE `events_tags` ADD COLUMN `photo_url` VARCHAR(2000) NULL DEFAULT NULL',
        'website_url' => 'ALTER TABLE `events_tags` ADD COLUMN `website_url` VARCHAR(2000) NULL DEFAULT NULL',
        'facebook_url' => 'ALTER TABLE `events_tags` ADD COLUMN `facebook_url` VARCHAR(2000) NULL DEFAULT NULL',
        'instagram_url' => 'ALTER TABLE `events_tags` ADD COLUMN `instagram_url` VARCHAR(2000) NULL DEFAULT NULL',
        'soundcloud_url' => 'ALTER TABLE `events_tags` ADD COLUMN `soundcloud_url` VARCHAR(2000) NULL DEFAULT NULL',
        'email' => 'ALTER TABLE `events_tags` ADD COLUMN `email` VARCHAR(255) NULL DEFAULT NULL',
        'phone' => 'ALTER TABLE `events_tags` ADD COLUMN `phone` VARCHAR(64) NULL DEFAULT NULL',
    ];
    foreach ($alters as $col => $sql) {
        try {
            $chk = $db->query("
                SELECT COUNT(*)
                FROM `information_schema`.`COLUMNS`
                WHERE `TABLE_SCHEMA` = DATABASE()
                  AND `TABLE_NAME` = 'events_tags'
                  AND `COLUMN_NAME` = " . $db->quote($col) . '
            ');
            if (((int) $chk->fetchColumn()) > 0) {
                continue;
            }
            $db->exec($sql);
        } catch (PDOException) {
            // párhuzamos / már létezik
        }
    }
    events_tags_profile_columns_available($db, true);
}

/**
 * @return array{
 *   description: string,
 *   photo_url: string,
 *   website_url: string,
 *   facebook_url: string,
 *   instagram_url: string,
 *   soundcloud_url: string,
 *   email: string,
 *   phone: string
 * }
 */
function events_tag_profile_empty(): array {
    return [
        'description' => '',
        'photo_url' => '',
        'website_url' => '',
        'facebook_url' => '',
        'instagram_url' => '',
        'soundcloud_url' => '',
        'email' => '',
        'phone' => '',
    ];
}

/**
 * @param array<string, mixed> $row
 * @return array{
 *   description: string,
 *   photo_url: string,
 *   website_url: string,
 *   facebook_url: string,
 *   instagram_url: string,
 *   soundcloud_url: string,
 *   email: string,
 *   phone: string
 * }
 */
function events_tag_profile_from_row(array $row): array {
    $out = events_tag_profile_empty();
    foreach (array_keys($out) as $key) {
        $out[$key] = trim((string) ($row[$key] ?? ''));
    }

    return $out;
}

/**
 * @return array{
 *   description: string,
 *   photo_url: string,
 *   website_url: string,
 *   facebook_url: string,
 *   instagram_url: string,
 *   soundcloud_url: string,
 *   email: string,
 *   phone: string
 * }
 */
function events_tag_profile_load(PDO $db, int $tagId): array {
    $empty = events_tag_profile_empty();
    if ($tagId <= 0 || !events_tags_tables_available($db)) {
        return $empty;
    }
    events_tags_ensure_profile_columns($db);
    if (!events_tags_profile_columns_available($db)) {
        return $empty;
    }
    $cols = '`' . implode('`, `', events_tag_profile_column_names()) . '`';
    $st = $db->prepare("SELECT {$cols} FROM `events_tags` WHERE `id` = ? LIMIT 1");
    $st->execute([$tagId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ? events_tag_profile_from_row($row) : $empty;
}

/**
 * POST → profil mezők (validációval).
 *
 * @return array{0: array<string, string>, 1: ?string} [profile, error]
 */
function events_tag_profile_from_post(): array {
    $profile = events_tag_profile_empty();
    $rawDesc = (string) ($_POST['tag_description'] ?? '');
    $profile['description'] = events_sanitize_html_fragment($rawDesc);

    [$photo, $photoErr] = events_normalize_featured_image_url((string) ($_POST['tag_photo_url'] ?? ''));
    if ($photoErr !== null) {
        return [$profile, $photoErr];
    }
    $profile['photo_url'] = $photo ?? '';

    $urlFields = [
        'website_url' => 'tag_website_url',
        'facebook_url' => 'tag_facebook_url',
        'instagram_url' => 'tag_instagram_url',
        'soundcloud_url' => 'tag_soundcloud_url',
    ];
    foreach ($urlFields as $key => $postKey) {
        [$url, $err] = events_normalize_safe_url((string) ($_POST[$postKey] ?? ''), true);
        if ($err !== null) {
            return [$profile, $err];
        }
        $profile[$key] = $url ?? '';
    }

    $email = trim((string) ($_POST['tag_email'] ?? ''));
    if ($email !== '') {
        if (strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return [$profile, 'Érvénytelen e-mail cím.'];
        }
        $profile['email'] = $email;
    }

    $phone = trim((string) ($_POST['tag_phone'] ?? ''));
    if ($phone !== '') {
        if (strlen($phone) > 64) {
            return [$profile, 'A telefonszám legfeljebb 64 karakter lehet.'];
        }
        if (!preg_match('/^[0-9+\s().\-\/]+$/u', $phone)) {
            return [$profile, 'A telefonszám érvénytelen karaktereket tartalmaz.'];
        }
        $profile['phone'] = $phone;
    }

    return [$profile, null];
}

/**
 * @param array{
 *   description: string,
 *   photo_url: string,
 *   website_url: string,
 *   facebook_url: string,
 *   instagram_url: string,
 *   soundcloud_url: string,
 *   email: string,
 *   phone: string
 * } $profile
 */
function events_tag_profile_save(PDO $db, int $tagId, array $profile): void {
    if ($tagId <= 0) {
        return;
    }
    events_tags_ensure_profile_columns($db);
    if (!events_tags_profile_columns_available($db)) {
        return;
    }
    $st = $db->prepare('
        UPDATE `events_tags` SET
            `description` = ?,
            `photo_url` = ?,
            `website_url` = ?,
            `facebook_url` = ?,
            `instagram_url` = ?,
            `soundcloud_url` = ?,
            `email` = ?,
            `phone` = ?
        WHERE `id` = ?
    ');
    $st->execute([
        $profile['description'] !== '' ? $profile['description'] : null,
        $profile['photo_url'] !== '' ? $profile['photo_url'] : null,
        $profile['website_url'] !== '' ? $profile['website_url'] : null,
        $profile['facebook_url'] !== '' ? $profile['facebook_url'] : null,
        $profile['instagram_url'] !== '' ? $profile['instagram_url'] : null,
        $profile['soundcloud_url'] !== '' ? $profile['soundcloud_url'] : null,
        $profile['email'] !== '' ? $profile['email'] : null,
        $profile['phone'] !== '' ? $profile['phone'] : null,
        $tagId,
    ]);
}

/**
 * SQL SELECT lista profil oszlopokhoz (üres string, ha nincs oszlop).
 */
function events_tag_profile_sql_select(PDO $db, string $alias = 't'): string {
    events_tags_ensure_profile_columns($db);
    if (!events_tags_profile_columns_available($db)) {
        $parts = [];
        foreach (events_tag_profile_column_names() as $col) {
            $parts[] = 'NULL AS `' . $col . '`';
        }

        return implode(', ', $parts);
    }
    $parts = [];
    foreach (events_tag_profile_column_names() as $col) {
        $parts[] = $alias . '.`' . $col . '`';
    }

    return implode(', ', $parts);
}

/**
 * Van-e megjeleníthető profil tartalom.
 *
 * @param array<string, string> $profile
 */
function events_tag_profile_has_public_content(array $profile): bool {
    foreach (['description', 'photo_url', 'website_url', 'facebook_url', 'instagram_url', 'soundcloud_url', 'email', 'phone'] as $key) {
        if (trim((string) ($profile[$key] ?? '')) !== '') {
            return true;
        }
    }

    return false;
}
