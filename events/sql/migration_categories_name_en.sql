-- Kategória angol megnevezés (üres = EN nézetben is a magyar név)
-- Futtatás: mysql ... < events/sql/migration_categories_name_en.sql

SET NAMES utf8mb4;

ALTER TABLE `events_categories`
    ADD COLUMN `name_en` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Angol név; üres = EN nézetben a name (HU) jelenik meg' AFTER `name`;
