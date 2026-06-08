-- event_status: magyar ENUM → WordPress post_status szerű VARCHAR (meglévő tábla frissítése).
-- Futtatás: mysql ... < events/sql/migration_event_status_wp_post_status.sql

SET NAMES utf8mb4;

UPDATE `events_calendar_events` SET `event_status` = 'draft' WHERE `event_status` = 'vázlat';
UPDATE `events_calendar_events` SET `event_status` = 'publish' WHERE `event_status` = 'közzétéve';
UPDATE `events_calendar_events` SET `event_status` = 'trash' WHERE `event_status` = 'törölve';

ALTER TABLE `events_calendar_events`
    MODIFY COLUMN `event_status` VARCHAR(20) NOT NULL DEFAULT 'draft'
    COMMENT 'WordPress post_status (publish, draft, …)';
