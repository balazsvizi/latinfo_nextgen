<?php
/**
 * NextGen nyilvános landing – vendégfelület (bejelentkezés nélkül).
 * Meghívás: lanueva/index.php
 */
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/config/config.php';
}
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/landingpage_table.php';

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
        redirect((BASE_URL !== '' ? rtrim(BASE_URL, '/') : '') . '/lanueva/');
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
        redirect((BASE_URL !== '' ? rtrim(BASE_URL, '/') : '') . '/lanueva/');
    }
}

$siker_feedback = (string) (flash('landing_ok_feedback') ?? '');
$siker_notify = (string) (flash('landing_ok_notify') ?? '');

$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$scheme = $https ? 'https' : 'http';
$httpHost = $_SERVER['HTTP_HOST'] ?? '';
$ogOrigin = (BASE_URL !== '') ? rtrim(BASE_URL, '/') : (($httpHost !== '') ? $scheme . '://' . $httpHost : '');
$landingPublicUrl = (BASE_URL !== '' ? rtrim(BASE_URL, '/') : '') . '/lanueva/';
if (BASE_URL !== '') {
    $ogCanonical = $landingPublicUrl;
} elseif ($ogOrigin !== '') {
    $path = strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?') ?: '/lanueva/';
    $ogCanonical = $ogOrigin . $path;
} else {
    $ogCanonical = '';
}
$ogHasSharePng = is_file(__DIR__ . '/assets/images/og/nextgen-share.png');
$ogImageRel = $ogHasSharePng
    ? '/assets/images/og/nextgen-share.png'
    : '/assets/images/logo/logo.jpg';
$ogImage = ($ogOrigin !== '') ? $ogOrigin . $ogImageRel : '';
$ogTitle = SITE_NAME . ' – La nueva';
$ogDescription = 'Megújul a Latinfo.hu! Oszd meg az ötleteidet, vagy iratkozz fel induláskori értesítésre.';
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($ogTitle) ?></title>
    <meta name="description" content="<?= h($ogDescription) ?>">
    <?php if ($ogCanonical !== ''): ?>
    <link rel="canonical" href="<?= h($ogCanonical) ?>">
    <?php endif; ?>

    <!-- Open Graph (Facebook, LinkedIn, stb.) -->
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
    <?php if ($ogHasSharePng): ?>
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <?php endif; ?>
    <meta property="og:image:alt" content="<?= h(SITE_NAME) ?> – La nueva, visszajelzés">
    <?php endif; ?>
    <meta property="og:locale" content="hu_HU">

    <!-- Twitter / X -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= h($ogTitle) ?>">
    <meta name="twitter:description" content="<?= h($ogDescription) ?>">
    <?php if ($ogImage !== ''): ?>
    <meta name="twitter:image" content="<?= h($ogImage) ?>">
    <?php endif; ?>

    <meta name="theme-color" content="#050816">
    <link rel="stylesheet" href="<?= h(BASE_URL) ?>/assets/css/landing.css">
</head>
<body class="landing-nextgen">
    <div class="landing-bg-grid" aria-hidden="true"></div>
    <div class="landing-glow landing-glow-a" aria-hidden="true"></div>
    <div class="landing-glow landing-glow-b" aria-hidden="true"></div>

    <main class="landing-main">
        <section class="landing-hero">
            <h1 class="landing-title">Megújul a Latinfo.hu!</h1>
            <p class="landing-lead">Oszd meg, milyen legyen (és ne legyen) a weboldalunk – segíts te is alakítani.</p>
        </section>

        <div class="landing-stack">
            <!-- Visszajelzés -->
            <article class="landing-panel landing-panel-feedback">
                <div class="landing-panel-head">
                    <span class="landing-panel-icon" aria-hidden="true">◆</span>
                    <div>
                        <h2 class="landing-panel-title">Visszajelzés</h2>
                        <p class="landing-panel-sub">Mit látnál szívesen, és mit hagynál el?</p>
                    </div>
                </div>

                <?php if ($siker_feedback !== ''): ?>
                    <div class="landing-toast landing-toast-success" role="status"><?= h($siker_feedback) ?></div>
                <?php endif; ?>
                <?php if ($hiba_feedback): ?>
                    <div class="landing-toast landing-toast-error" role="alert"><?= h($hiba_feedback) ?></div>
                <?php endif; ?>

                <form method="post" class="landing-form" action="" novalidate>
                    <input type="hidden" name="landing_feedback" value="1">

                    <div class="landing-field">
                        <label for="ilyen_legyen">Ilyen legyen az új Latinfo.hu</label>
                        <textarea id="ilyen_legyen" name="ilyen_legyen" rows="4" placeholder="Funkciók, kinézet, ötletek…"><?= h($_POST['ilyen_legyen'] ?? '') ?></textarea>
                    </div>

                    <div class="landing-field">
                        <label for="ilyen_ne_legyen">Ilyen ne legyen az új Latinfo.hu</label>
                        <textarea id="ilyen_ne_legyen" name="ilyen_ne_legyen" rows="4" placeholder="Ami zavar vagy felesleges…"><?= h($_POST['ilyen_ne_legyen'] ?? '') ?></textarea>
                    </div>

                    <div class="landing-form-actions">
                        <button type="submit" class="landing-btn landing-btn-primary">Elküldöm</button>
                    </div>
                </form>
            </article>

            <!-- Értesítés – vizuálisan elkülönítve -->
            <article class="landing-panel landing-panel-notify">
                <div class="landing-panel-head landing-panel-head-notify">
                    <span class="landing-panel-icon landing-panel-icon-mail" aria-hidden="true">✉</span>
                    <div>
                        <h2 class="landing-panel-title">Értesítés induláskor</h2>
                        <p class="landing-panel-lead-notify">Add meg az e-mail címed, és értesítünk az új szolgáltatás indulásáról.</p>
                    </div>
                </div>

                <?php if ($siker_notify !== ''): ?>
                    <div class="landing-toast landing-toast-success" role="status"><?= h($siker_notify) ?></div>
                <?php endif; ?>
                <?php if ($hiba_notify): ?>
                    <div class="landing-toast landing-toast-error" role="alert"><?= h($hiba_notify) ?></div>
                <?php endif; ?>

                <form method="post" class="landing-form landing-form-notify" action="" novalidate>
                    <input type="hidden" name="landing_notify" value="1">
                    <div class="landing-field landing-field-notify-email">
                        <label for="notify_email">E-mail</label>
                        <input type="email" id="notify_email" name="notify_email" autocomplete="email" inputmode="email" placeholder="pelda@email.hu" value="<?= h($_POST['notify_email'] ?? '') ?>">
                    </div>
                    <div class="landing-form-actions">
                        <button type="submit" class="landing-btn landing-btn-notify">Feliratkozom az értesítésre</button>
                    </div>
                </form>
            </article>
        </div>
    </main>

    <footer class="landing-footer">
        <p>&copy; <?= (int) date('Y') ?> <?= h(SITE_NAME) ?></p>
    </footer>
</body>
</html>
