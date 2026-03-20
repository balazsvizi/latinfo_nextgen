-- Latinfo.hu Backoffice - Adatbázis séma
-- PHP MySQL - Szervezők, kontaktok, számlázás

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Címkék (új címke szabadon felvehető, máshol is használható)
CREATE TABLE IF NOT EXISTS címkék (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    név VARCHAR(100) NOT NULL UNIQUE,
    szín CHAR(7) NOT NULL DEFAULT '#6366F1',
    létrehozva DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Szervezők
CREATE TABLE IF NOT EXISTS szervezők (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    név VARCHAR(255) NOT NULL,
    létrehozva DATETIME DEFAULT CURRENT_TIMESTAMP,
    módosítva DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adminok (előbb kell, mert más táblák hivatkoznak rá)
-- szint: admin = normál admin, superadmin = Admin menü + admin kezelés
CREATE TABLE IF NOT EXISTS adminok (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    név VARCHAR(255) NOT NULL,
    felhasználónév VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NULL,
    jelszó_hash VARCHAR(255) NOT NULL,
    szint ENUM('admin','superadmin') NOT NULL DEFAULT 'admin',
    aktív TINYINT(1) NOT NULL DEFAULT 1,
    létrehozva DATETIME DEFAULT CURRENT_TIMESTAMP,
    módosítva DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Szervező - címke kapcsolat (N:N)
CREATE TABLE IF NOT EXISTS szervező_címkék (
    szervező_id INT UNSIGNED NOT NULL,
    címke_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (szervező_id, címke_id),
    FOREIGN KEY (szervező_id) REFERENCES szervezők(id) ON DELETE CASCADE,
    FOREIGN KEY (címke_id) REFERENCES címkék(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Szervező megjegyzések (időbélyeges log)
CREATE TABLE IF NOT EXISTS szervező_megjegyzések (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    szervező_id INT UNSIGNED NOT NULL,
    megjegyzés TEXT NOT NULL,
    létrehozva DATETIME DEFAULT CURRENT_TIMESTAMP,
    admin_id INT UNSIGNED NULL,
    FOREIGN KEY (szervező_id) REFERENCES szervezők(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Szervező log (történések)
CREATE TABLE IF NOT EXISTS szervező_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    szervező_id INT UNSIGNED NOT NULL,
    esemény VARCHAR(500) NOT NULL,
    részletek TEXT NULL,
    létrehozva DATETIME DEFAULT CURRENT_TIMESTAMP,
    admin_id INT UNSIGNED NULL,
    FOREIGN KEY (szervező_id) REFERENCES szervezők(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kontaktok
CREATE TABLE IF NOT EXISTS kontaktok (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    név VARCHAR(255) NOT NULL,
    email VARCHAR(255) NULL,
    telefon VARCHAR(50) NULL,
    fb VARCHAR(255) NULL,
    egyéb_kontakt VARCHAR(255) NULL,
    létrehozva DATETIME DEFAULT CURRENT_TIMESTAMP,
    módosítva DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kontakt típusok (címkeszerű értékek a kontaktokhoz)
CREATE TABLE IF NOT EXISTS kontakt_típusok (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    név VARCHAR(100) NOT NULL UNIQUE,
    leírás TEXT NULL,
    létrehozva DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kontakt - típus kapcsolat (N:N)
CREATE TABLE IF NOT EXISTS kontakt_típus_kapcsolat (
    kontakt_id INT UNSIGNED NOT NULL,
    típus_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (kontakt_id, típus_id),
    FOREIGN KEY (kontakt_id) REFERENCES kontaktok(id) ON DELETE CASCADE,
    FOREIGN KEY (típus_id) REFERENCES kontakt_típusok(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kontakt megjegyzések (időbélyeges log)
CREATE TABLE IF NOT EXISTS kontakt_megjegyzések (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kontakt_id INT UNSIGNED NOT NULL,
    megjegyzés TEXT NOT NULL,
    létrehozva DATETIME DEFAULT CURRENT_TIMESTAMP,
    admin_id INT UNSIGNED NULL,
    FOREIGN KEY (kontakt_id) REFERENCES kontaktok(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Szervező - Kontakt kapcsolat (N:N)
CREATE TABLE IF NOT EXISTS szervező_kontakt (
    szervező_id INT UNSIGNED NOT NULL,
    kontakt_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (szervező_id, kontakt_id),
    FOREIGN KEY (szervező_id) REFERENCES szervezők(id) ON DELETE CASCADE,
    FOREIGN KEY (kontakt_id) REFERENCES kontaktok(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Számlázási címek (egy szervezőhöz több, egy primary)
CREATE TABLE IF NOT EXISTS számlázási_címek (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    szervező_id INT UNSIGNED NOT NULL,
    név VARCHAR(255) NOT NULL,
    ország VARCHAR(100) NOT NULL,
    irsz VARCHAR(20) NOT NULL,
    település VARCHAR(100) NOT NULL DEFAULT '',
    cím VARCHAR(500) NOT NULL,
    adószám VARCHAR(50) NULL,
    megjegyzés TEXT NULL,
    alapértelmezett TINYINT(1) NOT NULL DEFAULT 0,
    létrehozva DATETIME DEFAULT CURRENT_TIMESTAMP,
    módosítva DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (szervező_id) REFERENCES szervezők(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Számlák
CREATE TABLE IF NOT EXISTS számlák (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    szervező_id INT UNSIGNED NOT NULL,
    számla_szám VARCHAR(50) NOT NULL,
    dátum DATE NOT NULL,
    összeg DECIMAL(12,2) NOT NULL,
    belső_megjegyzés TEXT NULL,
    státusz ENUM('generált','kiküldve','kiegyenlítve','egyéb','KP','sztornó') NOT NULL DEFAULT 'generált',
    törölve TINYINT(1) NOT NULL DEFAULT 0,
    létrehozva DATETIME DEFAULT CURRENT_TIMESTAMP,
    módosítva DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (szervező_id) REFERENCES szervezők(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Számla fájl csatolmányok
CREATE TABLE IF NOT EXISTS számla_fájlok (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    számla_id INT UNSIGNED NOT NULL,
    eredeti_név VARCHAR(255) NOT NULL,
    fájl_útvonal VARCHAR(500) NOT NULL,
    létrehozva DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (számla_id) REFERENCES számlák(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Számlázandó (időszak: év/hó, több hónap kijelölhető; opcionálisan egy számlához csatolva)
CREATE TABLE IF NOT EXISTS számlázandó (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    szervező_id INT UNSIGNED NOT NULL,
    számla_id INT UNSIGNED NULL,
    összeg DECIMAL(12,2) NOT NULL,
    megjegyzés TEXT NULL,
    törölve TINYINT(1) NOT NULL DEFAULT 0,
    létrehozva DATETIME DEFAULT CURRENT_TIMESTAMP,
    módosítva DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (szervező_id) REFERENCES szervezők(id) ON DELETE CASCADE,
    FOREIGN KEY (számla_id) REFERENCES számlák(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Számlázandó időszak (év, hónap - több hónap egy számlázandóhoz)
CREATE TABLE IF NOT EXISTS számlázandó_időszak (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    számlázandó_id INT UNSIGNED NOT NULL,
    év SMALLINT UNSIGNED NOT NULL,
    hónap TINYINT UNSIGNED NOT NULL CHECK (hónap BETWEEN 1 AND 12),
    UNIQUE KEY (számlázandó_id, év, hónap),
    FOREIGN KEY (számlázandó_id) REFERENCES számlázandó(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin ID hivatkozások (szervező_megjegyzések, szervező_log, kontakt_megjegyzések, log)
ALTER TABLE szervező_megjegyzések ADD CONSTRAINT fk_szerv_megj_admin FOREIGN KEY (admin_id) REFERENCES adminok(id) ON DELETE SET NULL;
ALTER TABLE szervező_log ADD CONSTRAINT fk_szerv_log_admin FOREIGN KEY (admin_id) REFERENCES adminok(id) ON DELETE SET NULL;
ALTER TABLE kontakt_megjegyzések ADD CONSTRAINT fk_kontakt_megj_admin FOREIGN KEY (admin_id) REFERENCES adminok(id) ON DELETE SET NULL;

-- E-mail / SMTP fiókok (több fiók, jelszó titkosítva)
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

-- Levélsablonok (e-mail sablonok)
CREATE TABLE IF NOT EXISTS levélsablonok (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    név VARCHAR(255) NOT NULL,
    kód VARCHAR(100) NOT NULL UNIQUE,
    tárgy VARCHAR(255) NOT NULL DEFAULT '',
    megjegyzés TEXT NULL,
    html_tartalom MEDIUMTEXT NOT NULL,
    létrehozva DATETIME DEFAULT CURRENT_TIMESTAMP,
    módosítva DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rendszer log (tétel felvétel, státusz módosítás stb.)
CREATE TABLE IF NOT EXISTS rendszer_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entitás VARCHAR(50) NOT NULL,
    entitás_id INT UNSIGNED NULL,
    művelet VARCHAR(100) NOT NULL,
    részletek TEXT NULL,
    admin_id INT UNSIGNED NULL,
    létrehozva DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES adminok(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alapértelmezett admin: felhasználónév = admin, jelszó = password, szint = superadmin
INSERT IGNORE INTO adminok (id, név, felhasználónév, jelszó_hash, szint, aktív) VALUES
(1, 'Főadmin', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin', 1);
