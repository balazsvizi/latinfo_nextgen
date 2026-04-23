-- Kontakt típus leírás mező (meglévő telepítésekhez)

ALTER TABLE finance_contact_types
  ADD COLUMN leírás TEXT NULL AFTER név;
