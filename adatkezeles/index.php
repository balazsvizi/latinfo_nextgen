<?php
declare(strict_types=1);

/**
 * Nyilvános adatkezelési tájékoztató – URL: /adatkezeles/
 */

require_once dirname(__DIR__) . '/nextgen/core/config.php';
require_once dirname(__DIR__) . '/nextgen/core/database.php';
require_once dirname(__DIR__) . '/nextgen/includes/functions.php';
require_once dirname(__DIR__) . '/nextgen/lib/user/consents.php';

$siteName = defined('SITE_NAME') ? (string) SITE_NAME : 'Latinfo.hu';
$controllerName = latinfo_privacy_controller_name();
$controllerEmail = latinfo_privacy_controller_email();
$controllerAddress = latinfo_privacy_controller_address();
$version = latinfo_privacy_policy_version();
$effective = LATINFO_PRIVACY_POLICY_EFFECTIVE;
$accountUrl = function_exists('user_url') ? user_url() : site_url('account/');
$homeUrl = defined('LATINFO_PUBLIC_HOME_URL') ? (string) LATINFO_PUBLIC_HOME_URL : site_url('/');
$styleCssUrl = nextgen_url('assets/css/style.css') . '?v=' . rawurlencode(nextgen_app_version());
$accountCssUrl = nextgen_url('user/assets/css/account.css') . '?v=' . rawurlencode(nextgen_app_version());

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Adatkezelési tájékoztató – <?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="A <?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?> adatkezelési tájékoztatója (GDPR).">
    <?php require dirname(__DIR__) . '/nextgen/includes/favicon_head.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars($styleCssUrl, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($accountCssUrl, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="login-page user-login-page user-privacy-page">
    <div class="user-privacy-doc">
        <p class="user-privacy-doc__brand">
            <a href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></a>
        </p>
        <h1>Adatkezelési tájékoztató</h1>
        <p class="user-privacy-doc__meta">
            Verzió: <?= htmlspecialchars($version, ENT_QUOTES, 'UTF-8') ?>
            · Hatályos: <?= htmlspecialchars($effective, ENT_QUOTES, 'UTF-8') ?>
        </p>

        <section>
            <h2>1. Adatkezelő</h2>
            <p>
                Az adatkezelő: <strong><?= htmlspecialchars($controllerName, ENT_QUOTES, 'UTF-8') ?></strong>
                (a továbbiakban: Adatkezelő), a <?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?> weboldal és kapcsolódó szolgáltatások üzemeltetője.
            </p>
            <?php if ($controllerAddress !== ''): ?>
                <p>Székhely / levelezési cím: <?= htmlspecialchars($controllerAddress, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <p>
                Kapcsolat adatvédelmi ügyekben:
                <a href="mailto:<?= htmlspecialchars($controllerEmail, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($controllerEmail, ENT_QUOTES, 'UTF-8') ?></a>
            </p>
        </section>

        <section>
            <h2>2. A tájékoztató célja</h2>
            <p>
                Jelen tájékoztató az Európai Unió általános adatvédelmi rendeletének (GDPR – 2016/679/EU rendelet)
                és a vonatkozó magyar jogszabályoknak megfelelően tájékoztatja az érintetteket a személyes adatok
                kezeléséről a <?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?> fiók, kedvencek, értesítések
                és kapcsolódó funkciók kapcsán.
            </p>
        </section>

        <section>
            <h2>3. Kezelt adatok és célok</h2>
            <ul>
                <li><strong>Fiókadatok:</strong> név, e-mail cím, jelszó hash (ha e-mailes regisztráció), OAuth azonosítók (Google / Facebook), avatar URL, utolsó belépés ideje – a fiók létrehozásához és azonosításához.</li>
                <li><strong>Kedvencek:</strong> a felhasználó által megjelölt események, szervezők, helyszínek, DJ-k, előadók – a személyre szabott listához.</li>
                <li><strong>Értesítési e-mail:</strong> opcionális, eltérő cím a fiók e-mailjétől – értesítések kézbesítéséhez.</li>
                <li><strong>Hírlevél / e-mail listák:</strong> a választott témák (pl. Latinfo hírek, bulik, workshopok, tánciskolák) szerinti értesítések – külön, visszavonható feliratkozással.</li>
                <li><strong>Technikai napló:</strong> hozzájárulás rögzítésekor IP-cím hash és böngésző azonosító (user-agent) – biztonság és audit.</li>
            </ul>
        </section>

        <section>
            <h2>4. Jogalapok</h2>
            <ul>
                <li><strong>Szerződés teljesítése</strong> (GDPR 6. cikk (1) b): fiók, bejelentkezés, kedvencek tárolása.</li>
                <li><strong>Hozzájárulás</strong> (GDPR 6. cikk (1) a): marketing / hírlevél jellegű e-mail értesítések – külön, visszavonható hozzájárulással.</li>
                <li><strong>Jogos érdek</strong> (GDPR 6. cikk (1) f): szolgáltatás biztonsága, visszaélések megelőzése – az érintett tiltakozási jogának figyelembevételével.</li>
            </ul>
        </section>

        <section>
            <h2>5. Adattovábbítás, adatfeldolgozók</h2>
            <p>
                Az Adatkezelő a szolgáltatás működtetéséhez külső szolgáltatókat vehet igénybe (pl. tárhely,
                e-mail kézbesítés / SMTP, Google vagy Facebook bejelentkezés). Ezek csak a szükséges mértékben
                férhetnek hozzá adatokhoz, szerződéses kötelezettségek mellett.
            </p>
            <p>
                OAuth belépés esetén a választott szolgáltató (Google, Meta/Facebook) saját adatkezelési
                szabályai is irányadók az ott kezelt adatokra.
            </p>
        </section>

        <section>
            <h2>6. Megőrzés</h2>
            <p>
                A fiókadatokat a fiók fennállásáig, illetve a törlés iránti kérelem teljesítéséig őrizzük.
                A hozzájárulási napló bejegyzéseit a jogi igények érvényesíthetőségéhez szükséges ideig
                (jellemzően a hozzájárulás visszavonásától / fióktörléstől számított ésszerű ideig) megőrizzük.
            </p>
        </section>

        <section>
            <h2>7. Érintetti jogok</h2>
            <p>Az érintett jogosult:</p>
            <ul>
                <li>tájékoztatást kérni a rá vonatkozó adatok kezeléséről,</li>
                <li>hozzáférést, helyesbítést kérni,</li>
                <li>a hozzájárulás visszavonására (a visszavonás nem érinti a korábbi jogszerű kezelést),</li>
                <li>tiltakozni a jogos érdeken alapuló kezelés ellen,</li>
                <li>adatainak törlését vagy korlátozását kérni a jogszabályi keretek között,</li>
                <li>panaszt tenni a Nemzeti Adatvédelmi és Információszabadság Hatóságnál (NAIH).</li>
            </ul>
            <p>
                Kérelmeidet az Adatkezelőnek a fenti e-mail címen, vagy a
                <a href="<?= htmlspecialchars($accountUrl, ENT_QUOTES, 'UTF-8') ?>">fiókod</a> oldalán jelezheted.
            </p>
        </section>

        <section>
            <h2>8. Süti (cookie) és hasonló technológiák</h2>
            <p>
                A bejelentkezéshez munkamenet-sütit használunk. A kedvencek vendégként történő tárolásához
                egy azonosító süti is szükséges lehet. Ezek a szolgáltatás működéséhez kapcsolódnak.
            </p>
        </section>

        <section>
            <h2>9. Módosítások</h2>
            <p>
                Az Adatkezelő a tájékoztatót időről időre frissítheti. Lényeges változás esetén az új verzió
                száma változik; a fiók további használatához az aktuális verzió elfogadása szükséges lehet.
            </p>
        </section>

        <p class="user-privacy-doc__footer">
            <a href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>">← <?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></a>
            <span aria-hidden="true">·</span>
            <a href="<?= htmlspecialchars($accountUrl, ENT_QUOTES, 'UTF-8') ?>">Fiókom</a>
            <span aria-hidden="true">·</span>
            <?= nextgen_footer_version_markup() ?>
        </p>
    </div>
</body>
</html>
