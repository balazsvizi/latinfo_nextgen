-- Egyszeri átnevezés: régi magyar / rövid táblanevek → finance_* (CRM/számlázás/e-mail) + nextgen_* (admin, log, landing, exporter) + events_*
-- Oszlopnevek változatlanok. Futtatás előtt backup.
-- Meglévő events FK-k: előtte migration_naptar_esemeny_fk_to_organizers.sql (organizer → organizers) lehet futott.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Events (régi magyar nevek)
RENAME TABLE `naptár_esemény_megtekintések` TO `events_calendar_event_views`;
RENAME TABLE `naptár_események` TO `events_calendar_events`;
RENAME TABLE `organizers` TO `events_organizers`;

-- Nextgen + finance (schema szerinti régi nevek)
RENAME TABLE `számlázandó_időszak` TO `finance_billing_periods`;
RENAME TABLE `számla_fájlok` TO `finance_invoice_files`;
RENAME TABLE `számlázandó` TO `finance_billing_items`;
RENAME TABLE `számlák` TO `finance_invoices`;
RENAME TABLE `számlázási_címek` TO `finance_billing_addresses`;
RENAME TABLE `kontakt_típus_kapcsolat` TO `finance_contact_type_links`;
RENAME TABLE `kontakt_típusok` TO `finance_contact_types`;
RENAME TABLE `kontakt_megjegyzések` TO `finance_contact_notes`;
RENAME TABLE `kontaktok` TO `finance_contacts`;
RENAME TABLE `szervező_megjegyzések` TO `finance_organizer_notes`;
RENAME TABLE `szervező_címkék` TO `finance_organizer_tags`;
RENAME TABLE `szervező_kontakt` TO `finance_organizer_contacts`;
RENAME TABLE `szervező_log` TO `finance_organizer_activity_log`;
RENAME TABLE `szervezők` TO `finance_organizers`;
RENAME TABLE `címkék` TO `finance_tags`;
RENAME TABLE `adminok` TO `nextgen_admins`;
RENAME TABLE `rendszer_log` TO `nextgen_system_log`;
RENAME TABLE `email_config` TO `finance_email_accounts`;
RENAME TABLE `levélsablonok` TO `finance_email_templates`;
RENAME TABLE `landingpage` TO `nextgen_landing_feedback`;
RENAME TABLE `exporter_connections` TO `nextgen_exporter_connections`;
RENAME TABLE `exporter_queries` TO `nextgen_exporter_queries`;

SET FOREIGN_KEY_CHECKS = 1;

-- CSV import presetek: cél tábla kulcs frissítése
UPDATE `events_import_settings` SET `target_table` = 'events_calendar_events' WHERE `target_table` = 'naptár_események';
UPDATE `events_import_settings` SET `target_table` = 'events_organizers' WHERE `target_table` = 'organizers';
