<?php
declare(strict_types=1);
/** @var string $authTitle */
?>
<!DOCTYPE html>
<html lang="<?= hb_h(hb_current_locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= hb_h(HB_APP_NAME) ?> – <?= hb_h($authTitle ?? hb_t('login.title')) ?></title>
    <link rel="stylesheet" href="<?= hb_h(hb_asset_url('css/app.css')) ?>">
</head>
<body class="auth-page">
