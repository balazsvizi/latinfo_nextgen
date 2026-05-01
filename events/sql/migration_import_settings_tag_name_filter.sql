-- CSV import: opcionális címkenév előfeltétel (events_import_settings)
SET NAMES utf8mb4;

ALTER TABLE `events_import_settings`
    ADD COLUMN `tag_name_filter` VARCHAR(255) NOT NULL DEFAULT '' AFTER `required_substring`;
