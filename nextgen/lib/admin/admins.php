<?php
declare(strict_types=1);

function nextgen_admin_ensure_notification_columns(PDO $db): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = true;
    try {
        $col = $db->query("SHOW COLUMNS FROM `nextgen_admins` LIKE 'email'")->fetch();
        if (!$col) {
            $db->exec("ALTER TABLE `nextgen_admins` ADD COLUMN `email` VARCHAR(255) NULL AFTER `felhasználónév`");
        }
        $notifyCol = $db->query("SHOW COLUMNS FROM `nextgen_admins` LIKE 'partner_uzenet_email'")->fetch();
        if (!$notifyCol) {
            $db->exec("ALTER TABLE `nextgen_admins` ADD COLUMN `partner_uzenet_email` TINYINT(1) NOT NULL DEFAULT 0 AFTER `email`");
        }
    } catch (Throwable $ex) {
        error_log('nextgen_admin_ensure_notification_columns: ' . $ex->getMessage());
        $ready = false;
    }

    return $ready;
}

/**
 * @return list<array{id: int, név: string, email: string}>
 */
function nextgen_admin_partner_message_email_recipients(PDO $db): array
{
    if (!nextgen_admin_ensure_notification_columns($db)) {
        return [];
    }
    try {
        $stmt = $db->query('
            SELECT `id`, `név`, `email`
            FROM `nextgen_admins`
            WHERE `aktív` = 1
              AND `partner_uzenet_email` = 1
              AND `email` IS NOT NULL
              AND TRIM(`email`) != \'\'
        ');
        $recipients = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $email = trim((string) ($row['email'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $recipients[] = [
                'id' => (int) ($row['id'] ?? 0),
                'név' => (string) ($row['név'] ?? ''),
                'email' => $email,
            ];
        }

        return $recipients;
    } catch (Throwable $ex) {
        error_log('nextgen_admin_partner_message_email_recipients: ' . $ex->getMessage());

        return [];
    }
}
