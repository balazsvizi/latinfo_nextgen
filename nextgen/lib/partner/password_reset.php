<?php
declare(strict_types=1);

require_once __DIR__ . '/partners.php';

function nextgen_partner_email_is_deliverable(string $email): bool
{
    $email = trim(mb_strtolower($email, 'UTF-8'));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    if (str_ends_with($email, '@partners.latinfo.hu')) {
        return false;
    }

    return true;
}

function partner_password_reset_url(string $token): string
{
    return partner_url('jelszo_uj.php?token=' . rawurlencode($token));
}

/**
 * @return array{ok: true, message: string}|array{ok: false, error: string}
 */
function nextgen_partner_password_reset_request(PDO $db, string $email): array
{
    $genericMessage = 'Ha az e-mail cím regisztrálva van, hamarosan kapsz egy jelszó-beállító linket.';

    if (!nextgen_partner_ensure_password_schema($db)) {
        return ['ok' => false, 'error' => 'A jelszó-visszaállítás jelenleg nem elérhető.'];
    }

    $email = trim(mb_strtolower($email, 'UTF-8'));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Érvényes e-mail cím szükséges.'];
    }

    $partner = nextgen_partner_by_email($db, $email);
    if ($partner === null || empty($partner['aktív']) || !nextgen_partner_email_is_deliverable($email)) {
        return ['ok' => true, 'message' => $genericMessage];
    }

    $partnerId = (int) ($partner['id'] ?? 0);
    if ($partnerId <= 0) {
        return ['ok' => true, 'message' => $genericMessage];
    }

    try {
        $db->prepare('DELETE FROM `nextgen_partner_password_reset_tokens` WHERE `partner_id` = ? AND (`felhasználva` IS NOT NULL OR `lejárat` < NOW())')
            ->execute([$partnerId]);

        $token = bin2hex(random_bytes(32));
        $tokenHash = password_hash($token, PASSWORD_DEFAULT);
        $lejarat = (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s');

        $stmt = $db->prepare('
            INSERT INTO `nextgen_partner_password_reset_tokens` (`partner_id`, `token_hash`, `lejárat`)
            VALUES (?, ?, ?)
        ');
        $stmt->execute([$partnerId, $tokenHash, $lejarat]);

        $resetUrl = partner_password_reset_url($token);
        $nev = (string) ($partner['név'] ?? 'Partner');
        $targy = SITE_NAME . ' – Új jelszó beállítása';
        $szoveg = '<p>Kedves ' . h($nev) . '!</p>'
            . '<p>Jelszó-visszaállítást kértél a partner portálhoz. Az alábbi linken 24 órán belül beállíthatod az új jelszavad:</p>'
            . '<p><a href="' . h($resetUrl) . '">' . h($resetUrl) . '</a></p>'
            . '<p>Ha nem te kérted, hagyd figyelmen kívül ezt az üzenetet.</p>';

        if (!function_exists('email_kuld')) {
            require_once dirname(__DIR__, 2) . '/includes/email.php';
        }
        $mailResult = email_kuld($email, $targy, $szoveg, ['html' => true]);
        if (!$mailResult['ok']) {
            error_log('nextgen_partner_password_reset_request mail: ' . ($mailResult['hiba'] ?? ''));
            nextgen_partner_log($db, $partnerId, 'Jelszó-emlékeztető sikertelen', 'E-mail küldés hiba');

            return ['ok' => false, 'error' => 'Az e-mail küldése sikertelen. Próbáld később, vagy vedd fel a kapcsolatot az üzemeltetővel.'];
        }

        nextgen_partner_log($db, $partnerId, 'Jelszó-emlékeztető kérve');

        return ['ok' => true, 'message' => $genericMessage];
    } catch (Throwable $ex) {
        error_log('nextgen_partner_password_reset_request: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'A kérés feldolgozása sikertelen.'];
    }
}

/**
 * @return array{ok: true, partner_id: int}|array{ok: false, error: string}
 */
function nextgen_partner_password_reset_validate_token(PDO $db, string $token): array
{
    if (!nextgen_partner_password_reset_table_ready($db) || trim($token) === '') {
        return ['ok' => false, 'error' => 'Érvénytelen vagy lejárt link.'];
    }

    try {
        $stmt = $db->query('
            SELECT t.*, p.`aktív`
            FROM `nextgen_partner_password_reset_tokens` t
            INNER JOIN `nextgen_partners` p ON p.`id` = t.`partner_id`
            WHERE t.`felhasználva` IS NULL AND t.`lejárat` >= NOW()
            ORDER BY t.`létrehozva` DESC
            LIMIT 50
        ');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!password_verify($token, (string) ($row['token_hash'] ?? ''))) {
                continue;
            }
            if (empty($row['aktív'])) {
                return ['ok' => false, 'error' => 'A partner fiók inaktív.'];
            }

            return ['ok' => true, 'partner_id' => (int) ($row['partner_id'] ?? 0), 'token_row' => $row];
        }

        return ['ok' => false, 'error' => 'Érvénytelen vagy lejárt link.'];
    } catch (Throwable $ex) {
        error_log('nextgen_partner_password_reset_validate_token: ' . $ex->getMessage());

        return ['ok' => false, 'error' => 'Érvénytelen vagy lejárt link.'];
    }
}

/**
 * @return array{ok: true}|array{ok: false, error: string}
 */
function nextgen_partner_password_reset_complete(PDO $db, string $token, string $password): array
{
    $validation = nextgen_partner_password_reset_validate_token($db, $token);
    if (!$validation['ok']) {
        return ['ok' => false, 'error' => (string) ($validation['error'] ?? 'Érvénytelen link.')];
    }

    $partnerId = (int) ($validation['partner_id'] ?? 0);
    $tokenRow = $validation['token_row'] ?? [];
    $tokenId = (int) ($tokenRow['id'] ?? 0);

    $result = nextgen_partner_update_password($db, $partnerId, $password, false);
    if (!$result['ok']) {
        return $result;
    }

    try {
        if ($tokenId > 0) {
            $db->prepare('UPDATE `nextgen_partner_password_reset_tokens` SET `felhasználva` = NOW() WHERE `id` = ?')
                ->execute([$tokenId]);
        }
        $db->prepare('DELETE FROM `nextgen_partner_password_reset_tokens` WHERE `partner_id` = ? AND `id` <> ?')
            ->execute([$partnerId, $tokenId > 0 ? $tokenId : 0]);
        nextgen_partner_log($db, $partnerId, 'Jelszó visszaállítva (emlékeztető)');
    } catch (Throwable $ex) {
        error_log('nextgen_partner_password_reset_complete: ' . $ex->getMessage());
    }

    return ['ok' => true];
}
