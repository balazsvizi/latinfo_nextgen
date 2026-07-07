<?php
declare(strict_types=1);

require_once __DIR__ . '/partners.php';
require_once __DIR__ . '/activity_log.php';

/**
 * finance_contact_id oszlop a visszakövethető migrációhoz.
 */
function nextgen_partner_ensure_finance_contact_id_column(PDO $db): bool
{
    if (!nextgen_partners_table_ready($db)) {
        return false;
    }
    try {
        $stmt = $db->query("SHOW COLUMNS FROM `nextgen_partners` LIKE 'finance_contact_id'");
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return true;
        }
        $db->exec('
            ALTER TABLE `nextgen_partners`
            ADD COLUMN `finance_contact_id` INT UNSIGNED NULL DEFAULT NULL AFTER `id`,
            ADD UNIQUE KEY `uq_partners_finance_contact` (`finance_contact_id`)
        ');

        return true;
    } catch (Throwable $ex) {
        error_log('nextgen_partner_ensure_finance_contact_id_column: ' . $ex->getMessage());

        return false;
    }
}

function nextgen_partner_finance_contacts_table_ready(PDO $db): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    try {
        $db->query('SELECT 1 FROM `finance_contacts` LIMIT 1');
        $cached = true;
    } catch (Throwable) {
        $cached = false;
    }

    return $cached;
}

/**
 * @return array{ok: bool, created: int, linked: int, skipped: int, merged: int, errors: list<string>}
 */
function nextgen_partner_migrate_from_finance_contacts(PDO $db): array
{
    $result = [
        'ok' => false,
        'created' => 0,
        'linked' => 0,
        'skipped' => 0,
        'merged' => 0,
        'errors' => [],
    ];

    if (!nextgen_partners_table_ready($db) || !nextgen_partner_finance_contacts_table_ready($db)) {
        $result['errors'][] = 'A partner vagy finance_contacts tábla nem elérhető.';

        return $result;
    }
    if (!nextgen_partner_ensure_finance_contact_id_column($db)) {
        $result['errors'][] = 'A finance_contact_id oszlop nem hozható létre.';

        return $result;
    }

    try {
        $contacts = $db->query('SELECT * FROM `finance_contacts` ORDER BY `id` ASC')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $ex) {
        $result['errors'][] = 'Finance kontaktok lekérdezése sikertelen.';
        error_log('nextgen_partner_migrate_from_finance_contacts: ' . $ex->getMessage());

        return $result;
    }

    $emailCounts = [];
    foreach ($contacts as $row) {
        $raw = trim((string) ($row['email'] ?? ''));
        if ($raw !== '' && filter_var($raw, FILTER_VALIDATE_EMAIL)) {
            $key = mb_strtolower($raw, 'UTF-8');
            $emailCounts[$key] = ($emailCounts[$key] ?? 0) + 1;
        }
    }

    $placeholderPassword = 'PartnerMigracio-' . bin2hex(random_bytes(8));
    $passwordHash = password_hash($placeholderPassword, PASSWORD_DEFAULT);

    foreach ($contacts as $contact) {
        $contactId = (int) ($contact['id'] ?? 0);
        if ($contactId <= 0) {
            continue;
        }

        try {
            $existingByContact = $db->prepare('SELECT `id` FROM `nextgen_partners` WHERE `finance_contact_id` = ? LIMIT 1');
            $existingByContact->execute([$contactId]);
            $partnerId = (int) ($existingByContact->fetchColumn() ?: 0);

            if ($partnerId > 0) {
                $result['skipped']++;
                $linked = nextgen_partner_migrate_link_finance_organizers($db, $partnerId, $contactId);
                $result['linked'] += $linked;
                if ($linked > 0) {
                    nextgen_partner_log($db, $partnerId, 'Finance migráció: hozzárendelések frissítve', $linked . ' szervező');
                }

                continue;
            }

            $nev = trim((string) ($contact['név'] ?? ''));
            if ($nev === '') {
                $nev = 'Kontakt #' . $contactId;
            }

            $rawEmail = trim((string) ($contact['email'] ?? ''));
            $loginEmail = '';
            $extraLines = [];

            if ($rawEmail !== '' && filter_var($rawEmail, FILTER_VALIDATE_EMAIL)) {
                $emailKey = mb_strtolower($rawEmail, 'UTF-8');
                if (($emailCounts[$emailKey] ?? 0) === 1) {
                    $loginEmail = $emailKey;
                } else {
                    $extraLines[] = 'Eredeti e-mail: ' . $rawEmail;
                    $loginEmail = nextgen_partner_migrate_synthetic_email($contactId);
                }
            } else {
                if ($rawEmail !== '') {
                    $extraLines[] = 'Eredeti e-mail: ' . $rawEmail;
                }
                $loginEmail = nextgen_partner_migrate_synthetic_email($contactId);
            }

            $telefon = trim((string) ($contact['telefon'] ?? ''));
            $fb = trim((string) ($contact['fb'] ?? ''));
            $egyeb = trim((string) ($contact['egyéb_kontakt'] ?? ''));
            if ($fb !== '') {
                $extraLines[] = 'Facebook: ' . $fb;
            }
            if ($egyeb !== '') {
                $extraLines[] = $egyeb;
            }
            $tipusok = nextgen_partner_migrate_contact_types_label($db, $contactId);
            if ($tipusok !== '') {
                $extraLines[] = 'Kontakt típusok: ' . $tipusok;
            }
            $extraLines[] = 'Migrálva finance_contacts #' . $contactId;
            $egyebKontakt = implode("\n", $extraLines);

            $existingByEmail = $db->prepare('SELECT `id`, `finance_contact_id` FROM `nextgen_partners` WHERE `email` = ? LIMIT 1');
            $existingByEmail->execute([$loginEmail]);
            $existingRow = $existingByEmail->fetch(PDO::FETCH_ASSOC);

            if ($existingRow) {
                $partnerId = (int) ($existingRow['id'] ?? 0);
                if ($partnerId > 0 && empty($existingRow['finance_contact_id'])) {
                    $upd = $db->prepare('
                        UPDATE `nextgen_partners`
                        SET `finance_contact_id` = ?,
                            `telefon` = COALESCE(NULLIF(`telefon`, \'\'), ?),
                            `egyéb_kontakt` = CASE
                                WHEN `egyéb_kontakt` IS NULL OR TRIM(`egyéb_kontakt`) = \'\' THEN ?
                                ELSE CONCAT(`egyéb_kontakt`, CHAR(10), ?)
                            END
                        WHERE `id` = ?
                    ');
                    $upd->execute([
                        $contactId,
                        $telefon !== '' ? $telefon : null,
                        $egyebKontakt,
                        $egyebKontakt,
                        $partnerId,
                    ]);
                    $result['merged']++;
                    nextgen_partner_log($db, $partnerId, 'Finance kontakt egyesítve', 'finance_contacts #' . $contactId);
                } else {
                    $loginEmail = nextgen_partner_migrate_synthetic_email($contactId);
                    if ($rawEmail !== '') {
                        $egyebKontakt = 'Eredeti e-mail: ' . $rawEmail . "\n" . $egyebKontakt;
                    }
                    $partnerId = nextgen_partner_migrate_insert_partner(
                        $db,
                        $contactId,
                        $nev,
                        $loginEmail,
                        $telefon,
                        $egyebKontakt,
                        $passwordHash
                    );
                    if ($partnerId > 0) {
                        $result['created']++;
                    }
                }
            } else {
                $partnerId = nextgen_partner_migrate_insert_partner(
                    $db,
                    $contactId,
                    $nev,
                    $loginEmail,
                    $telefon,
                    $egyebKontakt,
                    $passwordHash
                );
                if ($partnerId > 0) {
                    $result['created']++;
                }
            }

            if ($partnerId <= 0) {
                $result['errors'][] = 'Kontakt #' . $contactId . ': partner létrehozása sikertelen.';

                continue;
            }

            $result['linked'] += nextgen_partner_migrate_link_finance_organizers($db, $partnerId, $contactId);
        } catch (Throwable $ex) {
            $result['errors'][] = 'Kontakt #' . $contactId . ': ' . $ex->getMessage();
            error_log('nextgen_partner_migrate_from_finance_contacts contact #' . $contactId . ': ' . $ex->getMessage());
        }
    }

    $result['ok'] = $result['errors'] === [];

    return $result;
}

function nextgen_partner_migrate_synthetic_email(int $contactId): string
{
    return 'finance-kontakt-' . $contactId . '@partners.latinfo.hu';
}

function nextgen_partner_migrate_contact_types_label(PDO $db, int $contactId): string
{
    try {
        $stmt = $db->prepare('
            SELECT GROUP_CONCAT(t.`név` ORDER BY t.`név` SEPARATOR \', \') AS tipusok
            FROM `finance_contact_type_links` kt
            INNER JOIN `finance_contact_types` t ON t.`id` = kt.`típus_id`
            WHERE kt.`kontakt_id` = ?
        ');
        $stmt->execute([$contactId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return trim((string) ($row['tipusok'] ?? ''));
    } catch (Throwable) {
        return '';
    }
}

function nextgen_partner_migrate_insert_partner(
    PDO $db,
    int $contactId,
    string $nev,
    string $email,
    string $telefon,
    string $egyebKontakt,
    string $passwordHash
): int {
    $stmt = $db->prepare('
        INSERT INTO `nextgen_partners` (`finance_contact_id`, `név`, `email`, `telefon`, `egyéb_kontakt`, `jelszó_hash`, `aktív`)
        VALUES (?, ?, ?, ?, ?, ?, 0)
    ');
    $stmt->execute([
        $contactId,
        $nev,
        $email,
        $telefon !== '' ? $telefon : null,
        $egyebKontakt !== '' ? $egyebKontakt : null,
        $passwordHash,
    ]);

    $partnerId = (int) $db->lastInsertId();
    if ($partnerId > 0) {
        nextgen_partner_log($db, $partnerId, 'Finance kontakt migrálva', 'finance_contacts #' . $contactId, 'system', null);
    }

    return $partnerId;
}

function nextgen_partner_migrate_link_finance_organizers(PDO $db, int $partnerId, int $contactId): int
{
    $linked = 0;
    try {
        $stmt = $db->prepare('
            SELECT `szervező_id` FROM `finance_organizer_contacts` WHERE `kontakt_id` = ?
        ');
        $stmt->execute([$contactId]);
        $ins = $db->prepare('
            INSERT IGNORE INTO `nextgen_partner_finance_organizers` (`partner_id`, `finance_organizer_id`)
            VALUES (?, ?)
        ');
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $financeOrgId) {
            $fid = (int) $financeOrgId;
            if ($fid <= 0) {
                continue;
            }
            $ins->execute([$partnerId, $fid]);
            if ($ins->rowCount() > 0) {
                $linked++;
            }
        }
    } catch (Throwable $ex) {
        error_log('nextgen_partner_migrate_link_finance_organizers: ' . $ex->getMessage());
    }

    return $linked;
}

/**
 * @return array{total_contacts: int, migrated: int, pending: int}
 */
function nextgen_partner_finance_contacts_migration_status(PDO $db): array
{
    $status = ['total_contacts' => 0, 'migrated' => 0, 'pending' => 0];
    if (!nextgen_partners_table_ready($db) || !nextgen_partner_finance_contacts_table_ready($db)) {
        return $status;
    }
    try {
        $status['total_contacts'] = (int) $db->query('SELECT COUNT(*) FROM `finance_contacts`')->fetchColumn();
        if (nextgen_partner_ensure_finance_contact_id_column($db)) {
            $status['migrated'] = (int) $db->query('
                SELECT COUNT(*) FROM `nextgen_partners` WHERE `finance_contact_id` IS NOT NULL
            ')->fetchColumn();
        }
        $status['pending'] = max(0, $status['total_contacts'] - $status['migrated']);
    } catch (Throwable) {
        return $status;
    }

    return $status;
}
