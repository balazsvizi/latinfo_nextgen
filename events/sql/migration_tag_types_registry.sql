-- Címke típusok törzsadat (bővíthető, szerkeszthető) + kapcsolótábla átállítás
-- Futtatás: mysql ... < events/sql/migration_tag_types_registry.sql
-- Ha volt ENUM alapú events_tag_type_links: ez átemeli az adatokat.

SET NAMES utf8mb4;

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

CREATE TABLE IF NOT EXISTS `events_tag_type_links_new` (
    `tag_id` INT UNSIGNED NOT NULL,
    `tag_type_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`tag_id`, `tag_type_id`),
    KEY `idx_events_tag_type_links_type` (`tag_type_id`),
    CONSTRAINT `fk_events_tag_type_links_tag_new` FOREIGN KEY (`tag_id`) REFERENCES `events_tags` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_events_tag_type_links_type_new` FOREIGN KEY (`tag_type_id`) REFERENCES `events_tag_types` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_enum_col := (
    SELECT COUNT(*)
    FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'events_tag_type_links'
      AND `COLUMN_NAME` = 'tag_type'
);

SET @has_id_col := (
    SELECT COUNT(*)
    FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'events_tag_type_links'
      AND `COLUMN_NAME` = 'tag_type_id'
);

SET @migrate_sql := IF(
    @has_enum_col > 0,
    'INSERT IGNORE INTO `events_tag_type_links_new` (`tag_id`, `tag_type_id`)
     SELECT l.`tag_id`, t.`id`
     FROM `events_tag_type_links` l
     INNER JOIN `events_tag_types` t ON t.`code` = l.`tag_type`',
    IF(
        @has_id_col > 0,
        'INSERT IGNORE INTO `events_tag_type_links_new` (`tag_id`, `tag_type_id`)
         SELECT l.`tag_id`, l.`tag_type_id`
         FROM `events_tag_type_links` l
         WHERE l.`tag_type_id` IS NOT NULL',
        'SELECT 1'
    )
);

PREPARE stmt FROM @migrate_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

DROP TABLE IF EXISTS `events_calendar_event_djs`;
DROP TABLE IF EXISTS `events_djs`;
DROP TABLE IF EXISTS `events_tag_type_links`;
RENAME TABLE `events_tag_type_links_new` TO `events_tag_type_links`;
