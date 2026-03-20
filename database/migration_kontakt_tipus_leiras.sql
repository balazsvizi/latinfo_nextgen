-- Kontakt típus leírás mező (meglévő telepítésekhez)

ALTER TABLE kontakt_típusok
  ADD COLUMN leírás TEXT NULL AFTER név;
