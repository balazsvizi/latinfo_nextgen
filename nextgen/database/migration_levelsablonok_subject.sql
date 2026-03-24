-- Levélsablon tárgy mező

ALTER TABLE levélsablonok
ADD COLUMN tárgy VARCHAR(255) NOT NULL DEFAULT '' AFTER kód;
