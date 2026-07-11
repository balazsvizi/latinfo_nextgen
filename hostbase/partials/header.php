<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var string $activeNav */
/** @var list<array<string, mixed>> $properties */
/** @var array<string, mixed>|null $currentProperty */

if (!isset($properties) && hb_is_logged_in()) {
    $properties = HbPropertyRepository::listForSubscriber(hb_get_db(), hb_subscriber_id());
    $currentProperty = HbPropertyRepository::ensureCurrentProperty(hb_get_db(), hb_subscriber_id());
}
$properties = $properties ?? [];
$currentProperty = $currentProperty ?? null;
$showPropertySwitcher = $showPropertySwitcher ?? true;
?>
<!DOCTYPE html>
<html lang="<?= hb_h(hb_current_locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= hb_h(HB_APP_NAME) ?><?= isset($pageTitle) ? ' – ' . hb_h($pageTitle) : '' ?></title>
    <link rel="stylesheet" href="<?= hb_h(hb_asset_url('css/app.css')) ?>">
    <?php if (!empty($extraHead)) {
        echo $extraHead;
    } ?>
</head>
<body class="hb-app">
<header class="hb-header">
    <div class="hb-header__inner">
        <a href="<?= hb_h(hb_url('index.php')) ?>" class="hb-brand">
            <span class="hb-brand__name"><?= hb_h(HB_APP_NAME) ?></span>
        </a>
        <button type="button" class="hb-nav-toggle" id="hb-nav-toggle" aria-expanded="false" aria-controls="hb-nav">
            <span class="hb-nav-toggle__bar"></span>
            <span class="hb-nav-toggle__bar"></span>
            <span class="hb-nav-toggle__bar"></span>
            <span class="sr-only"><?= hb_h(hb_t('nav.menu')) ?></span>
        </button>
        <nav class="hb-nav" id="hb-nav" aria-label="HostBase">
            <a href="<?= hb_h(hb_url('index.php')) ?>" class="hb-nav__link<?= ($activeNav ?? '') === 'dashboard' ? ' is-active' : '' ?>"><?= hb_h(hb_t('nav.dashboard')) ?></a>
            <a href="<?= hb_h(hb_url('calendar/index.php')) ?>" class="hb-nav__link<?= ($activeNav ?? '') === 'calendar' ? ' is-active' : '' ?>"><?= hb_h(hb_t('nav.calendar')) ?></a>
            <a href="<?= hb_h(hb_url('bookings/index.php')) ?>" class="hb-nav__link<?= ($activeNav ?? '') === 'bookings' ? ' is-active' : '' ?>"><?= hb_h(hb_t('nav.bookings')) ?></a>
            <a href="<?= hb_h(hb_url('properties/index.php')) ?>" class="hb-nav__link<?= ($activeNav ?? '') === 'properties' ? ' is-active' : '' ?>"><?= hb_h(hb_t('nav.properties')) ?></a>
            <a href="<?= hb_h(hb_url('admin/logs.php')) ?>" class="hb-nav__link<?= ($activeNav ?? '') === 'logs' ? ' is-active' : '' ?>"><?= hb_h(hb_t('nav.logs')) ?></a>
            <div class="hb-nav__meta">
                <span class="hb-nav__user"><?= hb_h(hb_session_display_name()) ?></span>
                <div class="hb-lang-switch">
                    <a href="<?= hb_h(hb_url('set_lang.php?lang=hu&return=' . rawurlencode($_SERVER['REQUEST_URI'] ?? ''))) ?>" class="hb-lang-switch__link<?= hb_current_locale() === 'hu' ? ' is-active' : '' ?>">HU</a>
                    <a href="<?= hb_h(hb_url('set_lang.php?lang=en&return=' . rawurlencode($_SERVER['REQUEST_URI'] ?? ''))) ?>" class="hb-lang-switch__link<?= hb_current_locale() === 'en' ? ' is-active' : '' ?>">EN</a>
                </div>
                <a href="<?= hb_h(hb_url('logout.php')) ?>" class="hb-nav__logout"><?= hb_h(hb_t('nav.logout')) ?></a>
            </div>
        </nav>
    </div>
    <?php if (!empty($properties) && ($showPropertySwitcher ?? true)): ?>
    <div class="hb-property-bar">
        <form method="post" action="<?= hb_h(hb_url('set_property.php')) ?>" class="hb-property-form">
            <label for="hb-property-select" class="hb-property-form__label"><?= hb_h(hb_t('property.select')) ?></label>
            <select name="property_id" id="hb-property-select" class="hb-select" onchange="this.form.submit()">
                <?php foreach ($properties as $property): ?>
                    <option value="<?= (int) $property['id'] ?>"<?= (int) ($currentProperty['id'] ?? 0) === (int) $property['id'] ? ' selected' : '' ?>>
                        <?= hb_h((string) $property['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php endif; ?>
</header>
<main class="hb-main">
