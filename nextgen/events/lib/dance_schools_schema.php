<?php
declare(strict_types=1);

/**
 * Tánciskolák + tánctanár profil / partner kapcsolók – séma biztosítás.
 *
 * Helyszínek: events_venues (bulihelyszínek) + dance_school_venues kapcsolótábla.
 * Kínálat az iskola–helyszín kapcsolathoz (school_venue_id) tartozik.
 */

function dance_schools_tables_ready(PDO $db, bool $refresh = false): bool
{
    static $cached = null;
    if ($refresh) {
        $cached = null;
    }
    if ($cached !== null) {
        return $cached;
    }
    try {
        $db->query('SELECT 1 FROM `dance_schools` LIMIT 1');
        $cached = true;
    } catch (Throwable) {
        $cached = false;
    }

    return $cached;
}

function dance_schools_table_exists(PDO $db, string $table): bool
{
    try {
        $db->query('SELECT 1 FROM `' . str_replace('`', '', $table) . '` LIMIT 1');

        return true;
    } catch (Throwable) {
        return false;
    }
}

function dance_schools_column_exists(PDO $db, string $table, string $column): bool
{
    try {
        $st = $db->prepare('
            SELECT 1 FROM `INFORMATION_SCHEMA`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = DATABASE()
              AND `TABLE_NAME` = ?
              AND `COLUMN_NAME` = ?
            LIMIT 1
        ');
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    } catch (Throwable) {
        try {
            $db->query('SELECT `' . str_replace('`', '', $column) . '` FROM `' . str_replace('`', '', $table) . '` LIMIT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}

function dance_schools_ensure_schema(PDO $db): bool
{
    try {
        if (!dance_schools_tables_ready($db)) {
            $db->exec("
                CREATE TABLE IF NOT EXISTS `dance_schools` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `name` VARCHAR(255) NOT NULL,
                    `slug` VARCHAR(255) NOT NULL,
                    `description` MEDIUMTEXT NULL,
                    `founded_year` SMALLINT UNSIGNED NULL DEFAULT NULL,
                    `city` VARCHAR(128) NULL DEFAULT NULL,
                    `website_url` TEXT NULL,
                    `facebook_url` TEXT NULL,
                    `instagram_url` TEXT NULL,
                    `tiktok_url` TEXT NULL,
                    `youtube_url` TEXT NULL,
                    `email` VARCHAR(255) NULL DEFAULT NULL,
                    `email_is_private` TINYINT(1) NOT NULL DEFAULT 0,
                    `phone` VARCHAR(64) NULL DEFAULT NULL,
                    `phone_is_private` TINYINT(1) NOT NULL DEFAULT 0,
                    `photo_url` TEXT NULL,
                    `logo_url` TEXT NULL,
                    `trial_lesson_info` TEXT NULL,
                    `pricing_info` TEXT NULL,
                    `schedule_url` TEXT NULL,
                    `registration_url` TEXT NULL,
                    `languages` VARCHAR(255) NULL DEFAULT NULL,
                    `accepts_beginners` TINYINT(1) NOT NULL DEFAULT 1,
                    `has_kids_classes` TINYINT(1) NOT NULL DEFAULT 0,
                    `has_performance_team` TINYINT(1) NOT NULL DEFAULT 0,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `is_published` TINYINT(1) NOT NULL DEFAULT 0,
                    `admin_notes` TEXT NULL,
                    `sort_order` INT NOT NULL DEFAULT 0,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_dance_school_slug` (`slug`),
                    KEY `idx_dance_school_name` (`name`),
                    KEY `idx_dance_school_city` (`city`),
                    KEY `idx_dance_school_pub` (`is_published`, `is_active`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            dance_schools_tables_ready($db, true);
        }

        if (!dance_schools_tables_ready($db)) {
            return false;
        }

        $db->exec("
            CREATE TABLE IF NOT EXISTS `dance_school_venues` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `school_id` INT UNSIGNED NOT NULL,
                `venue_id` INT UNSIGNED NOT NULL,
                `notes` TEXT NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_dsv_school_venue` (`school_id`, `venue_id`),
                KEY `idx_dsv_venue` (`venue_id`),
                KEY `idx_dsv_school_sort` (`school_id`, `sort_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS `dance_school_offerings` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `school_venue_id` INT UNSIGNED NOT NULL,
                `style_id` INT UNSIGNED NULL DEFAULT NULL,
                `style_label` VARCHAR(128) NULL DEFAULT NULL,
                `age_group` VARCHAR(32) NOT NULL DEFAULT 'adult',
                `level` VARCHAR(32) NOT NULL DEFAULT 'all',
                `class_type` VARCHAR(32) NOT NULL DEFAULT 'group',
                `schedule_note` VARCHAR(500) NULL DEFAULT NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_dso_school_venue` (`school_venue_id`, `sort_order`),
                KEY `idx_dso_style` (`style_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS `dance_school_events` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `school_id` INT UNSIGNED NOT NULL,
                `venue_id` INT UNSIGNED NULL DEFAULT NULL,
                `title` VARCHAR(255) NOT NULL,
                `description` TEXT NULL,
                `event_type` VARCHAR(32) NOT NULL DEFAULT 'workshop',
                `starts_at` DATETIME NOT NULL,
                `ends_at` DATETIME NULL DEFAULT NULL,
                `price_info` VARCHAR(255) NULL DEFAULT NULL,
                `registration_url` TEXT NULL,
                `is_published` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_dse_school_dates` (`school_id`, `starts_at`),
                KEY `idx_dse_starts` (`starts_at`),
                KEY `idx_dse_type` (`event_type`),
                KEY `idx_dse_venue` (`venue_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS `dance_school_teachers` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `school_id` INT UNSIGNED NOT NULL,
                `tag_id` INT UNSIGNED NOT NULL,
                `role_type` VARCHAR(32) NOT NULL DEFAULT 'teacher',
                `role_note` VARCHAR(500) NULL DEFAULT NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_dst_school_tag_role` (`school_id`, `tag_id`, `role_type`),
                KEY `idx_dst_tag` (`tag_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS `dance_teacher_profiles` (
                `tag_id` INT UNSIGNED NOT NULL,
                `offers_private_lessons` TINYINT(1) NOT NULL DEFAULT 0,
                `private_lesson_note` TEXT NULL,
                `achievements` TEXT NULL,
                `certifications` TEXT NULL,
                `years_experience` SMALLINT UNSIGNED NULL DEFAULT NULL,
                `city` VARCHAR(128) NULL DEFAULT NULL,
                `teaches_online` TINYINT(1) NOT NULL DEFAULT 0,
                `available_for_events` TINYINT(1) NOT NULL DEFAULT 0,
                `bio_short` VARCHAR(500) NULL DEFAULT NULL,
                `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`tag_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS `dance_teacher_styles` (
                `tag_id` INT UNSIGNED NOT NULL,
                `style_id` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`tag_id`, `style_id`),
                KEY `idx_dts_style` (`style_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS `dance_entity_views` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `entity_type` VARCHAR(32) NOT NULL,
                `entity_id` INT UNSIGNED NOT NULL,
                `metric` VARCHAR(32) NOT NULL DEFAULT 'page_view',
                `viewed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `ip_hash` CHAR(64) NULL DEFAULT NULL,
                `user_id` INT UNSIGNED NULL DEFAULT NULL,
                `is_bot` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_dev_entity_time` (`entity_type`, `entity_id`, `viewed_at`),
                KEY `idx_dev_metric_time` (`metric`, `viewed_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        dance_schools_migrate_legacy_locations($db);

        if (function_exists('nextgen_partners_table_ready') && nextgen_partners_table_ready($db)) {
            $db->exec("
                CREATE TABLE IF NOT EXISTS `nextgen_partner_dance_schools` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `partner_id` INT UNSIGNED NOT NULL,
                    `school_id` INT UNSIGNED NOT NULL,
                    `role_type` VARCHAR(16) NOT NULL DEFAULT 'school',
                    `role_note` VARCHAR(500) NULL DEFAULT NULL,
                    `sort_order` INT NOT NULL DEFAULT 0,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_partner_school_role` (`partner_id`, `school_id`, `role_type`),
                    KEY `idx_npds_school` (`school_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $db->exec("
                CREATE TABLE IF NOT EXISTS `nextgen_partner_teachers` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `partner_id` INT UNSIGNED NOT NULL,
                    `tag_id` INT UNSIGNED NOT NULL,
                    `role_type` VARCHAR(16) NOT NULL DEFAULT 'teacher',
                    `role_note` VARCHAR(500) NULL DEFAULT NULL,
                    `sort_order` INT NOT NULL DEFAULT 0,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_partner_teacher_role` (`partner_id`, `tag_id`, `role_type`),
                    KEY `idx_npt_tag` (`tag_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        return true;
    } catch (Throwable $ex) {
        error_log('dance_schools_ensure_schema: ' . $ex->getMessage());

        return false;
    }
}

/**
 * Régi dance_school_locations → events_venues + dance_school_venues migráció.
 */
function dance_schools_migrate_legacy_locations(PDO $db): void
{
    $hasLegacyLoc = dance_schools_table_exists($db, 'dance_school_locations');
    $offeringsHasLoc = dance_schools_column_exists($db, 'dance_school_offerings', 'location_id');
    $offeringsHasSv = dance_schools_column_exists($db, 'dance_school_offerings', 'school_venue_id');
    $eventsHasLoc = dance_schools_column_exists($db, 'dance_school_events', 'location_id');
    $eventsHasVenue = dance_schools_column_exists($db, 'dance_school_events', 'venue_id');

    if (!$offeringsHasSv && dance_schools_table_exists($db, 'dance_school_offerings')) {
        try {
            $db->exec('ALTER TABLE `dance_school_offerings` ADD COLUMN `school_venue_id` INT UNSIGNED NULL DEFAULT NULL AFTER `id`');
            $offeringsHasSv = true;
        } catch (Throwable $ex) {
            error_log('dance_schools_migrate add school_venue_id: ' . $ex->getMessage());
        }
    }

    if (!$eventsHasVenue && dance_schools_table_exists($db, 'dance_school_events')) {
        try {
            $db->exec('ALTER TABLE `dance_school_events` ADD COLUMN `venue_id` INT UNSIGNED NULL DEFAULT NULL AFTER `school_id`');
            $eventsHasVenue = true;
        } catch (Throwable $ex) {
            error_log('dance_schools_migrate add venue_id: ' . $ex->getMessage());
        }
    }

    if ($hasLegacyLoc && $offeringsHasSv) {
        require_once __DIR__ . '/slug.php';
        require_once __DIR__ . '/venue_request.php';
        require_once __DIR__ . '/entity_quick_create.php';

        $locRows = $db->query('SELECT * FROM `dance_school_locations` ORDER BY `id` ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $locToSchoolVenue = [];
        $locToVenue = [];

        foreach ($locRows as $loc) {
            $oldLocId = (int) ($loc['id'] ?? 0);
            $schoolId = (int) ($loc['school_id'] ?? 0);
            $name = trim((string) ($loc['name'] ?? ''));
            if ($oldLocId <= 0 || $schoolId <= 0 || $name === '') {
                continue;
            }

            $venueId = dance_schools_find_or_create_venue_from_legacy($db, $loc);
            if ($venueId <= 0) {
                continue;
            }
            $locToVenue[$oldLocId] = $venueId;

            $linkId = 0;
            $chk = $db->prepare('SELECT `id` FROM `dance_school_venues` WHERE `school_id` = ? AND `venue_id` = ? LIMIT 1');
            $chk->execute([$schoolId, $venueId]);
            $found = $chk->fetchColumn();
            if ($found !== false) {
                $linkId = (int) $found;
                $db->prepare('
                    UPDATE `dance_school_venues`
                    SET `notes` = COALESCE(NULLIF(?, \'\'), `notes`),
                        `sort_order` = ?,
                        `is_active` = ?
                    WHERE `id` = ?
                ')->execute([
                    trim((string) ($loc['notes'] ?? '')),
                    (int) ($loc['sort_order'] ?? 0),
                    !empty($loc['is_active']) ? 1 : 0,
                    $linkId,
                ]);
            } else {
                $db->prepare('
                    INSERT INTO `dance_school_venues` (`school_id`, `venue_id`, `notes`, `sort_order`, `is_active`)
                    VALUES (?,?,?,?,?)
                ')->execute([
                    $schoolId,
                    $venueId,
                    trim((string) ($loc['notes'] ?? '')) ?: null,
                    (int) ($loc['sort_order'] ?? 0),
                    !empty($loc['is_active']) ? 1 : 0,
                ]);
                $linkId = (int) $db->lastInsertId();
            }
            $locToSchoolVenue[$oldLocId] = $linkId;
        }

        if ($offeringsHasLoc && $locToSchoolVenue !== []) {
            $updOff = $db->prepare('UPDATE `dance_school_offerings` SET `school_venue_id` = ? WHERE `location_id` = ? AND (`school_venue_id` IS NULL OR `school_venue_id` = 0)');
            foreach ($locToSchoolVenue as $oldLocId => $svId) {
                $updOff->execute([$svId, $oldLocId]);
            }
        }

        if ($eventsHasLoc && $eventsHasVenue && $locToVenue !== []) {
            $updEv = $db->prepare('UPDATE `dance_school_events` SET `venue_id` = ? WHERE `location_id` = ? AND (`venue_id` IS NULL OR `venue_id` = 0)');
            foreach ($locToVenue as $oldLocId => $venueId) {
                $updEv->execute([$venueId, $oldLocId]);
            }
        }
    }

    if ($offeringsHasLoc && $offeringsHasSv) {
        try {
            $db->exec('DELETE FROM `dance_school_offerings` WHERE `school_venue_id` IS NULL OR `school_venue_id` = 0');
            $db->exec('ALTER TABLE `dance_school_offerings` MODIFY `school_venue_id` INT UNSIGNED NOT NULL');
            $db->exec('ALTER TABLE `dance_school_offerings` DROP COLUMN `location_id`');
        } catch (Throwable $ex) {
            error_log('dance_schools_migrate drop offerings.location_id: ' . $ex->getMessage());
        }
    }

    if ($eventsHasLoc && $eventsHasVenue) {
        try {
            $db->exec('ALTER TABLE `dance_school_events` DROP COLUMN `location_id`');
        } catch (Throwable $ex) {
            error_log('dance_schools_migrate drop events.location_id: ' . $ex->getMessage());
        }
    }

    if ($hasLegacyLoc) {
        try {
            $db->exec('DROP TABLE IF EXISTS `dance_school_locations`');
        } catch (Throwable $ex) {
            error_log('dance_schools_migrate drop locations: ' . $ex->getMessage());
        }
    }

    if (dance_schools_column_exists($db, 'dance_school_offerings', 'school_venue_id')) {
        try {
            $db->exec('ALTER TABLE `dance_school_offerings` ADD KEY `idx_dso_school_venue` (`school_venue_id`, `sort_order`)');
        } catch (Throwable) {
            // index már létezik
        }
    }
    if (dance_schools_column_exists($db, 'dance_school_events', 'venue_id')) {
        try {
            $db->exec('ALTER TABLE `dance_school_events` ADD KEY `idx_dse_venue` (`venue_id`)');
        } catch (Throwable) {
            // index már létezik
        }
    }
}

/**
 * @param array<string, mixed> $loc
 */
function dance_schools_find_or_create_venue_from_legacy(PDO $db, array $loc): int
{
    $name = trim((string) ($loc['name'] ?? ''));
    if ($name === '') {
        return 0;
    }
    $city = trim((string) ($loc['city'] ?? ''));
    $address = trim((string) ($loc['address'] ?? ''));
    $postal = trim((string) ($loc['postal_code'] ?? ''));

    if ($city !== '') {
        $st = $db->prepare('SELECT `id` FROM `events_venues` WHERE `name` = ? AND (`city` = ? OR `city` IS NULL OR `city` = \'\') LIMIT 1');
        $st->execute([$name, $city]);
    } else {
        $st = $db->prepare('SELECT `id` FROM `events_venues` WHERE `name` = ? LIMIT 1');
        $st->execute([$name]);
    }
    $existing = $st->fetchColumn();
    if ($existing !== false) {
        $venueId = (int) $existing;
        $db->prepare('
            UPDATE `events_venues`
            SET `address` = COALESCE(NULLIF(?, \'\'), `address`),
                `city` = COALESCE(NULLIF(?, \'\'), `city`),
                `postal_code` = COALESCE(NULLIF(?, \'\'), `postal_code`),
                `google_maps_url` = COALESCE(NULLIF(?, \'\'), `google_maps_url`),
                `latitude` = COALESCE(?, `latitude`),
                `longitude` = COALESCE(?, `longitude`)
            WHERE `id` = ?
        ')->execute([
            $address,
            $city,
            $postal,
            trim((string) ($loc['google_maps_url'] ?? '')),
            isset($loc['latitude']) && $loc['latitude'] !== '' && $loc['latitude'] !== null ? (float) $loc['latitude'] : null,
            isset($loc['longitude']) && $loc['longitude'] !== '' && $loc['longitude'] !== null ? (float) $loc['longitude'] : null,
            $venueId,
        ]);

        return $venueId;
    }

    require_once __DIR__ . '/slug.php';
    $slug = events_ensure_unique_venue_slug($db, events_slugify($name), null);
    $country = trim((string) ($loc['country'] ?? ''));
    if ($country === '' && function_exists('events_venue_default_country')) {
        $country = events_venue_default_country();
    }
    if ($country === '') {
        $country = 'Magyarország';
    }
    $lat = isset($loc['latitude']) && $loc['latitude'] !== '' && $loc['latitude'] !== null ? (float) $loc['latitude'] : null;
    $lng = isset($loc['longitude']) && $loc['longitude'] !== '' && $loc['longitude'] !== null ? (float) $loc['longitude'] : null;
    $maps = trim((string) ($loc['google_maps_url'] ?? ''));

    $ins = $db->prepare('
        INSERT INTO `events_venues`
        (`name`, `slug`, `description`, `country`, `city`, `postal_code`, `address`, `latitude`, `longitude`, `website_url`, `google_maps_url`, `linked_venue_id`)
        VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, NULL, ?, NULL)
    ');
    $ins->execute([
        $name,
        $slug,
        $country,
        $city !== '' ? $city : null,
        $postal !== '' ? $postal : null,
        $address !== '' ? $address : null,
        $lat,
        $lng,
        $maps !== '' ? $maps : null,
    ]);

    return (int) $db->lastInsertId();
}

/**
 * @return array<string, string>
 */
function dance_school_age_group_labels(): array
{
    return [
        'kids' => 'Gyerek',
        'youth' => 'Ifjúsági',
        'adult' => 'Felnőtt',
        'senior' => 'Szenior',
        'all' => 'Minden korosztály',
    ];
}

/**
 * @return array<string, string>
 */
function dance_school_level_labels(): array
{
    return [
        'beginner' => 'Kezdő',
        'intermediate' => 'Haladó',
        'advanced' => 'Profi / verseny',
        'all' => 'Minden szint',
    ];
}

/**
 * @return array<string, string>
 */
function dance_school_class_type_labels(): array
{
    return [
        'group' => 'Csoportóra',
        'private' => 'Magánóra',
        'performance' => 'Fellépő csoport',
        'social' => 'Social / practica',
        'competition' => 'Verseny felkészítő',
        'kids_group' => 'Gyerek csoport',
        'open_class' => 'Nyílt óra',
        'other' => 'Egyéb',
    ];
}

/**
 * @return array<string, string>
 */
function dance_school_event_type_labels(): array
{
    return [
        'workshop' => 'Workshop',
        'intensive' => 'Intenzív',
        'course' => 'Tanfolyam',
        'camp' => 'Tábor',
        'showcase' => 'Bemutató / gála',
        'exam' => 'Vizsga',
        'open_day' => 'Nyílt nap',
        'other' => 'Egyéb',
    ];
}

/**
 * @return array<string, string>
 */
function dance_school_teacher_role_labels(): array
{
    return [
        'owner' => 'Tulajdonos',
        'director' => 'Művészeti vezető',
        'teacher' => 'Tánctanár',
        'guest' => 'Vendégoktató',
    ];
}
