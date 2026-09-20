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
 * @var string|null $mcalToggleUrl Hun/Eng közti „/” → mobil / klasszikus naptár váltó
 * @var string|null $mcalToggleTitle
 * @var list<array{href:string,label:string,aria?:string}> $heroExtraBackLinks Extra vissza-linkek a „← Naptár” mellett
 */
$isEventsHome = $isEventsHome ?? false;
$showAdminEdit = $showAdminEdit ?? false;
$adminEditUrl = $adminEditUrl ?? '';
$heroInlineTitle = trim((string) ($heroInlineTitle ?? ''));
$mcalToggleUrl = trim((string) ($mcalToggleUrl ?? ''));
$mcalToggleTitle = trim((string) ($mcalToggleTitle ?? ''));
$heroExtraBackLinks = is_array($heroExtraBackLinks ?? null) ? $heroExtraBackLinks : [];
require_once __DIR__ . '/../lib/public_nav_menu.php';
$C = events_public_common_nav_strings($lang);
$eventsHomeUrl = events_public_home_page_url($lang);
$latinfoHomeUrl = defined('LATINFO_PUBLIC_HOME_URL') ? (string) LATINFO_PUBLIC_HOME_URL : site_url('/');
$logoHomeTitle = (string) ($S['logo_home_title'] ?? $C['logo_home_title']);
$logoHomeAria = (string) ($S['logo_home_aria'] ?? $C['logo_home_aria']);
$latinfoLogoSrc = events_public_logo_src();
$L = events_public_lang_switch_link_labels();
$N = events_public_nav_strings($lang);
?>
<div class="event-public__hero-chrome">
    <?php if (!$isEventsHome): ?>
        <nav class="event-public__hero-backs" aria-label="<?= h($C['events_home_aria']) ?>">
            <a class="event-public__hero-back" href="<?= h($eventsHomeUrl) ?>" aria-label="<?= h($C['events_home_aria']) ?>"><?= h($C['events_home_back']) ?></a>
            <?php foreach ($heroExtraBackLinks as $extraBack): ?>
                <?php
                $extraHref = trim((string) ($extraBack['href'] ?? ''));
                $extraLabel = trim((string) ($extraBack['label'] ?? ''));
                if ($extraHref === '' || $extraLabel === '') {
                    continue;
                }
                $extraAria = trim((string) ($extraBack['aria'] ?? $extraLabel));
                ?>
                <a class="event-public__hero-back" href="<?= h($extraHref) ?>" aria-label="<?= h($extraAria) ?>"><?= h($extraLabel) ?></a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
    <div class="event-public__hero-bar">
        <div class="event-public__hero-bar-start">
            <?php if ($showAdminEdit && $adminEditUrl !== ''): ?>
                <a class="event-admin-edit" href="<?= h($adminEditUrl) ?>" title="<?= h($S['admin_edit_title'] ?? '') ?>" aria-label="<?= h($S['admin_edit_aria'] ?? '') ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" aria-hidden="true"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </a>
            <?php endif; ?>
            <a class="event-brand-logo" href="<?= h($latinfoHomeUrl) ?>" title="<?= h($logoHomeTitle) ?>" aria-label="<?= h($logoHomeAria) ?>" data-public-nav-track="logo">
                <img src="<?= h($latinfoLogoSrc) ?>" alt="<?= h($S['logo_alt']) ?>" width="240" height="80" decoding="async" fetchpriority="high">
            </a>
            <?php if ($heroInlineTitle !== ''): ?>
                <h1 class="event-public__hero-inline-title"><?= h($heroInlineTitle) ?></h1>
            <?php endif; ?>
        </div>
        <?php if ($isEventsHome): ?>
            <?php require __DIR__ . '/public_home_renewal_notice.php'; ?>
        <?php endif; ?>
        <div class="event-public__hero-actions">
            <div class="event-lang-switch" role="navigation" aria-label="<?= h($S['lang_nav']) ?>">
                <a class="event-lang-switch__link<?= $lang === 'hu' ? ' is-active' : '' ?>" href="<?= h($urlHu) ?>" hreflang="hu" lang="hu" aria-label="<?= h($L['hu_aria']) ?>" title="<?= h($L['hu_aria']) ?>" data-public-nav-track="lang-hu"><?= h($L['hu_short']) ?></a>
                <?php if ($mcalToggleUrl !== ''): ?>
                    <a class="event-lang-switch__sep event-lang-switch__sep--mcal" href="<?= h($mcalToggleUrl) ?>" title="<?= h($mcalToggleTitle !== '' ? $mcalToggleTitle : 'Mobil naptár') ?>" aria-label="<?= h($mcalToggleTitle !== '' ? $mcalToggleTitle : 'Mobil naptár') ?>" data-public-nav-track="lang-mcal">/</a>
                <?php else: ?>
                    <span class="event-lang-switch__sep" aria-hidden="true">/</span>
                <?php endif; ?>
                <a class="event-lang-switch__link<?= $lang === 'en' ? ' is-active' : '' ?>" href="<?= h($urlEn) ?>" hreflang="en" lang="en" aria-label="<?= h($L['en_aria']) ?>" title="<?= h($L['en_aria']) ?>" data-public-nav-track="lang-en"><?= h($L['en_short']) ?></a>
            </div>
            <button
                type="button"
                class="event-nav__toggle"
                id="event-nav-toggle"
                aria-controls="event-primary-nav"
                aria-expanded="false"
                aria-label="<?= h($N['toggle_open']) ?>"
                title="<?= h($N['toggle_open']) ?>"
            >
                <span class="event-nav__toggle-bars" aria-hidden="true"><span></span><span></span><span></span></span>
            </button>
        </div>
        <?php require __DIR__ . '/public_shell_nav.php'; ?>
    </div>
</div>
