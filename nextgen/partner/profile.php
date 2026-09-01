<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
partner_require_login();

$db = getDb();
$partnerId = partner_current_id();
$partner = partner_current($db);
if ($partner === null) {
    redirect(partner_url('login.php'));
}

$hiba = '';
$pageTitle = 'Profil';
$activeNav = 'profile';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['_action'] ?? '');
    if (!csrf_validate('partner_profil')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet.';
    } elseif ($action === 'profile') {
        $result = nextgen_partner_update_profile(
            $db,
            $partnerId,
            (string) ($_POST['nev'] ?? ''),
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['telefon'] ?? ''),
            (string) ($_POST['egyeb_kontakt'] ?? '')
        );
        if ($result['ok']) {
            partner_refresh_session_from_db($db);
            flash('success', 'Profil mentve.');
            redirect(partner_url('profil.php'));
        }
        $hiba = (string) ($result['error'] ?? 'Mentés sikertelen.');
    } elseif ($action === 'password') {
        $jelszo = (string) ($_POST['jelszo'] ?? '');
        $jelszo2 = (string) ($_POST['jelszo2'] ?? '');
        if ($jelszo === '' || $jelszo2 === '') {
            $hiba = 'Mindkét jelszó mező kitöltése kötelező.';
        } elseif ($jelszo !== $jelszo2) {
            $hiba = 'A két jelszó nem egyezik.';
        } else {
            $result = nextgen_partner_update_password($db, $partnerId, $jelszo);
            if ($result['ok']) {
                flash('success', 'Jelszó módosítva.');
                redirect(partner_url('profil.php'));
            }
            $hiba = (string) ($result['error'] ?? 'Jelszó mentése sikertelen.');
        }
    }
    $partner = partner_current($db) ?? $partner;
}

require_once __DIR__ . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="card">
    <h1 class="card-title">Saját adatok</h1>
    <?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post" class="venue-form">
        <?= csrf_input('partner_profil') ?>
        <input type="hidden" name="_action" value="profile">
        <div class="form-group">
            <label for="nev">Név *</label>
            <input type="text" id="nev" name="nev" value="<?= h((string) ($partner['név'] ?? '')) ?>" required maxlength="255">
        </div>
        <div class="form-group">
            <label for="email">E-mail *</label>
            <input type="email" id="email" name="email" value="<?= h((string) ($partner['email'] ?? '')) ?>" required>
        </div>
        <div class="form-group">
            <label for="telefon">Telefon</label>
            <input type="text" id="telefon" name="telefon" value="<?= h((string) ($partner['telefon'] ?? '')) ?>" maxlength="64">
        </div>
        <div class="form-group">
            <label for="egyeb_kontakt">Egyéb kontakt</label>
            <textarea id="egyeb_kontakt" name="egyeb_kontakt" rows="3" placeholder="Facebook, weboldal, egyéb elérhetőség…"><?= h((string) ($partner['egyéb_kontakt'] ?? '')) ?></textarea>
        </div>
        <p class="toolbar"><button type="submit" class="btn btn-primary">Mentés</button></p>
    </form>
</div>

<div class="card">
    <h2 class="card-title">Jelszó módosítása</h2>
    <form method="post" class="venue-form">
        <?= csrf_input('partner_profil') ?>
        <input type="hidden" name="_action" value="password">
        <div class="form-group">
            <label for="jelszo">Új jelszó</label>
            <input type="password" id="jelszo" name="jelszo" minlength="8" autocomplete="new-password">
        </div>
        <div class="form-group">
            <label for="jelszo2">Új jelszó újra</label>
            <input type="password" id="jelszo2" name="jelszo2" minlength="8" autocomplete="new-password">
        </div>
        <p class="toolbar"><button type="submit" class="btn btn-secondary">Jelszó mentése</button></p>
    </form>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
