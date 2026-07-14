<?php
/**
 * NextGen nyilvános landing – vendégfelület (bejelentkezés nélkül).
 * Meghívás: lanueva/index.php
 */
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../nextgen/core/config.php';
}
require_once __DIR__ . '/../nextgen/core/database.php';
require_once __DIR__ . '/../nextgen/includes/auth.php';
require_once __DIR__ . '/../nextgen/includes/landingpage_table.php';

$db = getDb();

function landing_client_meta(): array {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    if (is_string($ip) && strlen($ip) > 45) {
        $ip = substr($ip, 0, 45);
    }
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    if (is_string($ua) && strlen($ua) > 512) {
        $ua = substr($ua, 0, 512);
    }
    return [$ip, $ua];
}

$hiba_feedback = '';

ensure_landingpage_table($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['landing_feedback'])) {
    $ilyen = trim($_POST['ilyen_legyen'] ?? '');
    $ne = trim($_POST['ilyen_ne_legyen'] ?? '');
    $nev = trim($_POST['nev'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefon = trim($_POST['telefon'] ?? '');

    if ($ilyen === '' && $ne === '') {
        $hiba_feedback = 'Írd meg legalább röviden, hogyan tetszik a megújult naptár, vagy mit javítanál.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $hiba_feedback = 'Érvénytelen e-mail cím.';
    } else {
        [$ip, $ua] = landing_client_meta();
        $stmt = $db->prepare('INSERT INTO nextgen_landing_feedback (ilyen_legyen, ilyen_ne_legyen, email, nev, telefon, ip, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $ilyen !== '' ? $ilyen : null,
            $ne !== '' ? $ne : null,
            $email !== '' ? $email : null,
            $nev !== '' ? $nev : null,
            $telefon !== '' ? $telefon : null,
            $ip,
            $ua,
        ]);
        flash('landing_ok_feedback', 'Köszönjük! Megkaptuk a naptárral kapcsolatos visszajelzésed.');
        redirect(site_url('lanueva/'));
    }
}

$siker_feedback = (string) (flash('landing_ok_feedback') ?? '');

$naptarUrl = rtrim(site_url(EVENTS_HOME_PATH . '/'), '/') . '/';

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (!$https) {
    $xfp = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    if ($xfp !== '') {
        $https = str_contains($xfp, 'https');
    } elseif (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
        $https = true;
    }
}
$scheme = $https ? 'https' : 'http';
$httpHost = $_SERVER['HTTP_HOST'] ?? '';
$publicOrigin = ($httpHost !== '') ? $scheme . '://' . $httpHost : '';
$landingPublicPath = site_url('lanueva/');
$ogCanonical = $publicOrigin !== '' ? ($publicOrigin . $landingPublicPath) : '';

$ogCandidates = [
    __DIR__ . '/assets/images/og/lanueva-naptar.png',
    __DIR__ . '/assets/images/og/lanueva-fb2.png',
    __DIR__ . '/assets/images/og/lanueva-fb.png',
    __DIR__ . '/assets/images/og/nextgen-share.png',
    __DIR__ . '/assets/images/logo/latinfo_black.png',
    __DIR__ . '/assets/images/logo/logo.jpg',
];
$ogImageFs = '';
foreach ($ogCandidates as $candidate) {
    if (is_file($candidate)) {
        $ogImageFs = $candidate;
        break;
    }
}
$ogImageRel = '';
if ($ogImageFs !== '') {
    $fromLanueva = substr($ogImageFs, strlen(rtrim(__DIR__, '/\\')) + 1);
    $ogImageRel = 'lanueva/' . str_replace('\\', '/', $fromLanueva);
}
$ogImagePath = $ogImageRel !== '' ? site_url($ogImageRel) : '';
$ogImage = $publicOrigin !== '' && $ogImagePath !== '' ? ($publicOrigin . $ogImagePath) : '';
$ogImageDims = ($ogImageFs !== '' && is_readable($ogImageFs)) ? @getimagesize($ogImageFs) : false;
$ogImageW = is_array($ogImageDims) ? (int) $ogImageDims[0] : 0;
$ogImageH = is_array($ogImageDims) ? (int) $ogImageDims[1] : 0;

$ogTitle = SITE_NAME . ' – Megújult naptár';
$ogDescription = 'Megújult a Latinfo.hu naptár! Nézd meg, és írd meg, hogyan tetszik – segíts finomhangolni!';

$heroImagePath = site_url('lanueva/assets/images/og/lanueva-naptar.png');
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($ogTitle) ?></title>
    <meta name="description" content="<?= h($ogDescription) ?>">
    <?php if ($ogCanonical !== ''): ?>
    <link rel="canonical" href="<?= h($ogCanonical) ?>">
    <?php endif; ?>

    <meta name="theme-color" content="#ff1654">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= h(SITE_NAME) ?>">
    <meta property="og:title" content="<?= h($ogTitle) ?>">
    <meta property="og:description" content="<?= h($ogDescription) ?>">
    <?php if ($ogCanonical !== ''): ?>
    <meta property="og:url" content="<?= h($ogCanonical) ?>">
    <?php endif; ?>
    <?php if ($ogImage !== ''): ?>
    <meta property="og:image" content="<?= h($ogImage) ?>">
    <meta property="og:image:secure_url" content="<?= h($ogImage) ?>">
    <?php if ($ogImageW > 0 && $ogImageH > 0): ?>
    <meta property="og:image:width" content="<?= (int) $ogImageW ?>">
    <meta property="og:image:height" content="<?= (int) $ogImageH ?>">
    <?php endif; ?>
    <meta property="og:image:alt" content="<?= h(SITE_NAME) ?> – megújult naptár, visszajelzés">
    <?php endif; ?>
    <meta property="og:locale" content="hu_HU">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= h($ogTitle) ?>">
    <meta name="twitter:description" content="<?= h($ogDescription) ?>">
    <?php if ($ogImage !== ''): ?>
    <meta name="twitter:image" content="<?= h($ogImage) ?>">
    <?php endif; ?>

    <!-- Google Analytics 4 (GA4) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-RCTY9NEJRJ"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-RCTY9NEJRJ');
    </script>
    <?php require __DIR__ . '/../nextgen/includes/favicon_head.php'; ?>
    <link rel="stylesheet" href="<?= h(site_url('lanueva/assets/css/landing.css')) ?>">
</head>
<body class="ln-modern">
    <div class="ln-glow ln-glow-1" aria-hidden="true"></div>
    <div class="ln-glow ln-glow-2" aria-hidden="true"></div>

    <header class="ln-header">
        <div class="ln-header-logo">
            <span class="ln-logo-text"><?= h(SITE_NAME) ?></span>
            <span class="ln-logo-sub">La nueva</span>
        </div>
    </header>

    <main class="ln-container">
        <section class="ln-hero">
            <h1 class="ln-hero-title">
                <span class="ln-title-word-1">Megújult</span>
                <span class="ln-title-word-2">a naptár!</span>
            </h1>
            <p class="ln-hero-subtitle">Friss kinézet, könnyebb böngészés – nézd meg a Latinfo.hu eseménynaptárat, és írd meg, hogyan tetszik.</p>
            <p class="ln-hero-cta">
                <a href="<?= h($naptarUrl) ?>" class="ln-btn ln-btn-primary ln-btn-hero">Megnézem a naptárat</a>
            </p>
        </section>

        <article class="ln-card ln-card-feedback ln-card-main">
            <div class="ln-card-icon">📅</div>
            <h2 class="ln-card-title">Hogyan tetszik?</h2>
            <p class="ln-card-desc">Próbáld ki az új naptárat, majd írd meg a tapasztalataidat – mi működik jól, és min javítanál?</p>

            <?php if ($siker_feedback !== ''): ?>
                <div class="ln-toast ln-toast-success" role="status"><?= h($siker_feedback) ?></div>
            <?php endif; ?>
            <?php if ($hiba_feedback): ?>
                <div class="ln-toast ln-toast-error" role="alert"><?= h($hiba_feedback) ?></div>
            <?php endif; ?>

            <form method="post" action="" novalidate class="ln-form">
                <input type="hidden" name="landing_feedback" value="1">
                <textarea name="ilyen_legyen" placeholder="Mi tetszik? Pl. kinézet, szűrés, mobilnézet…" rows="4"><?= h($_POST['ilyen_legyen'] ?? '') ?></textarea>
                <textarea name="ilyen_ne_legyen" placeholder="Mit javítanál? Pl. hiányzó funkció, zavaró részlet…" rows="4"><?= h($_POST['ilyen_ne_legyen'] ?? '') ?></textarea>

                <div class="ln-form-contact">
                    <p class="ln-form-contact-lead">Segítenél további kérdések megválaszolásával? Add meg az elérhetőségedet és felvesszük veled a kapcsolatot!</p>
                    <div class="ln-form-contact-fields">
                        <input type="text" name="nev" maxlength="255" placeholder="Név" value="<?= h($_POST['nev'] ?? '') ?>" autocomplete="name">
                        <input type="email" name="email" maxlength="255" placeholder="E-mail" value="<?= h($_POST['email'] ?? '') ?>" autocomplete="email">
                        <input type="tel" name="telefon" maxlength="50" placeholder="Telefon" value="<?= h($_POST['telefon'] ?? '') ?>" autocomplete="tel">
                    </div>
                </div>

                <button type="submit" class="ln-btn ln-btn-primary">Elküldöm a visszajelzést</button>
            </form>
        </article>

        <section class="ln-hero-image-section">
            <img src="<?= h($heroImagePath) ?>" alt="Megújult a Latinfo.hu naptár – neon naptár illusztráció" loading="lazy" width="1536" height="1024">
        </section>
    </main>

    <footer class="ln-footer">
        <p>&copy; <?= (int) date('Y') ?> <?= h(SITE_NAME) ?></p>
    </footer>
</body>
</html>
