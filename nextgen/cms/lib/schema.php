<?php
declare(strict_types=1);

/**
 * CMS séma létrehozása / ellenőrzése (runtime ensure).
 */
function cms_ensure_schema(PDO $db): bool
{
    static $done = false;
    if ($done) {
        return true;
    }

    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `cms_themes` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(120) NOT NULL,
                `slug` VARCHAR(140) NOT NULL,
                `description` VARCHAR(400) NOT NULL DEFAULT '',
                `sort_order` INT NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_cms_themes_slug` (`slug`),
                KEY `idx_cms_themes_active_sort` (`is_active`, `sort_order`, `name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS `cms_posts` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `theme_id` INT UNSIGNED NULL DEFAULT NULL,
                `title` VARCHAR(255) NOT NULL,
                `slug` VARCHAR(200) NOT NULL,
                `excerpt` TEXT NULL,
                `content_html` MEDIUMTEXT NOT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
                `published_at` DATETIME NULL DEFAULT NULL,
                `featured_image_url` VARCHAR(500) NULL DEFAULT NULL,
                `seo_title` VARCHAR(255) NULL DEFAULT NULL,
                `seo_description` VARCHAR(500) NULL DEFAULT NULL,
                `created_by_admin_id` INT UNSIGNED NULL DEFAULT NULL,
                `updated_by_admin_id` INT UNSIGNED NULL DEFAULT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_cms_posts_slug` (`slug`),
                KEY `idx_cms_posts_status_published` (`status`, `published_at`),
                KEY `idx_cms_posts_theme` (`theme_id`),
                KEY `idx_cms_posts_updated` (`updated_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS `cms_post_tags` (
                `post_id` INT UNSIGNED NOT NULL,
                `tag_id` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`post_id`, `tag_id`),
                KEY `idx_cms_post_tags_tag` (`tag_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS `cms_post_views` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `post_id` INT UNSIGNED NOT NULL,
                `metric_type` VARCHAR(40) NOT NULL DEFAULT 'page_view',
                `source` VARCHAR(40) NOT NULL DEFAULT 'direct',
                `ip_hash` CHAR(64) NULL DEFAULT NULL,
                `is_bot` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `occurred_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_cms_views_post_time` (`post_id`, `occurred_at`),
                KEY `idx_cms_views_time` (`occurred_at`),
                KEY `idx_cms_views_bot` (`is_bot`, `occurred_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        cms_seed_default_themes($db);
        $done = true;

        return true;
    } catch (Throwable $e) {
        error_log('cms_ensure_schema: ' . $e->getMessage());

        return false;
    }
}

function cms_tables_available(PDO $db): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    try {
        $db->query('SELECT 1 FROM `cms_posts` LIMIT 1');
        $db->query('SELECT 1 FROM `cms_themes` LIMIT 1');
        $cached = true;
    } catch (Throwable $e) {
        $cached = false;
    }

    return $cached;
}

function cms_seed_default_themes(PDO $db): void
{
    try {
        $cnt = (int) $db->query('SELECT COUNT(*) FROM `cms_themes`')->fetchColumn();
        if ($cnt > 0) {
            return;
        }
        $ins = $db->prepare('
            INSERT INTO `cms_themes` (`name`, `slug`, `description`, `sort_order`, `is_active`)
            VALUES (?, ?, ?, ?, 1)
        ');
        $defaults = [
            ['Hír', 'hir', 'Aktuális hírek és közlemények', 10],
            ['Útmutató', 'utmutato', 'Hasznos tippek, leírások', 20],
            ['Oldal', 'oldal', 'Állandó tartalmi oldalak', 30],
        ];
        foreach ($defaults as [$name, $slug, $desc, $sort]) {
            $ins->execute([$name, $slug, $desc, $sort]);
        }
    } catch (Throwable $e) {
        error_log('cms_seed_default_themes: ' . $e->getMessage());
    }
}
