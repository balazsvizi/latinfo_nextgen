-- Exporter modul: mentett lekérdezések + kapcsolatok (futtasd az adatbázison)

-- Mentett SQL lekérdezések (connection_id = melyik adatbázis kapcsolat, NULL/0 = alapértelmezett)
CREATE TABLE IF NOT EXISTS nextgen_exporter_queries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    név VARCHAR(200) NOT NULL,
    query_sql TEXT NOT NULL,
    megjegyzés TEXT NULL,
    connection_id INT UNSIGNED NULL DEFAULT NULL,
    létrehozva DATETIME DEFAULT CURRENT_TIMESTAMP,
    módosítva DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mentett adatbázis kapcsolatok
CREATE TABLE IF NOT EXISTS nextgen_exporter_connections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    név VARCHAR(200) NOT NULL,
    host VARCHAR(255) NOT NULL DEFAULT 'localhost',
    port SMALLINT UNSIGNED NOT NULL DEFAULT 3306,
    dbname VARCHAR(255) NOT NULL,
    felhasználó VARCHAR(255) NOT NULL DEFAULT '',
    jelszó_titkosított TEXT NULL,
    létrehozva DATETIME DEFAULT CURRENT_TIMESTAMP,
    módosítva DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
