-- CSV import mentett beállítások (cél tábla szerint egy sor).
-- Futtatás: mysql ... < events/sql/migration_import_settings.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `events_import_settings` (
    `target_table` VARCHAR(64) NOT NULL,
    `delimiter` VARCHAR(8) NOT NULL DEFAULT ';',
    `required_substring` VARCHAR(500) NOT NULL DEFAULT '',
    `tag_name_filter` VARCHAR(255) NOT NULL DEFAULT '',
    `column_map` JSON NOT NULL,
    `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`target_table`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
