-- Admin szint (admin / superadmin) - meglévő adatbázishoz futtatandó
-- Futtasd: mysql -u root -p alatinfo < database/migration_admin_szint.sql

ALTER TABLE adminok
ADD COLUMN szint ENUM('admin','superadmin') NOT NULL DEFAULT 'admin' AFTER jelszó_hash;

UPDATE adminok SET szint = 'superadmin';
