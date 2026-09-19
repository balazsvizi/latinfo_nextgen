<?php
declare(strict_types=1);

/**
 * Latinfo.hu kezdőoldal előnézet – egyelőre csak belépett adminnak.
 * URL: /nextgen/site/
 */

require_once dirname(__DIR__) . '/init.php';
requireLogin();
require_once dirname(__DIR__) . '/events/bootstrap.php';
require_once dirname(__DIR__) . '/events/lib/event_public_lang.php';
require_once dirname(__DIR__) . '/events/lib/event_public_djs.php';
require_once dirname(__DIR__) . '/events/lib/public_djs_content.php';
require_once dirname(__DIR__) . '/events/lib/public_event_filters.php';
require_once __DIR__ . '/lib/site_home.php';

$lang = events_public_resolve_megjelenit_lang();
$D = events_public_home_strings($lang);
$S = $D;
$S['admin_edit_title'] = 'Szerkesztés';
$S['admin_edit_aria'] = 'Kezdőoldal szerkesztése';
$H = latinfo_home_strings($lang);

$db = getDb();
$schemaOk = latinfo_home_ensure_schema($db);
$news = $schemaOk ? latinfo_home_news_all($db, true) : [];
$quickNews = latinfo_home_quick_news($news, 3);
$dayEvents = latinfo_home_today_tomorrow_events($db);
$categoriesByEventId = events_public_load_categories_by_event_id(
    $db,
    array_merge($dayEvents['today'], $dayEvents['tomorrow'])
);
$djSpotlight = latinfo_home_dj_spotlight($db, $lang);

$calendarUrl = events_public_home_path();
$djsUrl = events_public_djs_hub_canonical_url();
$editUrl = latinfo_home_edit_url();
$cssPublicUrl = events_url('assets/event_public.css') . '?v=' . rawurlencode(nextgen_app_version());
$cssHomeUrl = latinfo_home_asset_url('css/site-home.css') . '?v=' . rawurlencode(nextgen_app_version());

$spotlightCards = $djSpotlight['cards'];
$spotlightMobileCount = $djSpotlight['visible_count'];
$spotlightVisible = array_slice($spotlightCards, 0, $spotlightMobileCount);
$cmsAnchorSpotlight = 'latinfo-dj-ajanlo';
$Dj = $djSpotlight['strings'];

$htmlLang = $lang === 'en' ? 'en' : 'hu';
$urlHu = latinfo_home_lang_switch_url('hu');
$urlEn = latinfo_home_lang_switch_url('en');
$isEventsHome = true;
$showAdminEdit = true;
$adminEditUrl = $editUrl;
$adminFloatTools = [
    [
        'href' => $editUrl,
        'title' => 'Kezdőoldal szerkesztése',
        'aria' => 'Kezdőoldal szerkesztése',
        'icon' => 'edit',
    ],
    [
        'href' => nextgen_url('latinfo/'),
        'title' => 'Latinfo.hu',
        'aria' => 'Vissza a Latinfo.hu alkalmazáshoz',
        'icon' => 'home',
    ],
];

events_public_send_noindex_header();
header('Content-Type: text/html; charset=UTF-8');

require __DIR__ . '/partials/home.php';
