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
$hiba_notify = '';

ensure_landingpage_table($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['landing_feedback'])) {
    $ilyen = trim($_POST['ilyen_legyen'] ?? '');
    $ne = trim($_POST['ilyen_ne_legyen'] ?? '');
    if ($ilyen === '' && $ne === '') {
        $hiba_feedback = 'Írj legalább az egyik mezőbe: mit szeretnél, vagy mit ne.';
    } else {
        [$ip, $ua] = landing_client_meta();
        $stmt = $db->prepare('INSERT INTO landingpage (ilyen_legyen, ilyen_ne_legyen, email, ip, user_agent) VALUES (?, ?, NULL, ?, ?)');
        $stmt->execute([
            $ilyen !== '' ? $ilyen : null,
            $ne !== '' ? $ne : null,
            $ip,
            $ua,
        ]);
        flash('landing_ok_feedback', 'Köszönjük a visszajelzést!');
        redirect(site_url('lanueva/'));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['landing_notify'])) {
    $email = trim($_POST['notify_email'] ?? '');
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $hiba_notify = 'Adj meg egy érvényes e-mail címet az értesítéshez.';
    } else {
        [$ip, $ua] = landing_client_meta();
        $stmt = $db->prepare('INSERT INTO landingpage (ilyen_legyen, ilyen_ne_legyen, email, ip, user_agent) VALUES (NULL, NULL, ?, ?, ?)');
        $stmt->execute([$email, $ip, $ua]);
        flash('landing_ok_notify', 'Köszönjük! Az e-mail címed elmentve – induláskor értesítünk.');
        redirect(site_url('lanueva/'));
    }
}

$siker_feedback = (string) (flash('landing_ok_feedback') ?? '');
$siker_notify = (string) (flash('landing_ok_notify') ?? '');

$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$scheme = $https ? 'https' : 'http';
$httpHost = $_SERVER['HTTP_HOST'] ?? '';
$publicOrigin = ($httpHost !== '') ? $scheme . '://' . $httpHost : '';
$landingPublicPath = site_url('lanueva/');
$ogCanonical = $publicOrigin !== '' ? ($publicOrigin . $landingPublicPath) : '';

$ogCandidates = [
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

$ogTitle = SITE_NAME . ' – La nueva';
$ogDescription = 'Megújul a Latinfo.hu! Mond el, milyen legyen a weboldalunk – segíts te is alakítani.';
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
    <meta property="og:image:alt" content="<?= h(SITE_NAME) ?> – La nueva, visszajelzés">
    <?php endif; ?>
    <meta property="og:locale" content="hu_HU">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= h($ogTitle) ?>">
    <meta name="twitter:description" content="<?= h($ogDescription) ?>">
    <?php if ($ogImage !== ''): ?>
    <meta name="twitter:image" content="<?= h($ogImage) ?>">
    <?php endif; ?>

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
                <span class="ln-title-word-1">Megújul</span>
                <span class="ln-title-word-2">a Latinfo.hu!</span>
            </h1>
            <p class="ln-hero-subtitle">Mond el, milyen legyen a weboldalunk – segíts te is alakítani.</p>
        </section>

        <div class="ln-cards">
            <article class="ln-card ln-card-feedback">
                <div class="ln-card-icon">🎺</div>
                <h2 class="ln-card-title">Visszajelzés</h2>
                <p class="ln-card-desc">Mit látnál szívesen, és mit hagynál el?</p>

                <?php if ($siker_feedback !== ''): ?>
                    <div class="ln-toast ln-toast-success" role="status"><?= h($siker_feedback) ?></div>
                <?php endif; ?>
                <?php if ($hiba_feedback): ?>
                    <div class="ln-toast ln-toast-error" role="alert"><?= h($hiba_feedback) ?></div>
                <?php endif; ?>

                <form method="post" action="" novalidate class="ln-form">
                    <input type="hidden" name="landing_feedback" value="1">
                    <textarea name="ilyen_legyen" placeholder="Funkciók, kinézet, ötletek…" rows="4"><?= h($_POST['ilyen_legyen'] ?? '') ?></textarea>
                    <textarea name="ilyen_ne_legyen" placeholder="Ami zavar vagy felesleges…" rows="4"><?= h($_POST['ilyen_ne_legyen'] ?? '') ?></textarea>
                    <button type="submit" class="ln-btn ln-btn-primary">Elküldöm</button>
                </form>
            </article>

            <article class="ln-card ln-card-notify">
                <div class="ln-card-icon">✉</div>
                <h2 class="ln-card-title">Értesítés induláskor</h2>
                <p class="ln-card-desc">Add meg az e-mail címed, és értesítünk az új szolgáltatás indulásáról.</p>

                <?php if ($siker_notify !== ''): ?>
                    <div class="ln-toast ln-toast-success" role="status"><?= h($siker_notify) ?></div>
                <?php endif; ?>
                <?php if ($hiba_notify): ?>
                    <div class="ln-toast ln-toast-error" role="alert"><?= h($hiba_notify) ?></div>
                <?php endif; ?>

                <form method="post" action="" novalidate class="ln-form">
                    <input type="hidden" name="landing_notify" value="1">
                    <input type="email" name="notify_email" placeholder="pelda@email.hu" value="<?= h($_POST['notify_email'] ?? '') ?>">
                    <button type="submit" class="ln-btn ln-btn-secondary">Feliratkozom</button>
                </form>
            </article>
        </div>

        <section class="ln-hero-image-section">
            <img src="<?= h(site_url('lanueva/assets/images/og/lanueva-fb.png')) ?>" alt="Salsa – Latin energia" loading="lazy">
        </section>
    </main>

    <footer class="ln-footer">
        <p>&copy; <?= (int) date('Y') ?> <?= h(SITE_NAME) ?></p>
    </footer>
</body>
</html>
