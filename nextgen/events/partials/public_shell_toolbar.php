<?php
declare(strict_types=1);

/**
 * Közös nyilvános esemény toolbar.
 *
 * @var string $lang
 * @var array<string, string> $S Oldal szövegei (lang_*, logo_alt)
 * @var string $urlHu
 * @var string $urlEn
 * @var bool $isEventsHome Alapból false — főoldalon ne jelenjen meg a vissza link
 * @var bool $showAdminEdit Opcionális admin szerkesztő gomb
 * @var string $adminEditUrl
 */
$isEventsHome = $isEventsHome ?? false;
$showAdminEdit = $showAdminEdit ?? false;
$adminEditUrl = $adminEditUrl ?? '';
$C = events_public_common_nav_strings($lang);
$eventsHomeUrl = events_public_home_page_url($lang);
$latinfoLogoSrc = events_public_logo_src();
?>
<div class="event-shell-toolbar">
    <div class="event-shell-toolbar__leading">
        <?php if ($showAdminEdit && $adminEditUrl !== ''): ?>
            <a class="event-admin-edit" href="<?= h($adminEditUrl) ?>" title="<?= h($S['admin_edit_title'] ?? '') ?>" aria-label="<?= h($S['admin_edit_aria'] ?? '') ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" aria-hidden="true"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </a>
        <?php endif; ?>
        <?php if (!$isEventsHome): ?>
            <a class="event-shell-toolbar__events-home" href="<?= h($eventsHomeUrl) ?>" aria-label="<?= h($C['events_home_aria']) ?>"><?= h($C['events_home_back']) ?></a>
        <?php endif; ?>
        <a class="event-brand-logo" href="<?= h($eventsHomeUrl) ?>" title="<?= h($C['logo_events_home_title']) ?>" aria-label="<?= h($C['logo_events_home_aria']) ?>">
            <img src="<?= h($latinfoLogoSrc) ?>" alt="<?= h($S['logo_alt']) ?>" width="240" height="80" decoding="async" fetchpriority="high">
        </a>
    </div>
    <div class="event-lang-switch" role="navigation" aria-label="<?= h($S['lang_nav']) ?>">
        <a class="event-lang-switch__link<?= $lang === 'hu' ? ' is-active' : '' ?>" href="<?= h($urlHu) ?>" hreflang="hu" lang="hu"><?= h($S['lang_hu']) ?></a>
        <span class="event-lang-switch__sep" aria-hidden="true">|</span>
        <a class="event-lang-switch__link<?= $lang === 'en' ? ' is-active' : '' ?>" href="<?= h($urlEn) ?>" hreflang="en" lang="en"><?= h($S['lang_en']) ?></a>
    </div>
</div>
