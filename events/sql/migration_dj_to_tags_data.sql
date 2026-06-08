-- DJ adatok átemelése címkékbe (futtatás migration_tag_types.sql ELŐTT, ha volt events_djs)
-- mysql ... < events/sql/migration_dj_to_tags_data.sql

SET NAMES utf8mb4;

INSERT INTO `events_tags` (`name`, `created`, `modified`)
SELECT d.`name`, d.`created`, d.`modified`
FROM `events_djs` d
WHERE NOT EXISTS (SELECT 1 FROM `events_tags` t WHERE t.`name` = d.`name`);

INSERT IGNORE INTO `events_tag_type_links` (`tag_id`, `tag_type`)
SELECT t.`id`, 'dj'
FROM `events_tags` t
INNER JOIN `events_djs` d ON d.`name` = t.`name`;

INSERT IGNORE INTO `events_calendar_event_tags` (`event_id`, `tag_id`)
SELECT ed.`event_id`, t.`id`
FROM `events_calendar_event_djs` ed
INNER JOIN `events_djs` d ON d.`id` = ed.`dj_id`
INNER JOIN `events_tags` t ON t.`name` = d.`name`;
