-- Kiemelt kép URL az eseményhez (OG + megjelenítő).
-- Futtatás: mysql ... < events/sql/migration_event_featured_image.sql

SET NAMES utf8mb4;

ALTER TABLE `events_calendar_events`
    ADD COLUMN `event_featured_image_url` VARCHAR(2000) NULL DEFAULT NULL
    AFTER `event_url`;
