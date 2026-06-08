-- DJ adatok átemelése címkékbe
-- Előfeltétel: events_tag_types tábla (migration_tags.sql vagy migration_tag_types_registry.sql)
-- mysql ... < events/sql/migration_dj_to_tags_data.sql

SET NAMES utf8mb4;

INSERT INTO `events_tags` (`name`, `created`, `modified`)
SELECT d.`name`, d.`created`, d.`modified`
FROM `events_djs` d
WHERE NOT EXISTS (SELECT 1 FROM `events_tags` t WHERE t.`name` = d.`name`);

INSERT IGNORE INTO `events_tag_type_links` (`tag_id`, `tag_type_id`)
SELECT t.`id`, ty.`id`
FROM `events_tags` t
INNER JOIN `events_djs` d ON d.`name` = t.`name`
INNER JOIN `events_tag_types` ty ON ty.`code` = 'dj';

INSERT IGNORE INTO `events_calendar_event_tags` (`event_id`, `tag_id`)
SELECT ed.`event_id`, t.`id`
FROM `events_calendar_event_djs` ed
INNER JOIN `events_djs` d ON d.`id` = ed.`dj_id`
INNER JOIN `events_tags` t ON t.`name` = d.`name`;
