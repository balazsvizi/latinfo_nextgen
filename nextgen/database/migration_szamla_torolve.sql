-- Soft delete: törölt számlák ne jelenjenek meg a listákban (meglévő telepítésekhez)

ALTER TABLE számlák
  ADD COLUMN törölve TINYINT(1) NOT NULL DEFAULT 0 AFTER státusz;
