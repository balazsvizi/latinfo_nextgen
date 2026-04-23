<?php
require_once __DIR__ . '/../../nextgen/core/database.php';
require_once __DIR__ . '/../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../nextgen/includes/functions.php';
requireLogin();

$db = getDb();
$hiba = '';
$hasCimkeSzin = cimkek_has_szin($db);
$cimkeSql = $hasCimkeSzin
    ? 'SELECT id, név, COALESCE(szín, "#6366F1") AS szín FROM finance_tags ORDER BY név'
    : 'SELECT id, név, "#6366F1" AS szín FROM finance_tags ORDER BY név';
$címkék = $db->query($cimkeSql)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $név = trim($_POST['név'] ?? '');
    $címke_ids = array_filter(array_map('intval', $_POST['címkék'] ?? []));

    if ($név === '') {
        $hiba = 'A név megadása kötelező.';
    } else {
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('INSERT INTO finance_organizers (név) VALUES (?)');
            $stmt->execute([$név]);
            $szervezo_id = (int) $db->lastInsertId();

            foreach ($címke_ids as $cid) {
                $db->prepare('INSERT INTO finance_organizer_tags (szervező_id, címke_id) VALUES (?, ?)')->execute([$szervezo_id, $cid]);
            }

            rendszer_log('szervező', $szervezo_id, 'Létrehozva', 'Név: ' . $név);
            $db->prepare('INSERT INTO finance_organizer_activity_log (szervező_id, esemény, részletek, admin_id) VALUES (?, ?, ?, ?)')
                ->execute([$szervezo_id, 'Szervező létrehozva', null, $_SESSION['admin_id'] ?? null]);

            $db->commit();
            flash('success', 'Szervező sikeresen létrehozva.');
            redirect(nextgen_url('organizers/megtekint.php?id=') . $szervezo_id);
        } catch (Exception $e) {
            $db->rollBack();
            $hiba = 'Hiba történt: ' . $e->getMessage();
        }
    }
    setOld($_POST);
}
$pageTitle = 'Új szervező';
require_once __DIR__ . '/../partials/header.php';
?>
<div class="card">
    <h2>Új szervező</h2>
    <?php if ($hiba): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label for="nev">Név *</label>
            <input type="text" id="nev" name="név" value="<?= old('név') ?>" required>
        </div>
        <div class="form-group">
            <label>Címkék</label>
            <div class="cimke-valaszto">
                <?php foreach ($címkék as $c): ?>
                    <?php
                        $szin = normalize_hex_color($c['szín'], '#6366F1');
                        $textColor = contrast_text_color($szin);
                    ?>
                    <label class="cimke-tag" style="--tag-color: <?= h($szin) ?>; --tag-text: <?= h($textColor) ?>;">
                        <input type="checkbox" name="címkék[]" value="<?= (int)$c['id'] ?>" <?= in_array((string)$c['id'], $_SESSION['_old']['címkék'] ?? []) ? 'checked' : '' ?>>
                        <span><?= h($c['név']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="help"><a href="<?= h(nextgen_url('config/cimkek.php')) ?>">Új címke felvétele</a></p>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Mentés</button>
            <a href="<?= h(nextgen_url('organizers/')) ?>" class="btn btn-secondary">Mégse</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
