-- Esemény címkék, speciális csoportok, kapcsolók
-- Futtatás: mysql ... < events/sql/migration_tags.sql

SET NAMES utf8mb4;

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

CREATE TABLE IF NOT EXISTS `events_tag_types` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(64) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `icon` VARCHAR(16) NOT NULL DEFAULT '🏷️',
    `tone` VARCHAR(32) NOT NULL DEFAULT 'default',
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_events_tag_types_code` (`code`),
    KEY `idx_events_tag_types_sort` (`sort_order`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `events_tag_types` (`code`, `name`, `icon`, `tone`, `sort_order`) VALUES
    ('dj', 'DJ', '🎧', 'dj', 10),
    ('zenekar', 'Zenekar', '🎸', 'zenekar', 20),
    ('tanar', 'Tanár', '📚', 'tanar', 30),
    ('muvesz', 'Művész', '🎨', 'muvesz', 40),
    ('szervezo', 'Szervező', '🎪', 'szervezo', 50);

CREATE TABLE IF NOT EXISTS `events_tag_type_links` (
    `tag_id` INT UNSIGNED NOT NULL,
    `tag_type_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`tag_id`, `tag_type_id`),
    KEY `idx_events_tag_type_links_type` (`tag_type_id`),
    CONSTRAINT `fk_events_tag_type_links_tag` FOREIGN KEY (`tag_id`) REFERENCES `events_tags` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_events_tag_type_links_type` FOREIGN KEY (`tag_type_id`) REFERENCES `events_tag_types` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
