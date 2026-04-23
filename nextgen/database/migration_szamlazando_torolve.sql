-- Számlázandó soft delete: törölt tételek ne jelenjenek meg (meglévő telepítésekhez)

ALTER TABLE finance_billing_items
  ADD COLUMN törölve TINYINT(1) NOT NULL DEFAULT 0 AFTER megjegyzés;
