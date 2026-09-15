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
require_once __DIR__ . '/lib/site_home.php';

$lang = events_public_resolve_megjelenit_lang();
$D = events_public_home_strings($lang);
$S = $D;
$S['admin_edit_title'] = 'Szerkesztés';
$S['admin_edit_aria'] = 'Kezdőoldal szerkesztése';

$db = getDb();
$schemaOk = latinfo_home_ensure_schema($db);
$settings = $schemaOk ? latinfo_home_load_settings($db) : latinfo_home_settings_defaults();
$news = $schemaOk ? latinfo_home_news_all($db, true) : [];
$split = latinfo_home_split_news($news);
$heroNews = $split['hero'];
$newsRest = $split['rest'];
$collectors = $schemaOk ? latinfo_home_collectors_all($db, true) : [];
$upcoming = latinfo_home_upcoming_events($db, 6);

$calendarUrl = events_public_home_path();
$editUrl = latinfo_home_edit_url();
$cssPublicUrl = events_url('assets/event_public.css') . '?v=' . rawurlencode(nextgen_app_version());
$cssHomeUrl = latinfo_home_asset_url('css/site-home.css') . '?v=' . rawurlencode(nextgen_app_version());

$heroTitle = $heroNews !== null ? trim((string) $heroNews['title']) : $settings['hero_title'];
$heroDek = $heroNews !== null ? trim((string) $heroNews['dek']) : $settings['hero_lead'];
$heroKicker = $heroNews !== null ? trim((string) $heroNews['kicker']) : $settings['hero_kicker'];
if ($heroKicker === '') {
    $heroKicker = $settings['hero_kicker'];
}
$heroUrl = '#';
if ($heroNews !== null) {
    $heroUrl = trim((string) $heroNews['url']);
    if ($heroUrl === '') {
        $heroUrl = $settings['hero_cta_url'] !== '' ? $settings['hero_cta_url'] : $calendarUrl;
    }
} elseif ($settings['hero_cta_url'] !== '') {
    $heroUrl = $settings['hero_cta_url'];
}
$heroImage = $heroNews !== null ? latinfo_home_media_src((string) $heroNews['image_url']) : '';
$heroCta = $settings['hero_cta_label'] !== '' ? $settings['hero_cta_label'] : 'Tovább';

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
        'href' => nextgen_url('apps.php'),
        'title' => 'Admin',
        'aria' => 'Vissza az admin alkalmazásokhoz',
        'icon' => 'home',
    ],
];

events_public_send_noindex_header();
header('Content-Type: text/html; charset=UTF-8');

require __DIR__ . '/partials/home.php';
