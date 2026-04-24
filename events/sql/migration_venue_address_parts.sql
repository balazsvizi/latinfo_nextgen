-- Helyszín cím bontása: ország, település, irányítószám, utca/cím.
-- Futtatás meglévő `events_venues` táblán (ha a CREATE már lefutott régi oszlopkészlettel).

SET NAMES utf8mb4;

ALTER TABLE `events_venues`
    ADD COLUMN `country` VARCHAR(120) NOT NULL DEFAULT 'Magyarország' AFTER `description`,
    ADD COLUMN `city` VARCHAR(255) NULL AFTER `country`,
    ADD COLUMN `postal_code` VARCHAR(16) NULL AFTER `city`;
