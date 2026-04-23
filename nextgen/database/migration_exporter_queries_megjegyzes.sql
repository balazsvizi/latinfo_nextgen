-- Exporter: opcionális megjegyzés a mentett lekérdezéshez

ALTER TABLE nextgen_exporter_queries
ADD COLUMN megjegyzés TEXT NULL AFTER query_sql;
