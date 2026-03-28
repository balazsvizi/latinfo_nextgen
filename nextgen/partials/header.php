<?php
require_once __DIR__ . '/../init.php';
requireLogin();
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
        <a href="<?= h(nextgen_url('index.php')) ?>" class="logo" title="<?= h(app_backoffice_brand_line()) ?>"><span class="logo-site"><?= h(SITE_NAME) ?></span><span class="logo-area-sep" aria-hidden="true"> </span><span class="logo-area"><?= h(app_backoffice_area()) ?></span></a>
        <?php if (isLoggedIn()): ?><a href="<?= h(nextgen_url('jelszo.php')) ?>" class="header-user"><?= h($_SESSION['admin_nev']) ?></a><?php endif; ?>
        <button type="button" class="nav-toggle" id="nav-toggle" aria-label="Menü" aria-expanded="false">
            <span class="icon">☰</span>
        </button>
        <nav class="main-nav" id="main-nav" aria-label="Főmenü">
            <ul class="nav-list">
                <li class="nav-item has-submenu">
                    <span class="nav-parent-wrap">
                        <a href="<?= h(nextgen_url('organizers/')) ?>" class="nav-parent-link">Szervezők</a>
                        <button type="button" class="nav-parent-arrow" aria-expanded="false" aria-haspopup="true" data-submenu="szervezo" aria-label="Almenü">▾</button>
                    </span>
                    <ul class="nav-submenu" id="submenu-szervezo" role="menu">
                        <li role="none"><a href="<?= h(nextgen_url('organizers/')) ?>" role="menuitem">Szervezők</a></li>
                        <li role="none"><a href="<?= h(nextgen_url('contacts/')) ?>" role="menuitem">Kontaktok</a></li>
                    </ul>
                </li>
                <li class="nav-item has-submenu">
                    <span class="nav-parent-wrap">
                        <a href="<?= h(nextgen_url('finance/szamlazando/')) ?>" class="nav-parent-link">Finance</a>
                        <button type="button" class="nav-parent-arrow" aria-expanded="false" aria-haspopup="true" data-submenu="finance" aria-label="Almenü">▾</button>
                    </span>
                    <ul class="nav-submenu" id="submenu-finance" role="menu">
                        <li role="none"><a href="<?= h(nextgen_url('finance/szamlazando/')) ?>" role="menuitem">Számlázandó</a></li>
                        <li role="none"><a href="<?= h(nextgen_url('finance/szamlak/')) ?>" role="menuitem">Számlák</a></li>
                    </ul>
                </li>
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
                <?php if (isLoggedIn()): ?>
                <li class="nav-item"><a href="<?= h(nextgen_url('logout.php')) ?>" class="nav-link-logout">Kijelentkezés</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>
<main class="main-content">
