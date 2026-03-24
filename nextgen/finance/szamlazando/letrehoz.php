<?php
require_once __DIR__ . '/../../../nextgen/core/database.php';
require_once __DIR__ . '/../../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../../nextgen/includes/functions.php';
requireLogin();

$szervezo_id = (int)($_GET['szervezo_id'] ?? 0);
if (!$szervezo_id) {
    flash('error', 'Hiányzó szervező.');
    redirect(nextgen_url('organizers/'));
}
$db = getDb();
$sz = $db->prepare('SELECT id, név FROM szervezők WHERE id = ?');
$sz->execute([$szervezo_id]);
if (!$sz->fetch()) {
    flash('error', 'Szervező nem található.');
    redirect(nextgen_url('organizers/'));
}

$hiba = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $osszeg = str_replace([' ', ','], ['', '.'], $_POST['összeg'] ?? '0');
    $megjegyzes = trim($_POST['megjegyzés'] ?? '');
    $honapok = $_POST['idoszak'] ?? []; // pl. ["2024-1", "2024-2"]

    if ($osszeg === '' || $osszeg === '0') {
        $hiba = 'Összeg megadása kötelező.';
    } elseif (empty($honapok)) {
        $hiba = 'Válasszon legalább egy hónapot (év/hó).';
    } else {
        $db->beginTransaction();
        try {
            $db->prepare('INSERT INTO számlázandó (szervező_id, összeg, megjegyzés) VALUES (?, ?, ?)')
                ->execute([$szervezo_id, $osszeg, $megjegyzes ?: null]);
            $sid = (int) $db->lastInsertId();
            foreach ($honapok as $evho) {
                if (preg_match('/^(\d{4})-(\d{1,2})$/', $evho, $m)) {
                    $ev = (int)$m[1];
                    $ho = (int)$m[2];
                    if ($ho >= 1 && $ho <= 12) {
                        $db->prepare('INSERT IGNORE INTO számlázandó_időszak (számlázandó_id, év, hónap) VALUES (?, ?, ?)')
                            ->execute([$sid, $ev, $ho]);
                    }
                }
            }
            rendszer_log('számlázandó', $sid, 'Létrehozva', null);
            $db->commit();
            flash('success', 'Mentve.');
            redirect(nextgen_url('organizers/megtekint.php?id=') . $szervezo_id);
        } catch (Exception $e) {
            $db->rollBack();
            $hiba = 'Hiba: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Új számlázandó';
require_once __DIR__ . '/../../partials/header.php';

// Aktuális hónap és 3 hónap hátra (összesen 4 hónap)
$most = new DateTime('first day of this month');
$honapok_abban = [];
for ($i = 3; $i >= 0; $i--) {
    $d = clone $most;
    $d->modify("-$i months");
    $honapok_abban[] = ['ev' => (int)$d->format('Y'), 'ho' => (int)$d->format('n'), 'label' => $d->format('Y') . '-' . $d->format('m'), 'value' => $d->format('Y') . '-' . $d->format('n')];
}
?>
<div class="card card-szamlazando">
    <h2>Új számlázandó</h2>
    <?php if ($hiba): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post" id="szamlazando-form">
        <div class="form-group idoszak-csoport">
            <label>Időszak – több is kijelölhető *</label>
            <p class="idoszak-leiras">Az aktuális hónap és az előző 3 hónap, vagy adj meg egyéb hónapot az éééé-hh formátummal.</p>
            <div class="idoszak-grid">
                <?php foreach ($honapok_abban as $h): ?>
                    <label class="idoszak-cella">
                        <input type="checkbox" name="idoszak[]" value="<?= h($h['value']) ?>">
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
            <div class="idoszak-extra" id="idoszak-extra"></div>
        </div>
        <div class="form-group"><label>Összeg *</label><input type="text" name="összeg" value="<?= h($_POST['összeg'] ?? '') ?>" required placeholder="0" inputmode="decimal"></div>
        <div class="form-group"><label>Megjegyzés</label><textarea name="megjegyzés" rows="2" placeholder="Opcionális"><?= h($_POST['megjegyzés'] ?? '') ?></textarea></div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Mentés</button>
            <a href="<?= h(nextgen_url('organizers/megtekint.php?id=')) ?><?= $szervezo_id ?>" class="btn btn-secondary">Mégse</a>
        </div>
    </form>
</div>
<script>
(function() {
    var form = document.getElementById('szamlazando-form');
    var ujEv = document.getElementById('uj_ev');
    var ujHo = document.getElementById('uj_ho');
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
    ujEv.addEventListener('input', function() { this.value = this.value.replace(/\D/g, '').slice(0,4); if (this.value.length === 4) ujHo.focus(); });
    ujHo.addEventListener('input', function() { this.value = this.value.replace(/\D/g, '').slice(0,2); });
    hozzaadBtn.addEventListener('click', function() {
        var ev = parseInt((ujEv.value || '').trim(), 10), ho = parseInt((ujHo.value || '').trim(), 10);
        if (!ev || !ho) { alert('Add meg az évet (4 számjegy) és a hónapot (01–12).'); ujEv.focus(); return; }
        if (!evHoErvenyes(ev, ho)) { alert('Érvényes év (2000–2100) és hónap (01–12).'); return; }
        var value = formatValue(ev, ho);
        var existing = form.querySelectorAll('input[name="idoszak[]"]');
        for (var i = 0; i < existing.length; i++) { if (existing[i].value === value) return; }
        var wrap = document.createElement('span');
        wrap.className = 'idoszak-tag';
        wrap.innerHTML = '<input type="hidden" name="idoszak[]" value="' + value + '"><span class="idoszak-tag-szoveg">' + labelFromValue(value) + '</span> <button type="button" class="idoszak-tag-del" aria-label="Eltávolítás">&times;</button>';
        extraDiv.appendChild(wrap);
        wrap.querySelector('button').addEventListener('click', function() { wrap.remove(); });
        ujEv.value = ''; ujHo.value = ''; ujEv.focus();
    });
    ujEv.addEventListener('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); hozzaadBtn.click(); } });
    ujHo.addEventListener('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); hozzaadBtn.click(); } });
})();
</script>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
