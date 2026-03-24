<?php
require_once __DIR__ . '/../../../nextgen/core/config.php';
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
$szamla = $db->prepare('SELECT s.*, sz.név AS szervezo_nev FROM számlák s JOIN szervezők sz ON sz.id = s.szervező_id WHERE s.id = ? AND (COALESCE(s.törölve,0) = 0)');
$szamla->execute([$id]);
$szamla = $szamla->fetch();
if (!$szamla) {
    flash('error', 'Számla nem található.');
    redirect(nextgen_url('finance/szamlak/'));
}
$szervezo_id = (int) $szamla['szervező_id'];

// Számlázandó lecsatolás
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lecsatol_szamlazando_id'])) {
    $sid = (int) $_POST['lecsatol_szamlazando_id'];
    if ($sid) {
        $db->prepare('UPDATE számlázandó SET számla_id = NULL WHERE id = ? AND számla_id = ?')->execute([$sid, $id]);
        flash('success', 'Számlázandó tétel lecsatolva.');
        redirect(nextgen_url('finance/szamlak/megtekint.php?id=') . $id);
    }
}
// Számlázandó további csatolása
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hozzacsatol_szamlazando_ids']) && is_array($_POST['hozzacsatol_szamlazando_ids'])) {
    $csatol_ids = array_map('intval', $_POST['hozzacsatol_szamlazando_ids']);
    $csatol_ids = array_filter($csatol_ids);
    if (!empty($csatol_ids)) {
        $placeholders = implode(',', array_fill(0, count($csatol_ids), '?'));
        $db->prepare("UPDATE számlázandó SET számla_id = ? WHERE id IN ($placeholders) AND szervező_id = ? AND számla_id IS NULL AND (COALESCE(törölve,0) = 0)")
            ->execute(array_merge([$id], $csatol_ids, [$szervezo_id]));
        flash('success', 'Számlázandó tétel(ek) csatolva.');
        redirect(nextgen_url('finance/szamlak/megtekint.php?id=') . $id);
    }
}
// Státusz módosítás
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['státusz'])) {
    $uj = $_POST['státusz'];
    if (in_array($uj, ['generált', 'kiküldve', 'kiegyenlítve', 'egyéb', 'KP', 'sztornó'], true)) {
        $db->prepare('UPDATE számlák SET státusz = ? WHERE id = ?')->execute([$uj, $id]);
        rendszer_log('számla', $id, 'Státusz módosítva', $uj);
        flash('success', 'Státusz frissítve.');
        redirect(nextgen_url('finance/szamlak/megtekint.php?id=') . $id);
    }
}

// Új fájl(ok) feltöltés
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['uj_fajl']['name']) && is_dir(UPLOAD_PATH)) {
    $upload_dir = UPLOAD_PATH . '/' . $id;
    if (!is_dir($upload_dir)) @mkdir($upload_dir, 0755, true);
    $names = is_array($_FILES['uj_fajl']['name']) ? $_FILES['uj_fajl']['name'] : [$_FILES['uj_fajl']['name']];
    $tmp = is_array($_FILES['uj_fajl']['tmp_name']) ? $_FILES['uj_fajl']['tmp_name'] : [$_FILES['uj_fajl']['tmp_name']];
    $errors = is_array($_FILES['uj_fajl']['error']) ? $_FILES['uj_fajl']['error'] : [$_FILES['uj_fajl']['error']];
    $feltoltve = 0;
    for ($i = 0; $i < count($names); $i++) {
        if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && !empty($names[$i])) {
            $name = $names[$i];
            $ext = pathinfo($name, PATHINFO_EXTENSION) ?: 'bin';
            $ujnev = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($name)) ?: 'file_' . $i . '.' . $ext;
            $cel = $upload_dir . '/' . $ujnev;
            if (move_uploaded_file($tmp[$i], $cel)) {
                $db->prepare('INSERT INTO számla_fájlok (számla_id, eredeti_név, fájl_útvonal) VALUES (?, ?, ?)')
                    ->execute([$id, $name, $id . '/' . $ujnev]);
                $feltoltve++;
            }
        }
    }
    if ($feltoltve > 0) {
        flash('success', $feltoltve === 1 ? 'Fájl feltöltve.' : $feltoltve . ' fájl feltöltve.');
        redirect(nextgen_url('finance/szamlak/megtekint.php?id=') . $id);
    }
}

$fajlok = $db->prepare('SELECT * FROM számla_fájlok WHERE számla_id = ? ORDER BY id');
$fajlok->execute([$id]);
$fajlok = $fajlok->fetchAll();

// Ehhez a számlához csatolt számlázandó tételek
$csatolt_szamlazando = $db->prepare("
    SELECT s.id, s.összeg, s.megjegyzés,
           (SELECT GROUP_CONCAT(CONCAT(si.év, '-', LPAD(si.hónap, 2, '0')) ORDER BY si.év, si.hónap)
            FROM számlázandó_időszak si WHERE si.számlázandó_id = s.id) AS idoszakok
    FROM számlázandó s
    WHERE s.számla_id = ? AND (COALESCE(s.törölve,0) = 0)
    ORDER BY s.létrehozva
");
$csatolt_szamlazando->execute([$id]);
$csatolt_szamlazando = $csatolt_szamlazando->fetchAll(PDO::FETCH_ASSOC);

// Ugyanahhoz a szervezőhöz tartozó, még nem csatolt számlázandó (további csatoláshoz)
$tovabbi_szamlazando = $db->prepare("
    SELECT s.id, s.összeg, s.megjegyzés,
           (SELECT GROUP_CONCAT(CONCAT(si.év, '-', LPAD(si.hónap, 2, '0')) ORDER BY si.év, si.hónap)
            FROM számlázandó_időszak si WHERE si.számlázandó_id = s.id) AS idoszakok
    FROM számlázandó s
    WHERE s.szervező_id = ? AND s.számla_id IS NULL AND (COALESCE(s.törölve,0) = 0)
    ORDER BY s.létrehozva DESC
");
$tovabbi_szamlazando->execute([$szervezo_id]);
$tovabbi_szamlazando = $tovabbi_szamlazando->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Számla: ' . $szamla['számla_szám'];
require_once __DIR__ . '/../../partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<div class="card">
    <h2>Számla: <?= h($szamla['számla_szám']) ?></h2>
    <p><a href="<?= h(nextgen_url('organizers/megtekint.php?id=')) ?><?= (int)$szamla['szervező_id'] ?>">← <?= h($szamla['szervezo_nev']) ?></a></p>
    <table>
        <tr><th>Számlaszám</th><td><?= h($szamla['számla_szám']) ?></td></tr>
        <tr><th>Dátum</th><td><?= h($szamla['dátum']) ?></td></tr>
        <tr><th>Összeg</th><td><?= number_format((float)$szamla['összeg'], 0, ',', ' ') ?></td></tr>
        <tr><th>Belső megjegyzés</th><td><?= nl2br(h($szamla['belső_megjegyzés'] ?? '')) ?></td></tr>
        <tr><th>Státusz</th><td>
            <form method="post" style="display:inline;">
                <select name="státusz" onchange="this.form.submit()">
                    <?php foreach (['generált','kiküldve','kiegyenlítve','egyéb','KP','sztornó'] as $st): ?>
                        <option value="<?= h($st) ?>" <?= $szamla['státusz'] === $st ? 'selected' : '' ?>><?= szamla_statusz_label($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </td></tr>
    </table>
</div>
<div class="card">
    <h2>Csatolt fájlok</h2>
    <form method="post" enctype="multipart/form-data" id="uj-fajl-form" style="margin-bottom:1rem;">
        <div class="file-upload-wrap file-drop-zone" id="drop-zone-megtekint">
            <input type="file" name="uj_fajl[]" id="uj_fajl" class="file-input-native" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
            <label for="uj_fajl" class="file-input-btn">Fájl kiválasztása</label>
            <span class="file-name" id="uj_fajl_nev">Vagy húzd ide a fájl(oka)t</span>
            <button type="submit" class="btn btn-primary btn-sm">Feltöltés</button>
        </div>
    </form>
    <ul>
        <?php foreach ($fajlok as $f): ?>
        <li>
            <a href="<?= h(nextgen_url('finance/szamlak/letoltes.php?id=')) ?><?= (int)$f['id'] ?>"><?= h($f['eredeti_név']) ?></a>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php if (empty($fajlok)): ?><p>Nincs csatolt fájl.</p><?php endif; ?>
</div>

<div class="card">
    <h2>Csatolt számlázandó tételek</h2>
    <?php if (!empty($csatolt_szamlazando)): ?>
    <ul class="szamlazando-csatolt-lista">
        <?php foreach ($csatolt_szamlazando as $sz): ?>
        <li class="szamlazando-csatolt-item">
            <a href="<?= h(nextgen_url('finance/szamlazando/szerkeszt.php?id=')) ?><?= (int)$sz['id'] ?>"><?= h($sz['idoszakok'] ?? '–') ?></a>
            – <?= number_format((float)$sz['összeg'], 0, ',', ' ') ?> Ft
            <?php if (!empty($sz['megjegyzés'])): ?><span class="muted">(<?= h(mb_substr($sz['megjegyzés'], 0, 50)) ?><?= mb_strlen($sz['megjegyzés']) > 50 ? '…' : '' ?>)</span><?php endif; ?>
            <form method="post" class="inline-form" style="display:inline; margin-left:0.5rem;">
                <input type="hidden" name="lecsatol_szamlazando_id" value="<?= (int)$sz['id'] ?>">
                <button type="submit" class="btn btn-secondary btn-sm">Lecsatolás</button>
            </form>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <p>Ehhez a számlához nincs csatolva számlázandó tétel.</p>
    <?php endif; ?>
    <?php if (!empty($tovabbi_szamlazando)): ?>
    <form method="post" class="mt-1">
        <p><strong>További csatolása:</strong></p>
        <div class="checkbox-group">
            <?php foreach ($tovabbi_szamlazando as $sz): ?>
            <label class="checkbox-label">
                <input type="checkbox" name="hozzacsatol_szamlazando_ids[]" value="<?= (int)$sz['id'] ?>">
                <span><?= h($sz['idoszakok'] ?? '–') ?> – <?= number_format((float)$sz['összeg'], 0, ',', ' ') ?> Ft</span>
            </label>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Csatolás</button>
    </form>
    <?php endif; ?>
</div>
<script>
(function() {
    var zone = document.getElementById('drop-zone-megtekint');
    var form = document.getElementById('uj-fajl-form');
    var inp = document.getElementById('uj_fajl');
    var nev = document.getElementById('uj_fajl_nev');
    function updateFileLabel() {
        if (inp && inp.files && inp.files.length > 0) {
            nev.textContent = inp.files.length === 1 ? inp.files[0].name : inp.files.length + ' fájl kiválasztva';
            nev.classList.add('has-file');
        } else {
            nev.textContent = 'Vagy húzd ide a fájl(oka)t';
            nev.classList.remove('has-file');
        }
    }
    if (inp && nev) inp.addEventListener('change', updateFileLabel);
    if (zone && form) {
        ['dragenter', 'dragover'].forEach(function(ev) {
            zone.addEventListener(ev, function(e) { e.preventDefault(); e.stopPropagation(); zone.classList.add('is-dragover'); });
        });
        zone.addEventListener('dragleave', function(e) { e.preventDefault(); zone.classList.remove('is-dragover'); });
        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            zone.classList.remove('is-dragover');
            var files = e.dataTransfer && e.dataTransfer.files;
            if (!files || files.length === 0) return;
            var fd = new FormData();
            for (var i = 0; i < files.length; i++) fd.append('uj_fajl[]', files[i]);
            fetch(form.action, { method: 'POST', body: fd })
                .then(function(r) { if (r.redirected) window.location.href = r.url; });
        });
    }
})();
</script>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
