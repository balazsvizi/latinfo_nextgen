-- Kontakt típusok és kapcsolat (meglévő telepítésekhez)

CREATE TABLE IF NOT EXISTS finance_contact_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    név VARCHAR(100) NOT NULL UNIQUE,
    létrehozva DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_contact_type_links (
    kontakt_id INT UNSIGNED NOT NULL,
    típus_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (kontakt_id, típus_id),
    FOREIGN KEY (kontakt_id) REFERENCES finance_contacts(id) ON DELETE CASCADE,
    FOREIGN KEY (típus_id) REFERENCES finance_contact_types(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

