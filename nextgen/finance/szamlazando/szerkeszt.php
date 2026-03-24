<?php
require_once __DIR__ . '/../../../nextgen/core/database.php';
require_once __DIR__ . '/../../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../../nextgen/includes/functions.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    flash('error', 'Hiányzó azonosító.');
    redirect(nextgen_url('organizers/'));
}
$db = getDb();
$s = $db->prepare('SELECT * FROM számlázandó WHERE id = ? AND (COALESCE(törölve,0) = 0)');
$s->execute([$id]);
$s = $s->fetch();
if (!$s) {
    flash('error', 'Nem található.');
    redirect(nextgen_url('organizers/'));
}
$szervezo_id = (int)$s['szervező_id'];

// Számlázandó törlése (soft delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['torol_szamlazando'])) {
    rendszer_log('számlázandó', $id, 'Törölve', null);
    $db->prepare('UPDATE számlázandó SET törölve = 1, számla_id = NULL WHERE id = ?')->execute([$id]);
    flash('success', 'Számlázandó tétel törölve.');
    if (!empty($_POST['vissza']) && $_POST['vissza'] === 'szamla' && !empty($_POST['szamla_id'])) {
        redirect(nextgen_url('finance/szamlak/szerkeszt.php?id=') . (int)$_POST['szamla_id']);
    }
    redirect(nextgen_url('organizers/megtekint.php?id=') . $szervezo_id);
}

$idoszakok = $db->prepare('SELECT év, hónap FROM számlázandó_időszak WHERE számlázandó_id = ?');
$idoszakok->execute([$id]);
$idoszakok = $idoszakok->fetchAll();

$hiba = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $osszeg = str_replace([' ', ','], ['', '.'], $_POST['összeg'] ?? '0');
    $megjegyzes = trim($_POST['megjegyzés'] ?? '');
    $honapok = $_POST['idoszak'] ?? [];

    if ($osszeg === '' || $osszeg === '0') {
        $hiba = 'Összeg megadása kötelező.';
    } elseif (empty($honapok)) {
        $hiba = 'Válasszon legalább egy hónapot.';
    } else {
        try {
            $db->prepare('UPDATE számlázandó SET összeg = ?, megjegyzés = ? WHERE id = ?')->execute([$osszeg, $megjegyzes ?: null, $id]);
            $db->prepare('DELETE FROM számlázandó_időszak WHERE számlázandó_id = ?')->execute([$id]);
            foreach ($honapok as $evho) {
                if (preg_match('/^(\d{4})-(\d{1,2})$/', $evho, $m)) {
                    $ev = (int)$m[1];
                    $ho = (int)$m[2];
                    if ($ho >= 1 && $ho <= 12) {
                        $db->prepare('INSERT INTO számlázandó_időszak (számlázandó_id, év, hónap) VALUES (?, ?, ?)')->execute([$id, $ev, $ho]);
                    }
                }
            }
            rendszer_log('számlázandó', $id, 'Módosítva', null);
            flash('success', 'Mentve.');
            if (!empty($_GET['vissza']) && $_GET['vissza'] === 'szamla' && !empty($_GET['szamla_id'])) {
                redirect(nextgen_url('finance/szamlak/szerkeszt.php?id=') . (int)$_GET['szamla_id']);
            } else {
                redirect(nextgen_url('organizers/megtekint.php?id=') . $szervezo_id);
            }
        } catch (Exception $e) {
            $hiba = 'Hiba: ' . $e->getMessage();
        }
    }
    $idoszakok = [];
    foreach ($honapok as $evho) {
        if (preg_match('/^(\d{4})-(\d{1,2})$/', $evho, $m)) {
            $idoszakok[] = ['év' => (int)$m[1], 'hónap' => (int)$m[2]];
        }
    }
}

$pageTitle = 'Számlázandó szerkesztése';
require_once __DIR__ . '/../../partials/header.php';

$most = new DateTime('first day of this month');
$honapok_abban = [];
for ($i = 3; $i >= 0; $i--) {
    $d = clone $most;
    $d->modify("-$i months");
    $honapok_abban[] = ['ev' => (int)$d->format('Y'), 'ho' => (int)$d->format('n'), 'label' => $d->format('Y') . '-' . $d->format('m'), 'value' => $d->format('Y') . '-' . $d->format('n')];
}
$kijelolt = array_map(function ($r) { return $r['év'] . '-' . $r['hónap']; }, $idoszakok);
$grid_values = array_column($honapok_abban, 'value');
$extra_idoszakok = array_filter($idoszakok, function ($r) use ($grid_values) {
    $v = $r['év'] . '-' . $r['hónap'];
    return !in_array($v, $grid_values);
});
?>
<div class="card card-szamlazando">
    <h2>Számlázandó szerkesztése</h2>
    <?php if ($hiba): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post" id="szamlazando-form">
        <div class="form-group idoszak-csoport">
            <label>Időszak – több is kijelölhető *</label>
            <p class="idoszak-leiras">Az aktuális hónap és az előző 3 hónap, vagy adj meg egyéb hónapot az éééé-hh formátummal.</p>
            <div class="idoszak-grid">
                <?php foreach ($honapok_abban as $h): ?>
                    <?php $v = $h['value']; $checked = in_array($v, $kijelolt); ?>
                    <label class="idoszak-cella">
                        <input type="checkbox" name="idoszak[]" value="<?= h($v) ?>" <?= $checked ? 'checked' : '' ?>>
                        <span class="idoszak-cella-szoveg"><?= h($h['label']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="idoszak-manual">
                <label class="idoszak-manual-label">Év–hónap:</label>
                <span class="idoszak-input-wrap">
                    <input type="text" id="uj_ev" placeholder="éééé" maxlength="4" inputmode="numeric" autocomplete="off" size="4">
                    <span class="idoszak-dash" aria-hidden="true">-</span>
                    <input type="text" id="uj_ho" placeholder="hh" maxlength="2" inputmode="numeric" autocomplete="off" size="2">
                </span>
                <button type="button" class="btn btn-secondary btn-sm" id="idoszak-hozzaad">Hónap hozzáadása</button>
            </div>
            <div class="idoszak-extra" id="idoszak-extra">
                <?php foreach ($extra_idoszakok as $ex): ?>
                    <?php $v = $ex['év'] . '-' . $ex['hónap']; $l = sprintf('%04d-%02d', $ex['év'], $ex['hónap']); ?>
                    <span class="idoszak-tag">
                        <input type="hidden" name="idoszak[]" value="<?= h($v) ?>">
                        <span class="idoszak-tag-szoveg"><?= h($l) ?></span>
                        <button type="button" class="idoszak-tag-del" aria-label="Eltávolítás">&times;</button>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="form-group"><label>Összeg *</label><input type="text" name="összeg" value="<?= h($s['összeg']) ?>" required placeholder="0" inputmode="decimal"></div>
        <div class="form-group"><label>Megjegyzés</label><textarea name="megjegyzés" rows="2" placeholder="Opcionális"><?= h($s['megjegyzés'] ?? '') ?></textarea></div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Mentés</button>
            <a href="<?= h(nextgen_url('organizers/megtekint.php?id=')) ?><?= $szervezo_id ?>" class="btn btn-secondary">Mégse</a>
        </div>
    </form>
    <div class="form-actions" style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border);">
        <form method="post" class="inline-form" onsubmit="return confirm('Biztosan törölni szeretnéd ezt a számlázandó tételt? A tétel nem jelenik meg a listákban, a kapcsolt számlától le fog szakadni.');">
            <input type="hidden" name="torol_szamlazando" value="1">
            <?php if (!empty($_GET['vissza']) && $_GET['vissza'] === 'szamla' && !empty($_GET['szamla_id'])): ?>
            <input type="hidden" name="vissza" value="szamla">
            <input type="hidden" name="szamla_id" value="<?= (int)$_GET['szamla_id'] ?>">
            <?php endif; ?>
            <button type="submit" class="btn btn-danger btn-sm">Számlázandó tétel törlése</button>
        </form>
    </div>
</div>
<script>
(function() {
    var form = document.getElementById('szamlazando-form');
    var hozzaadBtn = document.getElementById('idoszak-hozzaad');
    var extraDiv = document.getElementById('idoszak-extra');
    function evHoErvenyes(ev, ho) { return ev >= 2000 && ev <= 2100 && ho >= 1 && ho <= 12; }
    function formatValue(ev, ho) { return ev + '-' + ho; }
    function labelFromValue(v) {
        var p = v.split('-');
        if (p.length !== 2) return v;
        var ho = parseInt(p[1], 10);
        return p[0] + '-' + (ho < 10 ? '0' + ho : p[1]);
    }
    var ujEv = document.getElementById('uj_ev');
    var ujHo = document.getElementById('uj_ho');
    if (ujEv) ujEv.addEventListener('input', function() { this.value = this.value.replace(/\D/g, '').slice(0,4); if (this.value.length === 4) ujHo.focus(); });
    if (ujHo) ujHo.addEventListener('input', function() { this.value = this.value.replace(/\D/g, '').slice(0,2); });
    if (hozzaadBtn) hozzaadBtn.addEventListener('click', function() {
        var ev = parseInt((ujEv && ujEv.value || '').trim(), 10), ho = parseInt((ujHo && ujHo.value || '').trim(), 10);
        if (!ev || !ho) { alert('Add meg az évet (4 számjegy) és a hónapot (01–12).'); if (ujEv) ujEv.focus(); return; }
        if (!evHoErvenyes(ev, ho)) { alert('Érvényes év (2000–2100) és hónap (01–12).'); return; }
        var value = formatValue(ev, ho);
        var existing = form.querySelectorAll('input[name="idoszak[]"]');
        for (var i = 0; i < existing.length; i++) { if (existing[i].value === value) return; }
        var wrap = document.createElement('span');
        wrap.className = 'idoszak-tag';
        wrap.innerHTML = '<input type="hidden" name="idoszak[]" value="' + value + '"><span class="idoszak-tag-szoveg">' + labelFromValue(value) + '</span> <button type="button" class="idoszak-tag-del" aria-label="Eltávolítás">&times;</button>';
        extraDiv.appendChild(wrap);
        wrap.querySelector('button').addEventListener('click', function() { wrap.remove(); });
        if (ujEv) ujEv.value = ''; if (ujHo) ujHo.value = ''; if (ujEv) ujEv.focus();
    });
    if (ujEv) ujEv.addEventListener('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); hozzaadBtn.click(); } });
    if (ujHo) ujHo.addEventListener('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); hozzaadBtn.click(); } });
    extraDiv.querySelectorAll('.idoszak-tag-del').forEach(function(btn) {
        btn.addEventListener('click', function() { btn.closest('.idoszak-tag').remove(); });
    });
})();
</script>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
