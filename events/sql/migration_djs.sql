-- DJ-k és esemény–DJ kapcsolók (külön entitás a címkéktől)
-- Futtatás: mysql ... < events/sql/migration_djs.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `events_djs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_events_djs_name` (`name`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `events_djs` AUTO_INCREMENT = 25000;

CREATE TABLE IF NOT EXISTS `events_calendar_event_djs` (
    `event_id` INT UNSIGNED NOT NULL,
    `dj_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`event_id`, `dj_id`),
    KEY `idx_events_evt_dj_event` (`event_id`),
    KEY `idx_events_evt_dj_dj` (`dj_id`),
    CONSTRAINT `fk_events_evt_dj_event` FOREIGN KEY (`event_id`) REFERENCES `events_calendar_events` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_events_evt_dj_dj` FOREIGN KEY (`dj_id`) REFERENCES `events_djs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
