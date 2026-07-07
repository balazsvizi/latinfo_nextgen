<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

organizers_portal_require_login();

$db = getDb();
$accountId = organizers_portal_current_account_id();
$account = organizers_portal_current_account($db);
if ($account === null) {
    flash('error', 'A fiókod nem elérhető.');
    redirect(organizers_portal_url('login.php'));
}

$hiba = '';
$pageTitle = 'Jelszó módosítása';
$activeNav = 'password';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('organizers_portal_jelszo')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet. Töltsd újra az oldalt.';
    } else {
        $jelszo = (string) ($_POST['jelszo'] ?? '');
        $jelszo2 = (string) ($_POST['jelszo2'] ?? '');
        if ($jelszo === '' || $jelszo2 === '') {
            $hiba = 'Mindkét jelszó mező kitöltése kötelező.';
        } elseif ($jelszo !== $jelszo2) {
            $hiba = 'A két jelszó nem egyezik.';
        } else {
            $result = events_organizer_account_update_password($db, $accountId, $jelszo);
            if ($result['ok']) {
                flash('success', 'A jelszó sikeresen módosítva.');
                redirect(organizers_portal_url('jelszo.php'));
            }
            $hiba = (string) ($result['error'] ?? 'Jelszó mentése sikertelen.');
        }
    }
}

require_once __DIR__ . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="card">
    <h1 class="card-title">Jelszó módosítása</h1>
    <?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post" class="venue-form">
        <?= csrf_input('organizers_portal_jelszo') ?>
        <div class="form-group">
            <label for="jelszo">Új jelszó</label>
            <input type="password" id="jelszo" name="jelszo" required minlength="8" autocomplete="new-password">
            <p class="help">Legalább 8 karakter.</p>
        </div>
        <div class="form-group">
            <label for="jelszo2">Új jelszó újra</label>
            <input type="password" id="jelszo2" name="jelszo2" required minlength="8" autocomplete="new-password">
        </div>
        <p class="toolbar">
            <button type="submit" class="btn btn-primary">Mentés</button>
            <a href="<?= h(organizers_portal_url('index.php')) ?>" class="btn btn-secondary">Vissza</a>
        </p>
    </form>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
