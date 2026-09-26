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

if (!function_exists('cms_public_post_url')) {
    function cms_public_post_url(string $slug): string
    {
        return cms_url('megjelenit.php?slug=' . rawurlencode($slug));
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
