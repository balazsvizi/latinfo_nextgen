-- Soft delete: törölt finance_invoices ne jelenjenek meg a listákban (meglévő telepítésekhez)

ALTER TABLE finance_invoices
  ADD COLUMN törölve TINYINT(1) NOT NULL DEFAULT 0 AFTER státusz;
