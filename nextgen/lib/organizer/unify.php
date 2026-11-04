<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/partner/partners.php';

function organizer_unify_ensure_schema(PDO $db): bool
{
    static $done = null;
    if ($done !== null) {
        return $done;
    }
    try {
        $stmt = $db->query("SHOW COLUMNS FROM `finance_organizers` LIKE 'events_organizer_id'");
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $db->exec('ALTER TABLE `finance_organizers` ADD COLUMN `events_organizer_id` INT UNSIGNED NULL DEFAULT NULL');
            $db->exec('ALTER TABLE `finance_organizers` ADD KEY `idx_finance_org_events` (`events_organizer_id`)');
        }
        $done = true;

        return true;
    } catch (Throwable $ex) {
        error_log('organizer_unify_ensure_schema: ' . $ex->getMessage());
        $done = false;

        return false;
    }
}

function organizer_unify_normalize_name(string $name): string
{
    $name = mb_strtolower(trim($name), 'UTF-8');
    $name = str_replace(['.', ',', '-', '_', '/', '\\', '"', "'"], ' ', $name);
    $name = preg_replace('/\b(kft|bt|zrt|nyrt|ev)\b/u', ' ', $name) ?? $name;
    $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

    return trim($name);
}

/**
 * Egyértelmű névegyezés: egy finance sor és egy eseményszervező, még nincs kötés.
 *
 * @return array{linked: int}
 */
function organizer_unify_auto_match(PDO $db): array
{
    $result = ['linked' => 0];
    if (!organizer_unify_ensure_schema($db)) {
        return $result;
    }
    try {
        $events = $db->query('SELECT `id`, `name` FROM `events_organizers`')->fetchAll(PDO::FETCH_ASSOC);
        $finance = $db->query('SELECT `id`, `név`, `events_organizer_id` FROM `finance_organizers`')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $ex) {
        error_log('organizer_unify_auto_match: ' . $ex->getMessage());

        return $result;
    }

    $eventsByKey = [];
    foreach ($events as $row) {
        $key = organizer_unify_normalize_name((string) ($row['name'] ?? ''));
        if ($key === '') {
            continue;
        }
        $eventsByKey[$key][] = (int) $row['id'];
    }
    $financeByKey = [];
    foreach ($finance as $row) {
        if ((int) ($row['events_organizer_id'] ?? 0) > 0) {
            continue;
        }
        $key = organizer_unify_normalize_name((string) ($row['név'] ?? ''));
        if ($key === '') {
            continue;
        }
        $financeByKey[$key][] = (int) $row['id'];
    }

    $usedEvents = [];
    foreach ($finance as $row) {
        $eid = (int) ($row['events_organizer_id'] ?? 0);
        if ($eid > 0) {
            $usedEvents[$eid] = true;
        }
    }

    $update = $db->prepare('UPDATE `finance_organizers` SET `events_organizer_id` = ? WHERE `id` = ? AND `events_organizer_id` IS NULL');
    foreach ($financeByKey as $key => $financeIds) {
        $eventIds = $eventsByKey[$key] ?? [];
        if (count($financeIds) !== 1 || count($eventIds) !== 1) {
            continue;
        }
        $eventId = $eventIds[0];
        if (isset($usedEvents[$eventId])) {
            continue;
        }
        $update->execute([$eventId, $financeIds[0]]);
        if ($update->rowCount() > 0) {
            $usedEvents[$eventId] = true;
            $result['linked']++;
        }
    }

    return $result;
}

/**
 * @return array{ok: true}|array{ok: false, error: string}
 */
function organizer_unify_link(PDO $db, int $financeOrganizerId, int $eventsOrganizerId): array
{
    if ($financeOrganizerId <= 0 || $eventsOrganizerId <= 0 || !organizer_unify_ensure_schema($db)) {
        return ['ok' => false, 'error' => 'Érvénytelen szervező.'];
    }
    try {
        $check = $db->prepare('SELECT 1 FROM `events_organizers` WHERE `id` = ? LIMIT 1');
        $check->execute([$eventsOrganizerId]);
        if (!$check->fetchColumn()) {
            return ['ok' => false, 'error' => 'Az eseményszervező nem található.'];
        }
        $upd = $db->prepare('UPDATE `finance_organizers` SET `events_organizer_id` = ? WHERE `id` = ?');
        $upd->execute([$eventsOrganizerId, $financeOrganizerId]);
        if ($upd->rowCount() < 1) {
            $exists = $db->prepare('SELECT 1 FROM `finance_organizers` WHERE `id` = ? LIMIT 1');
            $exists->execute([$financeOrganizerId]);
            if (!$exists->fetchColumn()) {
                return ['ok' => false, 'error' => 'A finance szervező nem található.'];
            }
        }

        return ['ok' => true];
    } catch (Throwable $ex) {
        error_log('organizer_unify_link: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'Összekötés sikertelen.'];
    }
}

/**
 * @return array{ok: true, events_organizer_id: int}|array{ok: false, error: string}
 */
function organizer_unify_create_event_organizer(PDO $db, int $financeOrganizerId): array
{
    if (!organizer_unify_ensure_schema($db)) {
        return ['ok' => false, 'error' => 'A párosítás most nem elérhető.'];
    }
    try {
        $stmt = $db->prepare('SELECT `id`, `név`, `events_organizer_id` FROM `finance_organizers` WHERE `id` = ? LIMIT 1');
        $stmt->execute([$financeOrganizerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['ok' => false, 'error' => 'A finance szervező nem található.'];
        }
        $existing = (int) ($row['events_organizer_id'] ?? 0);
        if ($existing > 0) {
            return ['ok' => true, 'events_organizer_id' => $existing];
        }
        $name = trim((string) ($row['név'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'error' => 'A finance szervezőnek nincs neve.'];
        }
        $found = $db->prepare('SELECT `id` FROM `events_organizers` WHERE `name` = ? LIMIT 1');
        $found->execute([$name]);
        $eventId = (int) ($found->fetchColumn() ?: 0);
        if ($eventId <= 0) {
            $ins = $db->prepare('INSERT INTO `events_organizers` (`name`) VALUES (?)');
            $ins->execute([$name]);
            $eventId = (int) $db->lastInsertId();
        }
        $linked = organizer_unify_link($db, $financeOrganizerId, $eventId);
        if (!$linked['ok']) {
            return $linked;
        }

        return ['ok' => true, 'events_organizer_id' => $eventId];
    } catch (Throwable $ex) {
        error_log('organizer_unify_create_event_organizer: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'Eseményszervező létrehozása sikertelen.'];
    }
}

/**
 * @return array{ok: true}|array{ok: false, error: string}
 */
function organizer_unify_unlink(PDO $db, int $financeOrganizerId): array
{
    if ($financeOrganizerId <= 0 || !organizer_unify_ensure_schema($db)) {
        return ['ok' => false, 'error' => 'Érvénytelen szervező.'];
    }
    try {
        $db->prepare('UPDATE `finance_organizers` SET `events_organizer_id` = NULL WHERE `id` = ?')->execute([$financeOrganizerId]);

        return ['ok' => true];
    } catch (Throwable $ex) {
        error_log('organizer_unify_unlink: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'Szétkapcsolás sikertelen.'];
    }
}

/**
 * Új finance szervező: ha a név egy eseményszervezőhöz tartozik, ahhoz kötjük, különben újat hozunk létre.
 */
function organizer_unify_attach_new_finance(PDO $db, int $financeOrganizerId, string $name): void
{
    if ($financeOrganizerId <= 0 || !organizer_unify_ensure_schema($db)) {
        return;
    }
    $name = trim($name);
    if ($name === '') {
        return;
    }
    try {
        $found = $db->prepare('SELECT `id` FROM `events_organizers` WHERE `name` = ? LIMIT 1');
        $found->execute([$name]);
        $eventId = (int) ($found->fetchColumn() ?: 0);
        if ($eventId <= 0) {
            $key = organizer_unify_normalize_name($name);
            $candidates = $db->query('SELECT `id`, `name` FROM `events_organizers`')->fetchAll(PDO::FETCH_ASSOC);
            $hits = [];
            foreach ($candidates as $row) {
                if (organizer_unify_normalize_name((string) ($row['name'] ?? '')) === $key) {
                    $hits[] = (int) $row['id'];
                }
            }
            if (count($hits) === 1) {
                $eventId = $hits[0];
            }
        }
        if ($eventId <= 0) {
            $ins = $db->prepare('INSERT INTO `events_organizers` (`name`) VALUES (?)');
            $ins->execute([$name]);
            $eventId = (int) $db->lastInsertId();
        }
        organizer_unify_link($db, $financeOrganizerId, $eventId);
    } catch (Throwable $ex) {
        error_log('organizer_unify_attach_new_finance: ' . $ex->getMessage());
    }
}

/**
 * @return list<array{id: int, név: string, events_organizer_id: int, events_name: string}>
 */
function organizer_unify_finance_rows(PDO $db): array
{
    if (!organizer_unify_ensure_schema($db)) {
        return [];
    }
    try {
        $rows = $db->query('
            SELECT f.`id`, f.`név`, COALESCE(f.`events_organizer_id`, 0) AS events_organizer_id, COALESCE(e.`name`, \'\') AS events_name
            FROM `finance_organizers` f
            LEFT JOIN `events_organizers` e ON e.`id` = f.`events_organizer_id`
            ORDER BY f.`név` ASC, f.`id` ASC
        ')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $ex) {
        error_log('organizer_unify_finance_rows: ' . $ex->getMessage());

        return [];
    }
    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'id' => (int) ($row['id'] ?? 0),
            'név' => (string) ($row['név'] ?? ''),
            'events_organizer_id' => (int) ($row['events_organizer_id'] ?? 0),
            'events_name' => (string) ($row['events_name'] ?? ''),
        ];
    }

    return $out;
}

/**
 * @return list<array{id: int, name: string}>
 */
function organizer_unify_event_organizer_options(PDO $db): array
{
    try {
        $rows = $db->query('SELECT `id`, `name` FROM `events_organizers` ORDER BY `name` ASC, `id` ASC')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return [];
    }
    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
        ];
    }

    return $out;
}

/**
 * @return list<int>
 */
function organizer_unify_finance_ids_for_event(PDO $db, int $eventsOrganizerId): array
{
    if ($eventsOrganizerId <= 0 || !organizer_unify_ensure_schema($db)) {
        return [];
    }
    try {
        $stmt = $db->prepare('SELECT `id` FROM `finance_organizers` WHERE `events_organizer_id` = ? ORDER BY `id` ASC');
        $stmt->execute([$eventsOrganizerId]);
        $ids = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    } catch (Throwable) {
        return [];
    }
}

function organizer_unify_ensure_finance_row(PDO $db, int $eventsOrganizerId): int
{
    $existing = organizer_unify_finance_ids_for_event($db, $eventsOrganizerId);
    if ($existing !== []) {
        return $existing[0];
    }
    try {
        $nameStmt = $db->prepare('SELECT `name` FROM `events_organizers` WHERE `id` = ? LIMIT 1');
        $nameStmt->execute([$eventsOrganizerId]);
        $name = trim((string) ($nameStmt->fetchColumn() ?: ''));
        if ($name === '') {
            return 0;
        }
        $ins = $db->prepare('INSERT INTO `finance_organizers` (`név`, `events_organizer_id`) VALUES (?, ?)');
        $ins->execute([$name, $eventsOrganizerId]);

        return (int) $db->lastInsertId();
    } catch (Throwable $ex) {
        error_log('organizer_unify_ensure_finance_row: ' . $ex->getMessage());

        return 0;
    }
}

/**
 * @return list<array{id: int, név: string, invoice_count: int, open_invoice_count: int}>
 */
function organizer_unify_finance_summary(PDO $db, int $eventsOrganizerId): array
{
    $ids = organizer_unify_finance_ids_for_event($db, $eventsOrganizerId);
    if ($ids === []) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    try {
        $stmt = $db->prepare("
            SELECT f.`id`, f.`név`,
                (SELECT COUNT(*) FROM `finance_invoices` i WHERE i.`szervező_id` = f.`id` AND COALESCE(i.`törölve`, 0) = 0) AS invoice_count,
                (SELECT COUNT(*) FROM `finance_invoices` i WHERE i.`szervező_id` = f.`id` AND i.`státusz` NOT IN ('kiegyenlítve', 'sztornó') AND COALESCE(i.`törölve`, 0) = 0) AS open_invoice_count
            FROM `finance_organizers` f
            WHERE f.`id` IN ({$placeholders})
            ORDER BY f.`név` ASC
        ");
        $stmt->execute($ids);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'név' => (string) ($row['név'] ?? ''),
                'invoice_count' => (int) ($row['invoice_count'] ?? 0),
                'open_invoice_count' => (int) ($row['open_invoice_count'] ?? 0),
            ];
        }

        return $out;
    } catch (Throwable $ex) {
        error_log('organizer_unify_finance_summary: ' . $ex->getMessage());

        return [];
    }
}

/**
 * A régi számlázás jelölőt Finance szerepre írja, ha a finance szervező már eseményszervezőhöz van kötve.
 */
function nextgen_partner_migrate_szamlazas_to_finance_role(PDO $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (!nextgen_partner_ensure_extended_schema($db) || !organizer_unify_ensure_schema($db)) {
        return;
    }
    try {
        $db->exec("
            INSERT INTO `nextgen_partner_events_organizers`
                (`partner_id`, `organizer_id`, `role_type`, `sort_order`)
            SELECT pf.`partner_id`, f.`events_organizer_id`, 'finance', 0
            FROM `nextgen_partner_finance_organizers` pf
            INNER JOIN `finance_organizers` f ON f.`id` = pf.`finance_organizer_id`
            LEFT JOIN `nextgen_partner_events_organizers` po
                ON po.`partner_id` = pf.`partner_id`
               AND po.`organizer_id` = f.`events_organizer_id`
               AND po.`role_type` = 'finance'
            WHERE pf.`szamlazas` = 1
              AND f.`events_organizer_id` IS NOT NULL
              AND po.`partner_id` IS NULL
        ");
    } catch (Throwable $ex) {
        error_log('nextgen_partner_migrate_szamlazas_to_finance_role: ' . $ex->getMessage());
    }
}

/**
 * @return array<int, true>
 */
function nextgen_partner_billing_event_organizer_id_set(PDO $db, int $partnerId): array
{
    if ($partnerId <= 0 || !organizer_unify_ensure_schema($db)) {
        return [];
    }
    try {
        $stmt = $db->prepare('
            SELECT DISTINCT f.`events_organizer_id`
            FROM `nextgen_partner_finance_organizers` pf
            INNER JOIN `finance_organizers` f ON f.`id` = pf.`finance_organizer_id`
            WHERE pf.`partner_id` = ?
              AND pf.`szamlazas` = 1
              AND f.`events_organizer_id` IS NOT NULL
        ');
        $stmt->execute([$partnerId]);
        $set = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $set[$id] = true;
            }
        }

        return $set;
    } catch (Throwable $ex) {
        error_log('nextgen_partner_billing_event_organizer_id_set: ' . $ex->getMessage());

        return [];
    }
}

/**
 * @return list<int>
 */
function nextgen_partner_billing_event_ids_from_post(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $ids = [];
    foreach ($raw as $row) {
        if (!is_array($row)) {
            continue;
        }
        $roles = [];
        if (isset($row['role_types']) && is_array($row['role_types'])) {
            foreach ($row['role_types'] as $roleType) {
                $roles[] = strtolower(trim((string) $roleType));
            }
        }
        $financeRole = in_array('finance', $roles, true) || (string) ($row['szamlazas'] ?? '0') === '1';
        if (!$financeRole) {
            continue;
        }
        $organizerId = (int) ($row['organizer_id'] ?? 0);
        if ($organizerId > 0 && !in_array($organizerId, $ids, true)) {
            $ids[] = $organizerId;
        }
    }

    return $ids;
}

/**
 * A Finance jelleg köti a partnert a finance szervezőhöz. A számla a Számla értesítő e-mail címekre megy.
 *
 * @param list<int> $eventsOrganizerIds
 * @return array{ok: true}|array{ok: false, error: string}
 */
function nextgen_partner_sync_billing_on_event_organizers(PDO $db, int $partnerId, array $eventsOrganizerIds): array
{
    if ($partnerId <= 0) {
        return ['ok' => false, 'error' => 'Érvénytelen partner.'];
    }
    if (!organizer_unify_ensure_schema($db)) {
        return ['ok' => false, 'error' => 'A finance hozzárendelés nem menthető.'];
    }

    $wanted = [];
    foreach ($eventsOrganizerIds as $id) {
        $id = (int) $id;
        if ($id > 0 && !in_array($id, $wanted, true)) {
            $wanted[] = $id;
        }
    }

    try {
        $db->beginTransaction();
        $keep = [];
        foreach ($wanted as $eventsId) {
            $financeIds = organizer_unify_finance_ids_for_event($db, $eventsId);
            if ($financeIds === []) {
                $created = organizer_unify_ensure_finance_row($db, $eventsId);
                if ($created > 0) {
                    $financeIds = [$created];
                }
            }
            foreach ($financeIds as $financeId) {
                $keep[$financeId] = true;
            }
        }

        if ($keep === []) {
            $db->prepare('
                DELETE pf FROM `nextgen_partner_finance_organizers` pf
                INNER JOIN `finance_organizers` f ON f.`id` = pf.`finance_organizer_id`
                WHERE pf.`partner_id` = ? AND f.`events_organizer_id` IS NOT NULL
            ')->execute([$partnerId]);
        } else {
            $placeholders = implode(',', array_fill(0, count($keep), '?'));
            $sql = '
                DELETE pf FROM `nextgen_partner_finance_organizers` pf
                INNER JOIN `finance_organizers` f ON f.`id` = pf.`finance_organizer_id`
                WHERE pf.`partner_id` = ?
                  AND f.`events_organizer_id` IS NOT NULL
                  AND pf.`finance_organizer_id` NOT IN (' . $placeholders . ')
            ';
            $db->prepare($sql)->execute(array_merge([$partnerId], array_keys($keep)));
        }

        $ins = $db->prepare('
            INSERT INTO `nextgen_partner_finance_organizers` (`partner_id`, `finance_organizer_id`, `szamlazas`)
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE `szamlazas` = 1
        ');
        foreach (array_keys($keep) as $financeId) {
            $ins->execute([$partnerId, $financeId]);
        }
        $db->commit();
        nextgen_partner_log($db, $partnerId, 'Finance szervezők módosítva', count($keep) . ' finance szervező');

        return ['ok' => true];
    } catch (Throwable $ex) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('nextgen_partner_sync_billing_on_event_organizers: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'Finance hozzárendelés mentése sikertelen.'];
    }
}
