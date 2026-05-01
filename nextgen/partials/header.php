<?php
require_once __DIR__ . '/../init.php';
requireLogin();

$navZone = ng_nav_app_zone();
if ($navZone === 'events') {
    $logoHomeUrl = site_url('events/events_admin.php');
} elseif ($navZone === 'nextgen') {
    $logoHomeUrl = nextgen_url('apps.php');
} else {
    $logoHomeUrl = nextgen_url('index.php');
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h(app_backoffice_brand_line()) ?><?= isset($pageTitle) ? ' – ' . h($pageTitle) : '' ?></title>
    <?php require __DIR__ . '/../includes/favicon_head.php'; ?>
    <link rel="stylesheet" href="<?= h(nextgen_url('assets/css/style.css')) ?>">
</head>
<body>
<header class="main-header">
    <div class="header-inner">
        <div class="header-leading">
            <a href="<?= h($logoHomeUrl) ?>" class="logo" title="<?= h(app_backoffice_brand_line()) ?>"><span class="logo-site"><?= h(SITE_NAME) ?></span><span class="logo-area-sep" aria-hidden="true"> </span><span class="logo-area"><?= h(app_backoffice_area()) ?></span></a>
            <div class="header-app-launcher nav-item has-submenu">
                <span class="nav-parent-wrap">
                    <a href="<?= h(nextgen_url('apps.php')) ?>" class="nav-parent-link nav-parent-link--with-icon">
                        <span class="nav-apps-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg></span>
                        <span>Alkalmazások</span>
                    </a>
                    <button type="button" class="nav-parent-arrow" aria-expanded="false" aria-haspopup="true" data-submenu="apps-switcher" aria-label="Alkalmazások almenü">▾</button>
                </span>
                <ul class="nav-submenu" id="submenu-apps-switcher" role="menu">
                    <li role="none"><a href="<?= h(nextgen_url('index.php')) ?>" role="menuitem">Finance</a></li>
                    <li role="none"><a href="<?= h(site_url('events/events_admin.php')) ?>" role="menuitem">Event Admin</a></li>
                    <li role="none"><a href="<?= h(nextgen_url('config/cimkek.php')) ?>" role="menuitem">NextGen</a></li>
                </ul>
            </div>
        </div>
        <div class="header-trailing">
        <nav class="main-nav" id="main-nav" aria-label="Főmenü">
            <ul class="nav-list">
                <?php if ($navZone === 'finance'): ?>
                <li class="nav-item has-submenu">
                    <span class="nav-parent-wrap">
                        <a href="<?= h(nextgen_url('index.php')) ?>" class="nav-parent-link">Finance</a>
                        <button type="button" class="nav-parent-arrow" aria-expanded="false" aria-haspopup="true" data-submenu="finance-app" aria-label="Finance almenü">▾</button>
                    </span>
                    <ul class="nav-submenu" id="submenu-finance-app" role="menu">
                        <li class="nav-submenu-heading" role="presentation"><span>Dashboard</span></li>
                        <li role="none"><a href="<?= h(nextgen_url('index.php')) ?>" role="menuitem">Finance főoldal</a></li>
                        <li class="nav-submenu-heading" role="presentation"><span>Szervezők</span></li>
                        <li role="none"><a href="<?= h(nextgen_url('organizers/')) ?>" role="menuitem">Szervezők</a></li>
                        <li role="none"><a href="<?= h(nextgen_url('contacts/')) ?>" role="menuitem">Kontaktok</a></li>
                        <li class="nav-submenu-heading" role="presentation"><span>Pénzügy</span></li>
                        <li role="none"><a href="<?= h(nextgen_url('finance/szamlazando/')) ?>" role="menuitem">Számlázandó</a></li>
                        <li role="none"><a href="<?= h(nextgen_url('finance/szamlak/')) ?>" role="menuitem">Számlák</a></li>
                    </ul>
                </li>
                <?php elseif ($navZone === 'events'): ?>
                <li class="nav-item has-submenu">
                    <span class="nav-parent-wrap">
                        <a href="<?= h(site_url('events/events_admin.php')) ?>" class="nav-parent-link">Események</a>
                        <button type="button" class="nav-parent-arrow" aria-expanded="false" aria-haspopup="true" data-submenu="events-app" aria-label="Események almenü">▾</button>
                    </span>
                    <ul class="nav-submenu" id="submenu-events-app" role="menu">
                        <li role="none"><a href="<?= h(site_url('events/events_admin.php')) ?>" role="menuitem">Lista</a></li>
                        <li role="none"><a href="<?= h(site_url('events/letrehoz.php')) ?>" role="menuitem">Új esemény</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a href="<?= h(site_url('events/venues.php')) ?>" class="nav-parent-link">Helyszínek</a>
                </li>
                <li class="nav-item">
                    <a href="<?= h(site_url('events/organizers.php')) ?>" class="nav-parent-link">Szervezők</a>
                </li>
                <li class="nav-item has-submenu">
                    <span class="nav-parent-wrap">
                        <a href="<?= h(site_url('events/boritokepek.php')) ?>" class="nav-parent-link">Egyéb</a>
                        <button type="button" class="nav-parent-arrow" aria-expanded="false" aria-haspopup="true" data-submenu="events-egyeb" aria-label="Egyéb almenü">▾</button>
                    </span>
                    <ul class="nav-submenu" id="submenu-events-egyeb" role="menu">
                        <li role="none"><a href="<?= h(site_url('events/categories.php')) ?>" role="menuitem">Kategóriák</a></li>
                        <li role="none"><a href="<?= h(site_url('events/boritokepek.php')) ?>" role="menuitem">Borítóképek</a></li>
                        <li role="none"><a href="<?= h(site_url('events/import_csv.php')) ?>" role="menuitem">CSV import</a></li>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if ($navZone === 'nextgen'): ?>
                <li class="nav-item has-submenu">
                    <span class="nav-parent-wrap">
                        <a href="<?= h(nextgen_url('config/cimkek.php')) ?>" class="nav-parent-link">Config</a>
                        <button type="button" class="nav-parent-arrow" aria-expanded="false" aria-haspopup="true" data-submenu="config" aria-label="Config almenü">▾</button>
                    </span>
                    <ul class="nav-submenu" id="submenu-config" role="menu">
                        <li class="nav-submenu-heading" role="presentation"><span>Új táblázatok</span></li>
                        <li role="none"><a href="<?= h(nextgen_url('config/lanueva.php')) ?>" role="menuitem">LaNueva</a></li>
                        <li class="nav-submenu-heading" role="presentation"><span>Általános</span></li>
                        <li role="none"><a href="<?= h(nextgen_url('config/cimkek.php')) ?>" role="menuitem">Címkék</a></li>
                        <li role="none"><a href="<?= h(nextgen_url('config/kontakt_tipusok.php')) ?>" role="menuitem">Kontakt típusok</a></li>
                        <li role="none"><a href="<?= h(nextgen_url('config/levelsablonok/')) ?>" role="menuitem">Levélsablonok</a></li>
                    </ul>
                </li>
                <?php if (isLoggedIn() && isSuperadmin()): ?>
                <li class="nav-item has-submenu">
                    <span class="nav-parent-wrap">
                        <a href="<?= h(nextgen_url('admin/adminok/')) ?>" class="nav-parent-link">Admin</a>
                        <button type="button" class="nav-parent-arrow" aria-expanded="false" aria-haspopup="true" data-submenu="admin" aria-label="Almenü">▾</button>
                    </span>
                    <ul class="nav-submenu" id="submenu-admin" role="menu">
                        <li role="none"><a href="<?= h(nextgen_url('admin/adminok/')) ?>" role="menuitem">Adminok</a></li>
                        <li role="none"><a href="<?= h(nextgen_url('admin/log.php')) ?>" role="menuitem">Logok</a></li>
                        <li role="none"><a href="<?= h(nextgen_url('admin/email/')) ?>" role="menuitem">E-mail</a></li>
                        <li role="none"><a href="<?= h(nextgen_url('admin/exporter/')) ?>" role="menuitem">Exporter</a></li>
                        <li role="none"><a href="<?= h(nextgen_url('admin/exporter/connections.php')) ?>" role="menuitem">Exporter kapcsolatok</a></li>
                    </ul>
                </li>
                <?php endif; ?>
                <?php endif; ?>
                <?php if (isLoggedIn()): ?>
                <li class="nav-item"><a href="<?= h(nextgen_url('logout.php')) ?>" class="nav-link-logout">Kijelentkezés</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php if (isLoggedIn()): ?><a href="<?= h(nextgen_url('jelszo.php')) ?>" class="header-user"><?= h($_SESSION['admin_nev']) ?></a><?php endif; ?>
        <button type="button" class="nav-toggle" id="nav-toggle" aria-label="Menü" aria-expanded="false">
            <span class="icon">☰</span>
        </button>
        </div>
    </div>
</header>
<main class="<?= h($mainContentClass ?? 'main-content') ?>">
