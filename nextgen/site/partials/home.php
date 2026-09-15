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
 * @var string $logoSrc
 * @var string $editUrl
 * @var string $cssUrl
 * @var string $jsUrl
 * @var string $heroTitle
 * @var string $heroDek
 * @var string $heroKicker
 * @var string $heroUrl
 * @var string $heroImage
 * @var string $heroTone
 * @var string $heroCta
 */
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= h(SITE_NAME) ?> – kezdőoldal (előnézet)</title>
    <?php require dirname(__DIR__, 2) . '/includes/favicon_head.php'; ?>
    <link rel="stylesheet" href="<?= h($cssUrl) ?>">
</head>
<body class="lh-page">
<div class="lh-wrap">
    <div class="lh-chrome">
    <div class="lh-adminbar">
        <span class="lh-adminbar__note">Előnézet · csak admin</span>
        <div class="lh-adminbar__actions">
            <a href="<?= h($editUrl) ?>">Szerkesztés</a>
            <a href="<?= h(nextgen_url('apps.php')) ?>">Admin</a>
        </div>
    </div>

    <header class="lh-header" id="lh-header">
        <div class="lh-header__inner">
            <a class="lh-brand" href="<?= h(latinfo_home_preview_url()) ?>">
                <img src="<?= h($logoSrc) ?>" alt="" width="160" height="40" decoding="async">
                <span class="lh-brand__mark">Latinfo</span>
                <span class="lh-brand__tag">hu</span>
            </a>
            <button type="button" class="lh-nav-toggle" id="lh-nav-toggle" aria-expanded="false" aria-controls="lh-nav" aria-label="Menü megnyitása">
                <span></span>
            </button>
            <nav class="lh-nav" id="lh-nav" aria-label="Főmenü">
                <ul>
                    <li><a href="<?= h($calendarUrl) ?>">Naptár</a></li>
                    <li><a href="<?= h($djsUrl) ?>">DJ-k</a></li>
                    <li><a href="#gyujtok">Gyűjtők</a></li>
                    <li><a href="#hirek">Hírek</a></li>
                    <li><a href="<?= h($partnersUrl) ?>">Partnereink</a></li>
                </ul>
            </nav>
        </div>
    </header>
    </div>

    <main class="lh-main">
        <section class="lh-hero lh-hero--tone-<?= h($heroTone) ?>" aria-labelledby="lh-hero-title">
            <div class="lh-hero__media">
                <?php if ($heroImage !== ''): ?>
                    <img src="<?= h($heroImage) ?>" alt="" decoding="async" fetchpriority="high">
                <?php endif; ?>
            </div>
            <div class="lh-hero__shade" aria-hidden="true"></div>
            <div class="lh-hero__body">
                <?php if ($heroKicker !== ''): ?>
                    <p class="lh-kicker"><?= h($heroKicker) ?></p>
                <?php endif; ?>
                <h1 id="lh-hero-title"><?= h($heroTitle) ?></h1>
                <?php if ($heroDek !== ''): ?>
                    <p><?= h($heroDek) ?></p>
                <?php endif; ?>
                <a class="lh-btn lh-btn--gold" href="<?= h($heroUrl) ?>"><?= h($heroCta) ?></a>
            </div>
        </section>

        <section class="lh-section" id="hirek" aria-labelledby="lh-news-title">
            <div class="lh-section__head">
                <h2 id="lh-news-title">Kiemelt hírek</h2>
                <a href="<?= h(latinfo_home_edit_url('tab=news')) ?>">Szerkesztés</a>
            </div>
            <?php if ($newsRest === []): ?>
                <p class="lh-empty">Még nincs további kiemelt hír. Az adminból bármikor felvehetsz egyet.</p>
            <?php else: ?>
                <div class="lh-news">
                    <?php foreach ($newsRest as $item): ?>
                        <?php
                        $itemId = (int) ($item['id'] ?? 0);
                        $itemUrl = trim((string) ($item['url'] ?? ''));
                        if ($itemUrl === '') {
                            $itemUrl = $calendarUrl;
                        }
                        $itemImage = latinfo_home_media_src((string) ($item['image_url'] ?? ''));
                        $itemTone = latinfo_home_tone_from_id($itemId);
                        $itemKicker = trim((string) ($item['kicker'] ?? ''));
                        $itemDek = trim((string) ($item['dek'] ?? ''));
                        ?>
                        <a class="lh-news-card" href="<?= h($itemUrl) ?>">
                            <div class="lh-news-card__media lh-news-card__media--tone-<?= h($itemTone) ?>">
                                <?php if ($itemImage !== ''): ?>
                                    <img src="<?= h($itemImage) ?>" alt="" loading="lazy" decoding="async">
                                <?php endif; ?>
                            </div>
                            <div class="lh-news-card__body">
                                <?php if ($itemKicker !== ''): ?>
                                    <span class="lh-kicker"><?= h($itemKicker) ?></span>
                                <?php endif; ?>
                                <h3><?= h((string) ($item['title'] ?? '')) ?></h3>
                                <?php if ($itemDek !== ''): ?>
                                    <p><?= h($itemDek) ?></p>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="lh-section" id="gyujtok" aria-labelledby="lh-collectors-title">
            <div class="lh-section__head">
                <h2 id="lh-collectors-title">Gyűjtők</h2>
                <a href="<?= h(latinfo_home_edit_url('tab=collectors')) ?>">Szerkesztés</a>
            </div>
            <?php if ($collectors === []): ?>
                <p class="lh-empty">Még nincs gyűjtő. Vedd fel a naptárt, DJ-ket, iskolákat – amit a szcéna keres.</p>
            <?php else: ?>
                <div class="lh-collectors">
                    <?php foreach ($collectors as $collector): ?>
                        <?php
                        $cUrl = trim((string) ($collector['url'] ?? ''));
                        if ($cUrl === '') {
                            $cUrl = $calendarUrl;
                        }
                        $cImage = latinfo_home_media_src((string) ($collector['image_url'] ?? ''));
                        $cAccent = normalize_hex_color((string) ($collector['accent_color'] ?? ''), '#9CBF90');
                        $cSub = trim((string) ($collector['subtitle'] ?? ''));
                        ?>
                        <a class="lh-collector" href="<?= h($cUrl) ?>" style="--lh-accent: <?= h($cAccent) ?>">
                            <?php if ($cImage !== ''): ?>
                                <img src="<?= h($cImage) ?>" alt="" loading="lazy" decoding="async">
                            <?php endif; ?>
                            <?php if ($cSub !== ''): ?>
                                <span><?= h($cSub) ?></span>
                            <?php endif; ?>
                            <h3><?= h((string) ($collector['title'] ?? '')) ?></h3>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="lh-section" id="esemenyek" aria-labelledby="lh-events-title">
            <div class="lh-section__head">
                <h2 id="lh-events-title">Mi jön a héten</h2>
                <a href="<?= h($calendarUrl) ?>">Teljes naptár</a>
            </div>
            <?php if ($upcoming === []): ?>
                <p class="lh-empty">Most nincs közelgő, közzétett esemény a naptárban.</p>
            <?php else: ?>
                <div class="lh-events">
                    <?php foreach ($upcoming as $ev): ?>
                        <?php
                        $evUrl = latinfo_home_event_url($ev);
                        $featRaw = trim(html_entity_decode(trim((string) ($ev['event_featured_image_url'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                        $featSrc = $featRaw !== '' ? latinfo_home_media_src($featRaw) : '';
                        $when = latinfo_home_format_event_when($ev);
                        $place = latinfo_home_event_place($ev);
                        ?>
                        <a class="lh-event" href="<?= h($evUrl) ?>">
                            <div class="lh-event__media">
                                <?php if ($featSrc !== ''): ?>
                                    <img src="<?= h($featSrc) ?>" alt="" loading="lazy" decoding="async">
                                <?php endif; ?>
                            </div>
                            <div>
                                <?php if ($when !== ''): ?>
                                    <time datetime="<?= h((string) ($ev['event_start'] ?? '')) ?>"><?= h($when) ?></time>
                                <?php endif; ?>
                                <h3><?= h((string) ($ev['event_name'] ?? '')) ?></h3>
                                <?php if ($place !== ''): ?>
                                    <p><?= h($place) ?></p>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($settings['newsletter_title'] !== '' || $settings['newsletter_lead'] !== ''): ?>
            <section class="lh-cta" aria-labelledby="lh-cta-title">
                <div>
                    <h2 id="lh-cta-title"><?= h($settings['newsletter_title']) ?></h2>
                    <?php if ($settings['newsletter_lead'] !== ''): ?>
                        <p><?= h($settings['newsletter_lead']) ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($settings['newsletter_cta_label'] !== '' && $settings['newsletter_cta_url'] !== ''): ?>
                    <a class="lh-btn lh-btn--gold" href="<?= h($settings['newsletter_cta_url']) ?>"><?= h($settings['newsletter_cta_label']) ?></a>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>

    <footer class="lh-footer">
        <div class="lh-footer__inner">
            <div>
                <a href="<?= h($calendarUrl) ?>">Naptár</a>
                ·
                <a href="<?= h($djsUrl) ?>">DJ-k</a>
                ·
                <a href="<?= h($organizersUrl) ?>">Szervezők</a>
                ·
                <a href="<?= h($partnersUrl) ?>">Partnereink</a>
            </div>
            <div>
                &copy; <?= h((string) date('Y')) ?> <?= h(SITE_NAME) ?>
                <?= nextgen_footer_version_markup() ?>
            </div>
        </div>
    </footer>
</div>
<script src="<?= h($jsUrl) ?>" defer></script>
</body>
</html>
