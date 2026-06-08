-- Esemény → helyszín idegen kulcs (egyszer futtasd, ha a táblák már léteznek).
-- Ha „Duplicate foreign key constraint name” → a FK már megvan, hagyd ki.
-- Előfeltétel: `events_venues`, `events_calendar_events` létezik (`venue_id` oszlop).

SET NAMES utf8mb4;

UPDATE `events_calendar_events` SET `venue_id` = NULL
WHERE `venue_id` IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM `events_venues` v WHERE v.id = `events_calendar_events`.`venue_id`);

ALTER TABLE `events_calendar_events`
ADD CONSTRAINT `fk_ec_event_venue` FOREIGN KEY (`venue_id`) REFERENCES `events_venues` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
