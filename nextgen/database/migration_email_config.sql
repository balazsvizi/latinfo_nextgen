-- E-mail / SMTP fiókok (több fiók, jelszó titkosítva) – meglévő telepítésekhez

CREATE TABLE IF NOT EXISTS email_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    név VARCHAR(100) NOT NULL,
    host VARCHAR(255) NOT NULL,
    port SMALLINT UNSIGNED NOT NULL DEFAULT 587,
    titkosítás ENUM('','tls','ssl') NOT NULL DEFAULT 'tls',
    felhasználó VARCHAR(255) NOT NULL DEFAULT '',
    jelszó_titkosított TEXT NULL,
    from_email VARCHAR(255) NOT NULL,
    from_name VARCHAR(255) NOT NULL DEFAULT '',
    alapértelmezett TINYINT(1) NOT NULL DEFAULT 0,
    létrehozva DATETIME DEFAULT CURRENT_TIMESTAMP,
    módosítva DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
