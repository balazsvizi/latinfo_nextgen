<?php
declare(strict_types=1);

/**
 * Logó + nyelvváltó a cím (hero) dobozában.
 *
 * @var string $lang
 * @var array<string, string> $S
 * @var string $urlHu
 * @var string $urlEn
 * @var bool $isEventsHome
 * @var bool $showAdminEdit
 * @var string $adminEditUrl
 * @var string $heroInlineTitle Opcionális cím a logó mellett (főoldal)
 */
$isEventsHome = $isEventsHome ?? false;
$showAdminEdit = $showAdminEdit ?? false;
$adminEditUrl = $adminEditUrl ?? '';
$heroInlineTitle = trim((string) ($heroInlineTitle ?? ''));
$C = events_public_common_nav_strings($lang);
$eventsHomeUrl = events_public_home_page_url($lang);
$latinfoLogoSrc = events_public_logo_src();
$L = events_public_lang_switch_link_labels();
?>
<div class="event-public__hero-chrome">
    <?php if (!$isEventsHome): ?>
        <a class="event-public__hero-back" href="<?= h($eventsHomeUrl) ?>" aria-label="<?= h($C['events_home_aria']) ?>"><?= h($C['events_home_back']) ?></a>
    <?php endif; ?>
    <div class="event-public__hero-bar">
        <div class="event-public__hero-bar-start">
            <?php if ($showAdminEdit && $adminEditUrl !== ''): ?>
                <a class="event-admin-edit" href="<?= h($adminEditUrl) ?>" title="<?= h($S['admin_edit_title'] ?? '') ?>" aria-label="<?= h($S['admin_edit_aria'] ?? '') ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" aria-hidden="true"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </a>
            <?php endif; ?>
            <a class="event-brand-logo" href="<?= h($eventsHomeUrl) ?>" title="<?= h($C['logo_events_home_title']) ?>" aria-label="<?= h($C['logo_events_home_aria']) ?>">
                <img src="<?= h($latinfoLogoSrc) ?>" alt="<?= h($S['logo_alt']) ?>" width="240" height="80" decoding="async" fetchpriority="high">
            </a>
            <?php if ($heroInlineTitle !== ''): ?>
                <h1 class="event-public__hero-inline-title"><?= h($heroInlineTitle) ?></h1>
            <?php endif; ?>
        </div>
        <div class="event-lang-switch" role="navigation" aria-label="<?= h($S['lang_nav']) ?>">
            <a class="event-lang-switch__link<?= $lang === 'hu' ? ' is-active' : '' ?>" href="<?= h($urlHu) ?>" hreflang="hu" lang="hu" aria-label="<?= h($L['hu_aria']) ?>" title="<?= h($L['hu_aria']) ?>">
                <span class="event-lang-switch__text event-lang-switch__text--short"><?= h($L['hu_short']) ?></span>
                <span class="event-lang-switch__text event-lang-switch__text--long"><?= h($L['hu_long']) ?></span>
            </a>
            <span class="event-lang-switch__sep" aria-hidden="true">/</span>
            <a class="event-lang-switch__link<?= $lang === 'en' ? ' is-active' : '' ?>" href="<?= h($urlEn) ?>" hreflang="en" lang="en" aria-label="<?= h($L['en_aria']) ?>" title="<?= h($L['en_aria']) ?>">
                <span class="event-lang-switch__text event-lang-switch__text--short"><?= h($L['en_short']) ?></span>
                <span class="event-lang-switch__text event-lang-switch__text--long"><?= h($L['en_long']) ?></span>
            </a>
        </div>
    </div>
</div>
