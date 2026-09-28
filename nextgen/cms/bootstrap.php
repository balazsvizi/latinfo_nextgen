<?php
declare(strict_types=1);

/**
 * Közös betöltés cms/*.php számára.
 */
require_once dirname(__DIR__) . '/init.php';
require_once __DIR__ . '/lib/schema.php';
require_once __DIR__ . '/lib/status.php';
require_once __DIR__ . '/lib/themes.php';
require_once __DIR__ . '/lib/posts.php';
require_once __DIR__ . '/lib/images.php';
require_once __DIR__ . '/lib/views.php';

if (!function_exists('events_sanitize_html_fragment')) {
    require_once dirname(__DIR__) . '/events/lib/html_security.php';
}
if (!function_exists('events_tags_tables_available')) {
    require_once dirname(__DIR__) . '/events/bootstrap.php';
}
if (!function_exists('events_view_tracking_detect_bot')) {
    require_once dirname(__DIR__) . '/events/lib/event_view_tracking.php';
}

if (!function_exists('cms_url')) {
    function cms_url(string $path = ''): string
    {
        return nextgen_url('cms/' . ltrim($path, '/'));
    }
}

if (!function_exists('cms_public_slug_is_valid')) {
    function cms_public_slug_is_valid(string $slug): bool
    {
        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1;
    }
}

if (!function_exists('cms_public_post_url')) {
    /**
     * Publikus cikk URL: /{slug}
     */
    function cms_public_post_url(string $slug): string
    {
        return site_url(rawurlencode(trim($slug)));
    }
}

if (!function_exists('cms_public_is_legacy_megjelenit_request')) {
    /**
     * Közvetlen nextgen/cms/megjelenit.php kérés.
     * A /{slug} belső rewrite nem legacy (különben redirect loop).
     */
    function cms_public_is_legacy_megjelenit_request(): bool
    {
        $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');

        return str_contains(strtolower($path), 'megjelenit.php');
    }
}

if (!function_exists('cms_public_lang_switch_url')) {
    /**
     * HU/EN váltó URL – mindig explicit lang (különben az EN süti megmarad HU-nál).
     *
     * @param array<string, scalar|null> $extra
     */
    function cms_public_lang_switch_url(string $slug, string $targetLang, array $extra = []): string
    {
        $q = ['lang' => $targetLang === 'en' ? 'en' : 'hu'];
        foreach ($extra as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $q[(string) $k] = $v;
        }
        if (isset($_GET['preview']) && (string) $_GET['preview'] === '1' && !isset($q['preview'])) {
            $q['preview'] = '1';
        }

        return cms_public_post_url($slug) . '?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
    }
}

if (!function_exists('cms_public_list_url')) {
    /**
     * @param array<string, scalar|null> $extra
     */
    function cms_public_list_url(array $extra = []): string
    {
        $q = [];
        foreach ($extra as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $q[(string) $k] = $v;
        }

        return cms_url('lista.php' . ($q !== [] ? '?' . http_build_query($q) : ''));
    }
}
