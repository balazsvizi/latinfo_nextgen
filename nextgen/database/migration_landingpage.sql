-- NextGen landing: visszajelzés és értesítési e-mail
CREATE TABLE IF NOT EXISTS landingpage (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ilyen_legyen TEXT NULL,
    ilyen_ne_legyen TEXT NULL,
    email VARCHAR(255) NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(512) NULL,
    létrehozva DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
