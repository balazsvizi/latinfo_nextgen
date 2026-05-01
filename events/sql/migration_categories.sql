-- Esemény kategóriák (hierarchikus, egyéni színnel)
-- Futtatás: mysql ... < events/sql/migration_categories.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `events_categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
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

-- Új kategóriák ID-je 10000-től induljon.
ALTER TABLE `events_categories` AUTO_INCREMENT = 10000;

-- Kapcsolótábla (egy eseményhez több kategória is rendelhető).
CREATE TABLE IF NOT EXISTS `events_calendar_event_categories` (
    `event_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`event_id`, `category_id`),
    KEY `idx_ec_ec_event` (`event_id`),
    KEY `idx_ec_ec_category` (`category_id`),
    CONSTRAINT `fk_ec_ec_event` FOREIGN KEY (`event_id`) REFERENCES `events_calendar_events` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ec_ec_category` FOREIGN KEY (`category_id`) REFERENCES `events_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
