<?php
declare(strict_types=1);

require_once __DIR__ . '/html_security.php';

/**
 * Publikus esemény főoldal szerkeszthető HTML blokkjai (felül / alul) + fejléc tip.
 */

function events_public_home_table_available(PDO $db): bool {
    try {
        $db->query('SELECT 1 FROM `events_public_home` LIMIT 1');

        return true;
    } catch (Throwable) {
        return false;
    }
}

/**
 * Tip mezők biztosítása (idempotens).
 */
function events_public_home_ensure_notice_schema(PDO $db): bool {
    static $done = false;
    if ($done) {
        return true;
    }
    if (!events_public_home_table_available($db)) {
        return false;
    }

    try {
        $cols = [
            'notice_text' => "ADD COLUMN `notice_text` VARCHAR(500) NOT NULL DEFAULT '' AFTER `content_bottom`",
            'notice_text_en' => "ADD COLUMN `notice_text_en` VARCHAR(500) NOT NULL DEFAULT '' AFTER `notice_text`",
            'notice_url' => "ADD COLUMN `notice_url` VARCHAR(500) NOT NULL DEFAULT '' AFTER `notice_text_en`",
            'notice_color_scheme' => "ADD COLUMN `notice_color_scheme` VARCHAR(32) NOT NULL DEFAULT 'neon_green' AFTER `notice_url`",
            'notice_custom_color' => "ADD COLUMN `notice_custom_color` CHAR(7) NOT NULL DEFAULT '#39FF14' AFTER `notice_color_scheme`",
        ];
        $added = false;
        foreach ($cols as $name => $ddl) {
            $st = $db->query('SHOW COLUMNS FROM `events_public_home` LIKE ' . $db->quote($name));
            if ($st && $st->fetch(PDO::FETCH_ASSOC) === false) {
                $db->exec('ALTER TABLE `events_public_home` ' . $ddl);
                $added = true;
            }
        }

        // Csak az első séma-bővítéskor töltsük fel a jelenlegi tippel.
        if ($added) {
            $defaults = events_public_home_notice_defaults();
            $seed = $db->prepare('
                UPDATE `events_public_home`
                SET `notice_text` = ?, `notice_text_en` = ?, `notice_url` = ?,
                    `notice_color_scheme` = ?, `notice_custom_color` = ?
                WHERE `id` = 1
                  AND `notice_text` = \'\'
                  AND `notice_text_en` = \'\'
                  AND `notice_url` = \'\'
            ');
            $seed->execute([
                $defaults['notice_text'],
                $defaults['notice_text_en'],
                $defaults['notice_url'],
                $defaults['notice_color_scheme'],
                $defaults['notice_custom_color'],
            ]);
        }

        $done = true;

        return true;
    } catch (Throwable $e) {
        error_log('events_public_home_ensure_notice_schema: ' . $e->getMessage());

        return false;
    }
}

/**
 * @return array<string, array{label: string, accent: string, bg_from: string, bg_to: string}>
 */
function events_public_home_notice_color_presets(): array {
    return [
        'neon_green' => [
            'label' => 'Neon zöld',
            'accent' => '#39FF14',
            'bg_from' => '#0A0E27',
            'bg_to' => '#141829',
        ],
        'neon_lime' => [
            'label' => 'Lime',
            'accent' => '#B8FF3D',
            'bg_from' => '#0A0E27',
            'bg_to' => '#141829',
        ],
        'neon_cyan' => [
            'label' => 'Cián',
            'accent' => '#00E5FF',
            'bg_from' => '#0A0E27',
            'bg_to' => '#141829',
        ],
        'neon_amber' => [
            'label' => 'Borostyán',
            'accent' => '#FFC107',
            'bg_from' => '#0A0E27',
            'bg_to' => '#141829',
        ],
        'neon_magenta' => [
            'label' => 'Magenta',
            'accent' => '#FF2BD6',
            'bg_from' => '#0A0E27',
            'bg_to' => '#141829',
        ],
        'soft_mint' => [
            'label' => 'Menta',
            'accent' => '#7DFFB3',
            'bg_from' => '#0A0E27',
            'bg_to' => '#141829',
        ],
    ];
}

/**
 * @return array{
 *   notice_text: string,
 *   notice_text_en: string,
 *   notice_url: string,
 *   notice_color_scheme: string,
 *   notice_custom_color: string
 * }
 */
function events_public_home_notice_defaults(): array {
    $defaultUrl = function_exists('site_url') ? site_url('lanueva/') : '/lanueva/';

    return [
        'notice_text' => 'Megújult a Latinfo.hu naptár! Neked hogy tetszik? Írd meg nekünk!',
        'notice_text_en' => 'The Latinfo.hu calendar has been renewed! How do you like it? Tell us!',
        'notice_url' => $defaultUrl,
        'notice_color_scheme' => 'neon_green',
        'notice_custom_color' => '#39FF14',
    ];
}

function events_public_home_normalize_hex_color(string $raw): ?string {
    $c = strtoupper(trim($raw));
    if ($c !== '' && $c[0] !== '#') {
        $c = '#' . $c;
    }
    if (!preg_match('/^#[0-9A-F]{6}$/', $c)) {
        return null;
    }

    return $c;
}

function events_public_home_hex_to_rgba(string $hex, float $alpha): string {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $a = max(0.0, min(1.0, $alpha));

    return sprintf('rgba(%d, %d, %d, %.2f)', $r, $g, $b, $a);
}

function events_public_home_lighten_hex(string $hex, float $amount = 0.28): string {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $t = max(0.0, min(1.0, $amount));
    $r = (int) round($r + (255 - $r) * $t);
    $g = (int) round($g + (255 - $g) * $t);
    $b = (int) round($b + (255 - $b) * $t);

    return sprintf('#%02X%02X%02X', $r, $g, $b);
}

/**
 * Relatív vagy http(s) tip URL; üres engedett.
 */
function events_public_home_sanitize_notice_url(string $raw): ?string {
    $url = trim($raw);
    if ($url === '') {
        return '';
    }
    $lower = strtolower($url);
    if (
        str_starts_with($lower, 'javascript:')
        || str_starts_with($lower, 'vbscript:')
        || str_starts_with($lower, 'data:')
    ) {
        return null;
    }
    if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
        return $url;
    }
    if (str_starts_with($lower, 'http://') || str_starts_with($lower, 'https://')) {
        return events_http_https_url_is_acceptable($url) ? $url : null;
    }
    if (str_starts_with($lower, 'mailto:') || str_starts_with($lower, 'tel:')) {
        return $url;
    }

    return null;
}

/**
 * @return array{accent: string, bg_from: string, bg_to: string}
 */
function events_public_home_notice_resolve_theme(string $scheme, string $customHex): array {
    $presets = events_public_home_notice_color_presets();
    if ($scheme === 'custom') {
        $accent = events_public_home_normalize_hex_color($customHex) ?? '#39FF14';

        return [
            'accent' => $accent,
            'bg_from' => '#0A0E27',
            'bg_to' => '#141829',
        ];
    }
    $p = $presets[$scheme] ?? $presets['neon_green'];

    return [
        'accent' => $p['accent'],
        'bg_from' => $p['bg_from'],
        'bg_to' => $p['bg_to'],
    ];
}

/**
 * @return array<string, string> CSS custom property map (--rn-*)
 */
function events_public_home_notice_theme_css_vars(string $scheme, string $customHex): array {
    $theme = events_public_home_notice_resolve_theme($scheme, $customHex);
    $accent = $theme['accent'];
    $hover = events_public_home_lighten_hex($accent, 0.28);

    return [
        '--rn-accent' => $accent,
        '--rn-accent-hover' => $hover,
        '--rn-border' => events_public_home_hex_to_rgba($accent, 0.35),
        '--rn-border-hover' => events_public_home_hex_to_rgba($hover, 0.55),
        '--rn-shadow' => events_public_home_hex_to_rgba($accent, 0.15),
        '--rn-shadow-hover' => events_public_home_hex_to_rgba($accent, 0.28),
        '--rn-glow' => events_public_home_hex_to_rgba($accent, 0.55),
        '--rn-glow-soft' => events_public_home_hex_to_rgba($accent, 0.25),
        '--rn-bg-from' => $theme['bg_from'],
        '--rn-bg-to' => $theme['bg_to'],
    ];
}

function events_public_home_notice_css_vars_style(string $scheme, string $customHex): string {
    $parts = [];
    foreach (events_public_home_notice_theme_css_vars($scheme, $customHex) as $prop => $val) {
        $parts[] = $prop . ':' . $val;
    }

    return implode(';', $parts);
}

/**
 * @return array{
 *   content_top: string,
 *   content_bottom: string,
 *   notice_text: string,
 *   notice_text_en: string,
 *   notice_url: string,
 *   notice_color_scheme: string,
 *   notice_custom_color: string,
 *   notice_schema_ok: bool
 * }
 */
function events_public_home_load(PDO $db): array {
    $defaults = events_public_home_notice_defaults();
    $empty = [
        'content_top' => '',
        'content_bottom' => '',
        'notice_text' => $defaults['notice_text'],
        'notice_text_en' => $defaults['notice_text_en'],
        'notice_url' => $defaults['notice_url'],
        'notice_color_scheme' => $defaults['notice_color_scheme'],
        'notice_custom_color' => $defaults['notice_custom_color'],
        'notice_schema_ok' => false,
    ];
    if (!events_public_home_table_available($db)) {
        return $empty;
    }

    $noticeOk = events_public_home_ensure_notice_schema($db);
    try {
        if ($noticeOk) {
            $row = $db->query(
                'SELECT `content_top`, `content_bottom`, `notice_text`, `notice_text_en`, `notice_url`,
                        `notice_color_scheme`, `notice_custom_color`
                 FROM `events_public_home` WHERE `id` = 1 LIMIT 1'
            )->fetch(PDO::FETCH_ASSOC);
        } else {
            $row = $db->query('SELECT `content_top`, `content_bottom` FROM `events_public_home` WHERE `id` = 1 LIMIT 1')
                ->fetch(PDO::FETCH_ASSOC);
        }
        if (!is_array($row)) {
            $empty['notice_schema_ok'] = $noticeOk;

            return $empty;
        }

        $scheme = (string) ($row['notice_color_scheme'] ?? $defaults['notice_color_scheme']);
        $presets = events_public_home_notice_color_presets();
        if ($scheme !== 'custom' && !isset($presets[$scheme])) {
            $scheme = $defaults['notice_color_scheme'];
        }
        $custom = events_public_home_normalize_hex_color((string) ($row['notice_custom_color'] ?? ''))
            ?? $defaults['notice_custom_color'];

        return [
            'content_top' => (string) ($row['content_top'] ?? ''),
            'content_bottom' => (string) ($row['content_bottom'] ?? ''),
            'notice_text' => (string) ($row['notice_text'] ?? $defaults['notice_text']),
            'notice_text_en' => (string) ($row['notice_text_en'] ?? $defaults['notice_text_en']),
            'notice_url' => (string) ($row['notice_url'] ?? $defaults['notice_url']),
            'notice_color_scheme' => $scheme,
            'notice_custom_color' => $custom,
            'notice_schema_ok' => $noticeOk,
        ];
    } catch (Throwable $e) {
        error_log('events_public_home_load: ' . $e->getMessage());

        return $empty;
    }
}

/**
 * @param array{
 *   notice_text?: string,
 *   notice_text_en?: string,
 *   notice_url?: string,
 *   notice_color_scheme?: string,
 *   notice_custom_color?: string
 * } $notice
 */
function events_public_home_save(PDO $db, string $contentTop, string $contentBottom, array $notice = []): void {
    if (!events_public_home_table_available($db)) {
        throw new RuntimeException('Hiányzik az events_public_home tábla.');
    }
    $top = events_sanitize_html_fragment($contentTop);
    $bottom = events_sanitize_html_fragment($contentBottom);

    $defaults = events_public_home_notice_defaults();
    $noticeOk = events_public_home_ensure_notice_schema($db);

    if (!$noticeOk) {
        $st = $db->prepare('
            INSERT INTO `events_public_home` (`id`, `content_top`, `content_bottom`)
            VALUES (1, ?, ?)
            ON DUPLICATE KEY UPDATE `content_top` = VALUES(`content_top`), `content_bottom` = VALUES(`content_bottom`)
        ');
        $st->execute([$top, $bottom]);

        return;
    }

    $textHu = mb_substr(trim((string) ($notice['notice_text'] ?? '')), 0, 500);
    $textEn = mb_substr(trim((string) ($notice['notice_text_en'] ?? '')), 0, 500);
    $urlRaw = (string) ($notice['notice_url'] ?? '');
    $url = events_public_home_sanitize_notice_url($urlRaw);
    if ($url === null) {
        throw new InvalidArgumentException('Érvénytelen átkattintás URL.');
    }
    $scheme = trim((string) ($notice['notice_color_scheme'] ?? $defaults['notice_color_scheme']));
    $presets = events_public_home_notice_color_presets();
    if ($scheme !== 'custom' && !isset($presets[$scheme])) {
        $scheme = $defaults['notice_color_scheme'];
    }
    $custom = events_public_home_normalize_hex_color((string) ($notice['notice_custom_color'] ?? ''))
        ?? $defaults['notice_custom_color'];

    $st = $db->prepare('
        INSERT INTO `events_public_home` (
            `id`, `content_top`, `content_bottom`,
            `notice_text`, `notice_text_en`, `notice_url`,
            `notice_color_scheme`, `notice_custom_color`
        ) VALUES (1, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            `content_top` = VALUES(`content_top`),
            `content_bottom` = VALUES(`content_bottom`),
            `notice_text` = VALUES(`notice_text`),
            `notice_text_en` = VALUES(`notice_text_en`),
            `notice_url` = VALUES(`notice_url`),
            `notice_color_scheme` = VALUES(`notice_color_scheme`),
            `notice_custom_color` = VALUES(`notice_custom_color`)
    ');
    $st->execute([$top, $bottom, $textHu, $textEn, $url, $scheme, $custom]);
}

/**
 * Nyilvános megjelenítéshez: szöveg a nyelv szerint, CSS, URL.
 *
 * @param array<string, mixed> $content events_public_home_load() eredmény
 * @return array{visible: bool, text: string, aria: string, url: string, style: string}|null
 */
function events_public_home_notice_for_display(array $content, string $lang, array $langStrings = []): ?array {
    $textHu = trim((string) ($content['notice_text'] ?? ''));
    $textEn = trim((string) ($content['notice_text_en'] ?? ''));
    $text = $lang === 'en'
        ? ($textEn !== '' ? $textEn : $textHu)
        : ($textHu !== '' ? $textHu : $textEn);
    if ($text === '') {
        return null;
    }

    $url = trim((string) ($content['notice_url'] ?? ''));
    $scheme = (string) ($content['notice_color_scheme'] ?? 'neon_green');
    $custom = (string) ($content['notice_custom_color'] ?? '#39FF14');
    $aria = (string) ($langStrings['renewal_notice_aria'] ?? $text);

    return [
        'visible' => true,
        'text' => $text,
        'aria' => $aria,
        'url' => $url,
        'style' => events_public_home_notice_css_vars_style($scheme, $custom),
    ];
}
