<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/event_public_lang.php';
require_once __DIR__ . '/lib/event_public_tags.php';
require_once __DIR__ . '/lib/event_public_djs.php';
require_once __DIR__ . '/lib/admin_event_filters.php';
require_once __DIR__ . '/lib/public_event_filters.php';

$lang = events_public_resolve_megjelenit_lang();
$G = events_public_tag_strings($lang);

$db = getDb();
$slugParam = trim((string) ($_GET['slug'] ?? ''));
$tagId = (int) ($_GET['id'] ?? 0);
$tagSlug = '';
$fromDjPretty = events_public_is_dj_pretty_request();

if (!events_tags_tables_available($db)) {
    http_response_code(404);
    events_public_send_noindex_header();
    header('Content-Type: text/html; charset=UTF-8');
    echo events_public_tag_not_found_html($lang);
    exit;
}

events_tags_ensure_dj_slugs($db);

if ($slugParam !== '') {
    $djRow = events_public_dj_by_slug($db, $slugParam);
    if ($djRow === null || (int) $djRow['id'] <= 0) {
        http_response_code(404);
        events_public_send_noindex_header();
        header('Content-Type: text/html; charset=UTF-8');
        echo events_public_tag_not_found_html($lang);
        exit;
    }
    $tagId = (int) $djRow['id'];
    $tagSlug = (string) $djRow['slug'];
    $tagName = (string) $djRow['name'];
    $tag = ['id' => $tagId, 'name' => $tagName, 'slug' => $tagSlug];
} elseif ($tagId > 0) {
    $slugSelect = events_tags_slug_column_available($db) ? ', `slug`' : '';
    $st = $db->prepare('SELECT `id`, `name`' . $slugSelect . ' FROM `events_tags` WHERE `id` = ? LIMIT 1');
    $st->execute([$tagId]);
    $tag = $st->fetch(PDO::FETCH_ASSOC);
    if (!$tag) {
        http_response_code(404);
        events_public_send_noindex_header();
        header('Content-Type: text/html; charset=UTF-8');
        echo events_public_tag_not_found_html($lang);
        exit;
    }
    $tagName = (string) ($tag['name'] ?? '');
    $tagSlug = trim((string) ($tag['slug'] ?? ''));
} else {
    http_response_code(404);
    events_public_send_noindex_header();
    header('Content-Type: text/html; charset=UTF-8');
    echo events_public_tag_not_found_html($lang);
    exit;
}

$tagTypeRows = events_public_tag_type_rows_for_display($db, $tagId);
$tagIsDj = events_public_tag_has_type_code($db, $tagId, 'dj');

if ($fromDjPretty && !$tagIsDj) {
    http_response_code(404);
    events_public_send_noindex_header();
    header('Content-Type: text/html; charset=UTF-8');
    echo events_public_tag_not_found_html($lang);
    exit;
}

if ($tagIsDj && $tagSlug === '') {
    $synced = events_tag_sync_dj_slug($db, $tagId, $tagName, ['dj']);
    if ($synced !== null) {
        $tagSlug = $synced;
    }
}

if ($tagIsDj && $tagSlug !== '' && events_public_is_legacy_tag_request()) {
    $legacyParams = [];
    foreach (['lang', 'limit'] as $param) {
        if (isset($_GET[$param]) && (string) $_GET[$param] !== '') {
            $legacyParams[$param] = (string) $_GET[$param];
        }
    }
    $targetLang = ($legacyParams['lang'] ?? $lang) === 'en' ? 'en' : 'hu';
    unset($legacyParams['lang']);
    events_public_redirect_to(events_public_dj_page_url($tagSlug, $targetLang, $legacyParams));
}

$publishedStatus = events_public_post_status();
$listLimitParsed = events_admin_list_limit_from_get(EVENTS_ADMIN_EVENTS_LIST_DEFAULT_LIMIT);
$list_limit = $listLimitParsed['sql_limit'];
$listLimitValue = $listLimitParsed['value'];
$listTotalInDb = events_public_tag_published_events_total_count($db, $tagId, $publishedStatus);
$limitParams = events_public_events_list_get_params($listLimitValue);
$eventsList = events_public_tag_published_events($db, $tagId, $publishedStatus, $list_limit);
$partitioned = events_public_organizer_partition_events($eventsList);
$eventsUpcoming = $partitioned['upcoming'];
$eventsPast = $partitioned['past'];
$eventsTotalCount = count($eventsList);
$eventsUpcomingCount = count($eventsUpcoming);
$eventsPastCount = count($eventsPast);

$title = $tagName !== '' ? $tagName : ('#' . $tagId);
$desc = $lang === 'en'
    ? ($tagName !== '' ? 'Published events tagged with ' . $tagName . ' on Latinfo.hu.' : 'Published events on Latinfo.hu.')
    : ($tagName !== '' ? $tagName . ' címkéjű közzétett események a Latinfo.hu-n.' : 'Közzétett események a Latinfo.hu-n.');

if ($tagIsDj && $tagSlug !== '') {
    $canonical = events_absolute_url(events_public_dj_page_url($tagSlug, 'hu'));
    $ogPageUrl = events_absolute_url(events_public_dj_page_url($tagSlug, $lang, $limitParams));
    $urlHu = events_public_dj_lang_switch_url($tagSlug, 'hu', $limitParams);
    $urlEn = events_public_dj_lang_switch_url($tagSlug, 'en', $limitParams);
} else {
    $canonical = events_absolute_url(events_url('tag.php?id=' . $tagId));
    $ogPageUrl = events_absolute_url(events_public_tag_page_url($tagId, $lang, $limitParams));
    $urlHu = events_public_tag_lang_switch_url($tagId, 'hu', $limitParams);
    $urlEn = events_public_tag_lang_switch_url($tagId, 'en', $limitParams);
}
$cssUrl = events_url('assets/event_public.css');
$htmlLang = $lang === 'en' ? 'en' : 'hu';
$S = $G;
$showAdminEdit = isLoggedIn();
$adminEditUrl = events_url('tags.php?open_tag=') . $tagId;

events_public_send_noindex_follow_header();
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="<?= h($htmlLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= events_public_ga_head_markup() ?>
    <?= events_public_robots_noindex_follow_head_markup() ?>
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
<article class="event-public organizer-public">
    <header class="event-public__hero">
        <?php $S = $G; require __DIR__ . '/partials/public_shell_hero_bar.php'; ?>
        <div class="event-public__hero-inner">
            <?php if ($tagTypeRows !== []): ?>
                <div class="tag-public__types" aria-label="<?= h($lang === 'en' ? 'Tag types' : 'Címke típusok') ?>">
                    <?php foreach ($tagTypeRows as $typeRow): ?>
                        <?php
                        $tone = (string) ($typeRow['tone'] ?? 'default');
                        $icon = (string) ($typeRow['icon'] ?? '🏷️');
                        ?>
                        <span class="tag-public__type-pill tag-public__type-pill--<?= h($tone) ?>">
                            <span class="tag-public__type-pill__icon" aria-hidden="true"><?= h($icon) ?></span>
                            <span class="tag-public__type-pill__label"><?= h((string) ($typeRow['name'] ?? '')) ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <h1 class="event-public__title"><?= h($title) ?></h1>
            <?php if ($tagIsDj): ?>
                <p class="tag-public__nav-link">
                    <a href="<?= h(events_public_djs_page_url($lang)) ?>">← <?= h($G['all_djs_link']) ?></a>
                </p>
            <?php endif; ?>
        </div>
    </header>

    <section class="organizer-public__events" aria-labelledby="tag-events-heading">
        <div class="organizer-public__events-head">
            <h2 class="organizer-public__events-title" id="tag-events-heading">
                <?= h($G['events_heading']) ?>
                <span class="organizer-public__heading-count">(<?= $eventsTotalCount ?>)</span>
            </h2>
            <?php if ($eventsList !== []): ?>
                <?php $D = $G; require __DIR__ . '/partials/public_entity_events_display_limit.php'; ?>
            <?php endif; ?>
        </div>
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
                    <?php
                    $blockCount = ($block['id'] ?? '') === 'tag-past' ? $eventsPastCount : $eventsUpcomingCount;
                    ?>
                    <h3 class="<?= h($subsectionTitleClass) ?>">
                        <?= h((string) $block['heading']) ?>
                        <span class="organizer-public__heading-count">(<?= $blockCount ?>)</span>
                    </h3>
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
        <?php require __DIR__ . '/partials/public_shell_footer.php'; ?>
    </footer>
</article>
</div>
<?php if ($eventsList !== []): ?>
<?php
$listLimitDefault = EVENTS_ADMIN_EVENTS_LIST_DEFAULT_LIMIT;
require __DIR__ . '/partials/admin_list_display_limit_script.php';
?>
<?php endif; ?>
<?php require __DIR__ . '/partials/event_image_orientation_script.php'; ?>
</body>
</html>
