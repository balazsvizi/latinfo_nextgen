<?php
declare(strict_types=1);

function nextgen_partner_activity_log_table_ready(PDO $db): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    try {
        $db->query('SELECT 1 FROM `nextgen_partner_activity_log` LIMIT 1');
        $cached = true;
    } catch (Throwable) {
        $cached = false;
    }

    return $cached;
}

/**
 * @return array{0: 'admin'|'partner'|'system', 1: int|null}
 */
function nextgen_partner_log_resolve_actor(): array
{
    if (!empty($_SESSION['admin_id'])) {
        return ['admin', (int) $_SESSION['admin_id']];
    }
    if (!empty($_SESSION['partner_id'])) {
        return ['partner', (int) $_SESSION['partner_id']];
    }

    return ['system', null];
}

function nextgen_partner_log(
    PDO $db,
    int $partnerId,
    string $esemeny,
    ?string $reszletek = null,
    ?string $actorType = null,
    ?int $actorId = null
): void {
    if ($partnerId <= 0 || trim($esemeny) === '' || !nextgen_partner_activity_log_table_ready($db)) {
        return;
    }

    if ($actorType === null) {
        [$actorType, $actorId] = nextgen_partner_log_resolve_actor();
    }

    $esemeny = trim($esemeny);
    $reszletek = $reszletek !== null ? trim($reszletek) : null;
    if ($reszletek === '') {
        $reszletek = null;
    }

    try {
        $stmt = $db->prepare('
            INSERT INTO `nextgen_partner_activity_log` (`partner_id`, `esemény`, `részletek`, `actor_type`, `actor_id`)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $partnerId,
            $esemeny,
            $reszletek,
            $actorType,
            $actorId,
        ]);
    } catch (Throwable $ex) {
        error_log('nextgen_partner_log: ' . $ex->getMessage());
    }
}

/**
 * @return list<array<string, mixed>>
 */
function nextgen_partner_activity_log_for_partner(PDO $db, int $partnerId, int $limit = 100): array
{
    if ($partnerId <= 0 || !nextgen_partner_activity_log_table_ready($db)) {
        return [];
    }
    $limit = max(1, min(500, $limit));

    try {
        $stmt = $db->prepare("
            SELECT l.*,
                a.`név` AS admin_nev,
                p.`név` AS partner_nev
            FROM `nextgen_partner_activity_log` l
            LEFT JOIN `nextgen_admins` a ON l.`actor_type` = 'admin' AND a.`id` = l.`actor_id`
            LEFT JOIN `nextgen_partners` p ON l.`actor_type` = 'partner' AND p.`id` = l.`actor_id`
            WHERE l.`partner_id` = ?
            ORDER BY l.`létrehozva` DESC, l.`id` DESC
            LIMIT {$limit}
        ");
        $stmt->execute([$partnerId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return [];
    }
}

/**
 * @return list<array<string, mixed>>
 */
function nextgen_partner_activity_log_recent(PDO $db, int $limit = 50): array
{
    if (!nextgen_partner_activity_log_table_ready($db)) {
        return [];
    }
    $limit = max(1, min(200, $limit));

    try {
        $stmt = $db->query("
            SELECT l.*,
                pt.`név` AS target_partner_nev,
                pt.`kieg_info` AS target_partner_kieg_info,
                pt.`email` AS target_partner_email,
                a.`név` AS admin_nev,
                p.`név` AS partner_nev
            FROM `nextgen_partner_activity_log` l
            INNER JOIN `nextgen_partners` pt ON pt.`id` = l.`partner_id`
            LEFT JOIN `nextgen_admins` a ON l.`actor_type` = 'admin' AND a.`id` = l.`actor_id`
            LEFT JOIN `nextgen_partners` p ON l.`actor_type` = 'partner' AND p.`id` = l.`actor_id`
            ORDER BY l.`létrehozva` DESC, l.`id` DESC
            LIMIT {$limit}
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return [];
    }
}

function nextgen_partner_activity_log_actor_label(array $row): string
{
    return match ($row['actor_type'] ?? '') {
        'admin' => trim((string) ($row['admin_nev'] ?? '')) !== ''
            ? (string) $row['admin_nev'] . ' (admin)'
            : 'Admin',
        'partner' => trim((string) ($row['partner_nev'] ?? '')) !== ''
            ? (string) $row['partner_nev'] . ' (partner)'
            : 'Partner',
        default => 'Rendszer',
    };
}
