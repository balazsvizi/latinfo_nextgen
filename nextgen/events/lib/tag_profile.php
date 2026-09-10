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
        'logo_url',
        'website_url',
        'facebook_url',
        'instagram_url',
        'soundcloud_url',
        'youtube_url',
        'mixcloud_url',
        'email',
        'email_is_private',
        'phone',
        'phone_is_private',
        'admin_notes',
    ];
}

function events_tags_profile_columns_available(PDO $db, bool $forceRefresh = false): bool {
    return count(events_tag_profile_present_columns($db, $forceRefresh)) >= 4;
}

/**
 * Ténylegesen létező profil-oszlopok (wanted sorrendben).
 *
 * @return list<string>
 */
function events_tag_profile_present_columns(PDO $db, bool $forceRefresh = false): array {
    static $cached = null;
    if (!$forceRefresh && is_array($cached)) {
        return $cached;
    }
    $wanted = events_tag_profile_column_names();
    try {
        $placeholders = implode(',', array_fill(0, count($wanted), '?'));
        $st = $db->prepare("
            SELECT `COLUMN_NAME`
            FROM `information_schema`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = DATABASE()
              AND `TABLE_NAME` = 'events_tags'
              AND `COLUMN_NAME` IN ({$placeholders})
        ");
        $st->execute($wanted);
        $found = [];
        while (($name = $st->fetchColumn()) !== false) {
            $found[(string) $name] = true;
        }
        $cached = [];
        foreach ($wanted as $col) {
            if (isset($found[$col])) {
                $cached[] = $col;
            }
        }
    } catch (PDOException) {
        $cached = [];
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
    $alters = [
        'description' => 'ALTER TABLE `events_tags` ADD COLUMN `description` TEXT NULL DEFAULT NULL',
        'photo_url' => 'ALTER TABLE `events_tags` ADD COLUMN `photo_url` VARCHAR(2000) NULL DEFAULT NULL',
        'logo_url' => 'ALTER TABLE `events_tags` ADD COLUMN `logo_url` VARCHAR(2000) NULL DEFAULT NULL',
        'website_url' => 'ALTER TABLE `events_tags` ADD COLUMN `website_url` VARCHAR(2000) NULL DEFAULT NULL',
        'facebook_url' => 'ALTER TABLE `events_tags` ADD COLUMN `facebook_url` VARCHAR(2000) NULL DEFAULT NULL',
        'instagram_url' => 'ALTER TABLE `events_tags` ADD COLUMN `instagram_url` VARCHAR(2000) NULL DEFAULT NULL',
        'soundcloud_url' => 'ALTER TABLE `events_tags` ADD COLUMN `soundcloud_url` VARCHAR(2000) NULL DEFAULT NULL',
        'youtube_url' => 'ALTER TABLE `events_tags` ADD COLUMN `youtube_url` VARCHAR(2000) NULL DEFAULT NULL',
        'mixcloud_url' => 'ALTER TABLE `events_tags` ADD COLUMN `mixcloud_url` VARCHAR(2000) NULL DEFAULT NULL',
        'email' => 'ALTER TABLE `events_tags` ADD COLUMN `email` VARCHAR(255) NULL DEFAULT NULL',
        'email_is_private' => 'ALTER TABLE `events_tags` ADD COLUMN `email_is_private` TINYINT(1) NOT NULL DEFAULT 0',
        'phone' => 'ALTER TABLE `events_tags` ADD COLUMN `phone` VARCHAR(64) NULL DEFAULT NULL',
        'phone_is_private' => 'ALTER TABLE `events_tags` ADD COLUMN `phone_is_private` TINYINT(1) NOT NULL DEFAULT 0',
        'admin_notes' => 'ALTER TABLE `events_tags` ADD COLUMN `admin_notes` TEXT NULL DEFAULT NULL',
    ];
    $present = array_fill_keys(events_tag_profile_present_columns($db, true), true);
    foreach ($alters as $col => $sql) {
        if (isset($present[$col])) {
            continue;
        }
        try {
            $db->exec($sql);
            $present[$col] = true;
        } catch (PDOException $e) {
            error_log('events_tags_ensure_profile_columns ' . $col . ': ' . $e->getMessage());
        }
    }
    events_tag_profile_present_columns($db, true);
}

/**
 * Profil mező érték mentéshez (üres → NULL, flag → 0/1).
 */
function events_tag_profile_sql_value(string $column, array $profile): mixed {
    if ($column === 'email_is_private' || $column === 'phone_is_private') {
        return events_tag_profile_flag_is_on($profile[$column] ?? '0') ? 1 : 0;
    }
    $value = trim((string) ($profile[$column] ?? ''));

    return $value !== '' ? $value : null;
}

function events_tag_profile_flag_is_on(mixed $value): bool {
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return ((int) $value) === 1;
    }
    $normalized = strtolower(trim((string) $value));

    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

/**
 * @return array{
 *   description: string,
 *   photo_url: string,
 *   logo_url: string,
 *   website_url: string,
 *   facebook_url: string,
 *   instagram_url: string,
 *   soundcloud_url: string,
 *   youtube_url: string,
 *   email: string,
 *   email_is_private: string,
 *   phone: string,
 *   phone_is_private: string
 * }
 */
function events_tag_profile_empty(): array {
    return [
        'description' => '',
        'photo_url' => '',
        'logo_url' => '',
        'website_url' => '',
        'facebook_url' => '',
        'instagram_url' => '',
        'soundcloud_url' => '',
        'youtube_url' => '',
        'mixcloud_url' => '',
        'email' => '',
        'email_is_private' => '0',
        'phone' => '',
        'phone_is_private' => '0',
        'admin_notes' => '',
    ];
}

/**
 * @param array<string, mixed> $row
 * @return array{
 *   description: string,
 *   photo_url: string,
 *   logo_url: string,
 *   website_url: string,
 *   facebook_url: string,
 *   instagram_url: string,
 *   soundcloud_url: string,
 *   youtube_url: string,
 *   email: string,
 *   email_is_private: string,
 *   phone: string,
 *   phone_is_private: string
 * }
 */
function events_tag_profile_from_row(array $row): array {
    $out = events_tag_profile_empty();
    foreach (array_keys($out) as $key) {
        if ($key === 'email_is_private' || $key === 'phone_is_private') {
            $out[$key] = events_tag_profile_flag_is_on($row[$key] ?? false) ? '1' : '0';
            continue;
        }
        $out[$key] = trim((string) ($row[$key] ?? ''));
    }

    return $out;
}

/**
 * @return array{
 *   description: string,
 *   photo_url: string,
 *   logo_url: string,
 *   website_url: string,
 *   facebook_url: string,
 *   instagram_url: string,
 *   soundcloud_url: string,
 *   youtube_url: string,
 *   email: string,
 *   email_is_private: string,
 *   phone: string,
 *   phone_is_private: string
 * }
 */
function events_tag_profile_load(PDO $db, int $tagId): array {
    $empty = events_tag_profile_empty();
    if ($tagId <= 0 || !events_tags_tables_available($db)) {
        return $empty;
    }
    events_tags_ensure_profile_columns($db);
    $colsList = events_tag_profile_present_columns($db);
    if ($colsList === []) {
        return $empty;
    }
    $cols = '`' . implode('`, `', $colsList) . '`';
    $st = $db->prepare("SELECT {$cols} FROM `events_tags` WHERE `id` = ? LIMIT 1");
    $st->execute([$tagId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ? events_tag_profile_from_row($row) : $empty;
}

/**
 * Privát e-mail / telefon elrejtése publikus megjelenítéshez.
 *
 * @param array<string, string> $profile
 * @return array<string, string>
 */
function events_tag_profile_for_public(array $profile): array {
    if (events_tag_profile_flag_is_on($profile['email_is_private'] ?? '0')) {
        $profile['email'] = '';
    }
    if (events_tag_profile_flag_is_on($profile['phone_is_private'] ?? '0')) {
        $profile['phone'] = '';
    }
    unset(
        $profile['email_is_private'],
        $profile['phone_is_private'],
        $profile['admin_notes'],
        $profile['contact_email']
    );

    return $profile;
}

/**
 * @return array{0: array<string, string>, 1: ?string} [profile, error]
 */
function events_tag_profile_parse_contact_fields(array $profile): array {
    $email = trim((string) ($_POST['tag_email'] ?? ''));
    if ($email !== '') {
        if (strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return [$profile, 'Érvénytelen e-mail cím.'];
        }
        $profile['email'] = $email;
    }
    $profile['email_is_private'] = !empty($_POST['tag_email_is_private']) ? '1' : '0';

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
    $profile['phone_is_private'] = !empty($_POST['tag_phone_is_private']) ? '1' : '0';

    $notes = trim((string) ($_POST['tag_admin_notes'] ?? ''));
    if ($notes !== '') {
        $notes = strip_tags($notes);
        if (mb_strlen($notes) > 10000) {
            return [$profile, 'A megjegyzés legfeljebb 10 000 karakter lehet.'];
        }
        $profile['admin_notes'] = $notes;
    }

    return [$profile, null];
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
        'youtube_url' => 'tag_youtube_url',
        'mixcloud_url' => 'tag_mixcloud_url',
    ];
    foreach ($urlFields as $key => $postKey) {
        [$url, $err] = events_normalize_safe_url((string) ($_POST[$postKey] ?? ''), true);
        if ($err !== null) {
            return [$profile, $err];
        }
        $profile[$key] = $url ?? '';
    }

    return events_tag_profile_parse_contact_fields($profile);
}

/**
 * @param array{
 *   description: string,
 *   photo_url: string,
 *   logo_url: string,
 *   website_url: string,
 *   facebook_url: string,
 *   instagram_url: string,
 *   soundcloud_url: string,
 *   youtube_url: string,
 *   mixcloud_url: string,
 *   email: string,
 *   email_is_private: string,
 *   phone: string,
 *   phone_is_private: string,
 *   admin_notes: string
 * } $profile
 */
function events_tag_profile_save(PDO $db, int $tagId, array $profile): void {
    if ($tagId <= 0) {
        return;
    }
    events_tags_ensure_profile_columns($db);
    $cols = events_tag_profile_present_columns($db);
    if ($cols === []) {
        return;
    }
    $sets = [];
    $params = [];
    foreach ($cols as $col) {
        $sets[] = '`' . $col . '` = ?';
        $params[] = events_tag_profile_sql_value($col, $profile);
    }
    $params[] = $tagId;
    $st = $db->prepare('UPDATE `events_tags` SET ' . implode(', ', $sets) . ' WHERE `id` = ?');
    $st->execute($params);
}

/**
 * SQL SELECT lista profil oszlopokhoz (üres string, ha nincs oszlop).
 */
function events_tag_profile_sql_select(PDO $db, string $alias = 't'): string {
    events_tags_ensure_profile_columns($db);
    $present = array_fill_keys(events_tag_profile_present_columns($db), true);
    $parts = [];
    foreach (events_tag_profile_column_names() as $col) {
        if (isset($present[$col])) {
            $parts[] = $alias . '.`' . $col . '`';
        } else {
            $parts[] = 'NULL AS `' . $col . '`';
        }
    }

    return implode(', ', $parts);
}

/**
 * POST → profil mezők fotó nélkül (a fotót a djpics feltöltés kezeli).
 *
 * @return array{0: array<string, string>, 1: ?string} [profile, error]
 */
function events_tag_profile_from_post_without_photo(): array {
    $profile = events_tag_profile_empty();
    $rawDesc = (string) ($_POST['tag_description'] ?? '');
    $profile['description'] = events_sanitize_html_fragment($rawDesc);

    $urlFields = [
        'website_url' => 'tag_website_url',
        'facebook_url' => 'tag_facebook_url',
        'instagram_url' => 'tag_instagram_url',
        'soundcloud_url' => 'tag_soundcloud_url',
        'youtube_url' => 'tag_youtube_url',
        'mixcloud_url' => 'tag_mixcloud_url',
    ];
    foreach ($urlFields as $key => $postKey) {
        [$url, $err] = events_normalize_safe_url((string) ($_POST[$postKey] ?? ''), true);
        if ($err !== null) {
            return [$profile, $err];
        }
        $profile[$key] = $url ?? '';
    }

    return events_tag_profile_parse_contact_fields($profile);
}

/**
 * Van-e megjeleníthető (nem privát) profil tartalom.
 *
 * @param array<string, string> $profile
 */
function events_tag_profile_has_public_content(array $profile): bool {
    $public = events_tag_profile_for_public($profile);
    foreach (['description', 'photo_url', 'logo_url', 'website_url', 'facebook_url', 'instagram_url', 'soundcloud_url', 'youtube_url', 'mixcloud_url', 'email', 'phone'] as $key) {
        if (trim((string) ($public[$key] ?? '')) !== '') {
            return true;
        }
    }

    return false;
}
