<?php
declare(strict_types=1);

/**
 * @var array<string, string> $settings
 * @var array<string, mixed>|null $heroNews
 * @var list<array<string, mixed>> $newsRest
 * @var list<array<string, mixed>> $collectors
 * @var list<array<string, mixed>> $upcoming
 * @var string $calendarUrl
 * @var string $djsUrl
 * @var string $partnersUrl
 * @var string $organizersUrl
 * @var string $editUrl
 * @var string $cssPublicUrl
 * @var string $cssHomeUrl
 * @var string $heroTitle
 * @var string $heroDek
 * @var string $heroKicker
 * @var string $heroUrl
 * @var string $heroImage
 * @var string $heroCta
 * @var string $lang
 * @var string $htmlLang
 * @var array<string, string> $S
 * @var string $urlHu
 * @var string $urlEn
 * @var bool $isEventsHome
 * @var bool $showAdminEdit
 * @var string $adminEditUrl
 * @var list<array<string, mixed>> $adminFloatTools
 */
$eventsPartial = dirname(__DIR__, 2) . '/events/partials';
?>
<!DOCTYPE html>
<html lang="<?= h($htmlLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#6d8f63">
    <?= events_public_robots_noindex_head_markup() ?>
    <title><?= h(SITE_NAME) ?> – kezdőoldal (előnézet)</title>
    <?= events_public_favicon_head_markup() ?>
    <link rel="stylesheet" href="<?= h($cssPublicUrl) ?>">
    <link rel="stylesheet" href="<?= h($cssHomeUrl) ?>">
</head>
<body class="event-public-page event-public-page--home event-public-page--latinfo-home">
<?php require $eventsPartial . '/admin_float_tools.php'; ?>
<div class="event-shell">
<article class="event-public home-public latinfo-home">
    <header class="event-public__hero">
        <?php require $eventsPartial . '/public_shell_hero_bar.php'; ?>
        <div class="event-public__hero-inner">
            <p class="event-public__eyebrow"><?= h($heroKicker !== '' ? $heroKicker : 'Latinfo.hu') ?></p>
            <h1 class="event-public__title"><?= h($heroTitle) ?></h1>
            <?php if ($heroDek !== ''): ?>
                <p class="latinfo-home__lead"><?= h($heroDek) ?></p>
            <?php endif; ?>
            <div class="event-cta-wrap">
                <a class="event-cta" href="<?= h($heroUrl) ?>"><?= h($heroCta) ?></a>
            </div>
            <?php if ($heroImage !== ''): ?>
                <figure class="event-featured">
                    <img class="event-featured__img event-featured__img.is-landscape" src="<?= h($heroImage) ?>" alt="" decoding="async" fetchpriority="high">
                </figure>
            <?php endif; ?>
        </div>
    </header>

    <div class="home-public__main latinfo-home__main">
        <section class="latinfo-home__section" id="hirek" aria-labelledby="lh-news-title">
            <div class="latinfo-home__section-head">
                <h2 class="latinfo-home__heading" id="lh-news-title">Kiemelt hírek</h2>
                <a class="latinfo-home__edit" href="<?= h(latinfo_home_edit_url('tab=news')) ?>">Szerkesztés</a>
            </div>
            <?php if ($newsRest === []): ?>
                <p class="home-public__empty">Még nincs további kiemelt hír. Az adminból bármikor felvehetsz egyet.</p>
            <?php else: ?>
                <ul class="home-public__list" role="list">
                    <?php foreach ($newsRest as $item): ?>
                        <?php
                        $itemUrl = trim((string) ($item['url'] ?? ''));
                        if ($itemUrl === '') {
                            $itemUrl = $calendarUrl;
                        }
                        $itemImage = latinfo_home_media_src((string) ($item['image_url'] ?? ''));
                        $itemKicker = trim((string) ($item['kicker'] ?? ''));
                        $itemDek = trim((string) ($item['dek'] ?? ''));
                        ?>
                        <li class="home-public__list-item" role="listitem">
                            <a class="home-public__list-card" href="<?= h($itemUrl) ?>" style="--home-event-accent: var(--li-green)">
                                <div class="home-public__list-media">
                                    <?php if ($itemImage !== ''): ?>
                                        <img class="home-public__list-img" src="<?= h($itemImage) ?>" alt="" loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <div class="home-public__list-placeholder" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.25"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 16l5-5 4 4 5-6 5 7"/></svg>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="home-public__list-body">
                                    <?php if ($itemKicker !== ''): ?>
                                        <span class="home-public__list-date"><?= h($itemKicker) ?></span>
                                    <?php endif; ?>
                                    <span class="home-public__list-name"><?= h((string) ($item['title'] ?? '')) ?></span>
                                    <?php if ($itemDek !== ''): ?>
                                        <span class="home-public__list-venue"><?= h($itemDek) ?></span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="latinfo-home__section" id="gyujtok" aria-labelledby="lh-collectors-title">
            <div class="latinfo-home__section-head">
                <h2 class="latinfo-home__heading" id="lh-collectors-title">Gyűjtők</h2>
                <a class="latinfo-home__edit" href="<?= h(latinfo_home_edit_url('tab=collectors')) ?>">Szerkesztés</a>
            </div>
            <?php if ($collectors === []): ?>
                <p class="home-public__empty">Még nincs gyűjtő. Vedd fel a naptárt, DJ-ket, iskolákat – amit a szcéna keres.</p>
            <?php else: ?>
                <ul class="latinfo-home__collectors" role="list">
                    <?php foreach ($collectors as $collector): ?>
                        <?php
                        $cUrl = trim((string) ($collector['url'] ?? ''));
                        if ($cUrl === '') {
                            $cUrl = $calendarUrl;
                        }
                        $cImage = latinfo_home_media_src((string) ($collector['image_url'] ?? ''));
                        $cAccent = normalize_hex_color((string) ($collector['accent_color'] ?? ''), '#6D8F63');
                        $cSub = trim((string) ($collector['subtitle'] ?? ''));
                        ?>
                        <li role="listitem">
                            <a class="latinfo-home__collector" href="<?= h($cUrl) ?>" style="--home-event-accent: <?= h($cAccent) ?>">
                                <div class="home-public__list-media">
                                    <?php if ($cImage !== ''): ?>
                                        <img class="home-public__list-img" src="<?= h($cImage) ?>" alt="" loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <div class="home-public__list-placeholder" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.25"><path d="M4 7h16M4 12h10M4 17h13"/></svg>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="home-public__list-body">
                                    <?php if ($cSub !== ''): ?>
                                        <span class="home-public__list-date"><?= h($cSub) ?></span>
                                    <?php endif; ?>
                                    <span class="home-public__list-name"><?= h((string) ($collector['title'] ?? '')) ?></span>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="latinfo-home__section" id="esemenyek" aria-labelledby="lh-events-title">
            <div class="latinfo-home__section-head">
                <h2 class="latinfo-home__heading" id="lh-events-title">Mi jön a héten</h2>
                <a class="latinfo-home__edit" href="<?= h($calendarUrl) ?>">Teljes naptár</a>
            </div>
            <?php if ($upcoming === []): ?>
                <p class="home-public__empty">Most nincs közelgő, közzétett esemény a naptárban.</p>
            <?php else: ?>
                <ul class="home-public__list" role="list">
                    <?php foreach ($upcoming as $ev): ?>
                        <?php
                        $evUrl = latinfo_home_event_url($ev);
                        $featRaw = trim(html_entity_decode(trim((string) ($ev['event_featured_image_url'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                        $featSrc = $featRaw !== '' ? latinfo_home_media_src($featRaw) : '';
                        $when = latinfo_home_format_event_when($ev);
                        $place = latinfo_home_event_place($ev);
                        ?>
                        <li class="home-public__list-item" role="listitem">
                            <a class="home-public__list-card" href="<?= h($evUrl) ?>" style="--home-event-accent: var(--li-green)">
                                <div class="home-public__list-media">
                                    <?php if ($featSrc !== ''): ?>
                                        <img class="home-public__list-img" src="<?= h($featSrc) ?>" alt="" loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <div class="home-public__list-placeholder" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.25"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 16l5-5 4 4 5-6 5 7"/></svg>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="home-public__list-body">
                                    <?php if ($when !== ''): ?>
                                        <span class="home-public__list-date"><?= h($when) ?></span>
                                    <?php endif; ?>
                                    <span class="home-public__list-name"><?= h((string) ($ev['event_name'] ?? '')) ?></span>
                                    <?php if ($place !== ''): ?>
                                        <span class="home-public__list-venue"><?= h($place) ?></span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <?php if ($settings['newsletter_title'] !== '' || $settings['newsletter_lead'] !== ''): ?>
            <section class="latinfo-home__cta" aria-labelledby="lh-cta-title">
                <div>
                    <h2 class="latinfo-home__heading" id="lh-cta-title"><?= h($settings['newsletter_title']) ?></h2>
                    <?php if ($settings['newsletter_lead'] !== ''): ?>
                        <p class="latinfo-home__lead"><?= h($settings['newsletter_lead']) ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($settings['newsletter_cta_label'] !== '' && $settings['newsletter_cta_url'] !== ''): ?>
                    <a class="event-cta" href="<?= h($settings['newsletter_cta_url']) ?>"><?= h($settings['newsletter_cta_label']) ?></a>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>

    <?php require $eventsPartial . '/public_shell_footer.php'; ?>
</article>
</div>
</body>
</html>
