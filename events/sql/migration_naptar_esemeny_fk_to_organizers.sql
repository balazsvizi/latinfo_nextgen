-- `events_calendar_events.organizer_id` átkötése `szervezők` → `events_organizers`.
-- Előfeltétel: `events_organizers` tábla létezik (migration_organizers.sql).
-- Futtatás: mysql ... < events/sql/migration_naptar_esemeny_fk_to_organizers.sql
-- Megjegyzés: ha a tábla már `events_calendar_events` néven fut, a DROP/ADD constraint nevek lehetnek fk_naptar_esemeny_szervezo / fk_events_calendar_event_organizer.

SET NAMES utf8mb4;

ALTER TABLE `events_calendar_events` DROP FOREIGN KEY `fk_naptar_esemeny_szervezo`;

UPDATE `events_calendar_events` SET `organizer_id` = NULL WHERE `organizer_id` IS NOT NULL;

ALTER TABLE `events_calendar_events`
    ADD CONSTRAINT `fk_events_calendar_event_organizer` FOREIGN KEY (`organizer_id`) REFERENCES `events_organizers` (`id`) ON DELETE SET NULL;
