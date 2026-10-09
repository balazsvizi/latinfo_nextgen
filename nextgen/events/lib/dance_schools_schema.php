<?php
declare(strict_types=1);

/**
 * Tánciskolák + tánctanár profil / partner kapcsolók – séma biztosítás.
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
            CREATE TABLE IF NOT EXISTS `dance_school_locations` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `school_id` INT UNSIGNED NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `address` VARCHAR(500) NULL DEFAULT NULL,
                `city` VARCHAR(128) NULL DEFAULT NULL,
                `postal_code` VARCHAR(32) NULL DEFAULT NULL,
                `country` VARCHAR(64) NULL DEFAULT 'Magyarország',
                `latitude` DECIMAL(10,7) NULL DEFAULT NULL,
                `longitude` DECIMAL(10,7) NULL DEFAULT NULL,
                `google_maps_url` TEXT NULL,
                `notes` TEXT NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (`id`),
                KEY `idx_dsl_school` (`school_id`, `sort_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS `dance_school_offerings` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `location_id` INT UNSIGNED NOT NULL,
                `style_id` INT UNSIGNED NULL DEFAULT NULL,
                `style_label` VARCHAR(128) NULL DEFAULT NULL,
                `age_group` VARCHAR(32) NOT NULL DEFAULT 'adult',
                `level` VARCHAR(32) NOT NULL DEFAULT 'all',
                `class_type` VARCHAR(32) NOT NULL DEFAULT 'group',
                `schedule_note` VARCHAR(500) NULL DEFAULT NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_dso_location` (`location_id`, `sort_order`),
                KEY `idx_dso_style` (`style_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS `dance_school_events` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `school_id` INT UNSIGNED NOT NULL,
                `location_id` INT UNSIGNED NULL DEFAULT NULL,
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
                KEY `idx_dse_type` (`event_type`)
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
