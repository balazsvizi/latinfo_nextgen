-- GPS koordináták a helyszínekhez (nyilvános térkép megjelenítéshez).
-- Futtatás: events_venues tábla létezik (migration_events.sql / migration_venues.sql).

ALTER TABLE `events_venues`
    ADD COLUMN `latitude` DECIMAL(10, 7) NULL COMMENT 'WGS-84 szélesség' AFTER `address`,
    ADD COLUMN `longitude` DECIMAL(10, 7) NULL COMMENT 'WGS-84 hosszúság' AFTER `latitude`;
