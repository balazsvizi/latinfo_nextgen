-- Kontakt típusok és kapcsolat (meglévő telepítésekhez)

CREATE TABLE IF NOT EXISTS kontakt_típusok (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    név VARCHAR(100) NOT NULL UNIQUE,
    létrehozva DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kontakt_típus_kapcsolat (
    kontakt_id INT UNSIGNED NOT NULL,
    típus_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (kontakt_id, típus_id),
    FOREIGN KEY (kontakt_id) REFERENCES kontaktok(id) ON DELETE CASCADE,
    FOREIGN KEY (típus_id) REFERENCES kontakt_típusok(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

