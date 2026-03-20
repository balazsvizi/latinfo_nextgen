-- Számlázandó – számlához csatolás (meglévő telepítésekhez)
ALTER TABLE számlázandó
ADD COLUMN számla_id INT UNSIGNED NULL AFTER szervező_id,
ADD CONSTRAINT fk_szamlazando_szamla FOREIGN KEY (számla_id) REFERENCES számlák(id) ON DELETE SET NULL;
