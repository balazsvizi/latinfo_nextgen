<?php
declare(strict_types=1);

/**
 * Nyilvános feedback oldal – vendégfelület (bejelentkezés nélkül).
 * Meghívás: feedback/index.php  →  /feedback/
 */
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../nextgen/core/config.php';
}
require_once __DIR__ . '/../nextgen/core/database.php';
require_once __DIR__ . '/../nextgen/includes/auth.php';
require_once __DIR__ . '/../nextgen/includes/landingpage_table.php';

$db = getDb();
$hiba_feedback = '';

ensure_landingpage_table($db);

$forras = landing_feedback_resolve_forras(
    $_SERVER['REQUEST_METHOD'] === 'POST'
        ? (string) ($_POST['forras'] ?? '')
        : null
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['landing_feedback'])) {
    $ilyen = trim((string) ($_POST['ilyen_legyen'] ?? ''));
    $ne = trim((string) ($_POST['ilyen_ne_legyen'] ?? ''));
    $egyeb = trim((string) ($_POST['egyeb_uzenet'] ?? ''));
    $nev = trim((string) ($_POST['nev'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $telefon = trim((string) ($_POST['telefon'] ?? ''));
    $forras = landing_feedback_resolve_forras((string) ($_POST['forras'] ?? ''));

    if ($ilyen === '' && $ne === '' && $egyeb === '') {
        $hiba_feedback = 'Írd meg legalább röviden, mi tetszik, mit javítanál, vagy az egyéb üzenetet.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $hiba_feedback = 'Érvénytelen e-mail cím.';
    } else {
        [$ip, $ua] = landing_client_meta();
        $payload = [
            'ilyen_legyen' => $ilyen,
            'ilyen_ne_legyen' => $ne,
            'egyeb_uzenet' => $egyeb,
            'email' => $email,
            'nev' => $nev,
            'telefon' => $telefon,
            'forras' => $forras ?? 'feedback',
            'ip' => $ip,
            'user_agent' => $ua,
            'context' => 'feedback oldal',
        ];
        landing_feedback_insert($db, $payload);

        try {
            landing_feedback_send_mail($payload);
        } catch (Throwable $ex) {
            error_log('feedback page mail: ' . $ex->getMessage());
        }

        $homeUrlForFlash = (defined('LATINFO_PUBLIC_HOME_URL') && is_string(LATINFO_PUBLIC_HOME_URL) && LATINFO_PUBLIC_HOME_URL !== '')
            ? LATINFO_PUBLIC_HOME_URL
            : site_url('/');
        $visszaUrlFlash = landing_feedback_safe_return_url($forras, $homeUrlForFlash);
        unset($_SESSION['landing_feedback_forras']);
        flash('landing_ok_feedback', 'Köszönjük! Megkaptuk a visszajelzésed.');
        flash('landing_feedback_vissza', $visszaUrlFlash);
        redirect(site_url('feedback/'));
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
$publicPath = site_url('feedback/');
$ogCanonical = $publicOrigin !== '' ? ($publicOrigin . $publicPath) : '';

$homeUrl = (defined('LATINFO_PUBLIC_HOME_URL') && is_string(LATINFO_PUBLIC_HOME_URL) && LATINFO_PUBLIC_HOME_URL !== '')
    ? LATINFO_PUBLIC_HOME_URL
    : site_url('/');
$visszaUrl = landing_feedback_safe_return_url(
    is_string($visszaFlashed) ? $visszaFlashed : ($forras ?? null),
    $homeUrl
);
$eventsHome = defined('EVENTS_HOME_PATH') ? EVENTS_HOME_PATH : 'events';
$naptarUrl = rtrim(site_url($eventsHome . '/'), '/') . '/';

$pageTitle = SITE_NAME . ' – Feedback';
$pageDescription = 'Írd meg, hogyan tetszik a Latinfo.hu – mi működik jól, és min javítanál.';
$cssUrl = site_url('feedback/assets/css/feedback.css') . '?v=' . rawurlencode(
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
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= h(SITE_NAME) ?>">
    <meta property="og:title" content="<?= h($pageTitle) ?>">
    <meta property="og:description" content="<?= h($pageDescription) ?>">
    <?php if ($ogCanonical !== ''): ?>
    <meta property="og:url" content="<?= h($ogCanonical) ?>">
    <?php endif; ?>
    <meta property="og:locale" content="hu_HU">
    <?php require __DIR__ . '/../nextgen/includes/favicon_head.php'; ?>
    <link rel="stylesheet" href="<?= h($cssUrl) ?>">
</head>
<body class="fb-page">
    <header class="fb-header">
        <a class="fb-brand" href="<?= h($homeUrl) ?>"><?= h(SITE_NAME) ?></a>
        <a class="fb-header-link" href="<?= h($naptarUrl) ?>">Naptár</a>
    </header>

    <main class="fb-main">
        <section class="fb-intro">
            <h1 class="fb-title">Feedback</h1>
            <?php if ($siker_feedback === ''): ?>
                <p class="fb-lead">Mondd el, hogyan tetszik a Latinfo – mi működik jól, és min javítanál. Minden megjegyzés számít.</p>
            <?php endif; ?>
        </section>

        <article class="fb-card">
            <?php if ($siker_feedback !== ''): ?>
                <div class="fb-success">
                    <div class="fb-toast fb-toast--ok fb-toast--alone" role="status"><?= h($siker_feedback) ?></div>
                    <a class="fb-btn" href="<?= h($visszaUrl) ?>">Vissza</a>
                </div>
            <?php else: ?>
                <?php if ($hiba_feedback !== ''): ?>
                    <div class="fb-toast fb-toast--err" role="alert"><?= h($hiba_feedback) ?></div>
                <?php endif; ?>

                <form method="post" action="" novalidate class="fb-form">
                    <input type="hidden" name="landing_feedback" value="1">
                    <input type="hidden" name="forras" value="<?= h((string) ($forras ?? '')) ?>">

                    <label class="fb-label" for="fb-ilyen">Mi tetszik?</label>
                    <textarea id="fb-ilyen" name="ilyen_legyen" rows="4" placeholder="Pl. kinézet, keresés, mobilnézet…"><?= h($_POST['ilyen_legyen'] ?? '') ?></textarea>

                    <label class="fb-label" for="fb-ne">Mit javítanál?</label>
                    <textarea id="fb-ne" name="ilyen_ne_legyen" rows="4" placeholder="Pl. hiányzó funkció, zavaró részlet…"><?= h($_POST['ilyen_ne_legyen'] ?? '') ?></textarea>

                    <label class="fb-label" for="fb-egyeb">Egyéb üzenet</label>
                    <textarea id="fb-egyeb" name="egyeb_uzenet" rows="4" placeholder="Bármi, ami a fentiekbe nem fér bele…"><?= h($_POST['egyeb_uzenet'] ?? '') ?></textarea>

                    <div class="fb-contact">
                        <p class="fb-contact-lead">Opcionális: ha szeretnéd, megadhatod az elérhetőségedet – így vissza tudunk írni.</p>
                        <div class="fb-contact-fields">
                            <input type="text" name="nev" maxlength="255" placeholder="Név" value="<?= h($_POST['nev'] ?? '') ?>" autocomplete="name">
                            <input type="email" name="email" maxlength="255" placeholder="E-mail" value="<?= h($_POST['email'] ?? '') ?>" autocomplete="email">
                            <input type="tel" name="telefon" maxlength="50" placeholder="Telefon" value="<?= h($_POST['telefon'] ?? '') ?>" autocomplete="tel">
                        </div>
                    </div>

                    <button type="submit" class="fb-btn">Elküldöm a visszajelzést</button>
                </form>
            <?php endif; ?>
        </article>
    </main>

    <footer class="fb-footer">
        <p>&copy; <?= (int) date('Y') ?> <?= h(SITE_NAME) ?></p>
    </footer>
</body>
</html>
