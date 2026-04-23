<?php
require_once __DIR__ . '/../../nextgen/core/database.php';
require_once __DIR__ . '/../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../nextgen/includes/functions.php';
requireLogin();

$szervezo_id = (int)($_GET['szervezo_id'] ?? 0);
if (!$szervezo_id) {
    flash('error', 'Hiányzó szervező.');
    redirect(nextgen_url('organizers/'));
}

$db = getDb();
$sz = $db->prepare('SELECT id, név FROM finance_organizers WHERE id = ?');
$sz->execute([$szervezo_id]);
if (!$sz->fetch()) {
    flash('error', 'Szervező nem található.');
    redirect(nextgen_url('organizers/'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kontakt_id = (int)($_POST['kontakt_id'] ?? 0);
    if ($kontakt_id) {
        try {
            $db->prepare('INSERT IGNORE INTO finance_organizer_contacts (szervező_id, kontakt_id) VALUES (?, ?)')->execute([$szervezo_id, $kontakt_id]);
            rendszer_log('szervező_kontakt', null, 'Kapcsolat létrehozva', "szervező_id=$szervezo_id, kontakt_id=$kontakt_id");
            flash('success', 'Kontakt hozzáadva.');
        } catch (Exception $e) {}
        redirect(nextgen_url('organizers/megtekint.php?id=') . $szervezo_id . '#kontaktok');
    }
}

$mar_hozzarendelt = $db->prepare('SELECT kontakt_id FROM finance_organizer_contacts WHERE szervező_id = ?');
$mar_hozzarendelt->execute([$szervezo_id]);
$mar_hozzarendelt = $mar_hozzarendelt->fetchAll(PDO::FETCH_COLUMN);
$placeholders = implode(',', array_fill(0, count($mar_hozzarendelt) ?: 1, '?'));
$params = $mar_hozzarendelt ?: [0];
$stmt = $db->prepare("SELECT id, név, email FROM finance_contacts WHERE id NOT IN ($placeholders) ORDER BY név");
$stmt->execute($params);
$elerheto_kontaktok = $stmt->fetchAll();

$pageTitle = 'Kontakt hozzáadása';
require_once __DIR__ . '/../partials/header.php';
?>
<div class="card">
    <h2>Kontakt hozzáadása a szervezőhöz</h2>
    <p><a href="<?= h(nextgen_url('organizers/megtekint.php?id=')) ?><?= $szervezo_id ?>">← Vissza</a></p>

    <div class="kontakt-hozzaad-blokk">
        <h3>Válasszon a listából</h3>
        <?php if (empty($elerheto_kontaktok)): ?>
            <p class="text-muted">Nincs olyan kontakt, aki még nincs ehhez a szervezőhöz rendelve. Hozzon létre újat, vagy válasszon másik szervezőnél.</p>
        <?php else: ?>
        <form method="post">
            <div class="form-group">
                <label>Meglévő kontakt</label>
                <select name="kontakt_id" required>
                    <option value="">-- Válasszon kontaktot --</option>
                    <?php foreach ($elerheto_kontaktok as $k): ?>
                        <option value="<?= (int)$k['id'] ?>"><?= h($k['név']) ?><?= $k['email'] ? ' – ' . h($k['email']) : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Kiválasztott hozzáadása</button>
        </form>
        <?php endif; ?>
    </div>

    <div class="kontakt-hozzaad-blokk kontakt-hozzaad-uj">
        <h3>Vagy új kontakt létrehozása</h3>
        <p>Új kontakt felvétele, majd automatikus hozzárendelés ehhez a szervezőhöz.</p>
        <p><a href="<?= h(nextgen_url('contacts/letrehoz.php?szervezo_id=')) ?><?= $szervezo_id ?>" class="btn btn-primary">Új kontakt létrehozása</a></p>
    </div>

    <p style="margin-top:1rem;"><a href="<?= h(nextgen_url('organizers/megtekint.php?id=')) ?><?= $szervezo_id ?>" class="btn btn-secondary">Mégse</a></p>
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
