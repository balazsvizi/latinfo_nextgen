<?php
declare(strict_types=1);

require_once __DIR__ . '/partners.php';
require_once __DIR__ . '/password_reset.php';

/** Alapértelmezett levélsablon kód a partner login kiértesítéshez. */
const NEXTGEN_PARTNER_LOGIN_INVITE_TEMPLATE_CODE = 'partner_login_hozzaferes';

function nextgen_partner_login_invite_load_deps(): void
{
    if (!function_exists('ensure_levelsablonok_table')) {
        require_once dirname(__DIR__, 2) . '/config/levelsablonok/bootstrap.php';
    }
    if (!function_exists('email_kuld')) {
        require_once dirname(__DIR__, 2) . '/includes/email.php';
    }
    if (!function_exists('ng_absolute_url')) {
        require_once dirname(__DIR__, 2) . '/includes/functions.php';
    }
}

/**
 * Olvasható ideiglenes jelszó (12 karakter, egyértelmű karakterek nélkül).
 */
function nextgen_partner_generate_temporary_password(int $length = 12): string
{
    $length = max(8, min(32, $length));
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $max = strlen($alphabet) - 1;
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $alphabet[random_int(0, $max)];
    }

    return $password;
}

/**
 * @return array{targy: string, html_tartalom: string}
 */
function nextgen_partner_login_invite_default_template_content(): array
{
    $siteName = defined('SITE_NAME') ? (string) SITE_NAME : 'Latinfo.hu';

    return [
        'targy' => '{{site_name}} – Partner portál hozzáférés',
        'html_tartalom' => '<p>Kedves {{partner_nev}}!</p>'
            . '<p>Elkészítettük a partner portál hozzáférését.</p>'
            . '<p>A portálra az alábbi címen tud belépni:</p>'
            . '<p><a href="{{portal_url}}">{{portal_url}}</a></p>'
            . '<p>Belépési adatok:<br>'
            . 'E-mail: <strong>{{email}}</strong><br>'
            . 'Ideiglenes jelszó: <strong>{{jelszo}}</strong></p>'
            . '<p>Az első belépéskor kötelező megváltoztatnia a jelszavát.</p>'
            . '<p>Üdvözlettel,<br>' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '</p>',
    ];
}

/**
 * Biztosítja, hogy létezzen az alap partner login levélsablon.
 */
function nextgen_partner_login_invite_ensure_default_template(PDO $db): void
{
    nextgen_partner_login_invite_load_deps();
    ensure_levelsablonok_table($db);

    try {
        $check = $db->prepare('SELECT `id` FROM `finance_email_templates` WHERE `kód` = ? LIMIT 1');
        $check->execute([NEXTGEN_PARTNER_LOGIN_INVITE_TEMPLATE_CODE]);
        if ($check->fetchColumn()) {
            return;
        }

        $defaults = nextgen_partner_login_invite_default_template_content();
        $stmt = $db->prepare('
            INSERT INTO `finance_email_templates` (`név`, `kód`, `tárgy`, `megjegyzés`, `html_tartalom`)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            'Partner login hozzáférés',
            NEXTGEN_PARTNER_LOGIN_INVITE_TEMPLATE_CODE,
            $defaults['targy'],
            'Partner portál hozzáférés létrehozásakor kiküldött kiértesítő. Változók: {{partner_nev}}, {{email}}, {{jelszo}}, {{portal_url}}, {{site_name}}',
            $defaults['html_tartalom'],
        ]);
    } catch (Throwable $ex) {
        error_log('nextgen_partner_login_invite_ensure_default_template: ' . $ex->getMessage());
    }
}

/**
 * @return list<array{id: int, nev: string, kod: string, targy: string, html_tartalom: string}>
 */
function nextgen_partner_login_invite_list_templates(PDO $db): array
{
    nextgen_partner_login_invite_ensure_default_template($db);

    try {
        $stmt = $db->prepare('
            SELECT `id`, `név`, `kód`, `tárgy`, `html_tartalom`
            FROM `finance_email_templates`
            ORDER BY (`kód` = ?) DESC, `név` ASC
        ');
        $stmt->execute([NEXTGEN_PARTNER_LOGIN_INVITE_TEMPLATE_CODE]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $ex) {
        error_log('nextgen_partner_login_invite_list_templates: ' . $ex->getMessage());

        return [];
    }

    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'id' => (int) ($row['id'] ?? 0),
            'nev' => (string) ($row['név'] ?? ''),
            'kod' => (string) ($row['kód'] ?? ''),
            'targy' => (string) ($row['tárgy'] ?? ''),
            'html_tartalom' => (string) ($row['html_tartalom'] ?? ''),
        ];
    }

    return $out;
}

/**
 * @param list<array{id: int, nev: string, kod: string, targy: string, html_tartalom: string}> $templates
 * @return array{id: int, nev: string, kod: string, targy: string, html_tartalom: string}|null
 */
function nextgen_partner_login_invite_find_template(array $templates, int $templateId = 0, string $templateCode = ''): ?array
{
    if ($templateId > 0) {
        foreach ($templates as $tpl) {
            if ((int) ($tpl['id'] ?? 0) === $templateId) {
                return $tpl;
            }
        }
    }

    $code = trim($templateCode) !== '' ? trim($templateCode) : NEXTGEN_PARTNER_LOGIN_INVITE_TEMPLATE_CODE;
    foreach ($templates as $tpl) {
        if ((string) ($tpl['kod'] ?? '') === $code) {
            return $tpl;
        }
    }

    return $templates[0] ?? null;
}

/**
 * @return array<string, string>
 */
function nextgen_partner_login_invite_placeholders(array $partner, string $temporaryPassword): array
{
    $portalUrl = ng_absolute_url(partner_url(''));
    $siteName = defined('SITE_NAME') ? (string) SITE_NAME : 'Latinfo.hu';
    $nev = trim((string) ($partner['név'] ?? ''));
    if ($nev === '') {
        $nev = 'Partner';
    }

    return [
        '{{partner_nev}}' => $nev,
        '{{email}}' => trim((string) ($partner['email'] ?? '')),
        '{{jelszo}}' => $temporaryPassword,
        '{{portal_url}}' => $portalUrl,
        '{{site_name}}' => $siteName,
    ];
}

function nextgen_partner_login_invite_apply_placeholders(string $text, array $placeholders): string
{
    return strtr($text, $placeholders);
}

/**
 * Ideiglenes jelszó beállítása + kiértesítő e-mail.
 *
 * @return array{ok: true, password: string}|array{ok: false, error: string}
 */
function nextgen_partner_login_invite_create_and_notify(
    PDO $db,
    int $partnerId,
    string $temporaryPassword,
    string $subjectTemplate,
    string $htmlTemplate,
    bool $activateIfInactive = true,
    ?int $smtpConfigId = null
): array {
    $partner = nextgen_partner_by_id($db, $partnerId);
    if ($partner === null) {
        return ['ok' => false, 'error' => 'Partner nem található.'];
    }

    $email = trim(mb_strtolower((string) ($partner['email'] ?? ''), 'UTF-8'));
    if (!nextgen_partner_email_is_deliverable($email)) {
        return ['ok' => false, 'error' => 'A partner e-mail címe nem alkalmas kiértesítésre.'];
    }

    $temporaryPassword = trim($temporaryPassword);
    if (strlen($temporaryPassword) < 8) {
        return ['ok' => false, 'error' => 'Az ideiglenes jelszónak legalább 8 karakter hosszúnak kell lennie.'];
    }

    $subjectTemplate = trim($subjectTemplate);
    $htmlTemplate = trim($htmlTemplate);
    if ($subjectTemplate === '' || $htmlTemplate === '') {
        return ['ok' => false, 'error' => 'A levél tárgya és tartalma kötelező.'];
    }

    if ($activateIfInactive && empty($partner['aktív'])) {
        $activate = nextgen_partner_set_active($db, $partnerId, true);
        if (!$activate['ok']) {
            return ['ok' => false, 'error' => (string) ($activate['error'] ?? 'Partner aktiválása sikertelen.')];
        }
    }

    $pwdResult = nextgen_partner_update_password($db, $partnerId, $temporaryPassword, true);
    if (!$pwdResult['ok']) {
        return ['ok' => false, 'error' => (string) ($pwdResult['error'] ?? 'Jelszó mentése sikertelen.')];
    }

    nextgen_partner_login_invite_load_deps();

    $placeholders = nextgen_partner_login_invite_placeholders($partner, $temporaryPassword);
    $subject = nextgen_partner_login_invite_apply_placeholders($subjectTemplate, $placeholders);
    $body = nextgen_partner_login_invite_apply_placeholders($htmlTemplate, $placeholders);

    $mailOpts = ['html' => true];
    if ($smtpConfigId !== null && $smtpConfigId > 0) {
        $mailOpts['config_id'] = $smtpConfigId;
    }

    $mailResult = email_kuld($email, $subject, $body, $mailOpts);
    if (!$mailResult['ok']) {
        error_log('nextgen_partner_login_invite_create_and_notify mail: ' . ($mailResult['hiba'] ?? ''));
        nextgen_partner_log($db, $partnerId, 'Partner login kiértesítés sikertelen', 'E-mail küldés hiba');

        return [
            'ok' => false,
            'error' => 'A jelszó beállítva, de az e-mail küldése sikertelen: ' . (string) ($mailResult['hiba'] ?? 'ismeretlen hiba'),
        ];
    }

    nextgen_partner_log($db, $partnerId, 'Partner login létrehozva és kiküldve', 'Címzett: ' . $email);

    return ['ok' => true, 'password' => $temporaryPassword];
}
