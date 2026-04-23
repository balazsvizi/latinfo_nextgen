<?php
require_once __DIR__ . '/../../nextgen/core/database.php';
require_once __DIR__ . '/../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../nextgen/includes/functions.php';
requireLogin();

$szervezo_id = (int)($_GET['szervezo_id'] ?? 0);
$db = getDb();
$hiba = '';

$tipusok = $db->query('SELECT id, név, leírás FROM finance_contact_types ORDER BY név')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $név = trim($_POST['név'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefon = trim($_POST['telefon'] ?? '');
    $fb = trim($_POST['fb'] ?? '');
    $egyeb = trim($_POST['egyéb_kontakt'] ?? '');
    $link_szervezo = (int)($_POST['szervezo_id'] ?? 0);
    $tipus_ids = array_filter(array_map('intval', $_POST['kontakt_tipusok'] ?? []));

    if ($név === '') {
        $hiba = 'A név megadása kötelező.';
    } else {
        $db->prepare('INSERT INTO finance_contacts (név, email, telefon, fb, egyéb_kontakt) VALUES (?, ?, ?, ?, ?)')
            ->execute([$név, $email ?: null, $telefon ?: null, $fb ?: null, $egyeb ?: null]);
        $kid = (int) $db->lastInsertId();
        foreach ($tipus_ids as $tid) {
            $db->prepare('INSERT IGNORE INTO finance_contact_type_links (kontakt_id, típus_id) VALUES (?, ?)')
                ->execute([$kid, $tid]);
        }
        rendszer_log('kontakt', $kid, 'Létrehozva', null);
        if ($link_szervezo) {
            $db->prepare('INSERT IGNORE INTO finance_organizer_contacts (szervező_id, kontakt_id) VALUES (?, ?)')->execute([$link_szervezo, $kid]);
        }
        flash('success', 'Kontakt létrehozva.');
        if ($link_szervezo) {
            redirect(nextgen_url('organizers/megtekint.php?id=') . $link_szervezo . '#kontaktok');
        }
        redirect(nextgen_url('contacts/megtekint.php?id=') . $kid);
    }
    $szervezo_id = $link_szervezo ?: $szervezo_id;
}

$szervezok = $db->query('SELECT id, név FROM finance_organizers ORDER BY név')->fetchAll();
$pageTitle = 'Új kontakt';
require_once __DIR__ . '/../partials/header.php';
?>
<div class="card">
    <h2>Új kontakt</h2>
    <?php if ($hiba): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post">
        <div class="form-group"><label>Név *</label><input type="text" name="név" value="<?= h($_POST['név'] ?? '') ?>" required></div>
        <div class="form-group"><label>E-mail</label><input type="email" name="email" value="<?= h($_POST['email'] ?? '') ?>"></div>
        <div class="form-group"><label>Telefon</label><input type="text" name="telefon" value="<?= h($_POST['telefon'] ?? '') ?>"></div>
        <div class="form-group"><label>Facebook</label><input type="text" name="fb" value="<?= h($_POST['fb'] ?? '') ?>"></div>
        <div class="form-group"><label>Egyéb kontakt</label><input type="text" name="egyéb_kontakt" value="<?= h($_POST['egyéb_kontakt'] ?? '') ?>"></div>
        <div class="form-group">
            <label>Kontakt típus(ok)</label>
            <div class="cimke-valaszto cimke-valaszto-column">
                <?php foreach ($tipusok as $t): ?>
                    <div class="kontakt-tipus-sor">
                        <label class="cimke-tag">
                            <input type="checkbox" name="kontakt_tipusok[]" value="<?= (int)$t['id'] ?>" <?= in_array((string)$t['id'], $_POST['kontakt_tipusok'] ?? []) ? 'checked' : '' ?>>
                            <span><?= h($t['név']) ?></span>
                        </label>
                        <?php if (!empty(trim($t['leírás'] ?? ''))): ?>
                            <span class="kontakt-tipus-leiras"><?= h($t['leírás']) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="help">A típusokat a Config &rarr; Kontakt típusok menüben tudod bővíteni.</p>
        </div>
        <div class="form-group">
            <label>Szervezőhöz csatolás (opcionális)</label>
            <select name="szervezo_id">
                <option value="0">-- Nincs --</option>
                <?php foreach ($szervezok as $sz): ?>
                    <option value="<?= (int)$sz['id'] ?>" <?= ($szervezo_id && (int)$sz['id'] === $szervezo_id) ? 'selected' : '' ?>><?= h($sz['név']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Mentés</button>
            <a href="<?= h($szervezo_id ? nextgen_url('organizers/megtekint.php?id=') . $szervezo_id : nextgen_url('contacts/')) ?>" class="btn btn-secondary">Mégse</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
