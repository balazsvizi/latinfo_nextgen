<?php
declare(strict_types=1);

function events_organizer_accounts_table_ready(PDO $db): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    try {
        $db->query('SELECT 1 FROM `events_organizer_accounts` LIMIT 1');
        $cached = true;
    } catch (Throwable) {
        $cached = false;
    }

    return $cached;
}

/**
 * @return array<string, mixed>|null
 */
function events_organizer_account_by_organizer_id(PDO $db, int $organizerId): ?array
{
    if ($organizerId <= 0 || !events_organizer_accounts_table_ready($db)) {
        return null;
    }
    try {
        $stmt = $db->prepare('
            SELECT a.*, o.`name` AS organizer_name
            FROM `events_organizer_accounts` a
            INNER JOIN `events_organizers` o ON o.`id` = a.`organizer_id`
            WHERE a.`organizer_id` = ?
            LIMIT 1
        ');
        $stmt->execute([$organizerId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (Throwable) {
        return null;
    }
}

/**
 * @return array<string, mixed>|null
 */
function events_organizer_account_by_email(PDO $db, string $email): ?array
{
    $email = trim($email);
    if ($email === '' || !events_organizer_accounts_table_ready($db)) {
        return null;
    }
    try {
        $stmt = $db->prepare('
            SELECT a.*, o.`name` AS organizer_name
            FROM `events_organizer_accounts` a
            INNER JOIN `events_organizers` o ON o.`id` = a.`organizer_id`
            WHERE a.`email` = ?
            LIMIT 1
        ');
        $stmt->execute([$email]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    } catch (Throwable) {
        return null;
    }
}

/**
 * @return array{ok: true, id: int}|array{ok: false, error: string}
 */
function events_organizer_account_create(
    PDO $db,
    int $organizerId,
    string $email,
    string $password,
    ?string $nev = null
): array {
    if (!events_organizer_accounts_table_ready($db)) {
        return ['ok' => false, 'error' => 'A portál fiók tábla még nincs létrehozva. Futtasd: events/organizers/sql/migration_organizer_accounts.sql'];
    }
    if ($organizerId <= 0) {
        return ['ok' => false, 'error' => 'Érvénytelen szervező.'];
    }
    $email = trim($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Érvényes e-mail cím szükséges.'];
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'A jelszónak legalább 8 karakter hosszúnak kell lennie.'];
    }

    $existing = events_organizer_account_by_organizer_id($db, $organizerId);
    if ($existing !== null) {
        return ['ok' => false, 'error' => 'Ehhez a szervezőhöz már van portál fiók.'];
    }

    $dup = events_organizer_account_by_email($db, $email);
    if ($dup !== null) {
        return ['ok' => false, 'error' => 'Ez az e-mail cím már foglalt.'];
    }

    $nev = $nev !== null ? trim($nev) : '';
    if ($nev === '') {
        $nev = null;
    }

    try {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare('
            INSERT INTO `events_organizer_accounts` (`organizer_id`, `email`, `jelszó_hash`, `név`, `aktív`)
            VALUES (?, ?, ?, ?, 1)
        ');
        $stmt->execute([$organizerId, $email, $hash, $nev]);

        return ['ok' => true, 'id' => (int) $db->lastInsertId()];
    } catch (Throwable $ex) {
        error_log('events_organizer_account_create: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'Fiók létrehozása sikertelen.'];
    }
}

/**
 * @return array{ok: true}|array{ok: false, error: string}
 */
function events_organizer_account_update_password(PDO $db, int $accountId, string $password): array
{
    if ($accountId <= 0) {
        return ['ok' => false, 'error' => 'Érvénytelen fiók.'];
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'A jelszónak legalább 8 karakter hosszúnak kell lennie.'];
    }
    try {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare('UPDATE `events_organizer_accounts` SET `jelszó_hash` = ? WHERE `id` = ?');
        $stmt->execute([$hash, $accountId]);
        if ($stmt->rowCount() === 0) {
            return ['ok' => false, 'error' => 'Fiók nem található.'];
        }

        return ['ok' => true];
    } catch (Throwable $ex) {
        error_log('events_organizer_account_update_password: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'Jelszó mentése sikertelen.'];
    }
}

/**
 * @return array{ok: true}|array{ok: false, error: string}
 */
function events_organizer_account_set_active(PDO $db, int $accountId, bool $active): array
{
    if ($accountId <= 0) {
        return ['ok' => false, 'error' => 'Érvénytelen fiók.'];
    }
    try {
        $stmt = $db->prepare('UPDATE `events_organizer_accounts` SET `aktív` = ? WHERE `id` = ?');
        $stmt->execute([$active ? 1 : 0, $accountId]);

        return ['ok' => true];
    } catch (Throwable $ex) {
        error_log('events_organizer_account_set_active: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'Státusz mentése sikertelen.'];
    }
}

/**
 * @return array{ok: true}|array{ok: false, error: string}
 */
function events_organizer_account_update_profile(PDO $db, int $accountId, string $email, ?string $nev): array
{
    if ($accountId <= 0) {
        return ['ok' => false, 'error' => 'Érvénytelen fiók.'];
    }
    $email = trim($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Érvényes e-mail cím szükséges.'];
    }
    $nev = $nev !== null ? trim($nev) : '';
    if ($nev === '') {
        $nev = null;
    }

    try {
        $dup = $db->prepare('SELECT `id` FROM `events_organizer_accounts` WHERE `email` = ? AND `id` <> ? LIMIT 1');
        $dup->execute([$email, $accountId]);
        if ($dup->fetchColumn() !== false) {
            return ['ok' => false, 'error' => 'Ez az e-mail cím már foglalt.'];
        }
        $stmt = $db->prepare('UPDATE `events_organizer_accounts` SET `email` = ?, `név` = ? WHERE `id` = ?');
        $stmt->execute([$email, $nev, $accountId]);

        return ['ok' => true];
    } catch (Throwable $ex) {
        error_log('events_organizer_account_update_profile: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'Profil mentése sikertelen.'];
    }
}
