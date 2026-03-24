-- Új számla státusz: KP (meglévő telepítésekhez)

ALTER TABLE számlák
  MODIFY COLUMN státusz ENUM('generált','kiküldve','kiegyenlítve','egyéb','KP') NOT NULL DEFAULT 'generált';
