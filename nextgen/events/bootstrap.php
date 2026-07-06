<?php
declare(strict_types=1);

/**
 * Közös betöltés events/*.php számára (nextgen init, nincs kötelező login).
 */
require_once dirname(__DIR__) . '/init.php';
require_once __DIR__ . '/lib/slug.php';
require_once __DIR__ . '/lib/event_status.php';
require_once __DIR__ . '/lib/html_security.php';

if (!function_exists('events_url')) {
    /**
     * Abszolút URL az events mappa alatti admin / nyilvános PHP fájlokhoz.
     */
    function events_url(string $path = ''): string {
        return nextgen_url('events/' . ltrim($path, '/'));
    }
}

if (!function_exists('events_public_home_path')) {
    /**
     * Publikus naptár gyökér útvonal (pl. /events/).
     */
    function events_public_home_path(): string {
        $seg = defined('EVENTS_HOME_PATH') ? EVENTS_HOME_PATH : 'events';

        return rtrim(site_url($seg . '/'), '/') . '/';
    }
}

if (!function_exists('events_public_home_url')) {
    /**
     * Publikus naptár URL query paraméterekkel.
     *
     * @param array<string, scalar|null> $extraParams
     */
    function events_public_home_url(string $lang = 'hu', array $extraParams = []): string {
        $q = [];
        foreach ($extraParams as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $q[(string) $key] = $value;
        }
        if ($lang === 'en') {
            $q['lang'] = 'en';
        } else {
            unset($q['lang']);
        }
        $base = events_public_home_path();
        if ($q === []) {
            return $base;
        }

        return $base . '?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
    }
}

if (!function_exists('events_public_append_query')) {
    /**
     * @param array<string, scalar|null> $params
     */
    function events_public_append_query(string $url, array $params): string {
        $q = [];
        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $q[(string) $key] = $value;
        }
        if ($q === []) {
            return $url;
        }
        $sep = str_contains($url, '?') ? '&' : '?';

        return $url . $sep . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
    }
}

if (!function_exists('events_public_home_page_script')) {
    /**
     * @deprecated Belső admin útvonal; publikus linkekhez events_public_home_url().
     */
    function events_public_home_page_script(): string {
        return '';
    }
}

if (!function_exists('events_public_canonical_url')) {
    /**
     * Publikus esemény canonical URL: /event/{slug}/
     */
    function events_public_canonical_url(string $slug): string {
        $seg = defined('EVENTS_PUBLIC_PATH') ? EVENTS_PUBLIC_PATH : 'event';

        return rtrim(site_url($seg . '/' . rawurlencode($slug)), '/') . '/';
    }
}

if (!function_exists('events_megjelenit_url')) {
    function events_megjelenit_url(string $slug): string {
        return events_public_canonical_url($slug);
    }
}

if (!function_exists('events_public_is_legacy_home_request')) {
    function events_public_is_legacy_home_request(): bool {
        $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
        $path = rtrim($path, '/') ?: '/';
        if (str_ends_with($path, '/public_home.php')) {
            return true;
        }

        return preg_match('#/nextgen/events(?:/index\.php)?$#i', $path) === 1;
    }
}

if (!function_exists('events_public_is_legacy_megjelenit_request')) {
    function events_public_is_legacy_megjelenit_request(): bool {
        return str_contains((string) ($_SERVER['REQUEST_URI'] ?? ''), 'megjelenit.php');
    }
}

if (!function_exists('events_public_redirect_to')) {
    function events_public_redirect_to(string $url, int $code = 301): never {
        header('Location: ' . $url, true, $code);
        exit;
    }
}

if (!function_exists('events_public_maybe_redirect_legacy_home')) {
    function events_public_maybe_redirect_legacy_home(string $lang): void {
        if (!events_public_is_legacy_home_request()) {
            return;
        }
        $q = $_GET;
        unset($q['lang']);
        events_public_redirect_to(events_public_home_url($lang, $q));
    }
}

if (!function_exists('events_helyszin_megjelenit_url')) {
    /**
     * Nyilvános helyszín oldal (slug) – később átírható EVENTS_PUBLIC_PATH mintára.
     */
    function events_helyszin_megjelenit_url(string $slug): string {
        return events_url('helyszin_megjelenit.php?slug=' . rawurlencode($slug));
    }
}

if (!function_exists('events_absolute_url')) {
    /**
     * Teljes URL (OG, kép src): https://…, //…, vagy site_url szerinti útvonal.
     */
    function events_absolute_url(string $pathOrUrl): string {
        $t = trim($pathOrUrl);
        if ($t === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $t)) {
            return $t;
        }
        if (str_starts_with($t, '//')) {
            $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https:' : 'http:';

            return $scheme . $t;
        }
        $path = str_starts_with($t, '/') ? $t : site_url($t);
        if (!str_starts_with($path, '/')) {
            $path = '/' . ltrim($path, '/');
        }
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';

        return $scheme . '://' . $host . $path;
    }
}

if (!function_exists('events_public_logo_src')) {
    /**
     * Nyilvános esemény oldal logó (latinfo.hu custom-logo, helyi másolat).
     */
    function events_public_logo_src(): string {
        return events_url('assets/images/latinfo-logo.png');
    }
}

if (!function_exists('events_public_render_hero_bar')) {
    /**
     * @param array<string, string> $S
     */
    function events_public_render_hero_bar(
        string $lang,
        array $S,
        string $urlHu,
        string $urlEn,
        bool $isEventsHome = false,
        bool $showAdminEdit = false,
        string $adminEditUrl = ''
    ): string {
        ob_start();
        include __DIR__ . '/partials/public_shell_hero_bar.php';

        return (string) ob_get_clean();
    }
}

if (!function_exists('events_public_favicon_head_markup')) {
    /**
     * Nyilvános esemény oldal favicon (zöld Latinfo-hang, SVG).
     */
    function events_public_favicon_head_markup(): string {
        $href = events_url('assets/favicon-latinfo-event.svg');

        return '<link rel="icon" href="' . h($href) . '" type="image/svg+xml" sizes="any">' . "\n";
    }
}

if (!function_exists('events_public_ga_measurement_id')) {
    function events_public_ga_measurement_id(): string {
        if (!defined('GA4_MEASUREMENT_ID')) {
            return '';
        }
        $id = trim((string) GA4_MEASUREMENT_ID);
        if ($id === '' || !preg_match('/^G-[A-Z0-9]+$/', $id)) {
            return '';
        }

        return $id;
    }
}

if (!function_exists('events_public_ga_head_markup')) {
    /**
     * Google Analytics 4 (gtag.js) — csak nyilvános esemény oldalak head-jébe.
     */
    function events_public_ga_head_markup(): string {
        if (function_exists('isLoggedIn') && isLoggedIn()) {
            return '';
        }

        $id = events_public_ga_measurement_id();
        if ($id === '') {
            return '';
        }
        $safeId = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');

        return '<!-- Google Analytics 4 (GA4) -->' . "\n"
            . '<script async src="https://www.googletagmanager.com/gtag/js?id=' . $safeId . '"></script>' . "\n"
            . '<script>' . "\n"
            . 'window.dataLayer = window.dataLayer || [];' . "\n"
            . 'function gtag(){dataLayer.push(arguments);}' . "\n"
            . "gtag('js', new Date());" . "\n"
            . "gtag('config', '" . $safeId . "');" . "\n"
            . '</script>' . "\n";
    }
}

if (!function_exists('events_categories_name_en_available')) {
    /**
     * Van-e events_categories.name_en oszlop (régi DB-k migráció nélkül működjenek).
     */
    function events_categories_name_en_available(PDO $db): bool {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $db->query('SELECT `name_en` FROM `events_categories` LIMIT 1');
            $cached = true;
        } catch (PDOException $e) {
            $cached = false;
        }

        return $cached;
    }
}

if (!function_exists('events_categories_legend_order_available')) {
    /**
     * Van-e events_categories.legend_order oszlop (régi DB-k migráció nélkül működjenek).
     */
    function events_categories_legend_order_available(PDO $db): bool {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $db->query('SELECT `legend_order` FROM `events_categories` LIMIT 1');
            $cached = true;
        } catch (PDOException $e) {
            $cached = false;
        }

        return $cached;
    }
}

if (!function_exists('events_tags_tables_available')) {
    /** Van-e events_tags + esemény–tag kapcsoló (migráció nélkül ne omoljon össze az űrlap). */
    function events_tags_tables_available(PDO $db): bool {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $db->query('SELECT 1 FROM `events_tags` LIMIT 1');
            $db->query('SELECT 1 FROM `events_calendar_event_tags` LIMIT 1');
            $cached = true;
        } catch (PDOException $e) {
            $cached = false;
        }

        return $cached;
    }
}

if (!function_exists('events_styles_tables_available')) {
    /** Van-e events_styles + esemény–stílus kapcsolók (migráció nélkül ne omoljon össze az űrlap). */
    function events_styles_tables_available(PDO $db): bool {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $db->query('SELECT 1 FROM `events_styles` LIMIT 1');
            $db->query('SELECT 1 FROM `events_calendar_event_main_styles` LIMIT 1');
            $db->query('SELECT 1 FROM `events_calendar_event_supplementary_styles` LIMIT 1');
            $cached = true;
        } catch (PDOException $e) {
            $cached = false;
        }

        return $cached;
    }
}
