-- Helyszín weboldal és Google Maps link mezők.
-- Futtatás: events_venues tábla létezik.
-- Megjegyzés: az `address` oszlop után kerülnek (nem kell, hogy létezzen a `longitude`).

ALTER TABLE `events_venues`
    ADD COLUMN `website_url` VARCHAR(2000) NULL COMMENT 'Helyszín weboldala' AFTER `address`,
    ADD COLUMN `google_maps_url` VARCHAR(2000) NULL COMMENT 'Google Maps / térkép link' AFTER `website_url`;
