<?php
declare(strict_types=1);

$db = getDb();
$partnerId = partner_current_id();
partner_portal_apply_context_from_request($db, $partnerId);

$partnerRow = partner_current($db);
$partnerUserName = trim((string) ($partnerRow['név'] ?? partner_session_display_name()));
$partnerUserEmail = trim((string) ($partnerRow['email'] ?? ($_SESSION['partner_email'] ?? '')));

$partnerContexts = partner_portal_available_contexts($db, $partnerId);
$partnerContext = partner_portal_current_context($db, $partnerId);
$partnerMsgPending = partner_portal_admin_reply_pending($db, $partnerId);
$partnerHasOrganizers = false;
$partnerHasDjs = false;
foreach ($partnerContexts as $pc) {
    if ($pc['type'] === 'organizer') {
        $partnerHasOrganizers = true;
    }
    if ($pc['type'] === 'dj') {
        $partnerHasDjs = true;
    }
}

$nav = (string) ($activeNav ?? '');
$orgOpts = array_values(array_filter($partnerContexts, static fn (array $c): bool => $c['type'] === 'organizer'));
$djOpts = array_values(array_filter($partnerContexts, static fn (array $c): bool => $c['type'] === 'dj'));
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h(trim(SITE_NAME . ' Partnerportál')) ?><?= isset($pageTitle) ? ' – ' . h($pageTitle) : '' ?></title>
    <?php require dirname(__DIR__, 2) . '/includes/favicon_head.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= h(nextgen_url('assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= h(partner_asset_url('assets/css/portal.css')) ?>">
    <?php if (!empty($extraHead)) {
        echo $extraHead;
    } ?>
</head>
<body class="partner-portal-body">
<header class="partner-header">
    <div class="partner-header__inner">
        <div class="partner-header__top">
            <a href="<?= h(partner_url('index.php')) ?>" class="partner-header__brand">
                <span class="partner-header__site"><?= h(SITE_NAME) ?></span>
                <span class="partner-header__area">Partnerportál</span>
            </a>
            <button type="button" class="partner-header__menu-btn" id="partner-nav-toggle" aria-expanded="false" aria-controls="partner-main-nav">
                Menü
            </button>
        </div>

        <div class="partner-header__identity">
            <div class="partner-header__who" title="Bejelentkezett felhasználó">
                <span class="partner-header__who-label">Felhasználó</span>
                <span class="partner-header__who-name"><?= h($partnerUserName !== '' ? $partnerUserName : 'Partner') ?></span>
                <?php if ($partnerUserEmail !== ''): ?>
                    <span class="partner-header__who-email"><?= h($partnerUserEmail) ?></span>
                <?php endif; ?>
            </div>

            <?php if ($partnerContexts !== []): ?>
                <div class="partner-header__partner-pick">
                    <label class="partner-header__who-label" for="partner-context-select">Partner</label>
                    <select id="partner-context-select" class="partner-header__partner-select" aria-label="Partner választása">
                        <option value="all"<?= $partnerContext['key'] === 'all' ? ' selected' : '' ?>>Összes partner</option>
                        <?php if ($orgOpts !== []): ?>
                            <optgroup label="Szervezők">
                                <?php foreach ($orgOpts as $c): ?>
                                    <option value="<?= h($c['key']) ?>"<?= $partnerContext['key'] === $c['key'] ? ' selected' : '' ?>>
                                        <?= h($c['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        <?php if ($djOpts !== []): ?>
                            <optgroup label="DJ-k">
                                <?php foreach ($djOpts as $c): ?>
                                    <option value="<?= h($c['key']) ?>"<?= $partnerContext['key'] === $c['key'] ? ' selected' : '' ?>>
                                        <?= h($c['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                    <span class="partner-header__partner-current">
                        Aktív: <strong><?= h($partnerContext['label']) ?></strong>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <nav class="partner-header__nav" id="partner-main-nav" aria-label="Partner menü">
            <a href="<?= h(partner_url('index.php')) ?>" class="partner-header__link<?= $nav === 'home' ? ' is-active' : '' ?>">Kezdőlap</a>
            <a href="<?= h(partner_url('esemenyek.php')) ?>" class="partner-header__link<?= $nav === 'events' ? ' is-active' : '' ?>">Események</a>
            <a href="<?= h(partner_url('naptar.php')) ?>" class="partner-header__link<?= $nav === 'calendar' ? ' is-active' : '' ?>">Naptár</a>
            <?php if ($partnerHasOrganizers): ?>
                <a href="<?= h(partner_url('szervezok.php')) ?>" class="partner-header__link<?= $nav === 'organizers' ? ' is-active' : '' ?>">Szervezők</a>
            <?php endif; ?>
            <?php if ($partnerHasDjs): ?>
                <a href="<?= h(partner_url('djs.php')) ?>" class="partner-header__link<?= $nav === 'djs' ? ' is-active' : '' ?>">DJ-k</a>
            <?php endif; ?>
            <a href="<?= h(partner_url('uzenetek.php')) ?>" class="partner-header__link<?= $nav === 'messages' ? ' is-active' : '' ?>">
                Üzenetek
                <?php if ($partnerMsgPending): ?><span class="partner-nav-badge" title="Új admin válasz">!</span><?php endif; ?>
            </a>
            <a href="<?= h(partner_url('profil.php')) ?>" class="partner-header__link<?= $nav === 'profile' ? ' is-active' : '' ?>">Profil</a>
            <a href="<?= h(partner_url('logout.php')) ?>" class="partner-header__link partner-header__logout">Kijelentkezés</a>
        </nav>
    </div>
</header>
<script>
(function () {
    var sel = document.getElementById('partner-context-select');
    if (sel) {
        sel.addEventListener('change', function () {
            var url = new URL(window.location.href);
            url.searchParams.set('set_ctx', sel.value);
            window.location.href = url.toString();
        });
    }
    var btn = document.getElementById('partner-nav-toggle');
    var nav = document.getElementById('partner-main-nav');
    if (btn && nav) {
        btn.addEventListener('click', function () {
            var open = nav.classList.toggle('is-open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }
})();
</script>
<main class="main-content partner-main">
