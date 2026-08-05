-- Publikus esemény főoldal CMS szövegek (felül / alul) + fejléc tip
-- Futtatás: mysql ... < events/sql/migration_public_home.sql

CREATE TABLE IF NOT EXISTS `events_public_home` (
    `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `content_top` MEDIUMTEXT NOT NULL,
    `content_bottom` MEDIUMTEXT NOT NULL,
    `notice_text` VARCHAR(500) NOT NULL DEFAULT '',
    `notice_text_en` VARCHAR(500) NOT NULL DEFAULT '',
    `notice_url` VARCHAR(500) NOT NULL DEFAULT '',
    `notice_color_scheme` VARCHAR(32) NOT NULL DEFAULT 'neon_green',
    `notice_custom_color` CHAR(7) NOT NULL DEFAULT '#39FF14',
    `modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `events_public_home` (
    `id`, `content_top`, `content_bottom`,
    `notice_text`, `notice_text_en`, `notice_url`,
    `notice_color_scheme`, `notice_custom_color`
) VALUES (
    1, '', '',
    'Megújult a Latinfo.hu naptár! Neked hogy tetszik? Írd meg nekünk!',
    'The Latinfo.hu calendar has been renewed! How do you like it? Tell us!',
    '/lanueva/',
    'neon_green',
    '#39FF14'
);
