<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    redirect(BASE_URL . '/index.php');
}

$hiba = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fh = trim($_POST['felhasznalonev'] ?? '');
    $jelszo = $_POST['jelszo'] ?? '';
    if ($fh === '' || $jelszo === '') {
        $hiba = 'Kérjük, töltse ki mindkét mezőt.';
    } elseif (login($fh, $jelszo)) {
        $url = $_SESSION['_redirect_after_login'] ?? BASE_URL . '/index.php';
        unset($_SESSION['_redirect_after_login']);
        if ($url !== '' && $url[0] !== '/') {
            $url = rtrim(BASE_URL, '/') . '/' . ltrim($url, '/');
        }
        redirect($url);
    } else {
        $hiba = 'Hibás felhasználónév vagy jelszó.';
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bejelentkezés – <?= h(app_backoffice_brand_line()) ?></title>
    <link rel="stylesheet" href="<?= h(BASE_URL) ?>/assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-box">
        <h1 class="login-brand"><span class="logo-site"><?= h(SITE_NAME) ?></span> <span class="logo-area"><?= h(app_backoffice_area()) ?></span></h1>
        <p class="login-sub">Bejelentkezés</p>
        <?php if ($hiba): ?>
            <p class="error"><?= h($hiba) ?></p>
        <?php endif; ?>
        <form method="post" action="">
            <label>Felhasználónév</label>
            <input type="text" name="felhasznalonev" value="<?= h($_POST['felhasznalonev'] ?? '') ?>" required autofocus>
            <label>Jelszó</label>
            <input type="password" name="jelszo" required>
            <button type="submit">Bejelentkezés</button>
        </form>
    </div>
</body>
</html>
