<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h(trim(SITE_NAME . ' Partnerportál')) ?><?= isset($pageTitle) ? ' – ' . h($pageTitle) : '' ?></title>
    <?php require dirname(__DIR__, 2) . '/includes/favicon_head.php'; ?>
    <link rel="stylesheet" href="<?= h(nextgen_url('assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= h(partner_url('assets/css/portal.css')) ?>">
    <?php if (!empty($extraHead)) {
        echo $extraHead;
    } ?>
</head>
<body class="partner-portal-body">
<header class="partner-header">
    <div class="partner-header__inner">
        <a href="<?= h(partner_url('index.php')) ?>" class="partner-header__brand">
            <span class="partner-header__site"><?= h(SITE_NAME) ?></span>
            <span class="partner-header__area">Partnerportál</span>
        </a>
        <nav class="partner-header__nav" aria-label="Partner menü">
            <a href="<?= h(partner_url('index.php')) ?>" class="partner-header__link<?= ($activeNav ?? '') === 'home' ? ' is-active' : '' ?>">Kezdőlap</a>
            <span class="partner-header__user"><?= h(partner_session_display_name()) ?></span>
            <a href="<?= h(partner_url('logout.php')) ?>" class="partner-header__link partner-header__logout">Kijelentkezés</a>
        </nav>
    </div>
</header>
<main class="main-content partner-main">
