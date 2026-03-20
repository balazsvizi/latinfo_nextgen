<?php

/**
 * Levélsablonok modul bootstrap.
 * Biztosítja, hogy a szükséges tábla létezzen.
 */
function ensure_levelsablonok_table(PDO $db): void
{
    $db->exec("
        CREATE TABLE IF NOT EXISTS levélsablonok (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            név VARCHAR(255) NOT NULL,
            kód VARCHAR(100) NOT NULL UNIQUE,
            tárgy VARCHAR(255) NOT NULL DEFAULT '',
            megjegyzés TEXT NULL,
            html_tartalom MEDIUMTEXT NOT NULL,
            létrehozva DATETIME DEFAULT CURRENT_TIMESTAMP,
            módosítva DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Régi telepítéseknél lehet, hogy a tárgy oszlop hiányzik.
    try {
        $col = $db->query("SHOW COLUMNS FROM levélsablonok LIKE 'tárgy'")->fetch();
        if (!$col) {
            $db->exec("ALTER TABLE levélsablonok ADD COLUMN tárgy VARCHAR(255) NOT NULL DEFAULT '' AFTER kód");
        }
    } catch (Throwable $e) {
        // nincs ALTER jog -> marad fallback tárgy a kódban
    }
}

