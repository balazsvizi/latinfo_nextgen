-- Számlázandó – számlához csatolás (meglévő telepítésekhez)
ALTER TABLE finance_billing_items
ADD COLUMN számla_id INT UNSIGNED NULL AFTER szervező_id,
ADD CONSTRAINT fk_szamlazando_szamla FOREIGN KEY (számla_id) REFERENCES finance_invoices(id) ON DELETE SET NULL;
