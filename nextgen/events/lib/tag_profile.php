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
        'photo_fit',
        'photo_focus_x',
        'photo_focus_y',
        'photo_zoom',
        'logo_url',
        'logo_fit',
        'logo_focus_x',
        'logo_focus_y',
        'logo_zoom',
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
    $wantedMap = array_fill_keys($wanted, true);
    $found = [];
    try {
        $st = $db->query('SHOW COLUMNS FROM `events_tags`');
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $name = (string) ($row['Field'] ?? '');
            if ($name !== '' && isset($wantedMap[$name])) {
                $found[$name] = true;
            }
        }
    } catch (PDOException) {
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
            while (($name = $st->fetchColumn()) !== false) {
                $found[(string) $name] = true;
            }
        } catch (PDOException) {
            $cached = [];

            return $cached;
        }
    }
    $cached = [];
    foreach ($wanted as $col) {
        if (isset($found[$col])) {
            $cached[] = $col;
        }
    }

    return $cached;
}

/**
 * Profil oszlop ALTER utasítások (első sikeres változat érvényes).
 * URL mezők TEXT: elkerüli a MySQL „Row size too large” hibát sok VARCHAR(2000) mellett.
 *
 * @return array<string, list<string>>
 */
function events_tag_profile_column_alter_sqls(): array {
    return [
        'description' => [
            'ALTER TABLE `events_tags` ADD COLUMN `description` TEXT NULL',
        ],
        'photo_url' => [
            'ALTER TABLE `events_tags` ADD COLUMN `photo_url` TEXT NULL',
            'ALTER TABLE `events_tags` ADD COLUMN `photo_url` VARCHAR(512) NULL DEFAULT NULL',
        ],
        'photo_fit' => [
            "ALTER TABLE `events_tags` ADD COLUMN `photo_fit` VARCHAR(16) NOT NULL DEFAULT 'cover'",
        ],
        'photo_focus_x' => [
            'ALTER TABLE `events_tags` ADD COLUMN `photo_focus_x` TINYINT UNSIGNED NOT NULL DEFAULT 50',
        ],
        'photo_focus_y' => [
            'ALTER TABLE `events_tags` ADD COLUMN `photo_focus_y` TINYINT UNSIGNED NOT NULL DEFAULT 50',
        ],
        'photo_zoom' => [
            'ALTER TABLE `events_tags` ADD COLUMN `photo_zoom` SMALLINT UNSIGNED NOT NULL DEFAULT 100',
        ],
        'logo_url' => [
            'ALTER TABLE `events_tags` ADD COLUMN `logo_url` TEXT NULL',
            'ALTER TABLE `events_tags` ADD COLUMN `logo_url` VARCHAR(512) NULL DEFAULT NULL',
        ],
        'logo_fit' => [
            "ALTER TABLE `events_tags` ADD COLUMN `logo_fit` VARCHAR(16) NOT NULL DEFAULT 'contain'",
        ],
        'logo_focus_x' => [
            'ALTER TABLE `events_tags` ADD COLUMN `logo_focus_x` TINYINT UNSIGNED NOT NULL DEFAULT 50',
        ],
        'logo_focus_y' => [
            'ALTER TABLE `events_tags` ADD COLUMN `logo_focus_y` TINYINT UNSIGNED NOT NULL DEFAULT 50',
        ],
        'logo_zoom' => [
            'ALTER TABLE `events_tags` ADD COLUMN `logo_zoom` SMALLINT UNSIGNED NOT NULL DEFAULT 100',
        ],
        'website_url' => [
            'ALTER TABLE `events_tags` ADD COLUMN `website_url` TEXT NULL',
            'ALTER TABLE `events_tags` ADD COLUMN `website_url` VARCHAR(512) NULL DEFAULT NULL',
        ],
        'facebook_url' => [
            'ALTER TABLE `events_tags` ADD COLUMN `facebook_url` TEXT NULL',
            'ALTER TABLE `events_tags` ADD COLUMN `facebook_url` VARCHAR(512) NULL DEFAULT NULL',
        ],
        'instagram_url' => [
            'ALTER TABLE `events_tags` ADD COLUMN `instagram_url` TEXT NULL',
            'ALTER TABLE `events_tags` ADD COLUMN `instagram_url` VARCHAR(512) NULL DEFAULT NULL',
        ],
        'soundcloud_url' => [
            'ALTER TABLE `events_tags` ADD COLUMN `soundcloud_url` TEXT NULL',
            'ALTER TABLE `events_tags` ADD COLUMN `soundcloud_url` VARCHAR(512) NULL DEFAULT NULL',
        ],
        'youtube_url' => [
            'ALTER TABLE `events_tags` ADD COLUMN `youtube_url` TEXT NULL',
            'ALTER TABLE `events_tags` ADD COLUMN `youtube_url` VARCHAR(512) NULL DEFAULT NULL',
        ],
        'mixcloud_url' => [
            'ALTER TABLE `events_tags` ADD COLUMN `mixcloud_url` TEXT NULL',
            'ALTER TABLE `events_tags` ADD COLUMN `mixcloud_url` VARCHAR(512) NULL DEFAULT NULL',
        ],
        'email' => [
            'ALTER TABLE `events_tags` ADD COLUMN `email` VARCHAR(255) NULL DEFAULT NULL',
        ],
        'email_is_private' => [
            'ALTER TABLE `events_tags` ADD COLUMN `email_is_private` TINYINT(1) NOT NULL DEFAULT 0',
        ],
        'phone' => [
            'ALTER TABLE `events_tags` ADD COLUMN `phone` VARCHAR(64) NULL DEFAULT NULL',
        ],
        'phone_is_private' => [
            'ALTER TABLE `events_tags` ADD COLUMN `phone_is_private` TINYINT(1) NOT NULL DEFAULT 0',
        ],
        'admin_notes' => [
            'ALTER TABLE `events_tags` ADD COLUMN `admin_notes` TEXT NULL',
        ],
    ];
}

/**
 * Profil oszlopok létrehozása futás közben (migráció nélkül).
 *
 * @return array{missing: list<string>, errors: array<string, string>}
 */
function events_tags_ensure_profile_columns(PDO $db): array {
    $wanted = events_tag_profile_column_names();

    if (!events_tags_tables_available($db)) {
        return [
            'missing' => $wanted,
            'errors' => ['_table' => 'Az events_tags tábla nem elérhető.'],
        ];
    }
    // DDL tranzakción belül implicit commitot okoz (MySQL) — ne futtassuk.
    if ($db->inTransaction()) {
        return [
            'missing' => array_values(array_diff($wanted, events_tag_profile_present_columns($db, true))),
            'errors' => ['_tx' => 'Tranzakción belül nem futtatható ALTER.'],
        ];
    }

    $alters = events_tag_profile_column_alter_sqls();
    $present = array_fill_keys(events_tag_profile_present_columns($db, true), true);
    $errors = [];

    foreach ($alters as $col => $sqlList) {
        if (isset($present[$col])) {
            continue;
        }
        $lastErr = '';
        $ok = false;
        foreach ($sqlList as $sql) {
            try {
                $db->exec($sql);
                $ok = true;
                break;
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                if (
                    str_contains($msg, 'Duplicate column')
                    || str_contains($msg, '1060')
                    || str_contains(strtolower($msg), 'already exists')
                ) {
                    $ok = true;
                    break;
                }
                $lastErr = $msg;
            }
        }
        if ($ok) {
            $present[$col] = true;
            continue;
        }
        if ($lastErr !== '') {
            $errors[$col] = $lastErr;
            error_log('events_tags_ensure_profile_columns ' . $col . ': ' . $lastErr);
        }
    }

    $fresh = events_tag_profile_present_columns($db, true);
    $missing = array_values(array_diff($wanted, $fresh));
    if ($missing !== []) {
        error_log('events_tags_ensure_profile_columns still missing: ' . implode(', ', $missing));
    }

    return [
        'missing' => $missing,
        'errors' => $errors,
    ];
}

/**
 * Hiányzó oszlopokhoz ajánlott ALTER SQL (kézi futtatáshoz).
 *
 * @param list<string> $columns
 * @return list<string>
 */
function events_tag_profile_manual_alter_sql(array $columns): array {
    $map = events_tag_profile_column_alter_sqls();
    $out = [];
    foreach ($columns as $col) {
        $col = (string) $col;
        if ($col === '' || !isset($map[$col][0])) {
            continue;
        }
        $out[] = rtrim((string) $map[$col][0], ';') . ';';
    }

    return $out;
}

/**
 * Ensure eredmény + profil → mentést blokkoló hibaüzenet, vagy null.
 *
 * @param array{missing: list<string>, errors: array<string, string>} $ensure
 * @param array<string, string> $profile
 */
function events_tag_profile_ensure_blocking_error(array $ensure, array $profile): ?string {
    $blocking = [];
    foreach ($ensure['missing'] as $missingCol) {
        if (events_tag_profile_column_has_value((string) $missingCol, $profile)) {
            $blocking[] = (string) $missingCol;
        }
    }
    if ($blocking === []) {
        return null;
    }
    $sqls = events_tag_profile_manual_alter_sql($blocking);
    $msg = 'Egyes profilmezők nem menthetők (hiányzó adatbázis-oszlop: '
        . implode(', ', $blocking) . ').';
    if ($sqls !== []) {
        $msg .= ' Futtasd phpMyAdminban: ' . implode(' ', $sqls);
    }
    $firstErr = (string) ($ensure['errors'][$blocking[0]] ?? '');
    if ($firstErr !== '') {
        $msg .= ' (' . $firstErr . ')';
    }

    return $msg;
}

/**
 * Van-e mentendő érték egy profiloszlophoz (üres defaultól eltérő).
 *
 * @param array<string, string> $profile
 */
function events_tag_profile_column_has_value(string $column, array $profile): bool {
    $empty = events_tag_profile_empty();
    $current = (string) ($profile[$column] ?? '');
    $default = (string) ($empty[$column] ?? '');

    return $current !== $default;
}

/**
 * Profil mező érték mentéshez (üres → NULL, flag → 0/1).
 */
function events_tag_profile_sql_value(string $column, array $profile): mixed {
    if ($column === 'email_is_private' || $column === 'phone_is_private') {
        return events_tag_profile_flag_is_on($profile[$column] ?? '0') ? 1 : 0;
    }
    if ($column === 'photo_fit' || $column === 'logo_fit') {
        $default = $column === 'logo_fit' ? 'contain' : 'cover';

        return events_dj_media_fit_normalize((string) ($profile[$column] ?? ''), $default);
    }
    if ($column === 'photo_focus_x' || $column === 'photo_focus_y' || $column === 'logo_focus_x' || $column === 'logo_focus_y') {
        return events_dj_media_pct_normalize($profile[$column] ?? 50, 50);
    }
    if ($column === 'photo_zoom' || $column === 'logo_zoom') {
        return events_dj_media_zoom_normalize($profile[$column] ?? 100, 100);
    }
    $value = trim((string) ($profile[$column] ?? ''));

    return $value !== '' ? $value : null;
}

function events_dj_media_fit_normalize(string $fit, string $default = 'cover'): string {
    $fit = strtolower(trim($fit));

    return in_array($fit, ['cover', 'contain'], true) ? $fit : $default;
}

function events_dj_media_pct_normalize(mixed $value, int $default = 50): int {
    if ($value === null || $value === '') {
        return $default;
    }
    $n = (int) $value;
    if ($n < 0) {
        return 0;
    }
    if ($n > 100) {
        return 100;
    }

    return $n;
}

function events_dj_media_zoom_normalize(mixed $value, int $default = 100): int {
    if ($value === null || $value === '') {
        return $default;
    }
    $n = (int) $value;
    if ($n < 100) {
        return 100;
    }
    if ($n > 200) {
        return 200;
    }

    return $n;
}

/**
 * Publikus / admin kép stílus (object-fit, fókusz, zoom).
 *
 * @param array<string, string> $profile
 */
function events_dj_media_img_style(array $profile, string $kind): string {
    $kind = $kind === 'logo' ? 'logo' : 'photo';
    $defaultFit = $kind === 'logo' ? 'contain' : 'cover';
    $fit = events_dj_media_fit_normalize((string) ($profile[$kind . '_fit'] ?? ''), $defaultFit);
    $x = events_dj_media_pct_normalize($profile[$kind . '_focus_x'] ?? 50, 50);
    $y = events_dj_media_pct_normalize($profile[$kind . '_focus_y'] ?? 50, 50);
    $zoom = events_dj_media_zoom_normalize($profile[$kind . '_zoom'] ?? 100, 100);
    $parts = [
        'object-fit:' . $fit,
        'object-position:' . $x . '% ' . $y . '%',
    ];
    if ($zoom !== 100) {
        $scale = number_format($zoom / 100, 2, '.', '');
        $parts[] = 'transform:scale(' . $scale . ')';
        $parts[] = 'transform-origin:' . $x . '% ' . $y . '%';
    }

    return implode(';', $parts);
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
        'photo_fit' => 'cover',
        'photo_focus_x' => '50',
        'photo_focus_y' => '50',
        'photo_zoom' => '100',
        'logo_url' => '',
        'logo_fit' => 'contain',
        'logo_focus_x' => '50',
        'logo_focus_y' => '50',
        'logo_zoom' => '100',
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
        if ($key === 'photo_fit' || $key === 'logo_fit') {
            $default = $key === 'logo_fit' ? 'contain' : 'cover';
            $out[$key] = events_dj_media_fit_normalize((string) ($row[$key] ?? ''), $default);
            continue;
        }
        if ($key === 'photo_focus_x' || $key === 'photo_focus_y' || $key === 'logo_focus_x' || $key === 'logo_focus_y') {
            $out[$key] = (string) events_dj_media_pct_normalize($row[$key] ?? 50, 50);
            continue;
        }
        if ($key === 'photo_zoom' || $key === 'logo_zoom') {
            $out[$key] = (string) events_dj_media_zoom_normalize($row[$key] ?? 100, 100);
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
    $colsList = events_tag_profile_present_columns($db, true);
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
 * Fotó / logó illesztés, fókusz és zoom POST mezők.
 *
 * @param array<string, string> $profile
 * @return array<string, string>
 */
function events_tag_profile_parse_media_display_fields(array $profile): array {
    $profile['photo_fit'] = events_dj_media_fit_normalize((string) ($_POST['tag_photo_fit'] ?? ''), 'cover');
    $profile['photo_focus_x'] = (string) events_dj_media_pct_normalize($_POST['tag_photo_focus_x'] ?? 50, 50);
    $profile['photo_focus_y'] = (string) events_dj_media_pct_normalize($_POST['tag_photo_focus_y'] ?? 50, 50);
    $profile['photo_zoom'] = (string) events_dj_media_zoom_normalize($_POST['tag_photo_zoom'] ?? 100, 100);

    $profile['logo_fit'] = events_dj_media_fit_normalize((string) ($_POST['tag_logo_fit'] ?? ''), 'contain');
    $profile['logo_focus_x'] = (string) events_dj_media_pct_normalize($_POST['tag_logo_focus_x'] ?? 50, 50);
    $profile['logo_focus_y'] = (string) events_dj_media_pct_normalize($_POST['tag_logo_focus_y'] ?? 50, 50);
    $profile['logo_zoom'] = (string) events_dj_media_zoom_normalize($_POST['tag_logo_zoom'] ?? 100, 100);

    return $profile;
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

    $profile = events_tag_profile_parse_media_display_fields($profile);

    return events_tag_profile_parse_contact_fields($profile);
}

/**
 * @param array<string, string> $profile
 * @return ?string hibaüzenet, vagy null siker esetén
 */
function events_tag_profile_save(PDO $db, int $tagId, array $profile): ?string {
    if ($tagId <= 0) {
        return null;
    }
    $ensureErrors = [];
    // Ne hívjunk ALTER-t tranzakción belül.
    if (!$db->inTransaction()) {
        $ensure = events_tags_ensure_profile_columns($db);
        $ensureErrors = $ensure['errors'];
    }
    $cols = events_tag_profile_present_columns($db, true);
    if ($cols === []) {
        return 'A profilmezők mentése sikertelen (nincsenek profil-oszlopok).';
    }
    $present = array_fill_keys($cols, true);
    $unpersistable = [];
    foreach (events_tag_profile_column_names() as $col) {
        if (isset($present[$col])) {
            continue;
        }
        if (events_tag_profile_column_has_value($col, $profile)) {
            $unpersistable[] = $col;
        }
    }
    if ($unpersistable !== []) {
        error_log('events_tag_profile_save missing columns: ' . implode(', ', $unpersistable));
        $sqls = events_tag_profile_manual_alter_sql($unpersistable);
        $msg = 'Egyes profilmezők nem menthetők (hiányzó adatbázis-oszlop: '
            . implode(', ', $unpersistable)
            . ').';
        if ($sqls !== []) {
            $msg .= ' Futtasd phpMyAdminban: ' . implode(' ', $sqls);
        }
        foreach ($unpersistable as $col) {
            if (isset($ensureErrors[$col]) && $ensureErrors[$col] !== '') {
                $msg .= ' (' . $col . ': ' . $ensureErrors[$col] . ')';
                break;
            }
        }

        return $msg;
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

    return null;
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

    $profile = events_tag_profile_parse_media_display_fields($profile);

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
