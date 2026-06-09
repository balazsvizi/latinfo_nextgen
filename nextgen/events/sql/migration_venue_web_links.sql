-- Helyszín weboldal és Google Maps link mezők.
-- Futtatás: events_venues tábla létezik.

ALTER TABLE `events_venues`
    ADD COLUMN `website_url` VARCHAR(2000) NULL COMMENT 'Helyszín weboldala' AFTER `longitude`,
    ADD COLUMN `google_maps_url` VARCHAR(2000) NULL COMMENT 'Google Maps / térkép link' AFTER `website_url`;
