<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/event_public_lang.php';
require_once __DIR__ . '/lib/partner_blocks.php';

$lang = events_public_resolve_megjelenit_lang();
$D = events_public_partners_strings($lang);

$db = getDb();
if (events_partner_blocks_table_available($db)) {
    events_partner_blocks_ensure_schema($db);
}
$blocks = events_partner_blocks_table_available($db)
    ? events_partner_blocks_all($db, true)
    : [];

$title = (string) $D['page_title'];
$desc = (string) $D['page_desc'];
$canonical = events_absolute_url(events_public_partners_page_url($lang));
$ogPageUrl = $canonical;
$cssUrl = events_url('assets/event_public.css');
$urlHu = events_public_partners_lang_switch_url('hu');
$urlEn = events_public_partners_lang_switch_url('en');
$htmlLang = $lang === 'en' ? 'en' : 'hu';
$S = $D;
$showAdminEdit = isLoggedIn();
$adminEditUrl = $showAdminEdit ? events_url('partnerek_szerkeszt.php') : '';

require_once __DIR__ . '/lib/public_traffic.php';
$eventsPublicTrafficPageKey = 'partners';
events_public_traffic_hit($db, 'partners', $lang);

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="<?= h($htmlLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= events_public_ga_head_markup() ?>
    <?= events_public_robots_index_head_markup() ?>
    <meta name="theme-color" content="#6d8f63">
    <title><?= h($title) ?><?= h($D['html_title_suffix']) ?><?= h(SITE_NAME) ?></title>
    <meta name="description" content="<?= h($desc) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= h(SITE_NAME) ?>">
    <meta property="og:title" content="<?= h($title) ?>">
    <meta property="og:description" content="<?= h($desc) ?>">
    <meta property="og:url" content="<?= h($ogPageUrl) ?>">
    <link rel="canonical" href="<?= h($canonical) ?>">
    <link rel="alternate" hreflang="hu" href="<?= h(events_absolute_url(events_public_partners_page_url('hu'))) ?>">
    <link rel="alternate" hreflang="en" href="<?= h(events_absolute_url(events_public_partners_page_url('en'))) ?>">
    <link rel="alternate" hreflang="x-default" href="<?= h(events_absolute_url(events_public_partners_page_url('hu'))) ?>">
    <?= events_public_favicon_head_markup() ?>
    <link rel="stylesheet" href="<?= h($cssUrl) ?>">
</head>
<body class="event-public-page event-public-page--partners">
<div class="event-shell">
<article class="event-public partners-public">
    <header class="event-public__hero">
        <?php require __DIR__ . '/partials/public_shell_hero_bar.php'; ?>
        <div class="event-public__hero-inner">
            <p class="event-public__eyebrow">🤝 <?= h((string) $D['eyebrow']) ?></p>
            <h1 class="event-public__title"><?= h($title) ?></h1>
        </div>
    </header>

    <section class="partners-public__blocks" aria-label="<?= h((string) $D['page_title']) ?>">
        <?php if ($blocks === []): ?>
            <p class="partners-public__empty"><?= h((string) $D['empty']) ?></p>
        <?php endif; ?>

        <?php foreach ($blocks as $blockRow): ?>
            <?php $block = events_partner_block_localized($blockRow, $lang); ?>

            <?php if ($block['type'] === 'heading'): ?>
                <?php $tag = $block['heading_level'] === 3 ? 'h3' : 'h2'; ?>
                <<?= $tag ?> class="partners-public__heading partners-public__heading--<?= $tag ?>"><?= h($block['title']) ?></<?= $tag ?>>

            <?php elseif ($block['type'] === 'html'): ?>
                <?php if (trim($block['body']) !== ''): ?>
                    <div class="partners-public__html event-rich-text"><?= $block['body'] ?></div>
                <?php endif; ?>

            <?php else: ?>
                <?php
                $partnerName = $block['title'];
                $partnerUrl = $block['link_url'];
                $partnerLogo = ($block['show_logo'] && $block['logo_url'] !== '')
                    ? events_absolute_url($block['logo_url'])
                    : '';
                $showName = $block['show_name'] && $partnerName !== '';
                $logoSize = $block['logo_size'];
                $hasCardVisual = $partnerLogo !== '' || $showName;
                $partnerAria = sprintf((string) $D['partner_link_aria'], $partnerName !== '' ? $partnerName : (string) $D['page_title']);
                ?>
                <div class="partners-public__partner">
                    <?php if (trim($block['note_before']) !== ''): ?>
                        <div class="partners-public__note partners-public__note--before event-rich-text"><?= $block['note_before'] ?></div>
                    <?php endif; ?>

                    <?php if ($hasCardVisual): ?>
                        <?php if ($partnerUrl !== ''): ?>
                            <a class="partners-public__card partners-public__card--<?= h($logoSize) ?>" href="<?= h($partnerUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= h($partnerAria) ?>">
                        <?php else: ?>
                            <div class="partners-public__card partners-public__card--static partners-public__card--<?= h($logoSize) ?>">
                        <?php endif; ?>
                            <?php if ($partnerLogo !== ''): ?>
                                <span class="partners-public__logo partners-public__logo--<?= h($logoSize) ?>">
                                    <img src="<?= h($partnerLogo) ?>" alt="" loading="lazy" decoding="async">
                                </span>
                            <?php endif; ?>
                            <?php if ($showName): ?>
                                <span class="partners-public__name"><?= h($partnerName) ?></span>
                            <?php endif; ?>
                        <?php if ($partnerUrl !== ''): ?>
                            </a>
                        <?php else: ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if (trim($block['note_after']) !== ''): ?>
                        <div class="partners-public__note partners-public__note--after event-rich-text"><?= $block['note_after'] ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </section>

    <footer class="event-public__footer">
        <?php require __DIR__ . '/partials/public_shell_footer.php'; ?>
    </footer>
</article>
</div>
</body>
</html>
