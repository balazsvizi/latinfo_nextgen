<?php
declare(strict_types=1);

/**
 * Latinfo.hu kezdőoldal CMS: beállítások, kiemelt hírek, gyűjtők.
 */

if (!function_exists('events_http_https_url_is_acceptable')) {
    require_once dirname(__DIR__, 2) . '/events/lib/html_security.php';
}

function latinfo_home_preview_url(): string
{
    return defined('LATINFO_PUBLIC_HOME_URL') ? (string) LATINFO_PUBLIC_HOME_URL : site_url('/');
}

function latinfo_home_edit_url(string $query = ''): string
{
    $base = nextgen_url('site/szerkeszt.php');

    return $query === '' ? $base : $base . '?' . ltrim($query, '?');
}

function latinfo_home_asset_url(string $path): string
{
    return nextgen_url('site/assets/' . ltrim($path, '/'));
}

function latinfo_home_is_preview_request(): bool
{
    $path = str_replace('\\', '/', (string) strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?'));
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    return str_contains($path, '/nextgen/site') || str_contains($script, '/nextgen/site/');
}

/**
 * Aktuális kezdőoldal-bázis: /nextgen/site/ előnézet vagy publikus /.
 */
function latinfo_home_current_page_base_url(): string
{
    if (latinfo_home_is_preview_request()) {
        return nextgen_url('site/');
    }

    return latinfo_home_preview_url();
}

function latinfo_home_public_canonical_url(string $lang): string
{
    if ($lang === 'en') {
        return latinfo_home_preview_query_url(['lang' => 'en']);
    }

    return rtrim(latinfo_home_preview_url(), '/') . '/';
}

function latinfo_home_lang_switch_url(string $lang): string
{
    // Mindig explicit lang — különben az EN süti megmarad HU kattintáskor.
    return latinfo_home_preview_query_url_on(
        latinfo_home_current_page_base_url(),
        ['lang' => $lang === 'en' ? 'en' : 'hu']
    );
}

/**
 * @param array<string, scalar|null> $extra
 */
function latinfo_home_preview_query_url_on(string $base, array $extra = []): string
{
    $params = [];
    foreach ($extra as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $params[(string) $key] = $value;
    }
    if ($params === []) {
        return $base;
    }
    if (function_exists('events_public_append_query')) {
        return events_public_append_query($base, $params);
    }
    $sep = str_contains($base, '?') ? '&' : '?';

    return $base . $sep . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

/**
 * @param array<string, scalar|null> $extra
 */
function latinfo_home_preview_query_url(array $extra = []): string
{
    return latinfo_home_preview_query_url_on(latinfo_home_preview_url(), $extra);
}

/**
 * @return array<string, string>
 */
function latinfo_home_settings_defaults(): array
{
    return [
        'hero_kicker' => 'A latin életérzés',
        'hero_title' => 'Hol a ritmus, ott a Latinfo',
        'hero_lead' => 'Naptár, DJ-k, iskolák, fesztiválok és a szcéna hírei – egy helyen, a magyarországi latin életérzéshez.',
        'hero_cta_label' => 'Naptár megnyitása',
        'hero_cta_url' => function_exists('events_public_home_path') ? events_public_home_path() : site_url('events/'),
        'newsletter_title' => 'Ne maradj le a következő körre',
        'newsletter_lead' => 'Heti ritmus: kiemelt esték, új gyűjtők, a szcéna hírei. Röviden, táncosan.',
        'newsletter_cta_label' => 'Értesítést kérek',
        'newsletter_cta_url' => site_url('lanueva/'),
    ];
}

function latinfo_home_ensure_schema(PDO $db): bool
{
    static $done = false;
    if ($done) {
        return true;
    }

    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `latinfo_home_settings` (
                `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY,
                `hero_kicker` VARCHAR(120) NOT NULL DEFAULT '',
                `hero_title` VARCHAR(200) NOT NULL DEFAULT '',
                `hero_lead` VARCHAR(500) NOT NULL DEFAULT '',
                `hero_cta_label` VARCHAR(80) NOT NULL DEFAULT '',
                `hero_cta_url` VARCHAR(500) NOT NULL DEFAULT '',
                `newsletter_title` VARCHAR(160) NOT NULL DEFAULT '',
                `newsletter_lead` VARCHAR(400) NOT NULL DEFAULT '',
                `newsletter_cta_label` VARCHAR(80) NOT NULL DEFAULT '',
                `newsletter_cta_url` VARCHAR(500) NOT NULL DEFAULT '',
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS `latinfo_home_news` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `title` VARCHAR(200) NOT NULL,
                `dek` VARCHAR(500) NOT NULL DEFAULT '',
                `kicker` VARCHAR(80) NOT NULL DEFAULT '',
                `image_url` VARCHAR(500) NOT NULL DEFAULT '',
                `url` VARCHAR(500) NOT NULL DEFAULT '',
                `is_hero` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `is_visible` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY `idx_visible_sort` (`is_visible`, `sort_order`, `id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS `latinfo_home_collectors` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `title` VARCHAR(120) NOT NULL,
                `subtitle` VARCHAR(200) NOT NULL DEFAULT '',
                `url` VARCHAR(500) NOT NULL DEFAULT '',
                `image_url` VARCHAR(500) NOT NULL DEFAULT '',
                `accent_color` CHAR(7) NOT NULL DEFAULT '#9CBF90',
                `is_visible` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY `idx_visible_sort` (`is_visible`, `sort_order`, `id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        latinfo_home_seed_if_empty($db);
        $done = true;

        return true;
    } catch (Throwable $e) {
        error_log('latinfo_home_ensure_schema: ' . $e->getMessage());

        return false;
    }
}

function latinfo_home_seed_if_empty(PDO $db): void
{
    $settingsCount = (int) $db->query('SELECT COUNT(*) FROM `latinfo_home_settings`')->fetchColumn();
    if ($settingsCount === 0) {
        $defaults = latinfo_home_settings_defaults();
        $st = $db->prepare('
            INSERT INTO `latinfo_home_settings`
                (`id`, `hero_kicker`, `hero_title`, `hero_lead`, `hero_cta_label`, `hero_cta_url`,
                 `newsletter_title`, `newsletter_lead`, `newsletter_cta_label`, `newsletter_cta_url`)
            VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $st->execute([
            $defaults['hero_kicker'],
            $defaults['hero_title'],
            $defaults['hero_lead'],
            $defaults['hero_cta_label'],
            $defaults['hero_cta_url'],
            $defaults['newsletter_title'],
            $defaults['newsletter_lead'],
            $defaults['newsletter_cta_label'],
            $defaults['newsletter_cta_url'],
        ]);
    }

    $newsCount = (int) $db->query('SELECT COUNT(*) FROM `latinfo_home_news`')->fetchColumn();
    if ($newsCount === 0) {
        $st = $db->prepare('
            INSERT INTO `latinfo_home_news`
                (`title`, `dek`, `kicker`, `image_url`, `url`, `is_hero`, `is_visible`, `sort_order`)
            VALUES (?, ?, ?, ?, ?, ?, 1, ?)
        ');
        foreach (latinfo_home_seed_news() as $i => $row) {
            $st->execute([
                $row['title'],
                $row['dek'],
                $row['kicker'],
                $row['image_url'],
                $row['url'],
                $row['is_hero'],
                $i + 1,
            ]);
        }
    }

    $collectorCount = (int) $db->query('SELECT COUNT(*) FROM `latinfo_home_collectors`')->fetchColumn();
    if ($collectorCount === 0) {
        $st = $db->prepare('
            INSERT INTO `latinfo_home_collectors`
                (`title`, `subtitle`, `url`, `image_url`, `accent_color`, `is_visible`, `sort_order`)
            VALUES (?, ?, ?, ?, ?, 1, ?)
        ');
        foreach (latinfo_home_seed_collectors() as $i => $row) {
            $st->execute([
                $row['title'],
                $row['subtitle'],
                $row['url'],
                $row['image_url'],
                $row['accent_color'],
                $i + 1,
            ]);
        }
    }
}

/**
 * @return list<array{title:string,dek:string,kicker:string,image_url:string,url:string,is_hero:int}>
 */
function latinfo_home_seed_news(): array
{
    $calendar = function_exists('events_public_home_path') ? events_public_home_path() : site_url('events/');
    $djs = function_exists('events_public_djs_hub_canonical_url')
        ? events_public_djs_hub_canonical_url()
        : site_url('DJ/');
    $partners = function_exists('events_public_partners_canonical_url')
        ? events_public_partners_canonical_url()
        : site_url('partnereink/');

    return [
        [
            'title' => 'A szezon nem ér véget szeptemberben',
            'dek' => 'Beltéri maratonok, új DJ-párosok és a naptár, ami összetartja a magyar latin szcénát – innen indul a hét.',
            'kicker' => 'Kiemelt',
            'image_url' => '',
            'url' => $calendar,
            'is_hero' => 1,
        ],
        [
            'title' => 'Kik pörgetik az őszi estéket?',
            'dek' => 'A DJ gyűjtőben már nemcsak nevek vannak: stílus, hangulat, és hogy hol találkozol velük a héten.',
            'kicker' => 'DJ-k',
            'image_url' => '',
            'url' => $djs,
            'is_hero' => 0,
        ],
        [
            'title' => 'Első lépés a terembe: hol érdemes kezdeni',
            'dek' => 'Salsa, bachata vagy kizomba – a lényeg, hogy a megfelelő teremben, a megfelelő ritmuson kapj el.',
            'kicker' => 'Iskola',
            'image_url' => '',
            'url' => $calendar,
            'is_hero' => 0,
        ],
        [
            'title' => 'Aki mögöttünk áll: partnereink',
            'dek' => 'Fesztiválok, iskolák, márkák – a szcéna nem magától forog. Itt gyűjtjük, kik viszik a ritmust tovább.',
            'kicker' => 'Közösség',
            'image_url' => '',
            'url' => $partners,
            'is_hero' => 0,
        ],
        [
            'title' => 'Egy naptár, három világ: salsa, bachata, kizomba',
            'dek' => 'Nem egymás ellen. Egy este, több stílus, tiszta szűrés – hogy megtaláld, ami neked szól.',
            'kicker' => 'Stílus',
            'image_url' => '',
            'url' => $calendar,
            'is_hero' => 0,
        ],
    ];
}

/**
 * @return list<array{title:string,subtitle:string,url:string,image_url:string,accent_color:string}>
 */
function latinfo_home_seed_collectors(): array
{
    $calendar = function_exists('events_public_home_path') ? events_public_home_path() : site_url('events/');
    $list = function_exists('events_public_home_url')
        ? events_public_home_url('hu', ['view' => 'list'])
        : $calendar;
    $map = function_exists('events_public_home_url')
        ? events_public_home_url('hu', ['view' => 'map'])
        : $calendar;
    $djs = function_exists('events_public_djs_hub_canonical_url')
        ? events_public_djs_hub_canonical_url()
        : site_url('DJ/');
    $organizers = function_exists('events_url')
        ? events_url('szervezok.php')
        : nextgen_url('events/szervezok.php');
    $partners = function_exists('events_public_partners_canonical_url')
        ? events_public_partners_canonical_url()
        : site_url('partnereink/');

    return [
        [
            'title' => 'Naptár',
            'subtitle' => 'Hol táncolsz ma este',
            'url' => $calendar,
            'image_url' => '',
            'accent_color' => '#9CBF90',
        ],
        [
            'title' => 'DJ-k',
            'subtitle' => 'A pult mögött',
            'url' => $djs,
            'image_url' => '',
            'accent_color' => '#D4A054',
        ],
        [
            'title' => 'Szervezők',
            'subtitle' => 'Aki összehozza a partit',
            'url' => $organizers,
            'image_url' => '',
            'accent_color' => '#C45C3E',
        ],
        [
            'title' => 'Partnereink',
            'subtitle' => 'Aki mögöttünk áll',
            'url' => $partners,
            'image_url' => '',
            'accent_color' => '#7EB8C9',
        ],
        [
            'title' => 'Tánciskolák',
            'subtitle' => 'Hol tanulj, hol ess bele',
            'url' => $list,
            'image_url' => '',
            'accent_color' => '#C98BB8',
        ],
        [
            'title' => 'Fesztiválok',
            'subtitle' => 'Hosszú hétvégék, nagy színpad',
            'url' => $calendar,
            'image_url' => '',
            'accent_color' => '#E07A5F',
        ],
        [
            'title' => 'Stílusok',
            'subtitle' => 'Salsa, bachata, kizomba',
            'url' => $list,
            'image_url' => '',
            'accent_color' => '#8B9DC3',
        ],
        [
            'title' => 'Helyszínek',
            'subtitle' => 'Terem, kert, rooftop',
            'url' => $map,
            'image_url' => '',
            'accent_color' => '#A3B18A',
        ],
    ];
}

function latinfo_home_clamp(string $value, int $max): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (function_exists('mb_substr') && function_exists('mb_strlen')) {
        if (mb_strlen($value, 'UTF-8') <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max, 'UTF-8');
    }

    return strlen($value) <= $max ? $value : substr($value, 0, $max);
}

function latinfo_home_sanitize_url(string $raw): string
{
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
        throw new InvalidArgumentException('Érvénytelen hivatkozás.');
    }
    if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
        return latinfo_home_clamp($url, 500);
    }
    if (str_starts_with($lower, 'http://') || str_starts_with($lower, 'https://')) {
        if (!events_http_https_url_is_acceptable($url)) {
            throw new InvalidArgumentException('Érvénytelen http(s) hivatkozás.');
        }

        return latinfo_home_clamp($url, 500);
    }
    if (str_starts_with($lower, 'mailto:') || str_starts_with($lower, 'tel:')) {
        return latinfo_home_clamp($url, 500);
    }

    throw new InvalidArgumentException('A hivatkozás csak /útvonal, #horgony vagy http(s) lehet.');
}

function latinfo_home_media_src(string $raw): string
{
    $url = trim($raw);
    if ($url === '') {
        return '';
    }
    try {
        $safe = latinfo_home_sanitize_url($url);
    } catch (InvalidArgumentException) {
        return '';
    }
    if ($safe === '' || str_starts_with($safe, '#')) {
        return '';
    }
    if (function_exists('events_absolute_url') && (str_starts_with($safe, '/') || !preg_match('#^https?://#i', $safe))) {
        return events_absolute_url($safe);
    }

    return $safe;
}

/**
 * @return array<string, string>
 */
function latinfo_home_load_settings(PDO $db): array
{
    $defaults = latinfo_home_settings_defaults();
    try {
        $row = $db->query('SELECT * FROM `latinfo_home_settings` WHERE `id` = 1 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return $defaults;
    }
    if (!is_array($row)) {
        return $defaults;
    }
    foreach ($defaults as $key => $fallback) {
        if (array_key_exists($key, $row)) {
            $defaults[$key] = trim((string) $row[$key]);
        }
    }

    return $defaults;
}

/**
 * @param array<string, mixed> $input
 */
function latinfo_home_save_settings(PDO $db, array $input): void
{
    $st = $db->prepare('
        INSERT INTO `latinfo_home_settings`
            (`id`, `hero_kicker`, `hero_title`, `hero_lead`, `hero_cta_label`, `hero_cta_url`,
             `newsletter_title`, `newsletter_lead`, `newsletter_cta_label`, `newsletter_cta_url`)
        VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            `hero_kicker` = VALUES(`hero_kicker`),
            `hero_title` = VALUES(`hero_title`),
            `hero_lead` = VALUES(`hero_lead`),
            `hero_cta_label` = VALUES(`hero_cta_label`),
            `hero_cta_url` = VALUES(`hero_cta_url`),
            `newsletter_title` = VALUES(`newsletter_title`),
            `newsletter_lead` = VALUES(`newsletter_lead`),
            `newsletter_cta_label` = VALUES(`newsletter_cta_label`),
            `newsletter_cta_url` = VALUES(`newsletter_cta_url`)
    ');
    $st->execute([
        latinfo_home_clamp((string) ($input['hero_kicker'] ?? ''), 120),
        latinfo_home_clamp((string) ($input['hero_title'] ?? ''), 200),
        latinfo_home_clamp((string) ($input['hero_lead'] ?? ''), 500),
        latinfo_home_clamp((string) ($input['hero_cta_label'] ?? ''), 80),
        latinfo_home_sanitize_url((string) ($input['hero_cta_url'] ?? '')),
        latinfo_home_clamp((string) ($input['newsletter_title'] ?? ''), 160),
        latinfo_home_clamp((string) ($input['newsletter_lead'] ?? ''), 400),
        latinfo_home_clamp((string) ($input['newsletter_cta_label'] ?? ''), 80),
        latinfo_home_sanitize_url((string) ($input['newsletter_cta_url'] ?? '')),
    ]);
}

/**
 * @return list<array<string, mixed>>
 */
function latinfo_home_news_all(PDO $db, bool $visibleOnly = false): array
{
    $sql = 'SELECT * FROM `latinfo_home_news`';
    if ($visibleOnly) {
        $sql .= ' WHERE `is_visible` = 1';
    }
    $sql .= ' ORDER BY `sort_order` ASC, `id` ASC';

    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @return array<string, mixed>|null
 */
function latinfo_home_news_get(PDO $db, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $st = $db->prepare('SELECT * FROM `latinfo_home_news` WHERE `id` = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

/**
 * @param array<string, mixed> $input
 */
function latinfo_home_news_save(PDO $db, int $id, array $input): int
{
    $title = latinfo_home_clamp((string) ($input['title'] ?? ''), 200);
    if ($title === '') {
        throw new InvalidArgumentException('A hír címe kötelező.');
    }
    $dek = latinfo_home_clamp((string) ($input['dek'] ?? ''), 500);
    $kicker = latinfo_home_clamp((string) ($input['kicker'] ?? ''), 80);
    $imageUrl = latinfo_home_sanitize_url((string) ($input['image_url'] ?? ''));
    $url = latinfo_home_sanitize_url((string) ($input['url'] ?? ''));
    $isHero = !empty($input['is_hero']) ? 1 : 0;
    $isVisible = !empty($input['is_visible']) ? 1 : 0;
    $sort = filter_var($input['sort_order'] ?? 0, FILTER_VALIDATE_INT);
    $sortOrder = ($sort === false) ? 0 : (int) $sort;

    if ($id > 0) {
        $st = $db->prepare('
            UPDATE `latinfo_home_news`
            SET `title` = ?, `dek` = ?, `kicker` = ?, `image_url` = ?, `url` = ?,
                `is_hero` = ?, `is_visible` = ?, `sort_order` = ?
            WHERE `id` = ?
        ');
        $st->execute([$title, $dek, $kicker, $imageUrl, $url, $isHero, $isVisible, $sortOrder, $id]);

        return $id;
    }

    $st = $db->prepare('
        INSERT INTO `latinfo_home_news`
            (`title`, `dek`, `kicker`, `image_url`, `url`, `is_hero`, `is_visible`, `sort_order`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $st->execute([$title, $dek, $kicker, $imageUrl, $url, $isHero, $isVisible, $sortOrder]);

    return (int) $db->lastInsertId();
}

function latinfo_home_news_delete(PDO $db, int $id): void
{
    if ($id <= 0) {
        return;
    }
    $st = $db->prepare('DELETE FROM `latinfo_home_news` WHERE `id` = ?');
    $st->execute([$id]);
}

/**
 * @return list<array<string, mixed>>
 */
function latinfo_home_collectors_all(PDO $db, bool $visibleOnly = false): array
{
    $sql = 'SELECT * FROM `latinfo_home_collectors`';
    if ($visibleOnly) {
        $sql .= ' WHERE `is_visible` = 1';
    }
    $sql .= ' ORDER BY `sort_order` ASC, `id` ASC';

    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @return array<string, mixed>|null
 */
function latinfo_home_collectors_get(PDO $db, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $st = $db->prepare('SELECT * FROM `latinfo_home_collectors` WHERE `id` = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

/**
 * @param array<string, mixed> $input
 */
function latinfo_home_collectors_save(PDO $db, int $id, array $input): int
{
    $title = latinfo_home_clamp((string) ($input['title'] ?? ''), 120);
    if ($title === '') {
        throw new InvalidArgumentException('A gyűjtő címe kötelező.');
    }
    $subtitle = latinfo_home_clamp((string) ($input['subtitle'] ?? ''), 200);
    $url = latinfo_home_sanitize_url((string) ($input['url'] ?? ''));
    $imageUrl = latinfo_home_sanitize_url((string) ($input['image_url'] ?? ''));
                $accent = normalize_hex_color((string) ($input['accent_color'] ?? ''), '#6D8F63');
    $isVisible = !empty($input['is_visible']) ? 1 : 0;
    $sort = filter_var($input['sort_order'] ?? 0, FILTER_VALIDATE_INT);
    $sortOrder = ($sort === false) ? 0 : (int) $sort;

    if ($id > 0) {
        $st = $db->prepare('
            UPDATE `latinfo_home_collectors`
            SET `title` = ?, `subtitle` = ?, `url` = ?, `image_url` = ?,
                `accent_color` = ?, `is_visible` = ?, `sort_order` = ?
            WHERE `id` = ?
        ');
        $st->execute([$title, $subtitle, $url, $imageUrl, $accent, $isVisible, $sortOrder, $id]);

        return $id;
    }

    $st = $db->prepare('
        INSERT INTO `latinfo_home_collectors`
            (`title`, `subtitle`, `url`, `image_url`, `accent_color`, `is_visible`, `sort_order`)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $st->execute([$title, $subtitle, $url, $imageUrl, $accent, $isVisible, $sortOrder]);

    return (int) $db->lastInsertId();
}

function latinfo_home_collectors_delete(PDO $db, int $id): void
{
    if ($id <= 0) {
        return;
    }
    $st = $db->prepare('DELETE FROM `latinfo_home_collectors` WHERE `id` = ?');
    $st->execute([$id]);
}

/**
 * @param list<array<string, mixed>> $news
 * @return array{hero: ?array<string, mixed>, rest: list<array<string, mixed>>}
 */
function latinfo_home_split_news(array $news): array
{
    $hero = null;
    $rest = [];
    foreach ($news as $row) {
        if ($hero === null && !empty($row['is_hero'])) {
            $hero = $row;
            continue;
        }
        $rest[] = $row;
    }
    if ($hero === null && $news !== []) {
        $hero = $news[0];
        $rest = array_slice($news, 1);
    }

    return ['hero' => $hero, 'rest' => $rest];
}

function latinfo_home_format_event_when(array $ev): string
{
    $startRaw = trim((string) ($ev['event_start'] ?? ''));
    if ($startRaw === '') {
        return '';
    }
    try {
        $dt = new DateTimeImmutable($startRaw);
    } catch (Throwable) {
        return '';
    }
    $months = [1 => 'jan.', 2 => 'febr.', 3 => 'márc.', 4 => 'ápr.', 5 => 'máj.', 6 => 'jún.', 7 => 'júl.', 8 => 'aug.', 9 => 'szept.', 10 => 'okt.', 11 => 'nov.', 12 => 'dec.'];
    $days = ['vasárnap', 'hétfő', 'kedd', 'szerda', 'csütörtök', 'péntek', 'szombat'];
    $label = $dt->format('j') . ' ' . ($months[(int) $dt->format('n')] ?? '') . ' · ' . ($days[(int) $dt->format('w')] ?? '');
    if (empty($ev['event_allday'])) {
        $label .= ' · ' . $dt->format('H:i');
    }

    return $label;
}

/**
 * Helyszín a kezdőoldali eseménysorhoz.
 *
 * @return array{venue: string, city: string, outside_budapest: bool}
 */
function latinfo_home_event_place_parts(array $ev): array
{
    $venue = trim((string) ($ev['venue_name'] ?? ''));
    $city = trim((string) ($ev['venue_city'] ?? ''));
    $outside = $city !== '' && !latinfo_home_city_is_budapest($city);

    return [
        'venue' => $venue,
        'city' => $outside ? $city : '',
        'outside_budapest' => $outside,
    ];
}

function latinfo_home_event_place(array $ev): string
{
    $parts = latinfo_home_event_place_parts($ev);
    if ($parts['outside_budapest'] && $parts['city'] !== '') {
        return $parts['venue'] !== '' ? $parts['venue'] . ', ' . $parts['city'] : $parts['city'];
    }

    return $parts['venue'];
}

function latinfo_home_city_is_budapest(string $city): bool
{
    $normalized = mb_strtolower(trim($city), 'UTF-8');
    $normalized = strtr($normalized, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o', 'ő' => 'o',
        'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
    ]);
    $normalized = trim($normalized, " \t\n\r\0\x0B.,");

    return $normalized === 'budapest' || $normalized === 'bp' || str_starts_with($normalized, 'budapest ');
}

/**
 * @return list<array<string, mixed>>
 */
function latinfo_home_upcoming_events(PDO $db, int $limit = 6): array
{
    $limit = max(1, min(12, $limit));
    try {
        $status = function_exists('events_public_post_status') ? events_public_post_status() : 'publish';
        $st = $db->prepare('
            SELECT e.`id`, e.`event_slug`, e.`event_name`, e.`event_featured_image_url`,
                   e.`event_start`, e.`event_end`, e.`event_allday`,
                   v.`name` AS `venue_name`, v.`city` AS `venue_city`
            FROM `events_calendar_events` e
            LEFT JOIN `events_venues` v ON v.`id` = e.`venue_id`
            WHERE e.`event_status` = ?
              AND COALESCE(e.`event_end`, e.`event_start`) >= NOW()
              AND e.`event_start` IS NOT NULL
            ORDER BY e.`event_start` ASC
            LIMIT ' . $limit . '
        ');
        $st->execute([$status]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('latinfo_home_upcoming_events: ' . $e->getMessage());

        return [];
    }
}

function latinfo_home_event_url(array $ev): string
{
    $slug = trim((string) ($ev['event_slug'] ?? ''));
    if ($slug === '') {
        return '#';
    }
    if (function_exists('events_megjelenit_url')) {
        return events_megjelenit_url($slug);
    }

    return site_url('event/' . rawurlencode($slug) . '/');
}

/**
 * @param array<int, list<array{color?: string}>> $categoriesByEventId
 */
function latinfo_home_event_accent(array $ev, array $categoriesByEventId): string
{
    $eid = (int) ($ev['id'] ?? 0);
    $color = trim((string) (($categoriesByEventId[$eid][0]['color'] ?? '')));
    if (function_exists('normalize_hex_color')) {
        return normalize_hex_color($color !== '' ? $color : null, '#6D8F63');
    }
    if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color) === 1) {
        return $color;
    }

    return '#6D8F63';
}

function latinfo_home_tone_from_id(int $id): string
{
    $tones = ['ember', 'gold', 'green', 'rose', 'teal', 'violet'];

    return $tones[$id % count($tones)] ?? 'green';
}

/**
 * @return array<string, string>
 */
function latinfo_home_strings(string $lang): array
{
    $hu = [
        'page_title' => 'kezdőoldal (előnézet)',
        'share_title' => 'Ahol a táncos közösség informálódik',
        'share_desc' => 'Naptár, DJ-k, iskolák, fesztiválok és a szcéna hírei egy helyen.',
        'share_image_alt' => 'Latinfo.hu – ahol a táncos közösség informálódik',
        'quick_news' => 'Bejelentések',
        'quick_news_aria' => 'Kiemelt bejelentések',
        'today' => 'Ma',
        'tomorrow' => 'Holnap',
        'calendar' => 'Teljes naptár',
        'calendar_cta' => 'Naptár',
        'calendar_cta_aria' => 'Naptár megnyitása',
        'empty_today' => 'Ma nincs közzétett esemény.',
        'empty_tomorrow' => 'Holnapra még nincs esemény a naptárban.',
        'empty_news' => 'Most nincs bejelentés.',
        'more' => 'Továbbiak a naptárban',
        'collectors' => 'Gyűjtők',
        'collectors_empty' => 'Még nincs gyűjtő. Vedd fel a naptárt, DJ-ket, iskolákat – amit a szcéna keres.',
        'edit_news' => 'Szerkesztés',
        'edit_collectors' => 'Szerkesztés',
        'djs_all' => 'Összes DJ',
    ];
    $en = [
        'page_title' => 'home (preview)',
        'share_title' => 'Where the dance community gets informed',
        'share_desc' => 'Calendar, DJs, schools, festivals and scene news – all in one place.',
        'share_image_alt' => 'Latinfo.hu – where the dance community gets informed',
        'quick_news' => 'Announcements',
        'quick_news_aria' => 'Featured announcements',
        'today' => 'Today',
        'tomorrow' => 'Tomorrow',
        'calendar' => 'Full calendar',
        'calendar_cta' => 'Calendar',
        'calendar_cta_aria' => 'Open the calendar',
        'empty_today' => 'No published events today.',
        'empty_tomorrow' => 'Nothing on the calendar for tomorrow yet.',
        'empty_news' => 'No announcements right now.',
        'more' => 'More in the calendar',
        'collectors' => 'Collections',
        'collectors_empty' => 'No collections yet.',
        'edit_news' => 'Edit',
        'edit_collectors' => 'Edit',
        'djs_all' => 'All DJs',
    ];

    return $lang === 'en' ? $en : $hu;
}

function latinfo_home_document_title(string $lang): string
{
    $strings = latinfo_home_strings($lang);
    $suffix = latinfo_home_is_preview_request()
        ? (string) $strings['page_title']
        : (string) $strings['share_title'];

    return SITE_NAME . ' – ' . $suffix;
}

/**
 * Facebook / Open Graph megosztási adatok a kezdőoldalhoz.
 *
 * @return array{
 *   title: string,
 *   description: string,
 *   image_alt: string,
 *   canonical: string,
 *   url_hu: string,
 *   url_en: string,
 *   locale: string,
 *   locale_alt: string,
 *   image_url: string,
 *   image_w: int,
 *   image_h: int,
 *   image_type: string
 * }
 */
function latinfo_home_share_data(string $lang): array
{
    $strings = latinfo_home_strings($lang);
    $urlHu = latinfo_home_public_canonical_url('hu');
    $urlEn = latinfo_home_public_canonical_url('en');
    $canonical = $lang === 'en' ? $urlEn : $urlHu;
    $imageRel = 'images/og/latinfo-home-share.jpg';
    $imageFs = dirname(__DIR__) . '/assets/' . $imageRel;
    $imageUrl = '';
    $imageW = 0;
    $imageH = 0;
    $imageType = '';
    if (is_file($imageFs) && is_readable($imageFs)) {
        $imageUrl = events_absolute_url(latinfo_home_asset_url($imageRel));
        $ver = (string) filemtime($imageFs);
        if ($ver !== '') {
            $imageUrl .= (str_contains($imageUrl, '?') ? '&' : '?') . 'v=' . rawurlencode($ver);
        }
        $dims = @getimagesize($imageFs);
        if (is_array($dims)) {
            $imageW = (int) ($dims[0] ?? 0);
            $imageH = (int) ($dims[1] ?? 0);
            $imageType = (string) ($dims['mime'] ?? '');
        }
    }

    return [
        'title' => SITE_NAME . ' – ' . (string) $strings['share_title'],
        'description' => (string) $strings['share_desc'],
        'image_alt' => (string) $strings['share_image_alt'],
        'canonical' => $canonical,
        'url_hu' => $urlHu,
        'url_en' => $urlEn,
        'locale' => $lang === 'en' ? 'en_US' : 'hu_HU',
        'locale_alt' => $lang === 'en' ? 'hu_HU' : 'en_US',
        'image_url' => $imageUrl,
        'image_w' => $imageW,
        'image_h' => $imageH,
        'image_type' => $imageType,
    ];
}

function latinfo_home_share_head_markup(string $lang): string
{
    $share = latinfo_home_share_data($lang);
    $lines = [
        '<meta name="description" content="' . h($share['description']) . '">',
        '<link rel="canonical" href="' . h($share['canonical']) . '">',
        '<link rel="alternate" hreflang="hu" href="' . h($share['url_hu']) . '">',
        '<link rel="alternate" hreflang="en" href="' . h($share['url_en']) . '">',
        '<link rel="alternate" hreflang="x-default" href="' . h($share['url_hu']) . '">',
        '<meta property="og:type" content="website">',
        '<meta property="og:site_name" content="' . h(SITE_NAME) . '">',
        '<meta property="og:title" content="' . h($share['title']) . '">',
        '<meta property="og:description" content="' . h($share['description']) . '">',
        '<meta property="og:url" content="' . h($share['canonical']) . '">',
        '<meta property="og:locale" content="' . h($share['locale']) . '">',
        '<meta property="og:locale:alternate" content="' . h($share['locale_alt']) . '">',
    ];
    $fbAppId = preg_replace('/\D+/', '', (string) (defined('FACEBOOK_APP_ID') ? FACEBOOK_APP_ID : '')) ?? '';
    if ($fbAppId !== '') {
        $lines[] = '<meta property="fb:app_id" content="' . h($fbAppId) . '">';
    }
    if ($share['image_url'] !== '') {
        $lines[] = '<meta property="og:image" content="' . h($share['image_url']) . '">';
        if (str_starts_with($share['image_url'], 'https://')) {
            $lines[] = '<meta property="og:image:secure_url" content="' . h($share['image_url']) . '">';
        }
        if ($share['image_type'] !== '') {
            $lines[] = '<meta property="og:image:type" content="' . h($share['image_type']) . '">';
        }
        if ($share['image_w'] > 0 && $share['image_h'] > 0) {
            $lines[] = '<meta property="og:image:width" content="' . (int) $share['image_w'] . '">';
            $lines[] = '<meta property="og:image:height" content="' . (int) $share['image_h'] . '">';
        }
        $lines[] = '<meta property="og:image:alt" content="' . h($share['image_alt']) . '">';
    }
    $lines[] = '<meta name="twitter:card" content="' . ($share['image_url'] !== '' ? 'summary_large_image' : 'summary') . '">';
    $lines[] = '<meta name="twitter:title" content="' . h($share['title']) . '">';
    $lines[] = '<meta name="twitter:description" content="' . h($share['description']) . '">';
    if ($share['image_url'] !== '') {
        $lines[] = '<meta name="twitter:image" content="' . h($share['image_url']) . '">';
        $lines[] = '<meta name="twitter:image:alt" content="' . h($share['image_alt']) . '">';
    }

    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => SITE_NAME,
        'url' => $share['canonical'],
        'description' => $share['description'],
        'inLanguage' => $lang === 'en' ? 'en' : 'hu',
    ];
    if ($share['image_url'] !== '') {
        $jsonLd['image'] = [$share['image_url']];
    }
    $lines[] = '<script type="application/ld+json">'
        . json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
        . '</script>';

    return implode("\n    ", $lines) . "\n";
}

/**
 * @param list<array<string, mixed>> $news
 * @return list<array<string, mixed>>
 */
function latinfo_home_quick_news(array $news, int $limit = 3): array
{
    $limit = max(1, min(6, $limit));
    $hero = [];
    $rest = [];
    foreach ($news as $row) {
        if ($hero === [] && !empty($row['is_hero'])) {
            $hero[] = $row;
        } else {
            $rest[] = $row;
        }
    }

    return array_slice(array_merge($hero, $rest), 0, $limit);
}

function latinfo_home_clip(string $text, int $max = 110): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    if ($text === '' || mb_strlen($text, 'UTF-8') <= $max) {
        return $text;
    }

    return rtrim(mb_substr($text, 0, max(1, $max - 1), 'UTF-8')) . '…';
}

function latinfo_home_calendar_anchor(): DateTimeImmutable
{
    if (!function_exists('events_admin_calendar_effective_today')) {
        require_once dirname(__DIR__, 2) . '/events/lib/admin_event_calendar.php';
    }

    return events_admin_calendar_effective_today();
}

/**
 * Éjszakai ablak: 04:00-tól másnap 04:00-ig, mint a naptár „ma” napja.
 *
 * @return array{
 *     today: DateTimeImmutable,
 *     tomorrow: DateTimeImmutable,
 *     today_from: DateTimeImmutable,
 *     tomorrow_from: DateTimeImmutable,
 *     after_from: DateTimeImmutable
 * }
 */
function latinfo_home_day_windows(): array
{
    $today = latinfo_home_calendar_anchor();
    $todayFrom = $today->setTime(4, 0, 0);

    return [
        'today' => $today,
        'tomorrow' => $today->modify('+1 day'),
        'today_from' => $todayFrom,
        'tomorrow_from' => $todayFrom->modify('+1 day'),
        'after_from' => $todayFrom->modify('+2 days'),
    ];
}

function latinfo_home_parse_event_dt(string $raw): ?DateTimeImmutable
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    try {
        return new DateTimeImmutable($raw, new DateTimeZone('Europe/Budapest'));
    } catch (Throwable) {
        return null;
    }
}

function latinfo_home_event_overlaps_window(array $ev, DateTimeImmutable $from, DateTimeImmutable $until): bool
{
    $start = latinfo_home_parse_event_dt((string) ($ev['event_start'] ?? ''));
    if ($start === null) {
        return false;
    }
    $end = latinfo_home_parse_event_dt((string) ($ev['event_end'] ?? ''));
    if ($end === null || $end < $start) {
        $end = $start;
    }

    return $start < $until && $end >= $from;
}

/**
 * @return list<array<string, mixed>>
 */
function latinfo_home_events_in_range(PDO $db, DateTimeImmutable $from, DateTimeImmutable $until, int $limit = 40): array
{
    $limit = max(1, min(200, $limit));
    try {
        $status = function_exists('events_public_post_status') ? events_public_post_status() : 'publish';
        $st = $db->prepare('
            SELECT e.`id`, e.`event_slug`, e.`event_name`, e.`event_featured_image_url`,
                   e.`event_start`, e.`event_end`, e.`event_allday`,
                   e.`event_change_active`, e.`event_change_type`, e.`event_change_note`,
                   v.`name` AS `venue_name`, v.`city` AS `venue_city`
            FROM `events_calendar_events` e
            LEFT JOIN `events_venues` v ON v.`id` = e.`venue_id`
            WHERE e.`event_status` = ?
              AND e.`event_start` IS NOT NULL
              AND e.`event_start` < ?
              AND COALESCE(e.`event_end`, e.`event_start`) >= ?
            ORDER BY e.`event_start` ASC
            LIMIT ' . $limit . '
        ');
        $st->execute([
            $status,
            $until->format('Y-m-d H:i:s'),
            $from->format('Y-m-d H:i:s'),
        ]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('latinfo_home_events_in_range: ' . $e->getMessage());

        return [];
    }
}

/**
 * Ma / Holnap események. $perDay <= 0 esetén minden esemény (nincs „Továbbiak” link).
 *
 * @return array{
 *     today: list<array<string, mixed>>,
 *     tomorrow: list<array<string, mixed>>,
 *     today_more: bool,
 *     tomorrow_more: bool,
 *     today_date: DateTimeImmutable,
 *     tomorrow_date: DateTimeImmutable
 * }
 */
function latinfo_home_today_tomorrow_events(PDO $db, int $perDay = 0): array
{
    $showAll = $perDay <= 0;
    $perDay = $showAll ? 0 : max(1, min(12, $perDay));
    $windows = latinfo_home_day_windows();
    $fetchLimit = $showAll ? 200 : $perDay * 8;
    $rows = latinfo_home_events_in_range($db, $windows['today_from'], $windows['after_from'], $fetchLimit);
    $today = [];
    $tomorrow = [];
    foreach ($rows as $ev) {
        if (latinfo_home_event_overlaps_window($ev, $windows['today_from'], $windows['tomorrow_from'])) {
            $today[] = $ev;
        }
        if (latinfo_home_event_overlaps_window($ev, $windows['tomorrow_from'], $windows['after_from'])) {
            $tomorrow[] = $ev;
        }
    }

    return [
        'today' => $showAll ? $today : array_slice($today, 0, $perDay),
        'tomorrow' => $showAll ? $tomorrow : array_slice($tomorrow, 0, $perDay),
        'today_more' => !$showAll && count($today) > $perDay,
        'tomorrow_more' => !$showAll && count($tomorrow) > $perDay,
        'today_date' => $windows['today'],
        'tomorrow_date' => $windows['tomorrow'],
    ];
}

/**
 * Dátum a nap fejlécében: „szeptember 16. · szerda” / „September 16 · Wednesday”.
 */
function latinfo_home_day_date(DateTimeImmutable $day, string $lang): string
{
    $monthsHu = [
        1 => 'január', 2 => 'február', 3 => 'március', 4 => 'április',
        5 => 'május', 6 => 'június', 7 => 'július', 8 => 'augusztus',
        9 => 'szeptember', 10 => 'október', 11 => 'november', 12 => 'december',
    ];
    $monthsEn = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];
    $daysHu = ['vasárnap', 'hétfő', 'kedd', 'szerda', 'csütörtök', 'péntek', 'szombat'];
    $daysEn = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $monthNum = (int) $day->format('n');
    $weekday = (int) $day->format('w');

    if ($lang === 'en') {
        $month = $monthsEn[$monthNum] ?? '';
        $dayName = $daysEn[$weekday] ?? '';

        return trim($month . ' ' . $day->format('j') . ' · ' . $dayName);
    }

    $month = $monthsHu[$monthNum] ?? '';
    $dayName = $daysHu[$weekday] ?? '';

    return trim($month . ' ' . $day->format('j') . '. · ' . $dayName);
}

/**
 * @return array{cards: list<array<string, mixed>>, strings: array<string, string>, visible_count: int}
 */
function latinfo_home_dj_spotlight(PDO $db, string $lang): array
{
    if (!function_exists('events_public_dj_catalog')) {
        require_once dirname(__DIR__, 2) . '/events/lib/event_public_djs.php';
    }
    $status = function_exists('events_public_post_status') ? events_public_post_status() : 'publish';
    $strings = function_exists('events_public_djs_strings') ? events_public_djs_strings($lang) : [];
    try {
        $catalog = events_public_dj_catalog($db, $status, null);
        $cards = events_public_dj_spotlight_cards(
            events_public_dj_spotlight_pool($catalog, 24),
            $lang,
            $strings
        );
    } catch (Throwable $e) {
        error_log('latinfo_home_dj_spotlight: ' . $e->getMessage());
        $cards = [];
    }

    return [
        'cards' => $cards,
        'strings' => $strings,
        'visible_count' => 3,
    ];
}
