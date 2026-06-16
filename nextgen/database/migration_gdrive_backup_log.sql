-- Google Drive mentés napló

CREATE TABLE IF NOT EXISTS nextgen_gdrive_backup_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NULL,
    google_email VARCHAR(255) NULL,
    status ENUM('running','ok','error') NOT NULL DEFAULT 'running',
    include_db TINYINT(1) NOT NULL DEFAULT 1,
    include_files TINYINT(1) NOT NULL DEFAULT 1,
    date_filter VARCHAR(20) NOT NULL DEFAULT 'all',
    date_from DATE NULL,
    sql_drive_name VARCHAR(255) NULL,
    zip_drive_name VARCHAR(255) NULL,
    log_text TEXT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at DATETIME NULL,
    CONSTRAINT fk_gdrive_backup_log_admin FOREIGN KEY (admin_id) REFERENCES nextgen_admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
