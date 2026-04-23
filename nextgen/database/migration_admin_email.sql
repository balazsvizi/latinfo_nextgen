-- Admin e-mail mező

ALTER TABLE nextgen_admins
ADD COLUMN email VARCHAR(255) NULL AFTER felhasználónév;
