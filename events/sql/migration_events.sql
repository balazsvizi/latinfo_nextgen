-- Naptár / Events modul (Latinfo.hu)
-- Futtatás: mysql ... < events/sql/migration_events.sql
-- Tartalmazza az `events_organizers` táblát (ID 200000+), `events_venues` helyszíneket és az esemény–szervező kapcsolótáblát.
-- Meglévő DB, ahol a régi FK még `szervezők`: migration_organizers.sql + migration_naptar_esemeny_fk_to_organizers.sql
-- Régi táblanevekről: nextgen/database/migration_rename_legacy_tables_to_prefixed.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `events_organizers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_events_organizers_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `events_organizers` AUTO_INCREMENT = 200000;

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
    `event_featured_image_url` VARCHAR(2000) NULL DEFAULT NULL,
    `event_latinfohu_partner` TINYINT(1) NOT NULL DEFAULT 0,
    `venue_id` INT UNSIGNED NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_event_slug` (`event_slug`),
    KEY `idx_event_status` (`event_status`),
    KEY `idx_event_start` (`event_start`)
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

-- Helyszínek (venues); az esemény `venue_id` erre hivatkozhat (FK opcionálisan: events/sql/migration_event_venue_fk.sql).
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
