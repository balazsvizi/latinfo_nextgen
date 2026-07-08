<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/init.php';
require_once dirname(__DIR__, 2) . '/lib/partner/partners.php';
requireLogin();

$db = getDb();
$hiba = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('partner_admin_create')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet.';
    } else {
        $result = nextgen_partner_create(
            $db,
            (string) ($_POST['nev'] ?? ''),
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['jelszo'] ?? ''),
            (string) ($_POST['telefon'] ?? ''),
            (string) ($_POST['egyeb_kontakt'] ?? ''),
            !empty($_POST['jelszo_csere_kotelezo']),
            (string) ($_POST['egyeb_info'] ?? '')
        );
        if ($result['ok']) {
            $pid = (int) $result['id'];
            flash('success', 'Partner létrehozva.');
            redirect(nextgen_url('admin/partnerek/szerkeszt.php?id=') . $pid);
        }
        $hiba = (string) ($result['error'] ?? 'Létrehozás sikertelen.');
    }
}

$pageTitle = 'Új partner';
require_once dirname(__DIR__, 2) . '/partials/header.php';
?>
<div class="card">
    <h2>Új partner</h2>
    <?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post" class="venue-form">
        <?= csrf_input('partner_admin_create') ?>
        <div class="form-group">
            <label for="nev">Név *</label>
            <input type="text" id="nev" name="nev" value="<?= h($_POST['nev'] ?? '') ?>" required maxlength="255">
        </div>
        <div class="form-group">
            <label for="email">E-mail *</label>
            <input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label for="telefon">Telefon</label>
            <input type="text" id="telefon" name="telefon" value="<?= h($_POST['telefon'] ?? '') ?>" maxlength="64">
        </div>
        <div class="form-group">
            <label for="egyeb_kontakt">Egyéb kontakt</label>
            <textarea id="egyeb_kontakt" name="egyeb_kontakt" rows="3"><?= h($_POST['egyeb_kontakt'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label for="egyeb_info">Egyéb infó</label>
            <textarea id="egyeb_info" name="egyeb_info" rows="4" placeholder="Belső megjegyzések, egyéb információk a partnerről…"><?= h($_POST['egyeb_info'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label for="jelszo">Jelszó *</label>
            <input type="password" id="jelszo" name="jelszo" required minlength="8" autocomplete="new-password">
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" name="jelszo_csere_kotelezo" value="1"<?= !empty($_POST['jelszo_csere_kotelezo']) ? ' checked' : '' ?>>
                Kötelező új jelszó megadása az első belépéskor
            </label>
        </div>
        <p class="toolbar">
            <button type="submit" class="btn btn-primary">Létrehozás</button>
            <a href="<?= h(nextgen_url('admin/partnerek/')) ?>" class="btn btn-secondary">Mégse</a>
        </p>
    </form>
</div>
<?php require_once dirname(__DIR__, 2) . '/partials/footer.php'; ?>
