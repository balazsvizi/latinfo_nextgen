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

if (!function_exists('events_public_home_page_script')) {
    /**
     * Publikus esemény főoldal — nem a WP /events URL, hanem külön fájl (public_home.php).
     */
    function events_public_home_page_script(): string {
        return 'public_home.php';
    }
}

if (!function_exists('events_public_canonical_url')) {
    /**
     * Cél URL a spec „eventmappa” + slug mintához (SEO canonical; később .htaccess).
     */
    function events_public_canonical_url(string $slug): string {
        $seg = defined('EVENTS_PUBLIC_PATH') ? EVENTS_PUBLIC_PATH : 'esemenyek';
        return site_url($seg . '/' . rawurlencode($slug));
    }
}

if (!function_exists('events_megjelenit_url')) {
    function events_megjelenit_url(string $slug): string {
        return events_url('megjelenit.php?slug=' . rawurlencode($slug));
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
