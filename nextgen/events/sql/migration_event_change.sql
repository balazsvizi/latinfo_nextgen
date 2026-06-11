-- Esemény változás / elmaradás jelzése (publikus naptár és eseményoldal).
-- Futtatás: mysql ... < events/sql/migration_event_change.sql
SET NAMES utf8mb4;

ALTER TABLE `events_calendar_events`
    ADD COLUMN `event_change_active` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = aktív változás/elmaradás jelzés' AFTER `event_allday`,
    ADD COLUMN `event_change_type` VARCHAR(20) NULL DEFAULT NULL COMMENT 'cancelled | modified' AFTER `event_change_active`,
    ADD COLUMN `event_change_note` TEXT NULL DEFAULT NULL COMMENT 'Publikus megjegyzés' AFTER `event_change_type`;
