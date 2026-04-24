-- Opcionális kapcsolt helyszín (`linked_venue_id`) – megjelenített név + link a nyilvános oldalon.
-- Futtatás meglévő `events_venues` táblán. Ha a constraint már létezik, hagyd ki a második sort.

SET NAMES utf8mb4;

ALTER TABLE `events_venues`
    ADD COLUMN `linked_venue_id` INT UNSIGNED NULL AFTER `address`;

ALTER TABLE `events_venues`
    ADD CONSTRAINT `fk_events_venues_linked_venue`
    FOREIGN KEY (`linked_venue_id`) REFERENCES `events_venues` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;
