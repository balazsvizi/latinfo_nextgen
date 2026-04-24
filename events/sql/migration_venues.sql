-- Helyszínek tábla (ha csak ezt futtatod régi DB-n, ahol a migration_events.sql még nem tartalmazta).
-- FK: events/sql/migration_event_venue_fk.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `events_venues` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(500) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `description` MEDIUMTEXT NULL,
    `address` TEXT NULL,
    `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_events_venues_slug` (`slug`),
    KEY `idx_events_venues_name` (`name`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
