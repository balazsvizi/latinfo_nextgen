<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (organizers_portal_is_logged_in()) {
    redirect(organizers_portal_url('index.php'));
}

$hiba = '';
$tableReady = events_organizer_accounts_table_ready(getDb());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $jelszo = (string) ($_POST['jelszo'] ?? '');
    if (!$tableReady) {
        $hiba = 'A szervezői portál még nincs beállítva. Kérjük, vedd fel a kapcsolatot az üzemeltetővel.';
    } elseif ($email === '' || $jelszo === '') {
        $hiba = 'Kérjük, töltse ki mindkét mezőt.';
    } elseif (organizers_portal_login($email, $jelszo)) {
        $url = $_SESSION['_organizers_portal_redirect_after_login'] ?? organizers_portal_url('index.php');
        unset($_SESSION['_organizers_portal_redirect_after_login']);
        if ($url !== '' && $url[0] !== '/') {
            $url = rtrim(BASE_URL, '/') . '/' . ltrim($url, '/');
        }
        redirect($url);
    } else {
        $hiba = 'Hibás e-mail cím vagy jelszó.';
    }
}

$loginBrand = trim(SITE_NAME . ' Szervező');
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bejelentkezés – <?= h($loginBrand) ?></title>
    <?php require dirname(__DIR__, 2) . '/includes/favicon_head.php'; ?>
    <link rel="stylesheet" href="<?= h(nextgen_url('assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= h(organizers_portal_url('assets/css/portal.css')) ?>">
</head>
<body class="login-page organizers-portal-login-page">
    <div class="login-box">
        <h1 class="login-brand"><span class="logo-site"><?= h(SITE_NAME) ?></span> <span class="logo-area">Szervező</span></h1>
        <p class="login-sub">Szervezői portál – eseményeid és statisztikáid</p>
        <?php if (!$tableReady): ?>
            <p class="alert alert-warning">A portál fiók rendszer még nincs telepítve az adatbázisban.</p>
        <?php endif; ?>
        <?php if ($hiba !== ''): ?>
            <p class="error"><?= h($hiba) ?></p>
        <?php endif; ?>
        <form method="post" action="">
            <label for="email">E-mail cím</label>
            <input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required autofocus autocomplete="username">
            <label for="jelszo">Jelszó</label>
            <input type="password" id="jelszo" name="jelszo" required autocomplete="current-password">
            <button type="submit">Bejelentkezés</button>
        </form>
        <p class="login-back-home"><a href="<?= h(events_public_home_path()) ?>">← Naptár</a></p>
    </div>
</body>
</html>
