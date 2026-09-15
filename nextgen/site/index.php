<?php
declare(strict_types=1);

/**
 * Latinfo.hu kezdőoldal előnézet – egyelőre csak belépett adminnak.
 * URL: /nextgen/site/
 */

require_once dirname(__DIR__) . '/init.php';
requireLogin();
require_once dirname(__DIR__) . '/events/bootstrap.php';
require_once __DIR__ . '/lib/site_home.php';

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
$djsUrl = events_public_djs_hub_canonical_url();
$partnersUrl = events_public_partners_canonical_url();
$organizersUrl = events_url('szervezok.php');
$logoSrc = events_public_logo_src();
$editUrl = latinfo_home_edit_url();
$cssUrl = latinfo_home_asset_url('css/site-home.css') . '?v=' . rawurlencode(nextgen_app_version());
$jsUrl = latinfo_home_asset_url('js/site-home.js') . '?v=' . rawurlencode(nextgen_app_version());

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
$heroTone = $heroNews !== null ? latinfo_home_tone_from_id((int) $heroNews['id']) : 'ember';
$heroCta = $settings['hero_cta_label'] !== '' ? $settings['hero_cta_label'] : 'Tovább';

require __DIR__ . '/partials/home.php';
