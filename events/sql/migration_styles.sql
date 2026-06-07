-- Stílusok és esemény–stílus kapcsolók (fő + kiegészítő)
-- Futtatás: mysql ... < events/sql/migration_styles.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `events_styles` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_events_styles_name` (`name`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `events_styles` AUTO_INCREMENT = 26000;

CREATE TABLE IF NOT EXISTS `events_calendar_event_main_styles` (
    `event_id` INT UNSIGNED NOT NULL,
    `style_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`event_id`, `style_id`),
    KEY `idx_events_evt_main_style_event` (`event_id`),
    KEY `idx_events_evt_main_style_style` (`style_id`),
    CONSTRAINT `fk_events_evt_main_style_event` FOREIGN KEY (`event_id`) REFERENCES `events_calendar_events` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_events_evt_main_style_style` FOREIGN KEY (`style_id`) REFERENCES `events_styles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `events_calendar_event_supplementary_styles` (
    `event_id` INT UNSIGNED NOT NULL,
    `style_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`event_id`, `style_id`),
    KEY `idx_events_evt_supp_style_event` (`event_id`),
    KEY `idx_events_evt_supp_style_style` (`style_id`),
    CONSTRAINT `fk_events_evt_supp_style_event` FOREIGN KEY (`event_id`) REFERENCES `events_calendar_events` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_events_evt_supp_style_style` FOREIGN KEY (`style_id`) REFERENCES `events_styles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
