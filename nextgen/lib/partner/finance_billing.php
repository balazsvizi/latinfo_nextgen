<?php
declare(strict_types=1);

require_once __DIR__ . '/partners.php';
require_once __DIR__ . '/migrate_finance_contacts.php';

/**
 * Finance kontaktok egyszeri átvétele, majd a számlák kötése a partnerekhez.
 */
function nextgen_partner_ensure_finance_contacts_migrated(PDO $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    if (!nextgen_partner_ensure_finance_billing_schema($db)) {
        return;
    }

    $status = nextgen_partner_finance_contacts_migration_status($db);
    if ($status['pending'] > 0) {
        nextgen_partner_migrate_from_finance_contacts($db);
    }
    nextgen_partner_import_pending_finance_contact_extras($db);
    if (is_file(__DIR__ . '/../organizer/unify.php')) {
        require_once __DIR__ . '/../organizer/unify.php';
        nextgen_partner_migrate_szamlazas_to_finance_role($db);
    }
    nextgen_finance_invoices_bind_pending_partners($db);
}

/**
 * @return list<array{id: int, name: string}>
 */
function nextgen_partner_selectable_finance_organizers(PDO $db): array
{
    try {
        $rows = $db->query('SELECT `id`, `név` AS `name` FROM `finance_organizers` ORDER BY `név` ASC')->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    } catch (Throwable) {
        return [];
    }
}

/**
 * @return list<array{finance_organizer_id: int, name: string, szamlazas: bool}>
 */
function nextgen_partner_finance_organizer_assignments(PDO $db, int $partnerId): array
{
    if ($partnerId <= 0) {
        return [];
    }
    nextgen_partner_ensure_finance_billing_schema($db);
    try {
        $stmt = $db->prepare('
            SELECT o.`id` AS finance_organizer_id, o.`név` AS name, pf.`szamlazas`
            FROM `nextgen_partner_finance_organizers` pf
            INNER JOIN `finance_organizers` o ON o.`id` = pf.`finance_organizer_id`
            WHERE pf.`partner_id` = ?
            ORDER BY o.`név` ASC
        ');
        $stmt->execute([$partnerId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = [
                'finance_organizer_id' => (int) ($row['finance_organizer_id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'szamlazas' => ((int) ($row['szamlazas'] ?? 0) === 1),
            ];
        }

        return $out;
    } catch (Throwable $ex) {
        error_log('nextgen_partner_finance_organizer_assignments: ' . $ex->getMessage());

        return [];
    }
}

/**
 * @return list<array{finance_organizer_id: int, szamlazas: bool}>
 */
function nextgen_partner_finance_rows_from_post(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    $seen = [];
    foreach ($raw as $row) {
        if (!is_array($row)) {
            continue;
        }
        $organizerId = (int) ($row['finance_organizer_id'] ?? 0);
        if ($organizerId <= 0 || isset($seen[$organizerId])) {
            continue;
        }
        $seen[$organizerId] = true;
        $out[] = [
            'finance_organizer_id' => $organizerId,
            'szamlazas' => (string) ($row['szamlazas'] ?? '0') === '1',
        ];
    }

    return $out;
}

/**
 * @param list<array{finance_organizer_id: int, szamlazas: bool}> $rows
 * @return array{ok: true}|array{ok: false, error: string}
 */
function nextgen_partner_sync_finance_organizers(PDO $db, int $partnerId, array $rows): array
{
    if ($partnerId <= 0) {
        return ['ok' => false, 'error' => 'Érvénytelen partner.'];
    }
    if (!nextgen_partner_ensure_finance_billing_schema($db)) {
        return ['ok' => false, 'error' => 'A finance hozzárendelés nem menthető.'];
    }

    try {
        $db->beginTransaction();
        $db->prepare('DELETE FROM `nextgen_partner_finance_organizers` WHERE `partner_id` = ?')->execute([$partnerId]);
        $check = $db->prepare('SELECT 1 FROM `finance_organizers` WHERE `id` = ? LIMIT 1');
        $ins = $db->prepare('
            INSERT INTO `nextgen_partner_finance_organizers` (`partner_id`, `finance_organizer_id`, `szamlazas`)
            VALUES (?, ?, ?)
        ');
        $saved = 0;
        foreach ($rows as $row) {
            $organizerId = (int) $row['finance_organizer_id'];
            $check->execute([$organizerId]);
            if (!$check->fetchColumn()) {
                continue;
            }
            $ins->execute([$partnerId, $organizerId, !empty($row['szamlazas']) ? 1 : 0]);
            $saved++;
        }
        $db->commit();
        nextgen_partner_log($db, $partnerId, 'Finance szervezők módosítva', $saved . ' szervező');

        return ['ok' => true];
    } catch (Throwable $ex) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('nextgen_partner_sync_finance_organizers: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'Finance szervezők mentése sikertelen.'];
    }
}

/**
 * Finance jellegű partnerek egy finance szervezőhöz, a számla értesítő e-mail címekkel.
 *
 * @return list<array{id: int, név: string, emails: list<string>}>
 */
function nextgen_partner_billing_partners_for_organizer(PDO $db, int $financeOrganizerId): array
{
    if ($financeOrganizerId <= 0 || !nextgen_partner_ensure_finance_billing_schema($db)) {
        return [];
    }
    nextgen_partner_ensure_finance_contacts_migrated($db);
    try {
        $stmt = $db->prepare('
            SELECT p.`id`, p.`név`, p.`email`, COALESCE(p.`email_szamla_ertesito`, 1) AS email_szamla_ertesito
            FROM `nextgen_partners` p
            WHERE EXISTS (
                SELECT 1
                FROM `nextgen_partner_events_organizers` po
                INNER JOIN `finance_organizers` f ON f.`events_organizer_id` = po.`organizer_id`
                WHERE f.`id` = ?
                  AND po.`partner_id` = p.`id`
                  AND po.`role_type` = \'finance\'
            ) OR EXISTS (
                SELECT 1
                FROM `nextgen_partner_finance_organizers` pf
                INNER JOIN `finance_organizers` f ON f.`id` = pf.`finance_organizer_id`
                WHERE pf.`finance_organizer_id` = ?
                  AND pf.`partner_id` = p.`id`
                  AND pf.`szamlazas` = 1
                  AND f.`events_organizer_id` IS NULL
            )
            ORDER BY p.`név` ASC, p.`id` ASC
        ');
        $stmt->execute([$financeOrganizerId, $financeOrganizerId]);
        $partners = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $partnerId = (int) ($row['id'] ?? 0);
            if ($partnerId <= 0) {
                continue;
            }
            $emails = [];
            $emailRows = nextgen_partner_emails_for_partner($db, $partnerId);
            if ($emailRows === []) {
                if ((int) ($row['email_szamla_ertesito'] ?? 1) === 1) {
                    $login = nextgen_partner_billing_normalize_email((string) ($row['email'] ?? ''));
                    if ($login !== null) {
                        $emails[$login] = true;
                    }
                }
            } else {
                foreach ($emailRows as $emailRow) {
                    if (empty($emailRow['email_szamla_ertesito'])) {
                        continue;
                    }
                    $email = nextgen_partner_billing_normalize_email((string) ($emailRow['email'] ?? ''));
                    if ($email !== null) {
                        $emails[$email] = true;
                    }
                }
            }
            $partners[] = [
                'id' => $partnerId,
                'név' => (string) ($row['név'] ?? ''),
                'emails' => array_keys($emails),
            ];
        }

        return $partners;
    } catch (Throwable $ex) {
        error_log('nextgen_partner_billing_partners_for_organizer: ' . $ex->getMessage());

        return [];
    }
}

function nextgen_partner_billing_normalize_email(string $email): ?string
{
    $email = mb_strtolower(trim($email), 'UTF-8');
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    if (str_ends_with($email, '@partners.latinfo.hu')) {
        return null;
    }

    return $email;
}

/**
 * @param list<array{id: int, név: string, emails: list<string>}> $partners
 * @return list<string>
 */
function nextgen_partner_billing_recipient_emails(array $partners): array
{
    $emails = [];
    foreach ($partners as $partner) {
        foreach ($partner['emails'] as $email) {
            $email = nextgen_partner_billing_normalize_email((string) $email);
            if ($email !== null) {
                $emails[$email] = true;
            }
        }
    }

    return array_keys($emails);
}

/**
 * @return list<array{id: int, név: string, emails: list<string>}>
 */
function nextgen_finance_invoice_bind_billing_partners(PDO $db, int $invoiceId, int $financeOrganizerId): array
{
    $partners = nextgen_partner_billing_partners_for_organizer($db, $financeOrganizerId);
    if ($invoiceId <= 0 || !nextgen_partner_ensure_finance_billing_schema($db)) {
        return $partners;
    }
    try {
        $db->prepare('DELETE FROM `finance_invoice_partners` WHERE `invoice_id` = ?')->execute([$invoiceId]);
        $ins = $db->prepare('INSERT INTO `finance_invoice_partners` (`invoice_id`, `partner_id`) VALUES (?, ?)');
        $firstId = null;
        foreach ($partners as $partner) {
            $partnerId = (int) $partner['id'];
            $ins->execute([$invoiceId, $partnerId]);
            if ($firstId === null) {
                $firstId = $partnerId;
            }
        }
        $db->prepare('UPDATE `finance_invoices` SET `partner_id` = ?, `partnerek_szinkron` = 1 WHERE `id` = ?')
            ->execute([$firstId, $invoiceId]);
    } catch (Throwable $ex) {
        error_log('nextgen_finance_invoice_bind_billing_partners: ' . $ex->getMessage());
    }

    return $partners;
}

function nextgen_finance_invoices_bind_pending_partners(PDO $db): void
{
    if (!nextgen_partner_ensure_finance_billing_schema($db)) {
        return;
    }
    try {
        $rows = $db->query('
            SELECT `id`, `szervező_id`
            FROM `finance_invoices`
            WHERE COALESCE(`partnerek_szinkron`, 0) = 0
              AND COALESCE(`törölve`, 0) = 0
        ')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $ex) {
        error_log('nextgen_finance_invoices_bind_pending_partners: ' . $ex->getMessage());

        return;
    }
    foreach ($rows as $row) {
        nextgen_finance_invoice_bind_billing_partners(
            $db,
            (int) ($row['id'] ?? 0),
            (int) ($row['szervező_id'] ?? 0)
        );
    }
}

/**
 * @return list<array{id: int, név: string, email: string, telefon: string, finance: bool, email_szamla_ertesito: bool}>
 */
function nextgen_partner_finance_organizer_partners(PDO $db, int $financeOrganizerId): array
{
    if ($financeOrganizerId <= 0) {
        return [];
    }
    nextgen_partner_ensure_finance_contacts_migrated($db);
    try {
        $stmt = $db->prepare('
            SELECT p.`id`, p.`név`, p.`email`, p.`telefon`, pf.`szamlazas`,
                   f.`events_organizer_id`,
                   COALESCE(e.`email_szamla_ertesito`, p.`email_szamla_ertesito`, 1) AS email_szamla_ertesito,
                   CASE WHEN po.`partner_id` IS NULL THEN 0 ELSE 1 END AS finance_role
            FROM `nextgen_partner_finance_organizers` pf
            INNER JOIN `nextgen_partners` p ON p.`id` = pf.`partner_id`
            INNER JOIN `finance_organizers` f ON f.`id` = pf.`finance_organizer_id`
            LEFT JOIN `nextgen_partner_emails` e ON e.`partner_id` = p.`id` AND e.`is_login` = 1
            LEFT JOIN `nextgen_partner_events_organizers` po
                ON po.`partner_id` = p.`id`
               AND po.`organizer_id` = f.`events_organizer_id`
               AND po.`role_type` = \'finance\'
            WHERE pf.`finance_organizer_id` = ?
            ORDER BY p.`név` ASC, p.`id` ASC
        ');
        $stmt->execute([$financeOrganizerId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $eventsOrganizerId = (int) ($row['events_organizer_id'] ?? 0);
            $financeRole = (int) ($row['finance_role'] ?? 0) === 1;
            if (!$financeRole && $eventsOrganizerId <= 0 && (int) ($row['szamlazas'] ?? 0) === 1) {
                $financeRole = true;
            }
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'név' => (string) ($row['név'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
                'telefon' => (string) ($row['telefon'] ?? ''),
                'finance' => $financeRole,
                'email_szamla_ertesito' => ((int) ($row['email_szamla_ertesito'] ?? 1) === 1),
            ];
        }

        return $out;
    } catch (Throwable $ex) {
        error_log('nextgen_partner_finance_organizer_partners: ' . $ex->getMessage());

        return [];
    }
}

/**
 * Szervező adatlap: partner e-mail, Finance jelleg és számla értesítő mentése.
 *
 * @return array{ok: true}|array{ok: false, error: string}
 */
function nextgen_partner_save_organizer_billing(
    PDO $db,
    int $financeOrganizerId,
    int $partnerId,
    string $email,
    bool $financeRole,
    bool $szamlaErtesito
): array {
    if ($financeOrganizerId <= 0 || $partnerId <= 0) {
        return ['ok' => false, 'error' => 'Érvénytelen partner vagy szervező.'];
    }
    if (!nextgen_partner_ensure_finance_billing_schema($db) || !nextgen_partner_ensure_extended_schema($db)) {
        return ['ok' => false, 'error' => 'A partner mentése most nem elérhető.'];
    }
    if (is_file(__DIR__ . '/../organizer/unify.php')) {
        require_once __DIR__ . '/../organizer/unify.php';
    }

    $partner = nextgen_partner_by_id($db, $partnerId);
    if ($partner === null) {
        return ['ok' => false, 'error' => 'A partner nem található.'];
    }

    $email = mb_strtolower(trim($email), 'UTF-8');
    $emailRows = nextgen_partner_emails_for_partner($db, $partnerId);
    if ($email !== '') {
        if ($emailRows === []) {
            $emailRows = [[
                'email' => $email,
                'email_event_bekerult' => true,
                'email_szervezo_stat' => true,
                'email_szamla_ertesito' => $szamlaErtesito,
            ]];
        } else {
            $emailRows[0]['email'] = $email;
            $emailRows[0]['email_szamla_ertesito'] = $szamlaErtesito;
        }
    } elseif ($emailRows !== []) {
        $emailRows[0]['email_szamla_ertesito'] = $szamlaErtesito;
        $email = (string) ($emailRows[0]['email'] ?? '');
    }

    if ($emailRows !== []) {
        $saved = nextgen_partner_update_profile(
            $db,
            $partnerId,
            (string) ($partner['név'] ?? ''),
            $email,
            (string) ($partner['telefon'] ?? ''),
            (string) ($partner['egyéb_kontakt'] ?? ''),
            (string) ($partner['egyéb_info'] ?? ''),
            (string) ($partner['kieg_info'] ?? ''),
            (string) ($partner['település'] ?? ''),
            null,
            null,
            $emailRows
        );
        if (!$saved['ok']) {
            return ['ok' => false, 'error' => (string) ($saved['error'] ?? 'E-mail mentése sikertelen.')];
        }
    }

    try {
        $eventsOrganizerId = 0;
        $lookup = $db->prepare('SELECT `events_organizer_id` FROM `finance_organizers` WHERE `id` = ? LIMIT 1');
        $lookup->execute([$financeOrganizerId]);
        $eventsOrganizerId = (int) ($lookup->fetchColumn() ?: 0);
        if ($financeRole && $eventsOrganizerId <= 0 && function_exists('organizer_unify_create_event_organizer')) {
            $created = organizer_unify_create_event_organizer($db, $financeOrganizerId);
            if (!$created['ok']) {
                return ['ok' => false, 'error' => (string) ($created['error'] ?? 'Eseményszervező létrehozása sikertelen.')];
            }
            $eventsOrganizerId = (int) ($created['events_organizer_id'] ?? 0);
        }

        if ($eventsOrganizerId > 0) {
            if ($financeRole) {
                $db->prepare('
                    INSERT IGNORE INTO `nextgen_partner_events_organizers`
                        (`partner_id`, `organizer_id`, `role_type`, `sort_order`)
                    VALUES (?, ?, \'finance\', 0)
                ')->execute([$partnerId, $eventsOrganizerId]);
            } else {
                $db->prepare('
                    DELETE FROM `nextgen_partner_events_organizers`
                    WHERE `partner_id` = ? AND `organizer_id` = ? AND `role_type` = \'finance\'
                ')->execute([$partnerId, $eventsOrganizerId]);
            }
        }

        $stmt = $db->prepare('
            INSERT INTO `nextgen_partner_finance_organizers` (`partner_id`, `finance_organizer_id`, `szamlazas`)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE `szamlazas` = VALUES(`szamlazas`)
        ');
        $stmt->execute([$partnerId, $financeOrganizerId, $financeRole ? 1 : 0]);
        nextgen_partner_log(
            $db,
            $partnerId,
            'Finance jelleg mentve',
            'finance szervező #' . $financeOrganizerId . ($financeRole ? ', finance' : '')
        );

        return ['ok' => true];
    } catch (Throwable $ex) {
        error_log('nextgen_partner_save_organizer_billing: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'Finance jelleg mentése sikertelen.'];
    }
}

function nextgen_partner_id_by_finance_contact(PDO $db, int $contactId): int
{
    if ($contactId <= 0) {
        return 0;
    }
    nextgen_partner_ensure_finance_contacts_migrated($db);
    try {
        $stmt = $db->prepare('SELECT `id` FROM `nextgen_partners` WHERE `finance_contact_id` = ? LIMIT 1');
        $stmt->execute([$contactId]);

        return (int) ($stmt->fetchColumn() ?: 0);
    } catch (Throwable) {
        return 0;
    }
}

function nextgen_partner_redirect_from_finance_contact(PDO $db, int $contactId): void
{
    $partnerId = nextgen_partner_id_by_finance_contact($db, $contactId);
    if ($partnerId > 0) {
        redirect(nextgen_url('admin/partnerek/szerkeszt.php?id=') . $partnerId);
    }
    flash('error', 'Ehhez a kontakthoz nem készült partner.');
    redirect(nextgen_url('admin/partnerek/'));
}
