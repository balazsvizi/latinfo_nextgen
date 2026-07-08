<?php
declare(strict_types=1);

require_once __DIR__ . '/activity_log.php';

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

function nextgen_partner_password_reset_table_ready(PDO $db): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    try {
        $db->query('SELECT 1 FROM `nextgen_partner_password_reset_tokens` LIMIT 1');
        $cached = true;
    } catch (Throwable) {
        $cached = false;
    }

    return $cached;
}

function nextgen_partner_ensure_password_schema(PDO $db): bool
{
    if (!nextgen_partners_table_ready($db)) {
        return false;
    }
    try {
        $stmt = $db->query("SHOW COLUMNS FROM `nextgen_partners` LIKE 'jelszó_csere_kötelező'");
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $db->exec('ALTER TABLE `nextgen_partners` ADD COLUMN `jelszó_csere_kötelező` TINYINT(1) NOT NULL DEFAULT 0 AFTER `aktív`');
        }
        if (!nextgen_partner_password_reset_table_ready($db)) {
            $db->exec('
                CREATE TABLE IF NOT EXISTS `nextgen_partner_password_reset_tokens` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `partner_id` INT UNSIGNED NOT NULL,
                    `token_hash` VARCHAR(255) NOT NULL,
                    `lejárat` DATETIME NOT NULL,
                    `felhasználva` DATETIME NULL DEFAULT NULL,
                    `létrehozva` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_partner_reset_partner` (`partner_id`, `létrehozva`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ');
        }

        return nextgen_partner_ensure_extended_schema($db);
    } catch (Throwable $ex) {
        error_log('nextgen_partner_ensure_password_schema: ' . $ex->getMessage());

        return false;
    }
}

function nextgen_partner_ensure_extended_schema(PDO $db): bool
{
    if (!nextgen_partners_table_ready($db)) {
        return false;
    }
    try {
        $stmt = $db->query("SHOW COLUMNS FROM `nextgen_partners` LIKE 'egyéb_info'");
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $db->exec('ALTER TABLE `nextgen_partners` ADD COLUMN `egyéb_info` TEXT NULL DEFAULT NULL AFTER `egyéb_kontakt`');
        }

        $stmt = $db->query("SHOW COLUMNS FROM `nextgen_partners` LIKE 'kieg_info'");
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $db->exec('ALTER TABLE `nextgen_partners` ADD COLUMN `kieg_info` VARCHAR(255) NULL DEFAULT NULL AFTER `név`');
        }

        $stmt = $db->query("SHOW COLUMNS FROM `nextgen_partner_events_organizers` LIKE 'role_type'");
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $db->exec("
                ALTER TABLE `nextgen_partner_events_organizers`
                ADD COLUMN `role_type` VARCHAR(16) NOT NULL DEFAULT 'event' AFTER `organizer_id`,
                ADD COLUMN `role_note` VARCHAR(500) NULL DEFAULT NULL AFTER `role_type`
            ");
        }

        $stmt = $db->query("SHOW COLUMNS FROM `nextgen_partner_djs` LIKE 'role_type'");
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $db->exec("
                ALTER TABLE `nextgen_partner_djs`
                ADD COLUMN `role_type` VARCHAR(16) NOT NULL DEFAULT 'dj' AFTER `tag_id`,
                ADD COLUMN `role_note` VARCHAR(500) NULL DEFAULT NULL AFTER `role_type`
            ");
        }

        return true;
    } catch (Throwable $ex) {
        error_log('nextgen_partner_ensure_extended_schema: ' . $ex->getMessage());

        return false;
    }
}

/**
 * @return array<string, string>
 */
function nextgen_partner_organizer_role_labels(): array
{
    return [
        'event' => 'Event',
        'finance' => 'Finance',
        'boss' => 'Boss',
        'other' => 'Other',
    ];
}

/**
 * @return array<string, string>
 */
function nextgen_partner_dj_role_labels(): array
{
    return [
        'dj' => 'DJ',
        'other' => 'Egyéb',
    ];
}

/**
 * @return list<array{organizer_id: int, role_type: string, role_note: ?string}>
 */
function nextgen_partner_organizer_rows_from_post(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $validRoles = array_keys(nextgen_partner_organizer_role_labels());
    $rows = [];
    $seen = [];
    foreach ($raw as $row) {
        if (!is_array($row)) {
            continue;
        }
        $organizerId = (int) ($row['organizer_id'] ?? 0);
        if ($organizerId <= 0 || isset($seen[$organizerId])) {
            continue;
        }
        $seen[$organizerId] = true;
        $roleType = strtolower(trim((string) ($row['role_type'] ?? 'event')));
        if (!in_array($roleType, $validRoles, true)) {
            $roleType = 'event';
        }
        $roleNote = trim((string) ($row['role_note'] ?? ''));
        if ($roleType !== 'other') {
            $roleNote = '';
        }
        $rows[] = [
            'organizer_id' => $organizerId,
            'role_type' => $roleType,
            'role_note' => $roleNote !== '' ? $roleNote : null,
        ];
    }

    return $rows;
}

/**
 * @return list<array{tag_id: int, role_type: string, role_note: ?string}>
 */
function nextgen_partner_dj_rows_from_post(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $validRoles = array_keys(nextgen_partner_dj_role_labels());
    $rows = [];
    $seen = [];
    foreach ($raw as $row) {
        if (!is_array($row)) {
            continue;
        }
        $tagId = (int) ($row['tag_id'] ?? 0);
        if ($tagId <= 0 || isset($seen[$tagId])) {
            continue;
        }
        $seen[$tagId] = true;
        $roleType = strtolower(trim((string) ($row['role_type'] ?? 'dj')));
        if (!in_array($roleType, $validRoles, true)) {
            $roleType = 'dj';
        }
        $roleNote = trim((string) ($row['role_note'] ?? ''));
        if ($roleType !== 'other') {
            $roleNote = '';
        }
        $rows[] = [
            'tag_id' => $tagId,
            'role_type' => $roleType,
            'role_note' => $roleNote !== '' ? $roleNote : null,
        ];
    }

    return $rows;
}

function nextgen_partner_organizer_role_label(string $roleType): string
{
    $labels = nextgen_partner_organizer_role_labels();

    return $labels[$roleType] ?? $roleType;
}

function nextgen_partner_dj_role_label(string $roleType): string
{
    $labels = nextgen_partner_dj_role_labels();

    return $labels[$roleType] ?? $roleType;
}

function nextgen_partner_kieg_info_from_row(array $row): string
{
    return trim((string) ($row['kieg_info'] ?? $row['partner_kieg_info'] ?? ''));
}

function nextgen_partner_nev_from_row(array $row): string
{
    return trim((string) ($row['név'] ?? $row['partner_nev'] ?? ''));
}

function nextgen_partner_must_change_password(array $partner): bool
{
    return !empty($partner['jelszó_csere_kötelező']);
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
        $where = 'WHERE (p.`név` LIKE ? OR p.`kieg_info` LIKE ? OR p.`email` LIKE ? OR CAST(p.`id` AS CHAR) LIKE ?)';
        $params = [$like, $like, $like, $like];
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
    ?string $egyebKontakt = null,
    bool $requireChangeOnLogin = false,
    ?string $egyebInfo = null,
    ?string $kiegInfo = null
): array {
    if (!nextgen_partners_table_ready($db)) {
        return ['ok' => false, 'error' => 'A partner tábla még nincs telepítve. Futtasd: partner/sql/migration_partners.sql'];
    }
    nextgen_partner_ensure_extended_schema($db);
    $nev = trim($nev);
    $email = trim($email);
    if ($nev === '') {
        return ['ok' => false, 'error' => 'A név megadása kötelező.'];
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Érvényes e-mail cím szükséges.'];
    }
    $password = trim($password);
    if ($password !== '' && strlen($password) < 8) {
        return ['ok' => false, 'error' => 'A jelszónak legalább 8 karakter hosszúnak kell lennie.'];
    }
    if (nextgen_partner_by_email($db, $email) !== null) {
        return ['ok' => false, 'error' => 'Ez az e-mail cím már foglalt.'];
    }

    $telefon = $telefon !== null ? trim($telefon) : '';
    $egyebKontakt = $egyebKontakt !== null ? trim($egyebKontakt) : '';
    $egyebInfo = $egyebInfo !== null ? trim($egyebInfo) : '';
    $kiegInfo = $kiegInfo !== null ? trim($kiegInfo) : '';
    if (mb_strlen($kiegInfo, 'UTF-8') > 255) {
        return ['ok' => false, 'error' => 'A kiegészítő infó legfeljebb 255 karakter lehet.'];
    }

    try {
        nextgen_partner_ensure_password_schema($db);
        $hash = $password !== ''
            ? password_hash($password, PASSWORD_DEFAULT)
            : password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $stmt = $db->prepare('
            INSERT INTO `nextgen_partners` (`név`, `kieg_info`, `email`, `telefon`, `egyéb_kontakt`, `egyéb_info`, `jelszó_hash`, `aktív`, `jelszó_csere_kötelező`)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)
        ');
        $stmt->execute([
            $nev,
            $kiegInfo !== '' ? $kiegInfo : null,
            $email,
            $telefon !== '' ? $telefon : null,
            $egyebKontakt !== '' ? $egyebKontakt : null,
            $egyebInfo !== '' ? $egyebInfo : null,
            $hash,
            $requireChangeOnLogin ? 1 : 0,
        ]);

        $partnerId = (int) $db->lastInsertId();
        $logDetail = $email . ($requireChangeOnLogin ? ' (kötelező jelszócsere)' : '');
        if ($password === '') {
            $logDetail .= ' (jelszó később állítható be)';
        }
        nextgen_partner_log($db, $partnerId, 'Partner létrehozva', $logDetail);

        return ['ok' => true, 'id' => $partnerId];
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
    ?string $egyebKontakt,
    ?string $egyebInfo = null,
    ?string $kiegInfo = null
): array {
    if ($partnerId <= 0) {
        return ['ok' => false, 'error' => 'Érvénytelen partner.'];
    }
    nextgen_partner_ensure_extended_schema($db);
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
    $egyebInfo = $egyebInfo !== null ? trim($egyebInfo) : '';
    $kiegInfo = $kiegInfo !== null ? trim($kiegInfo) : '';
    if (mb_strlen($kiegInfo, 'UTF-8') > 255) {
        return ['ok' => false, 'error' => 'A kiegészítő infó legfeljebb 255 karakter lehet.'];
    }

    try {
        $dup = $db->prepare('SELECT `id` FROM `nextgen_partners` WHERE `email` = ? AND `id` <> ? LIMIT 1');
        $dup->execute([$email, $partnerId]);
        if ($dup->fetchColumn() !== false) {
            return ['ok' => false, 'error' => 'Ez az e-mail cím már foglalt.'];
        }
        $stmt = $db->prepare('
            UPDATE `nextgen_partners`
            SET `név` = ?, `kieg_info` = ?, `email` = ?, `telefon` = ?, `egyéb_kontakt` = ?, `egyéb_info` = ?
            WHERE `id` = ?
        ');
        $stmt->execute([
            $nev,
            $kiegInfo !== '' ? $kiegInfo : null,
            $email,
            $telefon !== '' ? $telefon : null,
            $egyebKontakt !== '' ? $egyebKontakt : null,
            $egyebInfo !== '' ? $egyebInfo : null,
            $partnerId,
        ]);

        nextgen_partner_log($db, $partnerId, 'Profil módosítva', $email);

        return ['ok' => true];
    } catch (Throwable $ex) {
        error_log('nextgen_partner_update_profile: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'Profil mentése sikertelen.'];
    }
}

/**
 * @return array{ok: true}|array{ok: false, error: string}
 */
function nextgen_partner_update_password(
    PDO $db,
    int $partnerId,
    string $password,
    ?bool $requireChangeOnLogin = false
): array {
    if ($partnerId <= 0) {
        return ['ok' => false, 'error' => 'Érvénytelen partner.'];
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'A jelszónak legalább 8 karakter hosszúnak kell lennie.'];
    }
    try {
        nextgen_partner_ensure_password_schema($db);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare('
            UPDATE `nextgen_partners`
            SET `jelszó_hash` = ?, `jelszó_csere_kötelező` = ?
            WHERE `id` = ?
        ');
        $stmt->execute([$hash, $requireChangeOnLogin ? 1 : 0, $partnerId]);

        $logDetail = $requireChangeOnLogin ? 'Kötelező csere a következő belépéskor' : null;
        nextgen_partner_log($db, $partnerId, 'Jelszó módosítva', $logDetail);

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

        nextgen_partner_log($db, $partnerId, $active ? 'Partner aktiválva' : 'Partner deaktiválva');

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
            SELECT o.`id`, o.`name`, po.`sort_order`, po.`role_type`, po.`role_note`
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
            SELECT t.`id`, t.`name`, pd.`sort_order`, pd.`role_type`, pd.`role_note`
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
 * @param list<array{organizer_id: int, role_type: string, role_note: ?string}> $organizerRows
 * @param list<array{tag_id: int, role_type: string, role_note: ?string}> $djRows
 * @return array{ok: true}|array{ok: false, error: string}
 */
function nextgen_partner_sync_assignments(
    PDO $db,
    int $partnerId,
    array $organizerRows,
    array $djRows
): array {
    if ($partnerId <= 0) {
        return ['ok' => false, 'error' => 'Érvénytelen partner.'];
    }

    nextgen_partner_ensure_extended_schema($db);

    try {
        $db->beginTransaction();

        $db->prepare('DELETE FROM `nextgen_partner_events_organizers` WHERE `partner_id` = ?')->execute([$partnerId]);
        $insOrg = $db->prepare('
            INSERT INTO `nextgen_partner_events_organizers` (`partner_id`, `organizer_id`, `role_type`, `role_note`, `sort_order`)
            VALUES (?, ?, ?, ?, ?)
        ');
        foreach ($organizerRows as $i => $row) {
            $insOrg->execute([
                $partnerId,
                (int) $row['organizer_id'],
                (string) $row['role_type'],
                $row['role_note'] ?? null,
                $i,
            ]);
        }

        $db->prepare('DELETE FROM `nextgen_partner_djs` WHERE `partner_id` = ?')->execute([$partnerId]);
        $insDj = $db->prepare('
            INSERT INTO `nextgen_partner_djs` (`partner_id`, `tag_id`, `role_type`, `role_note`, `sort_order`)
            VALUES (?, ?, ?, ?, ?)
        ');
        foreach ($djRows as $i => $row) {
            $insDj->execute([
                $partnerId,
                (int) $row['tag_id'],
                (string) $row['role_type'],
                $row['role_note'] ?? null,
                $i,
            ]);
        }

        $db->commit();

        $summary = sprintf('%d esemény szervező, %d DJ', count($organizerRows), count($djRows));
        nextgen_partner_log($db, $partnerId, 'Hozzárendelések módosítva', $summary);

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
 * @return array{ok: true}|array{ok: false, error: string}
 */
function nextgen_partner_delete(PDO $db, int $partnerId): array
{
    if ($partnerId <= 0 || !nextgen_partners_table_ready($db)) {
        return ['ok' => false, 'error' => 'Érvénytelen partner.'];
    }

    $partner = nextgen_partner_by_id($db, $partnerId);
    if ($partner === null) {
        return ['ok' => false, 'error' => 'Partner nem található.'];
    }

    $snapshot = sprintf(
        'ID: %d, név: %s, e-mail: %s',
        $partnerId,
        (string) ($partner['név'] ?? ''),
        (string) ($partner['email'] ?? '')
    );

    try {
        $db->beginTransaction();

        $db->prepare('DELETE FROM `nextgen_partner_messages` WHERE `partner_id` = ?')->execute([$partnerId]);
        $db->prepare('DELETE FROM `nextgen_partner_events_organizers` WHERE `partner_id` = ?')->execute([$partnerId]);
        $db->prepare('DELETE FROM `nextgen_partner_djs` WHERE `partner_id` = ?')->execute([$partnerId]);
        $db->prepare('DELETE FROM `nextgen_partner_finance_organizers` WHERE `partner_id` = ?')->execute([$partnerId]);
        if (nextgen_partner_activity_log_table_ready($db)) {
            $db->prepare('DELETE FROM `nextgen_partner_activity_log` WHERE `partner_id` = ?')->execute([$partnerId]);
        }
        if (nextgen_partner_password_reset_table_ready($db)) {
            $db->prepare('DELETE FROM `nextgen_partner_password_reset_tokens` WHERE `partner_id` = ?')->execute([$partnerId]);
        }
        $db->prepare('DELETE FROM `nextgen_partners` WHERE `id` = ?')->execute([$partnerId]);

        $db->commit();

        rendszer_log('partner', $partnerId, 'Törölve', $snapshot);

        return ['ok' => true];
    } catch (Throwable $ex) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('nextgen_partner_delete: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'Partner törlése sikertelen.'];
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
    if (!function_exists('events_tags_tables_available')) {
        require_once dirname(__DIR__, 2) . '/events/bootstrap.php';
    }
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
