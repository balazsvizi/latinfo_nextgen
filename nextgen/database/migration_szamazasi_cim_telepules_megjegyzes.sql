-- Számlázási cím: település és megjegyzés mezők - meglévő adatbázishoz
-- Futtasd: mysql -u root -p alatinfo < database/migration_szamazasi_cim_telepules_megjegyzes.sql

ALTER TABLE finance_billing_addresses
ADD COLUMN település VARCHAR(100) NOT NULL DEFAULT '' AFTER irsz,
ADD COLUMN megjegyzés TEXT NULL AFTER adószám;
