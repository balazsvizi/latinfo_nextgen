<?php
declare(strict_types=1);

/**
 * Közös betöltés events/*.php számára (nextgen init, nincs kötelező login).
 */
require_once dirname(__DIR__) . '/nextgen/init.php';
require_once __DIR__ . '/lib/slug.php';
require_once __DIR__ . '/lib/event_status.php';

if (!function_exists('events_url')) {
    /**
     * Abszolút URL az events mappa alatti admin / nyilvános PHP fájlokhoz.
     */
    function events_url(string $path = ''): string {
        return site_url('events/' . ltrim($path, '/'));
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

if (!function_exists('events_public_favicon_head_markup')) {
    /**
     * Nyilvános esemény oldal favicon (zöld Latinfo-hang, SVG).
     */
    function events_public_favicon_head_markup(): string {
        $href = events_url('assets/favicon-latinfo-event.svg');

        return '<link rel="icon" href="' . h($href) . '" type="image/svg+xml" sizes="any">' . "\n";
    }
}
