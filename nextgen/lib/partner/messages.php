<?php
declare(strict_types=1);

require_once __DIR__ . '/partners.php';
require_once __DIR__ . '/message_notifications.php';

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

        $messageId = (int) $db->lastInsertId();
        $preview = mb_strlen($message) > 120 ? mb_substr($message, 0, 120) . '…' : $message;
        nextgen_partner_log($db, $partnerId, 'Üzenet küldve (partner)', $preview);
        nextgen_partner_message_notify_admins_on_partner_send($db, $partnerId, $message, $messageId);

        return ['ok' => true, 'id' => $messageId];
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

        $messageId = (int) $db->lastInsertId();
        $preview = mb_strlen($message) > 120 ? mb_substr($message, 0, 120) . '…' : $message;
        nextgen_partner_log($db, $partnerId, 'Üzenet küldve (admin)', $preview);
        nextgen_partner_message_notify_partner_on_admin_reply($db, $partnerId, $message, $adminId, $messageId);

        return ['ok' => true, 'id' => $messageId];
    } catch (Throwable $ex) {
        error_log('nextgen_partner_message_send_admin: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'Üzenet küldése sikertelen.'];
    }
}

/**
 * @return array{ok: true}|array{ok: false, error: string}
 */
function nextgen_partner_message_mark_no_reply(PDO $db, int $messageId, string $markedBy, int $actorId): array
{
    if ($messageId <= 0) {
        return ['ok' => false, 'error' => 'Érvénytelen üzenet.'];
    }
    if ($markedBy !== 'admin' && $markedBy !== 'partner') {
        return ['ok' => false, 'error' => 'Érvénytelen művelet.'];
    }
    if ($actorId <= 0) {
        return ['ok' => false, 'error' => 'Érvénytelen művelet.'];
    }
    try {
        $msgStmt = $db->prepare('
            SELECT `id`, `partner_id`, `creator_type`, `nincs_valasz`
            FROM `nextgen_partner_messages`
            WHERE `id` = ?
            LIMIT 1
        ');
        $msgStmt->execute([$messageId]);
        $msg = $msgStmt->fetch(PDO::FETCH_ASSOC);
        if (!$msg) {
            return ['ok' => false, 'error' => 'Üzenet nem található.'];
        }
        if (!empty($msg['nincs_valasz'])) {
            return ['ok' => true];
        }

        $partnerId = (int) ($msg['partner_id'] ?? 0);
        $creatorType = (string) ($msg['creator_type'] ?? '');

        if ($markedBy === 'partner') {
            if ($actorId !== $partnerId || $creatorType !== 'admin') {
                return ['ok' => false, 'error' => 'Ehhez az üzenethez nincs jogosultságod.'];
            }
        } elseif ($creatorType !== 'partner' && $creatorType !== 'admin') {
            return ['ok' => false, 'error' => 'Érvénytelen üzenet.'];
        }

        $stmt = $db->prepare('UPDATE `nextgen_partner_messages` SET `nincs_valasz` = 1 WHERE `id` = ?');
        $stmt->execute([$messageId]);

        if ($partnerId > 0) {
            $detail = $markedBy === 'partner'
                ? 'Partner jelölte: nem kell válaszolni'
                : 'Admin jelölte: nem kell válaszolni';
            nextgen_partner_log($db, $partnerId, 'Üzenet megjelölve: nem kell válaszolni', $detail . ' (#' . $messageId . ')');
        }

        return ['ok' => true];
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Művelet sikertelen.'];
    }
}

function nextgen_partner_message_needs_admin_reply(array $message, array $threadMessages): bool
{
    if (($message['creator_type'] ?? '') !== 'partner' || !empty($message['nincs_valasz'])) {
        return false;
    }

    $messageAt = (string) ($message['létrehozva'] ?? '');
    $messageId = (int) ($message['id'] ?? 0);
    foreach ($threadMessages as $other) {
        if (($other['creator_type'] ?? '') !== 'admin') {
            continue;
        }
        $otherAt = (string) ($other['létrehozva'] ?? '');
        $otherId = (int) ($other['id'] ?? 0);
        if ($otherAt > $messageAt || ($otherAt === $messageAt && $otherId > $messageId)) {
            return false;
        }
    }

    return true;
}

function nextgen_partner_message_needs_partner_reply(array $message, array $threadMessages): bool
{
    if (($message['creator_type'] ?? '') !== 'admin' || !empty($message['nincs_valasz'])) {
        return false;
    }

    $messageAt = (string) ($message['létrehozva'] ?? '');
    $messageId = (int) ($message['id'] ?? 0);
    foreach ($threadMessages as $other) {
        if (($other['creator_type'] ?? '') !== 'partner') {
            continue;
        }
        $otherAt = (string) ($other['létrehozva'] ?? '');
        $otherId = (int) ($other['id'] ?? 0);
        if ($otherAt > $messageAt || ($otherAt === $messageAt && $otherId > $messageId)) {
            return false;
        }
    }

    return true;
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
                p.`kieg_info` AS partner_kieg_info,
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
            GROUP BY p.`id`, p.`név`, p.`kieg_info`, p.`email`, p.`aktív`
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
                'partner_kieg_info' => (string) ($row['partner_kieg_info'] ?? ''),
                'partner_email' => (string) ($row['partner_email'] ?? ''),
                'last_at' => (string) ($row['last_at'] ?? ''),
                'last_message' => $lastMsg,
                'needs_reply' => $needsReply,
            ];
        }

        usort($threads, static function (array $a, array $b): int {
            $aOpen = !empty($a['needs_reply']) ? 1 : 0;
            $bOpen = !empty($b['needs_reply']) ? 1 : 0;
            if ($aOpen !== $bOpen) {
                return $bOpen <=> $aOpen;
            }

            return strcmp((string) ($b['last_at'] ?? ''), (string) ($a['last_at'] ?? ''));
        });

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
