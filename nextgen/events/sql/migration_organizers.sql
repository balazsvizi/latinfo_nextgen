-- Új `events_organizers` tábla (név + ID, auto increment 200000-tól).
-- Futtatás: mysql ... < events/sql/migration_organizers.sql
--
-- Ha a régi `migration_events.sql` már lefutott (FK: szervezők), futtasd utána:
--   events/sql/migration_naptar_esemeny_fk_to_organizers.sql
-- Régi `organizers` névről: nextgen/database/migration_rename_legacy_tables_to_prefixed.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `events_organizers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_events_organizers_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `events_organizers` AUTO_INCREMENT = 200000;
