-- Naptár / Events modul (Latinfo.hu)
-- Futtatás: mysql ... < events/sql/migration_events.sql
-- Tartalmazza az `events_organizers` táblát (ID 200000+) és az események FK-ját oda.
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
    `event_start_date` DATE NULL,
    `event_start_time` TIME NULL,
    `event_end_date` DATE NULL,
    `event_end_time` TIME NULL,
    `event_allday` TINYINT(1) NOT NULL DEFAULT 0,
    `event_cost_from` DECIMAL(10,2) NULL,
    `event_cost_to` DECIMAL(10,2) NULL,
    `event_url` VARCHAR(2000) NULL,
    `event_latinfohu_partner` TINYINT(1) NOT NULL DEFAULT 0,
    `organizer_id` INT UNSIGNED NULL,
    `venue_id` INT UNSIGNED NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_event_slug` (`event_slug`),
    KEY `idx_event_status` (`event_status`),
    KEY `idx_event_start_date` (`event_start_date`),
    KEY `idx_organizer_id` (`organizer_id`),
    CONSTRAINT `fk_events_calendar_event_organizer` FOREIGN KEY (`organizer_id`) REFERENCES `events_organizers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `events_calendar_events` AUTO_INCREMENT = 100000;

CREATE TABLE IF NOT EXISTS `events_calendar_event_views` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `esemény_id` INT UNSIGNED NOT NULL,
    `létrehozva` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ip_hash` CHAR(64) NULL,
    PRIMARY KEY (`id`),
    KEY `idx_esemeny_id` (`esemény_id`),
    CONSTRAINT `fk_events_calendar_event_views_event` FOREIGN KEY (`esemény_id`) REFERENCES `events_calendar_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
