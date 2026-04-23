-- Visszajelzés e-mail nélkül is menthető; értesítés külön űrlap
ALTER TABLE nextgen_landing_feedback MODIFY email VARCHAR(255) NULL;
