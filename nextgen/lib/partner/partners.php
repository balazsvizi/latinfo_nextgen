<?php
declare(strict_types=1);

function nextgen_partners_table_ready(PDO $db): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    try {
        $db->query('SELECT 1 FROM `nextgen_partners` LIMIT 1');
        $cached = true;
    } catch (Throwable) {
        $cached = false;
    }

    return $cached;
}

/**
 * @return array<string, mixed>|null
 */
function nextgen_partner_by_id(PDO $db, int $partnerId): ?array
{
    if ($partnerId <= 0 || !nextgen_partners_table_ready($db)) {
        return null;
    }
    try {
        $stmt = $db->prepare('SELECT * FROM `nextgen_partners` WHERE `id` = ? LIMIT 1');
        $stmt->execute([$partnerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (Throwable) {
        return null;
    }
}

/**
 * @return array<string, mixed>|null
 */
function nextgen_partner_by_email(PDO $db, string $email): ?array
{
    $email = trim($email);
    if ($email === '' || !nextgen_partners_table_ready($db)) {
        return null;
    }
    try {
        $stmt = $db->prepare('SELECT * FROM `nextgen_partners` WHERE `email` = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (Throwable) {
        return null;
    }
}

/**
 * @return list<array<string, mixed>>
 */
function nextgen_partners_list(PDO $db, ?string $search = null): array
{
    if (!nextgen_partners_table_ready($db)) {
        return [];
    }
    $where = '';
    $params = [];
    if ($search !== null && trim($search) !== '') {
        $like = '%' . trim($search) . '%';
        $where = 'WHERE (p.`név` LIKE ? OR p.`email` LIKE ? OR CAST(p.`id` AS CHAR) LIKE ?)';
        $params = [$like, $like, $like];
    }
    try {
        $stmt = $db->prepare("
            SELECT p.*,
                (SELECT COUNT(*) FROM `nextgen_partner_events_organizers` po WHERE po.`partner_id` = p.`id`) AS organizer_count,
                (SELECT COUNT(*) FROM `nextgen_partner_djs` pd WHERE pd.`partner_id` = p.`id`) AS dj_count,
                (SELECT COUNT(*) FROM `nextgen_partner_finance_organizers` pf WHERE pf.`partner_id` = p.`id`) AS finance_count
            FROM `nextgen_partners` p
            {$where}
            ORDER BY p.`név` ASC, p.`id` ASC
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return [];
    }
}

/**
 * @return array{ok: true, id: int}|array{ok: false, error: string}
 */
function nextgen_partner_create(
    PDO $db,
    string $nev,
    string $email,
    string $password,
    ?string $telefon = null,
    ?string $egyebKontakt = null
): array {
    if (!nextgen_partners_table_ready($db)) {
        return ['ok' => false, 'error' => 'A partner tábla még nincs telepítve. Futtasd: partner/sql/migration_partners.sql'];
    }
    $nev = trim($nev);
    $email = trim($email);
    if ($nev === '') {
        return ['ok' => false, 'error' => 'A név megadása kötelező.'];
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Érvényes e-mail cím szükséges.'];
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'A jelszónak legalább 8 karakter hosszúnak kell lennie.'];
    }
    if (nextgen_partner_by_email($db, $email) !== null) {
        return ['ok' => false, 'error' => 'Ez az e-mail cím már foglalt.'];
    }

    $telefon = $telefon !== null ? trim($telefon) : '';
    $egyebKontakt = $egyebKontakt !== null ? trim($egyebKontakt) : '';

    try {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare('
            INSERT INTO `nextgen_partners` (`név`, `email`, `telefon`, `egyéb_kontakt`, `jelszó_hash`, `aktív`)
            VALUES (?, ?, ?, ?, ?, 1)
        ');
        $stmt->execute([
            $nev,
            $email,
            $telefon !== '' ? $telefon : null,
            $egyebKontakt !== '' ? $egyebKontakt : null,
            $hash,
        ]);

        return ['ok' => true, 'id' => (int) $db->lastInsertId()];
    } catch (Throwable $ex) {
        error_log('nextgen_partner_create: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'Partner létrehozása sikertelen.'];
    }
}

/**
 * @return array{ok: true}|array{ok: false, error: string}
 */
function nextgen_partner_update_profile(
    PDO $db,
    int $partnerId,
    string $nev,
    string $email,
    ?string $telefon,
    ?string $egyebKontakt
): array {
    if ($partnerId <= 0) {
        return ['ok' => false, 'error' => 'Érvénytelen partner.'];
    }
    $nev = trim($nev);
    $email = trim($email);
    if ($nev === '') {
        return ['ok' => false, 'error' => 'A név megadása kötelező.'];
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Érvényes e-mail cím szükséges.'];
    }
    $telefon = $telefon !== null ? trim($telefon) : '';
    $egyebKontakt = $egyebKontakt !== null ? trim($egyebKontakt) : '';

    try {
        $dup = $db->prepare('SELECT `id` FROM `nextgen_partners` WHERE `email` = ? AND `id` <> ? LIMIT 1');
        $dup->execute([$email, $partnerId]);
        if ($dup->fetchColumn() !== false) {
            return ['ok' => false, 'error' => 'Ez az e-mail cím már foglalt.'];
        }
        $stmt = $db->prepare('
            UPDATE `nextgen_partners`
            SET `név` = ?, `email` = ?, `telefon` = ?, `egyéb_kontakt` = ?
            WHERE `id` = ?
        ');
        $stmt->execute([
            $nev,
            $email,
            $telefon !== '' ? $telefon : null,
            $egyebKontakt !== '' ? $egyebKontakt : null,
            $partnerId,
        ]);

        return ['ok' => true];
    } catch (Throwable $ex) {
        error_log('nextgen_partner_update_profile: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'Profil mentése sikertelen.'];
    }
}

/**
 * @return array{ok: true}|array{ok: false, error: string}
 */
function nextgen_partner_update_password(PDO $db, int $partnerId, string $password): array
{
    if ($partnerId <= 0) {
        return ['ok' => false, 'error' => 'Érvénytelen partner.'];
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'A jelszónak legalább 8 karakter hosszúnak kell lennie.'];
    }
    try {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare('UPDATE `nextgen_partners` SET `jelszó_hash` = ? WHERE `id` = ?');
        $stmt->execute([$hash, $partnerId]);

        return ['ok' => true];
    } catch (Throwable $ex) {
        error_log('nextgen_partner_update_password: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'Jelszó mentése sikertelen.'];
    }
}

/**
 * @return array{ok: true}|array{ok: false, error: string}
 */
function nextgen_partner_set_active(PDO $db, int $partnerId, bool $active): array
{
    if ($partnerId <= 0) {
        return ['ok' => false, 'error' => 'Érvénytelen partner.'];
    }
    try {
        $stmt = $db->prepare('UPDATE `nextgen_partners` SET `aktív` = ? WHERE `id` = ?');
        $stmt->execute([$active ? 1 : 0, $partnerId]);

        return ['ok' => true];
    } catch (Throwable $ex) {
        return ['ok' => false, 'error' => 'Státusz mentése sikertelen.'];
    }
}

/**
 * @return list<array<string, mixed>>
 */
function nextgen_partner_events_organizers(PDO $db, int $partnerId): array
{
    if ($partnerId <= 0) {
        return [];
    }
    try {
        $stmt = $db->prepare('
            SELECT o.`id`, o.`name`, po.`sort_order`
            FROM `nextgen_partner_events_organizers` po
            INNER JOIN `events_organizers` o ON o.`id` = po.`organizer_id`
            WHERE po.`partner_id` = ?
            ORDER BY po.`sort_order` ASC, o.`name` ASC
        ');
        $stmt->execute([$partnerId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return [];
    }
}

/**
 * @return list<array<string, mixed>>
 */
function nextgen_partner_djs(PDO $db, int $partnerId): array
{
    if ($partnerId <= 0) {
        return [];
    }
    try {
        $stmt = $db->prepare('
            SELECT t.`id`, t.`name`, pd.`sort_order`
            FROM `nextgen_partner_djs` pd
            INNER JOIN `events_tags` t ON t.`id` = pd.`tag_id`
            WHERE pd.`partner_id` = ?
            ORDER BY pd.`sort_order` ASC, t.`name` ASC
        ');
        $stmt->execute([$partnerId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return [];
    }
}

/**
 * @return list<array<string, mixed>>
 */
function nextgen_partner_finance_organizers(PDO $db, int $partnerId): array
{
    if ($partnerId <= 0) {
        return [];
    }
    try {
        $stmt = $db->prepare('
            SELECT f.`id`, f.`név` AS name
            FROM `nextgen_partner_finance_organizers` pf
            INNER JOIN `finance_organizers` f ON f.`id` = pf.`finance_organizer_id`
            WHERE pf.`partner_id` = ?
            ORDER BY f.`név` ASC
        ');
        $stmt->execute([$partnerId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return [];
    }
}

function nextgen_partner_can_access_organizer(PDO $db, int $partnerId, int $organizerId): bool
{
    if ($partnerId <= 0 || $organizerId <= 0) {
        return false;
    }
    try {
        $stmt = $db->prepare('
            SELECT 1 FROM `nextgen_partner_events_organizers`
            WHERE `partner_id` = ? AND `organizer_id` = ?
            LIMIT 1
        ');
        $stmt->execute([$partnerId, $organizerId]);

        return (bool) $stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

function nextgen_partner_can_access_dj(PDO $db, int $partnerId, int $tagId): bool
{
    if ($partnerId <= 0 || $tagId <= 0) {
        return false;
    }
    try {
        $stmt = $db->prepare('
            SELECT 1 FROM `nextgen_partner_djs`
            WHERE `partner_id` = ? AND `tag_id` = ?
            LIMIT 1
        ');
        $stmt->execute([$partnerId, $tagId]);

        return (bool) $stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

/**
 * @param list<int> $organizerIds
 * @param list<int> $djTagIds
 * @param list<int> $financeOrganizerIds
 * @return array{ok: true}|array{ok: false, error: string}
 */
function nextgen_partner_sync_assignments(
    PDO $db,
    int $partnerId,
    array $organizerIds,
    array $djTagIds,
    array $financeOrganizerIds
): array {
    if ($partnerId <= 0) {
        return ['ok' => false, 'error' => 'Érvénytelen partner.'];
    }

    $organizerIds = array_values(array_unique(array_filter(array_map('intval', $organizerIds), static fn (int $id): bool => $id > 0)));
    $djTagIds = array_values(array_unique(array_filter(array_map('intval', $djTagIds), static fn (int $id): bool => $id > 0)));
    $financeOrganizerIds = array_values(array_unique(array_filter(array_map('intval', $financeOrganizerIds), static fn (int $id): bool => $id > 0)));

    try {
        $db->beginTransaction();

        $db->prepare('DELETE FROM `nextgen_partner_events_organizers` WHERE `partner_id` = ?')->execute([$partnerId]);
        $insOrg = $db->prepare('INSERT INTO `nextgen_partner_events_organizers` (`partner_id`, `organizer_id`, `sort_order`) VALUES (?, ?, ?)');
        foreach ($organizerIds as $i => $oid) {
            $insOrg->execute([$partnerId, $oid, $i]);
        }

        $db->prepare('DELETE FROM `nextgen_partner_djs` WHERE `partner_id` = ?')->execute([$partnerId]);
        $insDj = $db->prepare('INSERT INTO `nextgen_partner_djs` (`partner_id`, `tag_id`, `sort_order`) VALUES (?, ?, ?)');
        foreach ($djTagIds as $i => $tid) {
            $insDj->execute([$partnerId, $tid, $i]);
        }

        $db->prepare('DELETE FROM `nextgen_partner_finance_organizers` WHERE `partner_id` = ?')->execute([$partnerId]);
        $insFin = $db->prepare('INSERT INTO `nextgen_partner_finance_organizers` (`partner_id`, `finance_organizer_id`) VALUES (?, ?)');
        foreach ($financeOrganizerIds as $fid) {
            $insFin->execute([$partnerId, $fid]);
        }

        $db->commit();

        return ['ok' => true];
    } catch (Throwable $ex) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('nextgen_partner_sync_assignments: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'Hozzárendelések mentése sikertelen.'];
    }
}

/**
 * @return list<array{id: int, name: string}>
 */
function nextgen_partner_selectable_events_organizers(PDO $db): array
{
    try {
        return $db->query('SELECT `id`, `name` FROM `events_organizers` ORDER BY `name` ASC')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return [];
    }
}

/**
 * @return list<array{id: int, name: string}>
 */
function nextgen_partner_selectable_djs(PDO $db): array
{
    if (!function_exists('events_tag_type_id_by_code')) {
        require_once dirname(__DIR__, 2) . '/events/lib/tag_type.php';
    }
    if (!events_tags_tables_available($db) || !events_tag_types_tables_available($db)) {
        return [];
    }
    $djTypeId = events_tag_type_id_by_code($db, 'dj');
    if ($djTypeId === null || $djTypeId <= 0) {
        return [];
    }
    try {
        $stmt = $db->prepare('
            SELECT t.`id`, t.`name`
            FROM `events_tags` t
            INNER JOIN `events_tag_type_links` l ON l.`tag_id` = t.`id` AND l.`tag_type_id` = ?
            ORDER BY t.`name` ASC
        ');
        $stmt->execute([$djTypeId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return [];
    }
}

/**
 * @return list<array{id: int, name: string}>
 */
function nextgen_partner_selectable_finance_organizers(PDO $db): array
{
    try {
        return $db->query('SELECT `id`, `név` AS name FROM `finance_organizers` ORDER BY `név` ASC')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return [];
    }
}
