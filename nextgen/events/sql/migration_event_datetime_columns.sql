-- Esemény időpont mezők összevonása:
-- event_start_date + event_start_time -> event_start (DATETIME)
-- event_end_date + event_end_time -> event_end (DATETIME)
-- Futtatás: mysql ... < events/sql/migration_event_datetime_columns.sql

SET NAMES utf8mb4;

ALTER TABLE `events_calendar_events`
    ADD COLUMN `event_start` DATETIME NULL AFTER `event_status`,
    ADD COLUMN `event_end` DATETIME NULL AFTER `event_start`;

UPDATE `events_calendar_events`
SET `event_start` = CASE
        WHEN `event_start_date` IS NULL THEN NULL
        WHEN `event_start_time` IS NULL THEN CONCAT(`event_start_date`, ' 00:00:00')
        ELSE CONCAT(`event_start_date`, ' ', TIME_FORMAT(`event_start_time`, '%H:%i:%s'))
    END,
    `event_end` = CASE
        WHEN `event_end_date` IS NULL THEN NULL
        WHEN `event_end_time` IS NULL THEN CONCAT(`event_end_date`, ' 00:00:00')
        ELSE CONCAT(`event_end_date`, ' ', TIME_FORMAT(`event_end_time`, '%H:%i:%s'))
    END;

ALTER TABLE `events_calendar_events`
    DROP COLUMN `event_start_date`,
    DROP COLUMN `event_start_time`,
    DROP COLUMN `event_end_date`,
    DROP COLUMN `event_end_time`;

ALTER TABLE `events_calendar_events`
    DROP INDEX `idx_event_start_date`,
    ADD INDEX `idx_event_start` (`event_start`);
