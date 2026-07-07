<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h(trim(SITE_NAME . ' Szervező')) ?><?= isset($pageTitle) ? ' – ' . h($pageTitle) : '' ?></title>
    <?php require dirname(__DIR__, 2) . '/includes/favicon_head.php'; ?>
    <link rel="stylesheet" href="<?= h(nextgen_url('assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= h(organizers_portal_url('assets/css/portal.css')) ?>">
    <?php if (!empty($extraHead)) {
        echo $extraHead;
    } ?>
</head>
<body class="szervezo-portal-body">
<header class="szervezo-header">
    <div class="szervezo-header__inner">
        <a href="<?= h(organizers_portal_url('index.php')) ?>" class="szervezo-header__brand" title="<?= h(SITE_NAME) ?> Szervező">
            <span class="szervezo-header__site"><?= h(SITE_NAME) ?></span>
            <span class="szervezo-header__area">Szervező</span>
        </a>
        <nav class="szervezo-header__nav" aria-label="Szervezői menü">
            <a href="<?= h(organizers_portal_url('index.php')) ?>" class="szervezo-header__link<?= ($activeNav ?? '') === 'home' ? ' is-active' : '' ?>">Kezdőlap</a>
            <a href="<?= h(organizers_portal_url('jelszo.php')) ?>" class="szervezo-header__link<?= ($activeNav ?? '') === 'password' ? ' is-active' : '' ?>">Jelszó</a>
            <span class="szervezo-header__user"><?= h(organizers_portal_session_display_name()) ?></span>
            <a href="<?= h(organizers_portal_url('logout.php')) ?>" class="szervezo-header__link szervezo-header__logout">Kijelentkezés</a>
        </nav>
    </div>
</header>
<main class="main-content szervezo-main">
