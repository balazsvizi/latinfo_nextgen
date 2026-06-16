<?php
/**
 * Exporter – kapcsolat szerkesztése
 */
require_once __DIR__ . '/../../../nextgen/core/database.php';
require_once __DIR__ . '/../../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../../nextgen/includes/functions.php';
require_once __DIR__ . '/../../../nextgen/includes/email.php';

requireLogin();
requireSuperadmin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Érvénytelen kapcsolat.');
    redirect(nextgen_url('admin/exporter/connections.php'));
}

$db = getDb();
$stmt = $db->prepare('SELECT id, név, host, port, dbname, felhasználó FROM nextgen_exporter_connections WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
    flash('error', 'Kapcsolat nem található.');
    redirect(nextgen_url('admin/exporter/connections.php'));
}

$hiba = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $név = trim($_POST['név'] ?? '');
    $host = trim($_POST['host'] ?? 'localhost');
    $port = (int) ($_POST['port'] ?? 3306);
    $dbname = trim($_POST['dbname'] ?? '');
    $felhasználó = trim($_POST['felhasználó'] ?? '');
    $jelszó = $_POST['jelszó'] ?? '';

    if ($név === '' || $dbname === '') {
        $hiba = 'A név és az adatbázis megadása kötelező.';
    } else {
        if ($jelszó !== '') {
            $jelszó_enc = email_jelszo_titkosit($jelszó);
            $stmt = $db->prepare('UPDATE nextgen_exporter_connections SET név=?, host=?, port=?, dbname=?, felhasználó=?, jelszó_titkosított=? WHERE id=?');
            $stmt->execute([$név, $host ?: 'localhost', $port ?: 3306, $dbname, $felhasználó, $jelszó_enc, $id]);
        } else {
            $stmt = $db->prepare('UPDATE nextgen_exporter_connections SET név=?, host=?, port=?, dbname=?, felhasználó=? WHERE id=?');
            $stmt->execute([$név, $host ?: 'localhost', $port ?: 3306, $dbname, $felhasználó, $id]);
        }
        flash('success', 'Kapcsolat mentve.');
        redirect(nextgen_url('admin/exporter/connections.php'));
    }
    $row = ['név' => $név, 'host' => $host, 'port' => $port, 'dbname' => $dbname, 'felhasználó' => $felhasználó];
}

$pageTitle = 'Exporter – Kapcsolat szerkesztése';
require_once __DIR__ . '/../../partials/header.php';
?>
<div class="card card-narrow">
    <h2>Kapcsolat szerkesztése</h2>
    <?php if ($hiba): ?><p class="msg msg-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label>Megjelenített név *</label>
            <input type="text" name="név" value="<?= h($row['név']) ?>" required>
        </div>
        <div class="form-group">
            <label>Host</label>
            <input type="text" name="host" value="<?= h($row['host']) ?>" placeholder="localhost">
        </div>
        <div class="form-group">
            <label>Port</label>
            <input type="number" name="port" value="<?= (int)$row['port'] ?>" min="1" max="65535" placeholder="3306">
        </div>
        <div class="form-group">
            <label>Adatbázis (dbname) *</label>
            <input type="text" name="dbname" value="<?= h($row['dbname']) ?>" required>
        </div>
        <div class="form-group">
            <label>Felhasználónév</label>
            <input type="text" name="felhasználó" value="<?= h($row['felhasználó']) ?>" autocomplete="off">
        </div>
        <div class="form-group">
            <label for="connection-jelszo">Jelszó</label>
            <div class="password-toggle-wrap">
                <input type="password" id="connection-jelszo" name="jelszó" value="" placeholder="üres = nem változik" autocomplete="new-password">
                <button type="button" class="password-toggle-btn" id="connection-jelszo-toggle" aria-label="Jelszó megjelenítése" aria-pressed="false" title="Jelszó megjelenítése">
                    <svg class="password-toggle-icon password-toggle-icon--show" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <svg class="password-toggle-icon password-toggle-icon--hide" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden>
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"></path>
                        <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"></path>
                        <path d="M1 1l22 22"></path>
                        <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"></path>
                    </svg>
                </button>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Mentés</button>
            <a href="<?= h(nextgen_url('admin/exporter/connections.php')) ?>" class="btn btn-secondary">Mégse</a>
        </div>
    </form>
</div>
<style>
.password-toggle-wrap {
    position: relative;
    max-width: 420px;
}
.password-toggle-wrap input {
    max-width: none;
    padding-right: 2.75rem;
}
.password-toggle-btn {
    position: absolute;
    top: 50%;
    right: 0.5rem;
    transform: translateY(-50%);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    padding: 0;
    border: none;
    border-radius: var(--radius-sm, 6px);
    background: transparent;
    color: var(--text-muted, #64748b);
    cursor: pointer;
}
.password-toggle-btn:hover {
    color: var(--text-secondary, #475569);
    background: rgba(0, 0, 0, 0.04);
}
.password-toggle-btn:focus-visible {
    outline: 2px solid var(--primary, #2563eb);
    outline-offset: 2px;
}
</style>
<script>
(function () {
    var input = document.getElementById('connection-jelszo');
    var btn = document.getElementById('connection-jelszo-toggle');
    if (!input || !btn) {
        return;
    }
    var iconShow = btn.querySelector('.password-toggle-icon--show');
    var iconHide = btn.querySelector('.password-toggle-icon--hide');
    btn.addEventListener('click', function () {
        var visible = input.type === 'text';
        input.type = visible ? 'password' : 'text';
        btn.setAttribute('aria-pressed', visible ? 'false' : 'true');
        btn.setAttribute('aria-label', visible ? 'Jelszó megjelenítése' : 'Jelszó elrejtése');
        btn.title = visible ? 'Jelszó megjelenítése' : 'Jelszó elrejtése';
        if (iconShow) {
            iconShow.hidden = !visible;
        }
        if (iconHide) {
            iconHide.hidden = visible;
        }
    });
})();
</script>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
