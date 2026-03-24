-- Számla státusz bővítés: sztornó

ALTER TABLE számlák
  MODIFY COLUMN státusz ENUM('generált','kiküldve','kiegyenlítve','egyéb','KP','sztornó') NOT NULL DEFAULT 'generált';
