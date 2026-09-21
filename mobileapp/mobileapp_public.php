<?php
declare(strict_types=1);

/**
 * Nyilvános mobilapp oldal – telepítés + info + visszajelzés.
 * Meghívás: mobileapp/index.php  →  /mobileapp/
 */
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../nextgen/core/config.php';
}
require_once __DIR__ . '/../nextgen/core/database.php';
require_once __DIR__ . '/../nextgen/includes/auth.php';
require_once __DIR__ . '/../nextgen/includes/landingpage_table.php';
require_once __DIR__ . '/../nextgen/includes/functions.php';

$db = getDb();
$hiba_feedback = '';

ensure_landingpage_table($db);

$forrasReturn = landing_feedback_resolve_forras(
    $_SERVER['REQUEST_METHOD'] === 'POST'
        ? (string) ($_POST['forras_return'] ?? '')
        : null
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['landing_feedback'])) {
    $ilyen = trim((string) ($_POST['ilyen_legyen'] ?? ''));
    $ne = trim((string) ($_POST['ilyen_ne_legyen'] ?? ''));
    $egyeb = trim((string) ($_POST['egyeb_uzenet'] ?? ''));
    $nev = trim((string) ($_POST['nev'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $telefon = trim((string) ($_POST['telefon'] ?? ''));
    $eszkoz = trim((string) ($_POST['eszkoz'] ?? ''));
    $forrasReturn = landing_feedback_resolve_forras((string) ($_POST['forras_return'] ?? ''));

    if ($ilyen === '' && $ne === '' && $egyeb === '') {
        $hiba_feedback = 'Írd meg legalább röviden, mi tetszik az appban, mit javítanál, vagy az egyéb üzenetet.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $hiba_feedback = 'Érvénytelen e-mail cím.';
    } elseif (function_exists('mb_strlen') && mb_strlen($eszkoz, 'UTF-8') > 120) {
        $hiba_feedback = 'A telefon típusa legfeljebb 120 karakter lehet.';
    } elseif (!function_exists('mb_strlen') && strlen($eszkoz) > 120) {
        $hiba_feedback = 'A telefon típusa legfeljebb 120 karakter lehet.';
    } else {
        [$ip, $ua] = landing_client_meta();
        $payload = [
            'ilyen_legyen' => $ilyen,
            'ilyen_ne_legyen' => $ne,
            'egyeb_uzenet' => $egyeb,
            'email' => $email,
            'nev' => $nev,
            'telefon' => $telefon,
            'eszkoz' => $eszkoz,
            'forras' => 'mobileapp',
            'ip' => $ip,
            'user_agent' => $ua,
            'context' => 'mobilapp oldal',
        ];
        landing_feedback_insert($db, $payload);

        try {
            landing_feedback_send_mail($payload);
        } catch (Throwable $ex) {
            error_log('mobileapp feedback mail: ' . $ex->getMessage());
        }

        $homeUrlForFlash = (defined('LATINFO_PUBLIC_HOME_URL') && is_string(LATINFO_PUBLIC_HOME_URL) && LATINFO_PUBLIC_HOME_URL !== '')
            ? LATINFO_PUBLIC_HOME_URL
            : site_url('/');
        $visszaUrlFlash = landing_feedback_safe_return_url($forrasReturn, $homeUrlForFlash);
        unset($_SESSION['landing_feedback_forras']);
        flash('landing_ok_feedback', 'Köszönjük! Megkaptuk a mobilapp visszajelzésed.');
        flash('landing_feedback_vissza', $visszaUrlFlash);
        redirect(site_url('mobileapp/'));
    }
}

$siker_feedback = (string) (flash('landing_ok_feedback') ?? '');
$visszaFlashed = flash('landing_feedback_vissza');

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
$httpHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
$publicOrigin = ($httpHost !== '') ? $scheme . '://' . $httpHost : '';
$publicPath = site_url('mobileapp/');
$ogCanonical = $publicOrigin !== '' ? ($publicOrigin . $publicPath) : '';

$homeUrl = (defined('LATINFO_PUBLIC_HOME_URL') && is_string(LATINFO_PUBLIC_HOME_URL) && LATINFO_PUBLIC_HOME_URL !== '')
    ? LATINFO_PUBLIC_HOME_URL
    : site_url('/');
$visszaUrl = landing_feedback_safe_return_url(
    is_string($visszaFlashed) ? $visszaFlashed : ($forrasReturn ?? null),
    $homeUrl
);
$eventsHome = defined('EVENTS_HOME_PATH') ? EVENTS_HOME_PATH : 'events';
$naptarUrl = rtrim(site_url($eventsHome . '/'), '/') . '/';
$manifestUrl = site_url('mobileapp/manifest.php');
$swUrl = site_url('mobileapp/sw.js');
$iconUrl = site_url('mobileapp/assets/icons/icon.svg');

$pageTitle = SITE_NAME . ' – Mobilapp';
$pageDescription = 'Telepítsd a Latinfo mobilalkalmazást a telefonodra: eseménynaptár egy koppintással, visszajelzéssel.';
$cssUrl = site_url('mobileapp/assets/css/mobileapp.css') . '?v=' . rawurlencode(
    defined('APP_VERSION') ? (string) APP_VERSION : '1'
);
$jsUrl = site_url('mobileapp/assets/js/install.js') . '?v=' . rawurlencode(
    defined('APP_VERSION') ? (string) APP_VERSION : '1'
);
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?></title>
    <meta name="description" content="<?= h($pageDescription) ?>">
    <?php if ($ogCanonical !== ''): ?>
    <link rel="canonical" href="<?= h($ogCanonical) ?>">
    <?php endif; ?>
    <meta name="theme-color" content="#6d8f63">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="<?= h(SITE_NAME) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= h(SITE_NAME) ?>">
    <meta property="og:title" content="<?= h($pageTitle) ?>">
    <meta property="og:description" content="<?= h($pageDescription) ?>">
    <?php if ($ogCanonical !== ''): ?>
    <meta property="og:url" content="<?= h($ogCanonical) ?>">
    <?php endif; ?>
    <meta property="og:locale" content="hu_HU">
    <link rel="manifest" href="<?= h($manifestUrl) ?>">
    <?php require __DIR__ . '/../nextgen/includes/favicon_head.php'; ?>
    <link rel="stylesheet" href="<?= h($cssUrl) ?>">
</head>
<body class="ma-page">
    <header class="ma-header">
        <a class="ma-brand" href="<?= h($homeUrl) ?>"><?= h(SITE_NAME) ?></a>
        <a class="ma-header-link" href="<?= h($naptarUrl) ?>">Naptár</a>
    </header>

    <main class="ma-main">
        <section class="ma-hero">
            <img class="ma-hero-icon" src="<?= h($iconUrl) ?>" width="72" height="72" alt="">
            <h1 class="ma-title">Latinfo mobilapp</h1>
            <p class="ma-lead">A latin táncos eseménynaptár a kezdőképernyődről – gyors, egyszerű, mindig kéznél.</p>
        </section>

        <article class="ma-card ma-card-install">
            <h2 class="ma-card-title">Telepítés a telefonra</h2>
            <p class="ma-card-desc">Ez egy telepíthető webalkalmazás (PWA): a telefonodon külön ikonként jelenik meg, és teljes képernyőn fut – mint egy „igazi” app.</p>

            <div class="ma-actions">
                <button type="button" class="ma-btn" id="ma-install-btn" hidden>Telepítés a telefonra</button>
                <a class="ma-btn ma-btn-secondary" href="<?= h($homeUrl) ?>?source=mobileapp">Megnyitom a főoldalt</a>
            </div>

            <p class="ma-install-status" id="ma-install-status" role="status" hidden></p>

            <ol class="ma-howto">
                <li><strong>Android (Chrome):</strong> nyisd meg ezt az oldalt telefonon, majd koppints a <em>Telepítés</em> gombra – vagy a menüben válaszd: „Alkalmazás telepítése” / „Hozzáadás a kezdőképernyőhöz”.</li>
                <li><strong>iPhone (Safari):</strong> Megosztás → <em>Hozzáadás a Főképernyőhöz</em> → Hozzáadás.</li>
                <li>Ezután a Latinfo ikonról indíthatod – a főoldal azonnal megnyílik.</li>
            </ol>
        </article>

        <article class="ma-card">
            <h2 class="ma-card-title">Mit tud az app?</h2>
            <ul class="ma-features">
                <li>A Latinfo főoldal egy koppintással a kezdőképernyőről</li>
                <li>Teljes képernyős nézet, böngészősáv nélkül</li>
                <li>Ugyanaz a friss tartalom, mint a latinfo.hu-n</li>
                <li>Nincs App Store / Play Store várakozás – azonnal telepíthető</li>
            </ul>
        </article>

        <article class="ma-card">
            <?php if ($siker_feedback !== ''): ?>
                <div class="ma-success">
                    <div class="ma-toast ma-toast--ok ma-toast--alone" role="status"><?= h($siker_feedback) ?></div>
                    <a class="ma-btn" href="<?= h($visszaUrl) ?>">Vissza</a>
                </div>
            <?php else: ?>
                <h2 class="ma-card-title">Visszajelzés az appról</h2>
                <p class="ma-card-desc">Próbáld ki a telepítést, majd írd meg a tapasztalataidat – különösen hasznos, ha megadod a telefonod típusát is.</p>

                <?php if ($hiba_feedback !== ''): ?>
                    <div class="ma-toast ma-toast--err" role="alert"><?= h($hiba_feedback) ?></div>
                <?php endif; ?>

                <form method="post" action="" novalidate class="ma-form">
                    <input type="hidden" name="landing_feedback" value="1">
                    <input type="hidden" name="forras_return" value="<?= h((string) ($forrasReturn ?? '')) ?>">

                    <label class="ma-label" for="ma-ilyen">Mi tetszik?</label>
                    <textarea id="ma-ilyen" name="ilyen_legyen" rows="4" placeholder="Pl. telepítés, naptár, gyorsaság…"><?= h($_POST['ilyen_legyen'] ?? '') ?></textarea>

                    <label class="ma-label" for="ma-ne">Mit javítanál?</label>
                    <textarea id="ma-ne" name="ilyen_ne_legyen" rows="4" placeholder="Pl. hiányzó funkció, hiba egy konkrét telefonon…"><?= h($_POST['ilyen_ne_legyen'] ?? '') ?></textarea>

                    <label class="ma-label" for="ma-egyeb">Egyéb üzenet</label>
                    <textarea id="ma-egyeb" name="egyeb_uzenet" rows="3" placeholder="Bármi, ami a fentiekbe nem fér bele…"><?= h($_POST['egyeb_uzenet'] ?? '') ?></textarea>

                    <label class="ma-label" for="ma-eszkoz">Mobiltelefon típusa</label>
                    <input type="text" id="ma-eszkoz" name="eszkoz" maxlength="120" placeholder="Pl. Samsung Galaxy A54, iPhone 14…" value="<?= h($_POST['eszkoz'] ?? '') ?>" autocomplete="off">

                    <div class="ma-contact">
                        <p class="ma-contact-lead">Opcionális: ha szeretnéd, megadhatod az elérhetőségedet – így vissza tudunk írni.</p>
                        <div class="ma-contact-fields">
                            <input type="text" name="nev" maxlength="255" placeholder="Név" value="<?= h($_POST['nev'] ?? '') ?>" autocomplete="name">
                            <input type="email" name="email" maxlength="255" placeholder="E-mail" value="<?= h($_POST['email'] ?? '') ?>" autocomplete="email">
                            <input type="tel" name="telefon" maxlength="50" placeholder="Telefonszám" value="<?= h($_POST['telefon'] ?? '') ?>" autocomplete="tel">
                        </div>
                    </div>

                    <button type="submit" class="ma-btn">Elküldöm a visszajelzést</button>
                </form>
            <?php endif; ?>
        </article>
    </main>

    <footer class="ma-footer">
        <p>&copy; <?= (int) date('Y') ?> <?= h(SITE_NAME) ?> · <?= nextgen_footer_version_markup() ?></p>
    </footer>

    <script>
        window.LATINFO_MOBILEAPP = {
            swUrl: <?= json_encode($swUrl, JSON_UNESCAPED_SLASHES) ?>,
            homeUrl: <?= json_encode(rtrim($homeUrl, '/') . '/?source=mobileapp', JSON_UNESCAPED_SLASHES) ?>
        };
    </script>
    <script src="<?= h($jsUrl) ?>" defer></script>
</body>
</html>
