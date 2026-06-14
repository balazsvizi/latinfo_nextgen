-- Admin Google Drive fiók (titkosított refresh token, felhasználónként)

CREATE TABLE IF NOT EXISTS nextgen_admin_gdrive (
    admin_id INT UNSIGNED NOT NULL PRIMARY KEY,
    google_email VARCHAR(255) NOT NULL,
    refresh_token_encrypted TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_nextgen_admin_gdrive_admin FOREIGN KEY (admin_id) REFERENCES nextgen_admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
