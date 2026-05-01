-- SQL_0423-full.sql
-- Egyben futtatható csomag a 2026-04-23-as átállításokhoz.
-- Cél: másik környezetben egyetlen futtatással menjen le.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_rename_if_exists $$
CREATE PROCEDURE sp_rename_if_exists(IN p_old VARCHAR(128), IN p_new VARCHAR(128))
BEGIN
    DECLARE v_old_exists INT DEFAULT 0;
    DECLARE v_new_exists INT DEFAULT 0;

    SELECT COUNT(*) INTO v_old_exists
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_old;

    SELECT COUNT(*) INTO v_new_exists
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_new;

    IF v_old_exists > 0 AND v_new_exists = 0 THEN
        SET @sql = CONCAT('RENAME TABLE `', p_old, '` TO `', p_new, '`');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DROP PROCEDURE IF EXISTS sp_add_col_if_missing $$
CREATE PROCEDURE sp_add_col_if_missing(IN p_table VARCHAR(128), IN p_col VARCHAR(128), IN p_sql TEXT)
BEGIN
    DECLARE v_exists INT DEFAULT 0;
    SELECT COUNT(*) INTO v_exists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_col;
    IF v_exists = 0 THEN
        SET @sql = p_sql;
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DROP PROCEDURE IF EXISTS sp_drop_col_if_exists $$
CREATE PROCEDURE sp_drop_col_if_exists(IN p_table VARCHAR(128), IN p_col VARCHAR(128))
BEGIN
    DECLARE v_exists INT DEFAULT 0;
    SELECT COUNT(*) INTO v_exists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_col;
    IF v_exists > 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` DROP COLUMN `', p_col, '`');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DROP PROCEDURE IF EXISTS sp_drop_index_if_exists $$
CREATE PROCEDURE sp_drop_index_if_exists(IN p_table VARCHAR(128), IN p_index VARCHAR(128))
BEGIN
    DECLARE v_exists INT DEFAULT 0;
    SELECT COUNT(*) INTO v_exists
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND INDEX_NAME = p_index;
    IF v_exists > 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` DROP INDEX `', p_index, '`');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DELIMITER ;

-- 1) Legacy -> prefixelt táblanevek (ha még régi nevük van)
CALL sp_rename_if_exists('naptár_esemény_megtekintések', 'events_calendar_event_views');
CALL sp_rename_if_exists('naptár_események', 'events_calendar_events');
CALL sp_rename_if_exists('organizers', 'events_organizers');

CALL sp_rename_if_exists('számlázandó_időszak', 'finance_billing_periods');
CALL sp_rename_if_exists('számla_fájlok', 'finance_invoice_files');
CALL sp_rename_if_exists('számlázandó', 'finance_billing_items');
CALL sp_rename_if_exists('számlák', 'finance_invoices');
CALL sp_rename_if_exists('számlázási_címek', 'finance_billing_addresses');
CALL sp_rename_if_exists('kontakt_típus_kapcsolat', 'finance_contact_type_links');
CALL sp_rename_if_exists('kontakt_típusok', 'finance_contact_types');
CALL sp_rename_if_exists('kontakt_megjegyzések', 'finance_contact_notes');
CALL sp_rename_if_exists('kontaktok', 'finance_contacts');
CALL sp_rename_if_exists('szervező_megjegyzések', 'finance_organizer_notes');
CALL sp_rename_if_exists('szervező_címkék', 'finance_organizer_tags');
CALL sp_rename_if_exists('szervező_kontakt', 'finance_organizer_contacts');
CALL sp_rename_if_exists('szervező_log', 'finance_organizer_activity_log');
CALL sp_rename_if_exists('szervezők', 'finance_organizers');
CALL sp_rename_if_exists('címkék', 'finance_tags');
CALL sp_rename_if_exists('adminok', 'nextgen_admins');
CALL sp_rename_if_exists('rendszer_log', 'nextgen_system_log');
CALL sp_rename_if_exists('email_config', 'finance_email_accounts');
CALL sp_rename_if_exists('levélsablonok', 'finance_email_templates');
CALL sp_rename_if_exists('landingpage', 'nextgen_landing_feedback');
CALL sp_rename_if_exists('exporter_connections', 'nextgen_exporter_connections');
CALL sp_rename_if_exists('exporter_queries', 'nextgen_exporter_queries');

-- 2) Korábbi részleges nextgen_* -> finance_* átállás (ha ott maradt)
CALL sp_rename_if_exists('nextgen_billing_addresses', 'finance_billing_addresses');
CALL sp_rename_if_exists('nextgen_contact_notes', 'finance_contact_notes');
CALL sp_rename_if_exists('nextgen_contact_type_links', 'finance_contact_type_links');
CALL sp_rename_if_exists('nextgen_contact_types', 'finance_contact_types');
CALL sp_rename_if_exists('nextgen_contacts', 'finance_contacts');
CALL sp_rename_if_exists('nextgen_email_accounts', 'finance_email_accounts');
CALL sp_rename_if_exists('nextgen_email_templates', 'finance_email_templates');
CALL sp_rename_if_exists('nextgen_organizer_activity_log', 'finance_organizer_activity_log');
CALL sp_rename_if_exists('nextgen_organizer_contacts', 'finance_organizer_contacts');
CALL sp_rename_if_exists('nextgen_organizer_notes', 'finance_organizer_notes');
CALL sp_rename_if_exists('nextgen_organizer_tags', 'finance_organizer_tags');
CALL sp_rename_if_exists('nextgen_organizers', 'finance_organizers');
CALL sp_rename_if_exists('nextgen_tags', 'finance_tags');

SET FOREIGN_KEY_CHECKS = 1;

-- 3) Events táblák létrehozása (ha hiányoznak)
CREATE TABLE IF NOT EXISTS `events_organizers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_events_organizers_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `events_organizers` AUTO_INCREMENT = 200000;

CREATE TABLE IF NOT EXISTS `events_venues` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(500) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `description` MEDIUMTEXT NULL,
    `country` VARCHAR(120) NOT NULL DEFAULT 'Magyarország',
    `city` VARCHAR(255) NULL,
    `postal_code` VARCHAR(16) NULL,
    `address` TEXT NULL COMMENT 'Utca, házszám',
    `linked_venue_id` INT UNSIGNED NULL,
    `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_events_venues_slug` (`slug`),
    KEY `idx_events_venues_name` (`name`(191)),
    CONSTRAINT `fk_events_venues_linked_venue` FOREIGN KEY (`linked_venue_id`) REFERENCES `events_venues` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `events_calendar_events` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_name` VARCHAR(500) NOT NULL,
    `event_slug` VARCHAR(255) NOT NULL,
    `event_content` MEDIUMTEXT NOT NULL,
    `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `event_status` VARCHAR(20) NOT NULL DEFAULT 'draft' COMMENT 'wp_posts.post_status (publish, draft, …)',
    `event_start` DATETIME NULL,
    `event_end` DATETIME NULL,
    `event_allday` TINYINT(1) NOT NULL DEFAULT 0,
    `event_cost_from` DECIMAL(10,2) NULL,
    `event_cost_to` DECIMAL(10,2) NULL,
    `event_url` VARCHAR(2000) NULL,
    `event_latinfohu_partner` TINYINT(1) NOT NULL DEFAULT 0,
    `venue_id` INT UNSIGNED NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_event_slug` (`event_slug`),
    KEY `idx_event_status` (`event_status`),
    KEY `idx_event_start` (`event_start`),
    CONSTRAINT `fk_ec_event_venue` FOREIGN KEY (`venue_id`) REFERENCES `events_venues` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `events_calendar_events` AUTO_INCREMENT = 100000;

CREATE TABLE IF NOT EXISTS `events_calendar_event_organizers` (
    `event_id` INT UNSIGNED NOT NULL,
    `organizer_id` INT UNSIGNED NOT NULL,
    `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`event_id`, `organizer_id`),
    KEY `idx_ec_eo_event` (`event_id`),
    KEY `idx_ec_eo_organizer` (`organizer_id`),
    CONSTRAINT `fk_ec_eo_event` FOREIGN KEY (`event_id`) REFERENCES `events_calendar_events` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ec_eo_organizer` FOREIGN KEY (`organizer_id`) REFERENCES `events_organizers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `events_calendar_event_views` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `esemény_id` INT UNSIGNED NOT NULL,
    `létrehozva` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ip_hash` CHAR(64) NULL,
    PRIMARY KEY (`id`),
    KEY `idx_esemeny_id` (`esemény_id`),
    CONSTRAINT `fk_events_calendar_event_views_event` FOREIGN KEY (`esemény_id`) REFERENCES `events_calendar_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `events_import_settings` (
    `target_table` VARCHAR(64) NOT NULL,
    `delimiter` VARCHAR(8) NOT NULL DEFAULT ';',
    `required_substring` VARCHAR(500) NOT NULL DEFAULT '',
    `column_map` JSON NOT NULL,
    `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`target_table`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `events_categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL COMMENT 'Magyar név (admin, választó, HU nyilvános)',
    `name_en` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Angol; üres = EN nézetben is a magyar név',
    `parent_id` INT UNSIGNED NULL,
    `color` VARCHAR(7) NOT NULL DEFAULT '#6d8f63' COMMENT 'Hex szín, pl. #AABBCC',
    `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_events_categories_parent` (`parent_id`),
    KEY `idx_events_categories_name` (`name`),
    CONSTRAINT `fk_events_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `events_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `events_categories` AUTO_INCREMENT = 10000;

CREATE TABLE IF NOT EXISTS `events_calendar_event_categories` (
    `event_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`event_id`, `category_id`),
    KEY `idx_ec_ec_event` (`event_id`),
    KEY `idx_ec_ec_category` (`category_id`),
    CONSTRAINT `fk_ec_ec_event` FOREIGN KEY (`event_id`) REFERENCES `events_calendar_events` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ec_ec_category` FOREIGN KEY (`category_id`) REFERENCES `events_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `events_tags` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_events_tags_name` (`name`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `events_tags` AUTO_INCREMENT = 20000;

CREATE TABLE IF NOT EXISTS `events_specialtags` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_events_specialtags_name` (`name`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `events_specialtags` AUTO_INCREMENT = 15000;

CREATE TABLE IF NOT EXISTS `events_special_tags` (
    `special_tag_id` INT UNSIGNED NOT NULL,
    `tag_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`special_tag_id`, `tag_id`),
    KEY `idx_events_special_tags_tag` (`tag_id`),
    CONSTRAINT `fk_events_special_tags_special` FOREIGN KEY (`special_tag_id`) REFERENCES `events_specialtags` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_events_special_tags_tag` FOREIGN KEY (`tag_id`) REFERENCES `events_tags` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `events_calendar_event_tags` (
    `event_id` INT UNSIGNED NOT NULL,
    `tag_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`event_id`, `tag_id`),
    KEY `idx_events_evt_event` (`event_id`),
    KEY `idx_events_evt_tag` (`tag_id`),
    CONSTRAINT `fk_events_evt_event` FOREIGN KEY (`event_id`) REFERENCES `events_calendar_events` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_events_evt_tag` FOREIGN KEY (`tag_id`) REFERENCES `events_tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) CSV import presetek kulcsainak frissítése
UPDATE `events_import_settings` SET `target_table` = 'events_calendar_events' WHERE `target_table` = 'naptár_események';
UPDATE `events_import_settings` SET `target_table` = 'events_organizers' WHERE `target_table` = 'organizers';

-- 5) event_status átállítás (régi magyar értékekből)
UPDATE `events_calendar_events` SET `event_status` = 'draft' WHERE `event_status` = 'vázlat';
UPDATE `events_calendar_events` SET `event_status` = 'publish' WHERE `event_status` = 'közzétéve';
UPDATE `events_calendar_events` SET `event_status` = 'trash' WHERE `event_status` = 'törölve';

ALTER TABLE `events_calendar_events`
    MODIFY COLUMN `event_status` VARCHAR(20) NOT NULL DEFAULT 'draft'
    COMMENT 'WordPress post_status (publish, draft, …)';

-- 6) Régi events dátum/idő oszlopok -> event_start / event_end (ha még léteznek)
CALL sp_add_col_if_missing(
    'events_calendar_events',
    'event_start',
    'ALTER TABLE `events_calendar_events` ADD COLUMN `event_start` DATETIME NULL AFTER `event_status`'
);
CALL sp_add_col_if_missing(
    'events_calendar_events',
    'event_end',
    'ALTER TABLE `events_calendar_events` ADD COLUMN `event_end` DATETIME NULL AFTER `event_start`'
);

SET @has_old_start_date := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events_calendar_events' AND COLUMN_NAME = 'event_start_date'
);
SET @has_old_start_time := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events_calendar_events' AND COLUMN_NAME = 'event_start_time'
);
SET @has_old_end_date := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events_calendar_events' AND COLUMN_NAME = 'event_end_date'
);
SET @has_old_end_time := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events_calendar_events' AND COLUMN_NAME = 'event_end_time'
);

SET @do_copy := IF(@has_old_start_date > 0 OR @has_old_start_time > 0 OR @has_old_end_date > 0 OR @has_old_end_time > 0, 1, 0);
SET @sql := IF(
    @do_copy = 1,
    'UPDATE `events_calendar_events`
     SET `event_start` = CASE
             WHEN `event_start_date` IS NULL THEN `event_start`
             WHEN `event_start_time` IS NULL THEN CONCAT(`event_start_date`, '' 00:00:00'')
             ELSE CONCAT(`event_start_date`, '' '', TIME_FORMAT(`event_start_time`, ''%H:%i:%s''))
         END,
         `event_end` = CASE
             WHEN `event_end_date` IS NULL THEN `event_end`
             WHEN `event_end_time` IS NULL THEN CONCAT(`event_end_date`, '' 00:00:00'')
             ELSE CONCAT(`event_end_date`, '' '', TIME_FORMAT(`event_end_time`, ''%H:%i:%s''))
         END',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CALL sp_drop_col_if_exists('events_calendar_events', 'event_start_date');
CALL sp_drop_col_if_exists('events_calendar_events', 'event_start_time');
CALL sp_drop_col_if_exists('events_calendar_events', 'event_end_date');
CALL sp_drop_col_if_exists('events_calendar_events', 'event_end_time');
CALL sp_drop_index_if_exists('events_calendar_events', 'idx_event_start_date');

CALL sp_add_col_if_missing(
    'events_calendar_events',
    'event_start',
    'ALTER TABLE `events_calendar_events` ADD COLUMN `event_start` DATETIME NULL'
);

SET @idx_event_start_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events_calendar_events' AND INDEX_NAME = 'idx_event_start'
);
SET @sql := IF(@idx_event_start_exists = 0, 'ALTER TABLE `events_calendar_events` ADD INDEX `idx_event_start` (`event_start`)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- takarítás
DROP PROCEDURE IF EXISTS sp_rename_if_exists;
DROP PROCEDURE IF EXISTS sp_add_col_if_missing;
DROP PROCEDURE IF EXISTS sp_drop_col_if_exists;
DROP PROCEDURE IF EXISTS sp_drop_index_if_exists;
