<?php
declare(strict_types=1);

final class HbSchemaInstaller
{
    public static function installSchema(PDO $db): void
    {
        $db->exec('SET NAMES utf8mb4');
        $db->exec('SET FOREIGN_KEY_CHECKS = 0');

        $statements = [
            "CREATE TABLE IF NOT EXISTS `hb_subscribers` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `slug` VARCHAR(100) NOT NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_hb_subscribers_slug` (`slug`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `hb_users` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `subscriber_id` INT UNSIGNED NOT NULL,
                `email` VARCHAR(255) NOT NULL,
                `password_hash` VARCHAR(255) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `role` ENUM('tenant_admin', 'host', 'cleaner', 'local_staff') NOT NULL DEFAULT 'tenant_admin',
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `locale` VARCHAR(5) NOT NULL DEFAULT 'hu',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_hb_users_subscriber_email` (`subscriber_id`, `email`),
                KEY `idx_hb_users_subscriber` (`subscriber_id`),
                CONSTRAINT `fk_hb_users_subscriber` FOREIGN KEY (`subscriber_id`) REFERENCES `hb_subscribers` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `hb_properties` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `subscriber_id` INT UNSIGNED NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `city` VARCHAR(255) NULL DEFAULT NULL,
                `address` VARCHAR(500) NULL DEFAULT NULL,
                `check_in_time` TIME NOT NULL DEFAULT '16:00:00',
                `check_out_time` TIME NOT NULL DEFAULT '10:00:00',
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_hb_properties_subscriber` (`subscriber_id`),
                CONSTRAINT `fk_hb_properties_subscriber` FOREIGN KEY (`subscriber_id`) REFERENCES `hb_subscribers` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `hb_units` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `property_id` INT UNSIGNED NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `max_guests` INT UNSIGNED NOT NULL DEFAULT 1,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_hb_units_property` (`property_id`),
                CONSTRAINT `fk_hb_units_property` FOREIGN KEY (`property_id`) REFERENCES `hb_properties` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `hb_bookings` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` INT UNSIGNED NOT NULL,
                `subscriber_id` INT UNSIGNED NOT NULL,
                `guest_name` VARCHAR(255) NOT NULL,
                `adults` INT UNSIGNED NOT NULL DEFAULT 1,
                `children` INT UNSIGNED NOT NULL DEFAULT 0,
                `check_in` DATE NOT NULL,
                `check_out` DATE NOT NULL,
                `notes` TEXT NULL DEFAULT NULL,
                `created_by` INT UNSIGNED NULL DEFAULT NULL,
                `updated_by` INT UNSIGNED NULL DEFAULT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_hb_bookings_unit_dates` (`unit_id`, `check_in`, `check_out`),
                KEY `idx_hb_bookings_subscriber` (`subscriber_id`),
                CONSTRAINT `fk_hb_bookings_unit` FOREIGN KEY (`unit_id`) REFERENCES `hb_units` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_hb_bookings_subscriber` FOREIGN KEY (`subscriber_id`) REFERENCES `hb_subscribers` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_hb_bookings_created_by` FOREIGN KEY (`created_by`) REFERENCES `hb_users` (`id`) ON DELETE SET NULL,
                CONSTRAINT `fk_hb_bookings_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `hb_users` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `hb_activity_log` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `subscriber_id` INT UNSIGNED NOT NULL,
                `user_id` INT UNSIGNED NULL DEFAULT NULL,
                `action` VARCHAR(100) NOT NULL,
                `entity_type` VARCHAR(50) NULL DEFAULT NULL,
                `entity_id` INT UNSIGNED NULL DEFAULT NULL,
                `details` TEXT NULL DEFAULT NULL,
                `ip_address` VARCHAR(45) NULL DEFAULT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_hb_activity_subscriber_created` (`subscriber_id`, `created_at`),
                KEY `idx_hb_activity_user` (`user_id`),
                CONSTRAINT `fk_hb_activity_subscriber` FOREIGN KEY (`subscriber_id`) REFERENCES `hb_subscribers` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_hb_activity_user` FOREIGN KEY (`user_id`) REFERENCES `hb_users` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($statements as $sql) {
            $db->exec($sql);
        }

        $db->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * @return list<string>
     */
    public static function seedDemo(PDO $db): array
    {
        $count = (int) $db->query('SELECT COUNT(*) FROM hb_subscribers')->fetchColumn();
        if ($count > 0) {
            return ['Seed kihagyva – már van előfizető.'];
        }

        $db->beginTransaction();

        $db->exec("INSERT INTO hb_subscribers (name, slug, active) VALUES ('Vizi Balázs', 'vizi-balazs', 1)");
        $subscriberId = (int) $db->lastInsertId();

        $passwordHash = password_hash('HostBase2026!', PASSWORD_DEFAULT);
        $stmt = $db->prepare('
            INSERT INTO hb_users (subscriber_id, email, password_hash, name, role, locale, active)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ');
        $stmt->execute([
            $subscriberId,
            'balazs@vizi.hu',
            $passwordHash,
            'Vizi Balázs',
            'tenant_admin',
            'hu',
        ]);

        $properties = [
            ['Marosi Ház', null, null, 6],
            ['Bázis Vendégház', 'Hajdúnánás', null, 8],
        ];

        $sort = 0;
        foreach ($properties as [$name, $city, $address, $maxGuests]) {
            $stmt = $db->prepare('
                INSERT INTO hb_properties (subscriber_id, name, city, address, check_in_time, check_out_time, sort_order, active)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ');
            $stmt->execute([$subscriberId, $name, $city, $address, '16:00:00', '10:00:00', $sort++]);
            $propertyId = (int) $db->lastInsertId();

            $stmt = $db->prepare('
                INSERT INTO hb_units (property_id, name, max_guests, active)
                VALUES (?, ?, ?, 1)
            ');
            $stmt->execute([$propertyId, 'Egész ház', $maxGuests]);
        }

        $db->commit();

        return [
            'Seed adatok létrehozva.',
            'Belépés: balazs@vizi.hu / HostBase2026!',
        ];
    }

    /**
     * @return list<string>
     */
    public static function install(PDO $db): array
    {
        self::installSchema($db);

        return self::seedDemo($db);
    }
}
