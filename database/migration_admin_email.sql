-- Admin e-mail mező

ALTER TABLE adminok
ADD COLUMN email VARCHAR(255) NULL AFTER felhasználónév;
