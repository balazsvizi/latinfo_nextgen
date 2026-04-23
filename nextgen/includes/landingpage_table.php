<?php
/**
 * landingpage tábla – CREATE / migráció (nyilvános landing + admin lista).
 */
if (!function_exists('ensure_landingpage_table')) {
    function ensure_landingpage_table(PDO $db): void {
        $db->exec("
            CREATE TABLE IF NOT EXISTS nextgen_landing_feedback (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ilyen_legyen TEXT NULL,
                ilyen_ne_legyen TEXT NULL,
                email VARCHAR(255) NULL,
                ip VARCHAR(45) NULL,
                user_agent VARCHAR(512) NULL,
                létrehozva DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        try {
            $db->exec('ALTER TABLE nextgen_landing_feedback MODIFY email VARCHAR(255) NULL');
        } catch (Throwable $e) {
            // tábla már jó, vagy nincs ALTER jog
        }
    }
}
