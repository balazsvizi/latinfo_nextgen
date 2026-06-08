-- Címke típusok (DJ, Zenekar, Tanár, Művész, Szervező)
-- Futtatás: mysql ... < events/sql/migration_tag_types.sql
-- Ha volt events_djs: előbb futtasd a migration_dj_to_tags_data.sql fájlt!

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `events_tag_type_links` (
    `tag_id` INT UNSIGNED NOT NULL,
    `tag_type` ENUM('dj', 'zenekar', 'tanar', 'muvesz', 'szervezo') NOT NULL,
    PRIMARY KEY (`tag_id`, `tag_type`),
    KEY `idx_events_tag_type_links_type` (`tag_type`),
    CONSTRAINT `fk_events_tag_type_links_tag` FOREIGN KEY (`tag_id`) REFERENCES `events_tags` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `events_calendar_event_djs`;
DROP TABLE IF EXISTS `events_djs`;
