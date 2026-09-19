<?php
declare(strict_types=1);

/**
 * Latinfo.hu kezdőoldal – Donably támogatás modul beállításai.
 */

require_once __DIR__ . '/site_home.php';

/**
 * @return array<string, string>
 */
function latinfo_home_donably_defaults(): array
{
    return [
        'title' => 'Támogasd a Latinfo.hu-t',
        'lead' => 'A naptár, a DJ-k és a szcéna hírei önkéntes munkából és szerverdíjból állnak össze. Ha hasznosnak tartod, egy kávé árával te is beszállhatsz.',
        'cta_label' => 'Támogatom',
        'cta_url' => '',
        'note' => 'Biztonságos fizetés a Donably felületén.',
        'show_icon' => '1',
        'title_en' => 'Support Latinfo.hu',
        'lead_en' => 'The calendar, the DJ profiles and the scene news run on volunteer work and server bills. If you find it useful, you can chip in for the price of a coffee.',
        'cta_label_en' => 'Support us',
        'note_en' => 'Secure payment on Donably.',
    ];
}

function latinfo_home_donably_ensure_schema(PDO $db): bool
{
    static $done = false;
    if ($done) {
        return true;
    }

    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `latinfo_home_donably` (
                `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY,
                `title` VARCHAR(120) NOT NULL DEFAULT '',
                `lead` VARCHAR(400) NOT NULL DEFAULT '',
                `cta_label` VARCHAR(60) NOT NULL DEFAULT '',
                `cta_url` VARCHAR(500) NOT NULL DEFAULT '',
                `note` VARCHAR(160) NOT NULL DEFAULT '',
                `show_icon` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
                `title_en` VARCHAR(120) NOT NULL DEFAULT '',
                `lead_en` VARCHAR(400) NOT NULL DEFAULT '',
                `cta_label_en` VARCHAR(60) NOT NULL DEFAULT '',
                `note_en` VARCHAR(160) NOT NULL DEFAULT '',
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        latinfo_home_donably_ensure_show_icon_column($db);
        latinfo_home_donably_seed_if_empty($db);
        $done = true;

        return true;
    } catch (Throwable $e) {
        error_log('latinfo_home_donably_ensure_schema: ' . $e->getMessage());

        return false;
    }
}

function latinfo_home_donably_ensure_show_icon_column(PDO $db): void
{
    try {
        $col = $db->query("SHOW COLUMNS FROM `latinfo_home_donably` LIKE 'show_icon'")->fetch(PDO::FETCH_ASSOC);
        if (is_array($col)) {
            return;
        }
        $db->exec('
            ALTER TABLE `latinfo_home_donably`
            ADD COLUMN `show_icon` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 AFTER `note`
        ');
    } catch (Throwable $e) {
        error_log('latinfo_home_donably_ensure_show_icon_column: ' . $e->getMessage());
    }
}

function latinfo_home_donably_seed_if_empty(PDO $db): void
{
    $count = (int) $db->query('SELECT COUNT(*) FROM `latinfo_home_donably`')->fetchColumn();
    if ($count > 0) {
        return;
    }
    $defaults = latinfo_home_donably_defaults();
    $st = $db->prepare('
        INSERT INTO `latinfo_home_donably`
            (`id`, `title`, `lead`, `cta_label`, `cta_url`, `note`, `show_icon`,
             `title_en`, `lead_en`, `cta_label_en`, `note_en`)
        VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $st->execute([
        $defaults['title'],
        $defaults['lead'],
        $defaults['cta_label'],
        $defaults['cta_url'],
        $defaults['note'],
        $defaults['show_icon'] === '1' ? 1 : 0,
        $defaults['title_en'],
        $defaults['lead_en'],
        $defaults['cta_label_en'],
        $defaults['note_en'],
    ]);
}

/**
 * @return array<string, string>
 */
function latinfo_home_donably_load(PDO $db): array
{
    $settings = latinfo_home_donably_defaults();
    try {
        $row = $db->query('SELECT * FROM `latinfo_home_donably` WHERE `id` = 1 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('latinfo_home_donably_load: ' . $e->getMessage());

        return $settings;
    }
    if (!is_array($row)) {
        return $settings;
    }
    foreach ($settings as $key => $fallback) {
        if (!array_key_exists($key, $row)) {
            continue;
        }
        if ($key === 'show_icon') {
            $settings[$key] = ((string) $row[$key] === '1') ? '1' : '0';
            continue;
        }
        $settings[$key] = trim((string) $row[$key]);
    }

    return $settings;
}

/**
 * @param array<string, mixed> $input
 * @throws InvalidArgumentException érvénytelen szöveg vagy hivatkozás esetén
 */
function latinfo_home_donably_save(PDO $db, array $input): void
{
    $title = latinfo_home_clamp((string) ($input['title'] ?? ''), 120);
    if ($title === '') {
        throw new InvalidArgumentException('A modul címe kötelező.');
    }
    $ctaLabel = latinfo_home_clamp((string) ($input['cta_label'] ?? ''), 60);
    if ($ctaLabel === '') {
        throw new InvalidArgumentException('A gomb feliratának megadása kötelező.');
    }

    $showIcon = ((string) ($input['show_icon'] ?? '0') === '1') ? 1 : 0;

    $st = $db->prepare('
        INSERT INTO `latinfo_home_donably`
            (`id`, `title`, `lead`, `cta_label`, `cta_url`, `note`, `show_icon`,
             `title_en`, `lead_en`, `cta_label_en`, `note_en`)
        VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            `title` = VALUES(`title`),
            `lead` = VALUES(`lead`),
            `cta_label` = VALUES(`cta_label`),
            `cta_url` = VALUES(`cta_url`),
            `note` = VALUES(`note`),
            `show_icon` = VALUES(`show_icon`),
            `title_en` = VALUES(`title_en`),
            `lead_en` = VALUES(`lead_en`),
            `cta_label_en` = VALUES(`cta_label_en`),
            `note_en` = VALUES(`note_en`)
    ');
    $st->execute([
        $title,
        latinfo_home_clamp((string) ($input['lead'] ?? ''), 400),
        $ctaLabel,
        latinfo_home_sanitize_url((string) ($input['cta_url'] ?? '')),
        latinfo_home_clamp((string) ($input['note'] ?? ''), 160),
        $showIcon,
        latinfo_home_clamp((string) ($input['title_en'] ?? ''), 120),
        latinfo_home_clamp((string) ($input['lead_en'] ?? ''), 400),
        latinfo_home_clamp((string) ($input['cta_label_en'] ?? ''), 60),
        latinfo_home_clamp((string) ($input['note_en'] ?? ''), 160),
    ]);
}

/**
 * Nyelvre feloldott, megjelenítésre kész modultartalom (angolban HU fallback).
 *
 * @param array<string, string> $settings
 * @return array{title: string, lead: string, cta_label: string, cta_url: string, note: string, show_icon: bool, is_external: bool, configured: bool}
 */
function latinfo_home_donably_resolve(array $settings, string $lang): array
{
    $settings = array_merge(latinfo_home_donably_defaults(), $settings);
    $pick = static function (string $key) use ($settings, $lang): string {
        $hu = trim((string) ($settings[$key] ?? ''));
        if ($lang !== 'en') {
            return $hu;
        }
        $en = trim((string) ($settings[$key . '_en'] ?? ''));

        return $en !== '' ? $en : $hu;
    };

    $url = trim((string) ($settings['cta_url'] ?? ''));

    return [
        'title' => $pick('title'),
        'lead' => $pick('lead'),
        'cta_label' => $pick('cta_label'),
        'cta_url' => $url,
        'note' => $pick('note'),
        'show_icon' => ((string) ($settings['show_icon'] ?? '1') === '1'),
        'is_external' => preg_match('#^https?://#i', $url) === 1,
        'configured' => $url !== '',
    ];
}

/**
 * @return array{title: string, lead: string, cta_label: string, cta_url: string, note: string, show_icon: bool, is_external: bool, configured: bool}
 */
function latinfo_home_donably_view(PDO $db, string $lang): array
{
    $settings = latinfo_home_donably_ensure_schema($db)
        ? latinfo_home_donably_load($db)
        : latinfo_home_donably_defaults();

    return latinfo_home_donably_resolve($settings, $lang);
}
