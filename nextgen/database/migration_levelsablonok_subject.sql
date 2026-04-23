-- Levélsablon tárgy mező

ALTER TABLE finance_email_templates
ADD COLUMN tárgy VARCHAR(255) NOT NULL DEFAULT '' AFTER kód;
