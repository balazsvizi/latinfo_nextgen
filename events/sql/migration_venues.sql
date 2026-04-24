-- Helyszínek tábla (ha csak ezt futtatod régi DB-n, ahol a migration_events.sql még nem tartalmazta).
-- FK: events/sql/migration_event_venue_fk.sql
-- Ha a tábla már létezik régi sémával (csak `address`), futtasd: migration_venue_address_parts.sql
-- Kapcsolt helyszín (`linked_venue_id`): migration_venue_linked_venue.sql

SET NAMES utf8mb4;

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
