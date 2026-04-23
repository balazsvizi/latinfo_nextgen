-- Lekérdezéshez tartozó kapcsolat mentése: connection_id oszlop hozzáadása

ALTER TABLE nextgen_exporter_queries
ADD COLUMN connection_id INT UNSIGNED NULL DEFAULT NULL AFTER query_sql;
