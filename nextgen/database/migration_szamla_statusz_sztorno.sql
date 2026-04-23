-- Számla státusz bővítés: sztornó

ALTER TABLE finance_invoices
  MODIFY COLUMN státusz ENUM('generált','kiküldve','kiegyenlítve','egyéb','KP','sztornó') NOT NULL DEFAULT 'generált';
