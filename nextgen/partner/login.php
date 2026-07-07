<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (partner_is_logged_in()) {
    redirect(partner_url('index.php'));
}

$hiba = '';
$tableReady = nextgen_partners_table_ready(getDb());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $jelszo = (string) ($_POST['jelszo'] ?? '');
    if (!$tableReady) {
        $hiba = 'A partner portál még nincs beállítva. Kérjük, vedd fel a kapcsolatot az üzemeltetővel.';
    } elseif ($email === '' || $jelszo === '') {
        $hiba = 'Kérjük, töltse ki mindkét mezőt.';
    } elseif (partner_login($email, $jelszo)) {
        $url = $_SESSION['_partner_redirect_after_login'] ?? partner_url('index.php');
        unset($_SESSION['_partner_redirect_after_login']);
        if ($url !== '' && $url[0] !== '/') {
            $url = rtrim(BASE_URL, '/') . '/' . ltrim($url, '/');
        }
        redirect($url);
    } else {
        $hiba = 'Hibás e-mail cím vagy jelszó.';
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Partner bejelentkezés – <?= h(SITE_NAME) ?></title>
    <?php require dirname(__DIR__) . '/includes/favicon_head.php'; ?>
    <link rel="stylesheet" href="<?= h(nextgen_url('assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= h(partner_url('assets/css/portal.css')) ?>">
</head>
<body class="login-page partner-login-page">
    <div class="login-box">
        <h1 class="login-brand"><span class="logo-site"><?= h(SITE_NAME) ?></span> <span class="logo-area">Partner</span></h1>
        <p class="login-sub">Partner portál – szervezők, DJ-k, üzenetek</p>
        <?php if (!$tableReady): ?>
            <p class="alert alert-warning">A partner rendszer még nincs telepítve az adatbázisban.</p>
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
