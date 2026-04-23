-- Ha a CRM táblák még a régi nextgen_* néven vannak (korábbi átnevezés után), futtasd ezt egyszer.
-- Backup előtt. Oszlopnevek változatlanok.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

RENAME TABLE `nextgen_billing_addresses` TO `finance_billing_addresses`;
RENAME TABLE `nextgen_contact_notes` TO `finance_contact_notes`;
RENAME TABLE `nextgen_contact_type_links` TO `finance_contact_type_links`;
RENAME TABLE `nextgen_contact_types` TO `finance_contact_types`;
RENAME TABLE `nextgen_contacts` TO `finance_contacts`;
RENAME TABLE `nextgen_email_accounts` TO `finance_email_accounts`;
RENAME TABLE `nextgen_email_templates` TO `finance_email_templates`;
RENAME TABLE `nextgen_organizer_activity_log` TO `finance_organizer_activity_log`;
RENAME TABLE `nextgen_organizer_contacts` TO `finance_organizer_contacts`;
RENAME TABLE `nextgen_organizer_notes` TO `finance_organizer_notes`;
RENAME TABLE `nextgen_organizer_tags` TO `finance_organizer_tags`;
RENAME TABLE `nextgen_organizers` TO `finance_organizers`;
RENAME TABLE `nextgen_tags` TO `finance_tags`;

SET FOREIGN_KEY_CHECKS = 1;
