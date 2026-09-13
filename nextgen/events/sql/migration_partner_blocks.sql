-- Partnereink publikus oldal blokkjai (partner / cím / HTML).
-- Futtatás: mysql ... < events/sql/migration_partner_blocks.sql
-- A PHP ensure_schema is létrehozza / bővíti, ha hiányzik.

CREATE TABLE IF NOT EXISTS `events_partner_blocks` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `block_type` VARCHAR(16) NOT NULL DEFAULT 'partner',
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_visible` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `title` VARCHAR(255) NOT NULL DEFAULT '',
    `title_en` VARCHAR(255) NOT NULL DEFAULT '',
    `heading_level` TINYINT UNSIGNED NOT NULL DEFAULT 2,
    `link_url` VARCHAR(500) NOT NULL DEFAULT '',
    `logo_url` VARCHAR(500) NOT NULL DEFAULT '',
    `show_logo` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `show_name` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `logo_size` VARCHAR(8) NOT NULL DEFAULT 'md',
    `body` MEDIUMTEXT NOT NULL,
    `body_en` MEDIUMTEXT NOT NULL,
    `note_before` MEDIUMTEXT NOT NULL,
    `note_before_en` MEDIUMTEXT NOT NULL,
    `note_after` MEDIUMTEXT NOT NULL,
    `note_after_en` MEDIUMTEXT NOT NULL,
    `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_events_partner_blocks_sort` (`sort_order`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Meglévő tábla bővítése (ha a fenti CREATE már korábban lefutott):
-- ALTER TABLE `events_partner_blocks`
--   ADD COLUMN `show_logo` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 AFTER `logo_url`,
--   ADD COLUMN `show_name` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 AFTER `show_logo`,
--   ADD COLUMN `logo_size` VARCHAR(8) NOT NULL DEFAULT 'md' AFTER `show_name`;
