-- Exporter: opcionális megjegyzés a mentett lekérdezéshez

ALTER TABLE exporter_queries
ADD COLUMN megjegyzés TEXT NULL AFTER query_sql;
