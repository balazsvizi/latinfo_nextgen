<?php
declare(strict_types=1);

function nextgen_partner_messages_table_ready(PDO $db): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    try {
        $db->query('SELECT 1 FROM `nextgen_partner_messages` LIMIT 1');
        $cached = true;
    } catch (Throwable) {
        $cached = false;
    }

    return $cached;
}

/**
 * @return list<array<string, mixed>>
 */
function nextgen_partner_messages_for_partner(PDO $db, int $partnerId): array
{
    if ($partnerId <= 0 || !nextgen_partner_messages_table_ready($db)) {
        return [];
    }
    try {
        $stmt = $db->prepare('
            SELECT m.*, a.`név` AS admin_nev
            FROM `nextgen_partner_messages` m
            LEFT JOIN `nextgen_admins` a ON m.`creator_type` = \'admin\' AND a.`id` = m.`creator_id`
            WHERE m.`partner_id` = ?
            ORDER BY m.`létrehozva` DESC, m.`id` DESC
        ');
        $stmt->execute([$partnerId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return [];
    }
}

/**
 * @return array{ok: true, id: int}|array{ok: false, error: string}
 */
function nextgen_partner_message_send_partner(PDO $db, int $partnerId, string $message): array
{
    $message = trim($message);
    if ($partnerId <= 0) {
        return ['ok' => false, 'error' => 'Érvénytelen partner.'];
    }
    if ($message === '') {
        return ['ok' => false, 'error' => 'Az üzenet nem lehet üres.'];
    }
    if (!nextgen_partner_messages_table_ready($db)) {
        return ['ok' => false, 'error' => 'Az üzenőfal tábla még nincs telepítve.'];
    }
    try {
        $stmt = $db->prepare('
            INSERT INTO `nextgen_partner_messages` (`partner_id`, `creator_type`, `creator_id`, `message`, `nincs_valasz`)
            VALUES (?, \'partner\', ?, ?, 0)
        ');
        $stmt->execute([$partnerId, $partnerId, $message]);

        return ['ok' => true, 'id' => (int) $db->lastInsertId()];
    } catch (Throwable $ex) {
        error_log('nextgen_partner_message_send_partner: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'Üzenet küldése sikertelen.'];
    }
}

/**
 * @return array{ok: true, id: int}|array{ok: false, error: string}
 */
function nextgen_partner_message_send_admin(PDO $db, int $partnerId, int $adminId, string $message): array
{
    $message = trim($message);
    if ($partnerId <= 0 || $adminId <= 0) {
        return ['ok' => false, 'error' => 'Érvénytelen adatok.'];
    }
    if ($message === '') {
        return ['ok' => false, 'error' => 'Az üzenet nem lehet üres.'];
    }
    if (!nextgen_partner_messages_table_ready($db)) {
        return ['ok' => false, 'error' => 'Az üzenőfal tábla még nincs telepítve.'];
    }
    try {
        $stmt = $db->prepare('
            INSERT INTO `nextgen_partner_messages` (`partner_id`, `creator_type`, `creator_id`, `message`, `nincs_valasz`)
            VALUES (?, \'admin\', ?, ?, 0)
        ');
        $stmt->execute([$partnerId, $adminId, $message]);

        return ['ok' => true, 'id' => (int) $db->lastInsertId()];
    } catch (Throwable $ex) {
        error_log('nextgen_partner_message_send_admin: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'Üzenet küldése sikertelen.'];
    }
}

/**
 * @return array{ok: true}|array{ok: false, error: string}
 */
function nextgen_partner_message_mark_no_reply(PDO $db, int $messageId): array
{
    if ($messageId <= 0) {
        return ['ok' => false, 'error' => 'Érvénytelen üzenet.'];
    }
    try {
        $stmt = $db->prepare('UPDATE `nextgen_partner_messages` SET `nincs_valasz` = 1 WHERE `id` = ?');
        $stmt->execute([$messageId]);

        return ['ok' => true];
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Művelet sikertelen.'];
    }
}

/**
 * @return list<array<string, mixed>>
 */
function nextgen_partner_messages_inbox_threads(PDO $db): array
{
    if (!nextgen_partner_messages_table_ready($db) || !nextgen_partners_table_ready($db)) {
        return [];
    }
    try {
        $stmt = $db->query('
            SELECT
                p.`id` AS partner_id,
                p.`név` AS partner_nev,
                p.`email` AS partner_email,
                p.`aktív` AS partner_aktiv,
                MAX(m.`létrehozva`) AS last_at,
                (
                    SELECT m2.`id` FROM `nextgen_partner_messages` m2
                    WHERE m2.`partner_id` = p.`id`
                    ORDER BY m2.`létrehozva` DESC, m2.`id` DESC
                    LIMIT 1
                ) AS last_message_id
            FROM `nextgen_partners` p
            INNER JOIN `nextgen_partner_messages` m ON m.`partner_id` = p.`id`
            GROUP BY p.`id`, p.`név`, p.`email`, p.`aktív`
            ORDER BY last_at DESC
        ');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $threads = [];
        foreach ($rows as $row) {
            $lastId = (int) ($row['last_message_id'] ?? 0);
            if ($lastId <= 0) {
                continue;
            }
            $lastStmt = $db->prepare('SELECT * FROM `nextgen_partner_messages` WHERE `id` = ? LIMIT 1');
            $lastStmt->execute([$lastId]);
            $lastMsg = $lastStmt->fetch(PDO::FETCH_ASSOC);
            if (!$lastMsg) {
                continue;
            }
            $needsReply = ($lastMsg['creator_type'] ?? '') === 'partner'
                && empty($lastMsg['nincs_valasz'])
                && !empty($row['partner_aktiv']);
            $threads[] = [
                'partner_id' => (int) $row['partner_id'],
                'partner_nev' => (string) ($row['partner_nev'] ?? ''),
                'partner_email' => (string) ($row['partner_email'] ?? ''),
                'last_at' => (string) ($row['last_at'] ?? ''),
                'last_message' => $lastMsg,
                'needs_reply' => $needsReply,
            ];
        }

        return $threads;
    } catch (Throwable) {
        return [];
    }
}

function nextgen_partner_message_author_label(array $message, ?string $partnerName = null): string
{
    if (($message['creator_type'] ?? '') === 'admin') {
        $adminNev = trim((string) ($message['admin_nev'] ?? ''));

        return $adminNev !== '' ? $adminNev . ' (admin)' : 'Admin';
    }

    return $partnerName !== null && $partnerName !== '' ? $partnerName : 'Partner';
}

function nextgen_partner_unread_reply_count(PDO $db): int
{
    $count = 0;
    foreach (nextgen_partner_messages_inbox_threads($db) as $thread) {
        if (!empty($thread['needs_reply'])) {
            $count++;
        }
    }

    return $count;
}
