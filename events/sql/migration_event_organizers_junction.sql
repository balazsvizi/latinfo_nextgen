-- Esemény ↔ több szervező kapcsolótábla; régi egyetlen `organizer_id` oszlop átköltése (ha még megvan).
-- Futtatás: mysql ... < events/sql/migration_event_organizers_junction.sql
-- Előfeltétel: `events_calendar_events`, `events_organizers` létezik.
-- Új telepítés: a `migration_events.sql` már tartalmazza a kapcsolótáblát és nincs `organizer_id` az eseményen –
--   ebben az esetben csak a CREATE IF NOT EXISTS rész kell; az INSERT/ALTER sorokat ne futtasd (hibát adnak).
-- A DROP FOREIGN KEY név eltérhet: SHOW CREATE TABLE `events_calendar_events`;

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `events_calendar_event_organizers` (
    `event_id` INT UNSIGNED NOT NULL,
    `organizer_id` INT UNSIGNED NOT NULL,
    `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`event_id`, `organizer_id`),
    KEY `idx_ec_eo_event` (`event_id`),
    KEY `idx_ec_eo_organizer` (`organizer_id`),
    CONSTRAINT `fk_ec_eo_event` FOREIGN KEY (`event_id`) REFERENCES `events_calendar_events` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ec_eo_organizer` FOREIGN KEY (`organizer_id`) REFERENCES `events_organizers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `events_calendar_event_organizers` (`event_id`, `organizer_id`, `sort_order`)
SELECT `id`, `organizer_id`, 0
FROM `events_calendar_events`
WHERE `organizer_id` IS NOT NULL;

-- FK név eltérhet (SHOW CREATE TABLE `events_calendar_events`;).
ALTER TABLE `events_calendar_events` DROP FOREIGN KEY `fk_events_calendar_event_organizer`;

ALTER TABLE `events_calendar_events` DROP COLUMN `organizer_id`;
