<?php
declare(strict_types=1);

/**
 * Esemény közzétételi értesítő e-mail a szervező(k)nek.
 */

const EVENTS_NOTIFY_EMAIL_TEMPLATE_CODE = 'event_published_notify';
const EVENTS_NOTIFY_EMAIL_SETTING_TEMPLATE_ID = 'event_notify_email_template_id';
const EVENTS_NOTIFY_EMAIL_SETTING_SMTP_ID = 'event_notify_email_smtp_id';
const EVENTS_NOTIFY_EMAIL_SETTING_BCC = 'event_notify_email_bcc';
const EVENTS_NOTIFY_EMAIL_DEFAULT_BCC = 'balazsv@gmail.com';
const EVENTS_NOTIFY_EMAIL_PREFERRED_FROM = 'naptar@latinfo.hu';

function events_notify_email_load_deps(): void
{
    if (!function_exists('ensure_levelsablonok_table')) {
        require_once dirname(__DIR__, 2) . '/config/levelsablonok/bootstrap.php';
    }
    if (!function_exists('email_kuld')) {
        require_once dirname(__DIR__, 2) . '/includes/email.php';
    }
    if (!function_exists('events_slug_redirects_ensure_schema')) {
        require_once __DIR__ . '/slug_redirects.php';
    }
    if (!function_exists('ng_absolute_url')) {
        require_once dirname(__DIR__, 2) . '/includes/functions.php';
    }
}

function events_notify_email_ensure_schema(PDO $db): bool
{
    static $done = false;
    if ($done) {
        return true;
    }

    events_notify_email_load_deps();
    events_slug_redirects_ensure_schema($db);
    ensure_levelsablonok_table($db);

    try {
        if (!$db->inTransaction()) {
            $db->exec("
                CREATE TABLE IF NOT EXISTS `events_email_send_log` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `event_id` INT UNSIGNED NOT NULL,
                    `template_id` INT UNSIGNED NULL,
                    `tracking_token` CHAR(64) NOT NULL,
                    `to_emails` TEXT NOT NULL,
                    `bcc_emails` TEXT NULL,
                    `from_email` VARCHAR(255) NULL,
                    `smtp_account_id` INT UNSIGNED NULL,
                    `subject` VARCHAR(255) NOT NULL,
                    `body_html` MEDIUMTEXT NOT NULL,
                    `organizer_ids` VARCHAR(500) NOT NULL DEFAULT '',
                    `admin_id` INT UNSIGNED NULL,
                    `sent_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `first_opened_at` DATETIME NULL,
                    `last_opened_at` DATETIME NULL,
                    `open_count` INT UNSIGNED NOT NULL DEFAULT 0,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_events_email_send_token` (`tracking_token`),
                    KEY `idx_events_email_send_event` (`event_id`),
                    KEY `idx_events_email_send_sent` (`sent_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    } catch (Throwable $ex) {
        error_log('events_notify_email_ensure_schema: ' . $ex->getMessage());

        return false;
    }

    events_notify_email_ensure_default_template($db);
    $done = true;

    return true;
}

/**
 * @return array{targy: string, html_tartalom: string}
 */
function events_notify_email_default_template_content(): array
{
    $siteName = defined('SITE_NAME') ? (string) SITE_NAME : 'Latinfo.hu';

    return [
        'targy' => '{{site_name}} – megjelent az eseményed: {{event_name}}',
        'html_tartalom' => '<p>Kedves {{organizer_names}}!</p>'
            . '<p>Örömmel jelezzük, hogy az alábbi eseményed megjelent a ' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . ' naptárában.</p>'
            . '<p><strong>{{event_name}}</strong><br>'
            . 'Időpont: {{event_start}}<br>'
            . 'Helyszín: {{venue_name}}</p>'
            . '<p>Az esemény nyilvános oldala:<br>'
            . '<a href="{{event_url}}">{{event_url}}</a></p>'
            . '<p>Ha bármit módosítanál vagy kérdésed van, válaszolj erre a levélre.</p>'
            . '<p>Üdvözlettel,<br>' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . ' naptár</p>',
    ];
}

function events_notify_email_ensure_default_template(PDO $db): void
{
    events_notify_email_load_deps();
    ensure_levelsablonok_table($db);

    try {
        $check = $db->prepare('SELECT `id` FROM `finance_email_templates` WHERE `kód` = ? LIMIT 1');
        $check->execute([EVENTS_NOTIFY_EMAIL_TEMPLATE_CODE]);
        $existingId = (int) ($check->fetchColumn() ?: 0);
        if ($existingId > 0) {
            if (events_notify_email_default_template_id($db) <= 0) {
                events_notify_email_set_default_template_id($db, $existingId);
            }

            return;
        }

        $defaults = events_notify_email_default_template_content();
        $stmt = $db->prepare('
            INSERT INTO `finance_email_templates` (`név`, `kód`, `tárgy`, `megjegyzés`, `html_tartalom`)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            'Esemény megjelent – szervezői értesítő',
            EVENTS_NOTIFY_EMAIL_TEMPLATE_CODE,
            $defaults['targy'],
            'Esemény szerkesztőből küldhető visszajelző. Változók: {{organizer_names}}, {{event_name}}, {{event_start}}, {{event_end}}, {{venue_name}}, {{event_url}}, {{site_name}}',
            $defaults['html_tartalom'],
        ]);
        $newId = (int) $db->lastInsertId();
        if ($newId > 0) {
            events_notify_email_set_default_template_id($db, $newId);
        }
    } catch (Throwable $ex) {
        error_log('events_notify_email_ensure_default_template: ' . $ex->getMessage());
    }
}

function events_notify_email_setting_get(PDO $db, string $key, string $fallback = ''): string
{
    events_notify_email_load_deps();
    if (!events_slug_redirects_tables_available($db)) {
        return $fallback;
    }
    try {
        $st = $db->prepare('SELECT `setting_value` FROM `events_app_settings` WHERE `setting_key` = ? LIMIT 1');
        $st->execute([$key]);
        $raw = $st->fetchColumn();

        return $raw === false ? $fallback : (string) $raw;
    } catch (Throwable) {
        return $fallback;
    }
}

function events_notify_email_setting_set(PDO $db, string $key, string $value): void
{
    events_notify_email_load_deps();
    if (!events_slug_redirects_ensure_schema($db)) {
        throw new RuntimeException('A beállítások táblája nem érhető el.');
    }
    $st = $db->prepare('
        INSERT INTO `events_app_settings` (`setting_key`, `setting_value`)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)
    ');
    $st->execute([$key, $value]);
}

function events_notify_email_default_template_id(PDO $db): int
{
    return (int) events_notify_email_setting_get($db, EVENTS_NOTIFY_EMAIL_SETTING_TEMPLATE_ID, '0');
}

function events_notify_email_set_default_template_id(PDO $db, int $templateId): void
{
    events_notify_email_setting_set($db, EVENTS_NOTIFY_EMAIL_SETTING_TEMPLATE_ID, (string) max(0, $templateId));
}

function events_notify_email_default_bcc(PDO $db): string
{
    $bcc = trim(events_notify_email_setting_get($db, EVENTS_NOTIFY_EMAIL_SETTING_BCC, EVENTS_NOTIFY_EMAIL_DEFAULT_BCC));

    return $bcc !== '' ? $bcc : EVENTS_NOTIFY_EMAIL_DEFAULT_BCC;
}

function events_notify_email_default_smtp_id(PDO $db): int
{
    $saved = (int) events_notify_email_setting_get($db, EVENTS_NOTIFY_EMAIL_SETTING_SMTP_ID, '0');
    if ($saved > 0) {
        return $saved;
    }

    try {
        $st = $db->prepare('
            SELECT `id` FROM `finance_email_accounts`
            WHERE LOWER(`from_email`) = LOWER(?)
            ORDER BY `alapértelmezett` DESC, `id` ASC
            LIMIT 1
        ');
        $st->execute([EVENTS_NOTIFY_EMAIL_PREFERRED_FROM]);
        $id = (int) ($st->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }

        $st2 = $db->query('
            SELECT `id` FROM `finance_email_accounts`
            ORDER BY `alapértelmezett` DESC, `név` ASC
            LIMIT 1
        ');

        return (int) ($st2->fetchColumn() ?: 0);
    } catch (Throwable) {
        return 0;
    }
}

/**
 * @return list<array{id: int, nev: string, kod: string, targy: string, html_tartalom: string}>
 */
function events_notify_email_list_templates(PDO $db): array
{
    events_notify_email_ensure_schema($db);
    $defaultId = events_notify_email_default_template_id($db);

    try {
        $stmt = $db->prepare('
            SELECT `id`, `név`, `kód`, `tárgy`, `html_tartalom`
            FROM `finance_email_templates`
            ORDER BY (`id` = ?) DESC, (`kód` = ?) DESC, `név` ASC
        ');
        $stmt->execute([$defaultId, EVENTS_NOTIFY_EMAIL_TEMPLATE_CODE]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $ex) {
        error_log('events_notify_email_list_templates: ' . $ex->getMessage());

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
function events_notify_email_find_template(array $templates, int $templateId = 0): ?array
{
    if ($templateId > 0) {
        foreach ($templates as $tpl) {
            if ((int) ($tpl['id'] ?? 0) === $templateId) {
                return $tpl;
            }
        }
    }
    foreach ($templates as $tpl) {
        if ((string) ($tpl['kod'] ?? '') === EVENTS_NOTIFY_EMAIL_TEMPLATE_CODE) {
            return $tpl;
        }
    }

    return $templates[0] ?? null;
}

/**
 * @return list<array{id: int, nev: string, from_email: string, from_name: string, alapertelmezett: int}>
 */
function events_notify_email_list_smtp_accounts(PDO $db): array
{
    try {
        $rows = $db->query('
            SELECT `id`, `név`, `from_email`, `from_name`, `alapértelmezett`
            FROM `finance_email_accounts`
            ORDER BY `alapértelmezett` DESC, `név` ASC
        ')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable) {
        return [];
    }

    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'id' => (int) ($row['id'] ?? 0),
            'nev' => (string) ($row['név'] ?? ''),
            'from_email' => (string) ($row['from_email'] ?? ''),
            'from_name' => (string) ($row['from_name'] ?? ''),
            'alapertelmezett' => (int) ($row['alapértelmezett'] ?? 0),
        ];
    }

    return $out;
}

/**
 * @param list<int> $organizerIds
 * @return list<string>
 */
function events_notify_email_recipient_emails(PDO $db, array $organizerIds): array
{
    $organizerIds = array_values(array_unique(array_filter(
        array_map(static fn ($v): int => (int) $v, $organizerIds),
        static fn (int $id): bool => $id > 0
    )));
    if ($organizerIds === []) {
        return [];
    }

    $emails = [];
    $placeholders = implode(',', array_fill(0, count($organizerIds), '?'));

    try {
        $st = $db->prepare("
            SELECT DISTINCT LOWER(TRIM(p.`email`)) AS email
            FROM `nextgen_partner_events_organizers` po
            INNER JOIN `nextgen_partners` p ON p.`id` = po.`partner_id`
            WHERE po.`organizer_id` IN ({$placeholders})
              AND TRIM(COALESCE(p.`email`, '')) <> ''
        ");
        $st->execute($organizerIds);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $email) {
            $email = trim((string) $email);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[$email] = true;
            }
        }
    } catch (Throwable) {
        // partner tábla hiányozhat
    }

    try {
        $st = $db->prepare("
            SELECT DISTINCT LOWER(TRIM(a.`email`)) AS email
            FROM `events_organizer_accounts` a
            WHERE a.`organizer_id` IN ({$placeholders})
              AND TRIM(COALESCE(a.`email`, '')) <> ''
              AND COALESCE(a.`aktív`, 1) = 1
        ");
        $st->execute($organizerIds);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $email) {
            $email = trim((string) $email);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[$email] = true;
            }
        }
    } catch (Throwable) {
        // legacy portál fiók tábla hiányozhat
    }

    return array_keys($emails);
}

/**
 * @param list<int> $organizerIds
 * @return array<string, string>
 */
function events_notify_email_placeholders(PDO $db, array $event, array $organizerIds): array
{
    $siteName = defined('SITE_NAME') ? (string) SITE_NAME : 'Latinfo.hu';
    $eventName = trim((string) ($event['event_name'] ?? ''));
    $slug = trim((string) ($event['event_slug'] ?? ''));
    $eventUrl = '';
    if ($slug !== '' && function_exists('events_megjelenit_url')) {
        $eventUrl = events_absolute_url(events_megjelenit_url($slug));
    }

    $organizerNames = [];
    $ids = array_values(array_unique(array_filter(
        array_map(static fn ($v): int => (int) $v, $organizerIds),
        static fn (int $id): bool => $id > 0
    )));
    if ($ids !== []) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        try {
            $st = $db->prepare("SELECT `name` FROM `events_organizers` WHERE `id` IN ({$ph}) ORDER BY `name` ASC");
            $st->execute($ids);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $name) {
                $name = trim((string) $name);
                if ($name !== '') {
                    $organizerNames[] = $name;
                }
            }
        } catch (Throwable) {
            // ignore
        }
    }

    $venueName = '–';
    $venueId = (int) ($event['venue_id'] ?? 0);
    if ($venueId > 0) {
        try {
            $st = $db->prepare('SELECT `name` FROM `events_venues` WHERE `id` = ? LIMIT 1');
            $st->execute([$venueId]);
            $vn = trim((string) ($st->fetchColumn() ?: ''));
            if ($vn !== '') {
                $venueName = $vn;
            }
        } catch (Throwable) {
            // ignore
        }
    }

    $fmt = static function (?string $raw): string {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '–';
        }
        $ts = strtotime($raw);

        return $ts !== false ? date('Y. m. d. H:i', $ts) : $raw;
    };

    return [
        '{{organizer_names}}' => $organizerNames !== [] ? implode(', ', $organizerNames) : 'Szervező',
        '{{event_name}}' => $eventName !== '' ? $eventName : 'Esemény',
        '{{event_start}}' => $fmt(isset($event['event_start']) ? (string) $event['event_start'] : null),
        '{{event_end}}' => $fmt(isset($event['event_end']) ? (string) $event['event_end'] : null),
        '{{venue_name}}' => $venueName,
        '{{event_url}}' => $eventUrl !== '' ? $eventUrl : '–',
        '{{site_name}}' => $siteName,
    ];
}

function events_notify_email_apply_placeholders(string $text, array $placeholders): string
{
    return strtr($text, $placeholders);
}

/**
 * @param list<array{id: int, nev: string, kod: string, targy: string, html_tartalom: string}> $templates
 * @param array<string, string> $placeholders
 * @return list<array{id: int, nev: string, kod: string, targy: string, html_tartalom: string}>
 */
function events_notify_email_render_templates(array $templates, array $placeholders): array
{
    $out = [];
    foreach ($templates as $tpl) {
        $out[] = [
            'id' => (int) ($tpl['id'] ?? 0),
            'nev' => (string) ($tpl['nev'] ?? ''),
            'kod' => (string) ($tpl['kod'] ?? ''),
            'targy' => events_notify_email_apply_placeholders((string) ($tpl['targy'] ?? ''), $placeholders),
            'html_tartalom' => events_notify_email_apply_placeholders((string) ($tpl['html_tartalom'] ?? ''), $placeholders),
        ];
    }

    return $out;
}

function events_notify_email_tracking_url(string $token): string
{
    return ng_absolute_url(events_url('email_open.php?t=' . rawurlencode($token)));
}

function events_notify_email_inject_tracking_pixel(string $html, string $token): string
{
    $url = htmlspecialchars(events_notify_email_tracking_url($token), ENT_QUOTES, 'UTF-8');
    $pixel = '<img src="' . $url . '" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0;" />';
    if (stripos($html, '</body>') !== false) {
        return (string) preg_replace('/<\/body>/i', $pixel . '</body>', $html, 1);
    }

    return $html . $pixel;
}

/**
 * @param list<string> $toEmails
 * @param list<int> $organizerIds
 * @return array{ok: true, log_id: int}|array{ok: false, error: string}
 */
function events_notify_email_send(
    PDO $db,
    int $eventId,
    array $event,
    array $organizerIds,
    array $toEmails,
    string $subject,
    string $bodyHtml,
    ?int $templateId,
    ?int $smtpConfigId,
    ?string $bccOverride = null
): array {
    events_notify_email_ensure_schema($db);
    events_notify_email_load_deps();

    $toClean = [];
    foreach ($toEmails as $email) {
        $email = trim(mb_strtolower((string) $email, 'UTF-8'));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $toClean[$email] = true;
        }
    }
    $toList = array_keys($toClean);
    if ($toList === []) {
        return ['ok' => false, 'error' => 'Nincs érvényes címzett e-mail cím.'];
    }

    $subject = trim($subject);
    $bodyHtml = trim($bodyHtml);
    if ($subject === '' || $bodyHtml === '') {
        return ['ok' => false, 'error' => 'A levél tárgya és tartalma kötelező.'];
    }

    $bccRaw = $bccOverride !== null ? $bccOverride : events_notify_email_default_bcc($db);
    $bccList = [];
    foreach (preg_split('/[\s,;]+/', $bccRaw) ?: [] as $part) {
        $part = trim(mb_strtolower($part, 'UTF-8'));
        if ($part !== '' && filter_var($part, FILTER_VALIDATE_EMAIL)) {
            $bccList[$part] = true;
        }
    }
    $bccEmails = array_keys($bccList);

    $smtpId = $smtpConfigId !== null && $smtpConfigId > 0
        ? $smtpConfigId
        : events_notify_email_default_smtp_id($db);
    if ($smtpId <= 0) {
        return ['ok' => false, 'error' => 'Nincs beállított SMTP fiók a küldéshez.'];
    }

    $fromEmail = '';
    try {
        $st = $db->prepare('SELECT `from_email` FROM `finance_email_accounts` WHERE `id` = ? LIMIT 1');
        $st->execute([$smtpId]);
        $fromEmail = trim((string) ($st->fetchColumn() ?: ''));
    } catch (Throwable) {
        $fromEmail = '';
    }

    $token = bin2hex(random_bytes(32));
    $bodyWithPixel = events_notify_email_inject_tracking_pixel($bodyHtml, $token);

    $mailOpts = [
        'html' => true,
        'config_id' => $smtpId,
        'bcc' => $bccEmails,
    ];
    $mailResult = email_kuld($toList, $subject, $bodyWithPixel, $mailOpts);
    if (!$mailResult['ok']) {
        error_log('events_notify_email_send: ' . ($mailResult['hiba'] ?? ''));

        return [
            'ok' => false,
            'error' => 'Az e-mail küldése sikertelen: ' . (string) ($mailResult['hiba'] ?? 'ismeretlen hiba'),
        ];
    }

    $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
    $orgIds = array_values(array_unique(array_filter(
        array_map(static fn ($v): int => (int) $v, $organizerIds),
        static fn (int $id): bool => $id > 0
    )));
    $orgIdsCsv = implode(',', $orgIds);

    try {
        $ins = $db->prepare('
            INSERT INTO `events_email_send_log`
                (`event_id`, `template_id`, `tracking_token`, `to_emails`, `bcc_emails`, `from_email`,
                 `smtp_account_id`, `subject`, `body_html`, `organizer_ids`, `admin_id`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $ins->execute([
            $eventId,
            $templateId !== null && $templateId > 0 ? $templateId : null,
            $token,
            implode(', ', $toList),
            $bccEmails !== [] ? implode(', ', $bccEmails) : null,
            $fromEmail !== '' ? $fromEmail : null,
            $smtpId,
            $subject,
            $bodyWithPixel,
            $orgIdsCsv,
            $adminId > 0 ? $adminId : null,
        ]);
        $logId = (int) $db->lastInsertId();
    } catch (Throwable $ex) {
        error_log('events_notify_email_send log: ' . $ex->getMessage());
        $logId = 0;
    }

    $detailLines = [
        'Címzett: ' . implode(', ', $toList),
        'Tárgy: ' . $subject,
        'Esemény ID: ' . $eventId,
    ];
    if ($bccEmails !== []) {
        $detailLines[] = 'BCC: ' . implode(', ', $bccEmails);
    }
    if ($fromEmail !== '') {
        $detailLines[] = 'Feladó: ' . $fromEmail;
    }
    $details = implode("\n", $detailLines);

    if (function_exists('rendszer_log')) {
        rendszer_log('esemény', $eventId, 'Szervezői értesítő e-mail kiküldve', $details);
        foreach ($orgIds as $orgId) {
            rendszer_log('szervező', $orgId, 'E-mail kiküldve (esemény megjelent)', $details);
        }
    }

    return ['ok' => true, 'log_id' => $logId];
}

function events_notify_email_record_open(PDO $db, string $token): bool
{
    $token = trim($token);
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return false;
    }

    events_notify_email_ensure_schema($db);

    try {
        $st = $db->prepare('
            SELECT `id`, `event_id`, `organizer_ids`, `open_count`, `first_opened_at`, `subject`
            FROM `events_email_send_log`
            WHERE `tracking_token` = ?
            LIMIT 1
        ');
        $st->execute([$token]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }

        $logId = (int) ($row['id'] ?? 0);
        $isFirst = empty($row['first_opened_at']);

        $upd = $db->prepare('
            UPDATE `events_email_send_log`
            SET `open_count` = `open_count` + 1,
                `last_opened_at` = NOW(),
                `first_opened_at` = COALESCE(`first_opened_at`, NOW())
            WHERE `id` = ?
        ');
        $upd->execute([$logId]);

        if ($isFirst && function_exists('rendszer_log')) {
            $eventId = (int) ($row['event_id'] ?? 0);
            $subject = (string) ($row['subject'] ?? '');
            $details = 'Tárgy: ' . $subject . "\nEsemény ID: " . $eventId;
            if ($eventId > 0) {
                rendszer_log('esemény', $eventId, 'Értesítő e-mail megnyitva', $details);
            }
            $orgCsv = trim((string) ($row['organizer_ids'] ?? ''));
            if ($orgCsv !== '') {
                foreach (explode(',', $orgCsv) as $part) {
                    $orgId = (int) trim($part);
                    if ($orgId > 0) {
                        rendszer_log('szervező', $orgId, 'Értesítő e-mail megnyitva', $details);
                    }
                }
            }
        }

        return true;
    } catch (Throwable $ex) {
        error_log('events_notify_email_record_open: ' . $ex->getMessage());

        return false;
    }
}

/**
 * @return list<array<string, mixed>>
 */
function events_notify_email_logs_for_event(PDO $db, int $eventId, int $limit = 20): array
{
    if ($eventId <= 0) {
        return [];
    }
    events_notify_email_ensure_schema($db);
    $limit = max(1, min(100, $limit));
    try {
        $st = $db->prepare("
            SELECT `id`, `to_emails`, `subject`, `sent_at`, `first_opened_at`, `last_opened_at`, `open_count`
            FROM `events_email_send_log`
            WHERE `event_id` = ?
            ORDER BY `sent_at` DESC
            LIMIT {$limit}
        ");
        $st->execute([$eventId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable) {
        return [];
    }
}
