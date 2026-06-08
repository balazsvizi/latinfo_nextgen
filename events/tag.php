<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/event_public_lang.php';
require_once __DIR__ . '/lib/event_public_tags.php';

$lang = events_public_resolve_megjelenit_lang();
$G = events_public_tag_strings($lang);

$tagId = (int) ($_GET['id'] ?? 0);
if ($tagId <= 0) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo events_public_tag_not_found_html($lang);
    exit;
}

$db = getDb();
if (!events_tags_tables_available($db)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo events_public_tag_not_found_html($lang);
    exit;
}

$st = $db->prepare('SELECT `id`, `name` FROM `events_tags` WHERE `id` = ? LIMIT 1');
$st->execute([$tagId]);
$tag = $st->fetch(PDO::FETCH_ASSOC);
if (!$tag) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo events_public_tag_not_found_html($lang);
    exit;
}

$tagName = (string) ($tag['name'] ?? '');
$tagTypeCodes = events_load_tag_type_codes($db, $tagId);
$eyebrow = events_public_tag_eyebrow_label($tagTypeCodes, $lang, $db);
$eventsList = events_public_tag_published_events($db, $tagId, events_public_post_status());
$partitioned = events_public_organizer_partition_events($eventsList);
$eventsUpcoming = $partitioned['upcoming'];
$eventsPast = $partitioned['past'];

$title = $tagName !== '' ? $tagName : ('#' . $tagId);
$desc = $lang === 'en'
    ? ($tagName !== '' ? 'Published events tagged with ' . $tagName . ' on Latinfo.hu.' : 'Published events on Latinfo.hu.')
    : ($tagName !== '' ? $tagName . ' címkéjű közzétett események a Latinfo.hu-n.' : 'Közzétett események a Latinfo.hu-n.');

$canonical = events_absolute_url(events_url('tag.php?id=' . $tagId));
$ogPageUrl = events_absolute_url(events_public_tag_page_url($tagId, $lang));
$cssUrl = events_url('assets/event_public.css');
$urlHu = events_public_tag_lang_switch_url($tagId, 'hu');
$urlEn = events_public_tag_lang_switch_url($tagId, 'en');
$htmlLang = $lang === 'en' ? 'en' : 'hu';
$latinfoHomeUrl = LATINFO_PUBLIC_HOME_URL;
$latinfoLogoSrc = site_url('lanueva/assets/images/logo/latinfo_black.png');
$showAdminEdit = isLoggedIn();
$tagEditUrl = events_url('tags.php?open_tag=') . $tagId;

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="<?= h($htmlLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#6d8f63">
    <title><?= h($title) ?><?= h($G['html_title_suffix']) ?><?= h(SITE_NAME) ?></title>
    <meta name="description" content="<?= h($desc) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= h(SITE_NAME) ?>">
    <meta property="og:title" content="<?= h($title) ?>">
    <meta property="og:description" content="<?= h($desc) ?>">
    <meta property="og:url" content="<?= h($ogPageUrl) ?>">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= h($title) ?>">
    <meta name="twitter:description" content="<?= h($desc) ?>">
    <link rel="canonical" href="<?= h($canonical) ?>">
    <link rel="alternate" hreflang="hu" href="<?= h($urlHu) ?>">
    <link rel="alternate" hreflang="en" href="<?= h($urlEn) ?>">
    <link rel="alternate" hreflang="x-default" href="<?= h($urlHu) ?>">
    <?= events_public_favicon_head_markup() ?>
    <link rel="stylesheet" href="<?= h($cssUrl) ?>">
</head>
<body class="event-public-page">
<div class="event-shell">
    <div class="event-shell-toolbar">
        <div class="event-shell-toolbar__leading">
            <?php if ($showAdminEdit): ?>
                <a class="event-admin-edit" href="<?= h($tagEditUrl) ?>" title="<?= h($G['admin_edit_title']) ?>" aria-label="<?= h($G['admin_edit_aria']) ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" aria-hidden="true"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </a>
            <?php endif; ?>
            <a class="event-brand-logo" href="<?= h($latinfoHomeUrl) ?>" title="<?= h($G['logo_home_title']) ?>" aria-label="<?= h($G['logo_home_aria']) ?>">
                <img src="<?= h($latinfoLogoSrc) ?>" alt="<?= h($G['logo_alt']) ?>" width="180" height="48" decoding="async" fetchpriority="high">
            </a>
        </div>
        <div class="event-lang-switch" role="navigation" aria-label="<?= h($G['lang_nav']) ?>">
            <a class="event-lang-switch__link<?= $lang === 'hu' ? ' is-active' : '' ?>" href="<?= h($urlHu) ?>" hreflang="hu" lang="hu"><?= h($G['lang_hu']) ?></a>
            <span class="event-lang-switch__sep" aria-hidden="true">|</span>
            <a class="event-lang-switch__link<?= $lang === 'en' ? ' is-active' : '' ?>" href="<?= h($urlEn) ?>" hreflang="en" lang="en"><?= h($G['lang_en']) ?></a>
        </div>
    </div>
<article class="event-public organizer-public">
    <header class="event-public__hero">
        <div class="event-public__hero-inner">
            <p class="event-public__eyebrow"><?= h($eyebrow) ?></p>
            <h1 class="event-public__title"><?= h($title) ?></h1>
        </div>
    </header>

    <section class="organizer-public__events" aria-labelledby="tag-events-heading">
        <h2 class="organizer-public__events-title" id="tag-events-heading"><?= h($G['events_heading']) ?></h2>
        <?php if ($eventsList === []): ?>
            <p class="organizer-public__empty"><?= h($G['list_empty']) ?></p>
        <?php else: ?>
            <?php
            $tagEventBlocks = [
                ['id' => 'tag-upcoming', 'heading' => $G['section_upcoming'], 'rows' => $eventsUpcoming, 'empty' => $G['upcoming_empty']],
                ['id' => 'tag-past', 'heading' => $G['section_past'], 'rows' => $eventsPast, 'empty' => $G['past_empty']],
            ];
            ?>
            <?php foreach ($tagEventBlocks as $block): ?>
                <?php
                $isPastSection = ($block['id'] ?? '') === 'tag-past';
                $subsectionClass = 'organizer-public__subsection' . ($isPastSection ? ' organizer-public__subsection--past' : '');
                $subsectionTitleClass = 'organizer-public__subsection-title' . ($isPastSection ? ' organizer-public__subsection-title--past' : '');
                ?>
                <div class="<?= h($subsectionClass) ?>" id="<?= h((string) $block['id']) ?>">
                    <h3 class="<?= h($subsectionTitleClass) ?>"><?= h((string) $block['heading']) ?></h3>
                    <?php if ($block['rows'] === []): ?>
                        <p class="organizer-public__subsection-empty"><?= h((string) $block['empty']) ?></p>
                    <?php else: ?>
                        <ul class="event-related-grid" role="list">
                            <?php foreach ($block['rows'] as $rel): ?>
                                <?php
                                $relSlug = (string) ($rel['event_slug'] ?? '');
                                $relTitle = (string) ($rel['event_name'] ?? '');
                                $relHref = events_public_event_page_url($relSlug, $lang);
                                $relAllday = !empty($rel['event_allday']);
                                $relTsStart = !empty($rel['event_start']) ? strtotime((string) $rel['event_start']) : false;
                                $dateDisplay = events_public_event_start_date_time_display($relAllday, $relTsStart, $lang);
                                $relFeatRaw = trim(html_entity_decode(trim((string) ($rel['event_featured_image_url'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                                $relFeatRaw = preg_replace('/^\x{FEFF}|\x{200B}/u', '', $relFeatRaw) ?? $relFeatRaw;
                                $relFeatAbs = $relFeatRaw !== '' ? events_absolute_url($relFeatRaw) : '';
                                $venueCity = trim((string) ($rel['venue_city'] ?? ''));
                                ?>
                                <li class="event-related-grid__cell">
                                    <a class="event-related-card" href="<?= h($relHref) ?>">
                                        <div class="event-related-card__media">
                                            <?php if ($relFeatAbs !== ''): ?>
                                                <img
                                                    class="event-related-card__img"
                                                    src="<?= h($relFeatAbs) ?>"
                                                    alt=""
                                                    width="640"
                                                    height="360"
                                                    loading="lazy"
                                                    decoding="async"
                                                >
                                            <?php else: ?>
                                                <div class="event-related-card__placeholder" aria-hidden="true">
                                                    <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.25"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 16l5-5 4 4 5-6 5 7"/></svg>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="event-related-card__body">
                                            <span class="event-related-card__title"><?= h($relTitle) ?></span>
                                            <?php if ($dateDisplay !== '' || $venueCity !== ''): ?>
                                                <div class="event-related-card__meta">
                                                    <?php if ($dateDisplay !== ''): ?>
                                                        <span class="event-related-card__date"><?= h($dateDisplay) ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($venueCity !== ''): ?>
                                                        <span class="event-related-card__city"><?= h($venueCity) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <footer class="event-public__footer">
        <p class="event-site-line">
            <a href="<?= h($latinfoHomeUrl) ?>"><?= h($G['footer_home_link']) ?></a>
        </p>
    </footer>
</article>
</div>
</body>
</html>
