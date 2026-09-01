<?php
$pageTitle = 'Jelszó módosítása';
require_once __DIR__ . '/core/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/lib/admin/admins.php';
requireLogin();

$admin_id = (int) $_SESSION['admin_id'];
$db = getDb();
nextgen_admin_ensure_notification_columns($db);

$stmt = $db->prepare('SELECT név, email, partner_uzenet_email FROM nextgen_admins WHERE id = ? LIMIT 1');
$stmt->execute([$admin_id]);
$adminRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['név' => '', 'email' => '', 'partner_uzenet_email' => 0];

$hiba = '';
$ertesitesHiba = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_notifications'])) {
        $email = trim($_POST['email'] ?? '');
        $partnerUzenetEmail = isset($_POST['partner_uzenet_email']) ? 1 : 0;

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $ertesitesHiba = 'Érvényes e-mail címet adj meg.';
        } elseif ($partnerUzenetEmail && $email === '') {
            $ertesitesHiba = 'Partner üzenet értesítéshez add meg az e-mail címed.';
        } else {
            $db->prepare('UPDATE nextgen_admins SET email = ?, partner_uzenet_email = ? WHERE id = ?')
                ->execute([$email !== '' ? $email : null, $partnerUzenetEmail, $admin_id]);
            $_SESSION['admin_email'] = $email !== '' ? $email : null;
            $adminRow['email'] = $email;
            $adminRow['partner_uzenet_email'] = $partnerUzenetEmail;
            flash('success', 'Értesítési beállítások mentve.');
            redirect(nextgen_url('jelszo.php'));
        }
    } else {
        $jelenlegi = $_POST['jelenlegi_jelszo'] ?? '';
        $uj = $_POST['uj_jelszo'] ?? '';
        $uj2 = $_POST['uj_jelszo_ujra'] ?? '';

        if ($jelenlegi === '') {
            $hiba = 'A jelenlegi jelszó megadása kötelező.';
        } elseif (strlen($uj) < 6) {
            $hiba = 'Az új jelszónak legalább 6 karakter hosszúnak kell lennie.';
        } elseif ($uj !== $uj2) {
            $hiba = 'Az új jelszó és a megerősítés nem egyezik.';
        } else {
            $stmt = $db->prepare('SELECT jelszó_hash FROM nextgen_admins WHERE id = ?');
            $stmt->execute([$admin_id]);
            $row = $stmt->fetch();
            if (!$row || !password_verify($jelenlegi, $row['jelszó_hash'])) {
                $hiba = 'A jelenlegi jelszó nem helyes.';
            } else {
                $hash = password_hash($uj, PASSWORD_DEFAULT);
                $db->prepare('UPDATE nextgen_admins SET jelszó_hash = ? WHERE id = ?')->execute([$hash, $admin_id]);
                rendszer_log('admin', $admin_id, 'Jelszó módosítva', null);
                flash('success', 'A jelszó sikeresen megváltozott.');
                redirect(nextgen_url('apps.php'));
            }
        }
    }
}

require_once __DIR__ . '/partials/header.php';
?>
<div class="card card-narrow">
    <h2>Jelszó módosítása</h2>
    <p>Bejelentkezve: <strong><?= h($_SESSION['admin_nev']) ?></strong></p>
    <?php if ($hiba): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post" autocomplete="off">
        <div class="form-group">
            <label>Jelenlegi jelszó *</label>
            <input type="password" name="jelenlegi_jelszo" required autocomplete="current-password">
        </div>
        <div class="form-group">
            <label>Új jelszó *</label>
            <input type="password" name="uj_jelszo" minlength="6" required autocomplete="new-password">
            <p class="help">Legalább 6 karakter.</p>
        </div>
        <div class="form-group">
            <label>Új jelszó újra *</label>
            <input type="password" name="uj_jelszo_ujra" minlength="6" required autocomplete="new-password">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Jelszó módosítása</button>
            <a href="<?= h(nextgen_url('index.php')) ?>" class="btn btn-secondary">Mégse</a>
        </div>
    </form>
</div>

<div class="card card-narrow">
    <h2>E-mail értesítések</h2>
    <?php if ($ertesitesHiba): ?><p class="alert alert-error"><?= h($ertesitesHiba) ?></p><?php endif; ?>
    <form method="post">
        <input type="hidden" name="save_notifications" value="1">
        <div class="form-group">
            <label>E-mail cím</label>
            <input type="email" name="email" value="<?= h((string) ($adminRow['email'] ?? '')) ?>" placeholder="nev@pelda.hu">
            <p class="help">Partner üzenet értesítéshez szükséges.</p>
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" name="partner_uzenet_email" value="1" <?= !empty($adminRow['partner_uzenet_email']) ? 'checked' : '' ?>>
                E-mail értesítés partner üzenetekről
            </label>
            <p class="help">Ha be van kapcsolva, e-mailt kapsz, amikor egy partner új üzenetet küld.</p>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Értesítések mentése</button>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
