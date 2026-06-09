-- Összes helyszín-mező egy lépésben (GPS + web linkek).
-- Futtatás: events_venues tábla létezik, az oszlopok még nincsenek benne.
-- Ha már futott részleges migráció, futtasd külön a hiányzó fájlokat, vagy töröld a már létező oszlopok ADD sorait.

ALTER TABLE `events_venues`
    ADD COLUMN `latitude` DECIMAL(10, 7) NULL COMMENT 'WGS-84 szélesség' AFTER `address`,
    ADD COLUMN `longitude` DECIMAL(10, 7) NULL COMMENT 'WGS-84 hosszúság' AFTER `latitude`,
    ADD COLUMN `website_url` VARCHAR(2000) NULL COMMENT 'Helyszín weboldala' AFTER `longitude`,
    ADD COLUMN `google_maps_url` VARCHAR(2000) NULL COMMENT 'Google Maps / térkép link' AFTER `website_url`;
