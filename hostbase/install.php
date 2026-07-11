<?php
declare(strict_types=1);

/**
 * HostBase telepítő – egyszeri futtatás: /hostbase/install.php
 * Éles környezetben töröld vagy védd jelszóval!
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/SchemaInstaller.php';

$done = false;
$error = '';
/** @var list<string> $info */
$info = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $info = HbSchemaInstaller::install(hb_get_db());
        $done = true;
    } catch (Throwable $ex) {
        $error = 'Telepítési hiba. Ellenőrizd a naplót.';
        error_log('HostBase install: ' . $ex->getMessage());
    }
}

?><!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>HostBase telepítés</title>
    <link rel="stylesheet" href="<?= hb_h(hb_asset_url('css/app.css')) ?>">
</head>
<body class="auth-page">
<div class="auth-card">
    <h1 class="auth-brand">HostBase</h1>
    <p class="auth-sub">Adatbázis telepítő</p>

    <?php if ($error !== ''): ?>
        <p class="alert alert-error"><?= hb_h($error) ?></p>
    <?php endif; ?>

    <?php if ($done): ?>
        <p class="alert alert-success">Telepítés kész.</p>
        <?php foreach ($info as $line): ?>
            <p class="help"><?= hb_h($line) ?></p>
        <?php endforeach; ?>
        <p><a class="btn btn-primary" href="<?= hb_h(hb_url('login.php')) ?>">Bejelentkezés</a></p>
    <?php else: ?>
        <p class="help">Létrehozza a hb_ táblákat és a kezdeti adatokat (Vizi Balázs).</p>
        <form method="post">
            <button type="submit" class="btn btn-primary btn-block">Telepítés indítása</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
