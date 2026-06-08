-- Publikus esemény főoldal CMS szövegek (felül / alul)
-- Futtatás: mysql ... < events/sql/migration_public_home.sql

CREATE TABLE IF NOT EXISTS `events_public_home` (
    `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `content_top` MEDIUMTEXT NOT NULL,
    `content_bottom` MEDIUMTEXT NOT NULL,
    `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `events_public_home` (`id`, `content_top`, `content_bottom`) VALUES (1, '', '');
