<?php
require_once __DIR__ . '/../../../nextgen/core/config.php';
require_once __DIR__ . '/../../../nextgen/core/database.php';
require_once __DIR__ . '/../../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../../nextgen/includes/functions.php';
requireLogin();

$szervezo_id = (int)($_GET['szervezo_id'] ?? ($_POST['szervezo_id'] ?? 0));
$szamlazando_id = (int)($_GET['szamlazando_id'] ?? ($_POST['szamlazando_id'] ?? 0));
$db = getDb();

// Ha csak szamlazando_id van, szervezo_id-t onnan vesszük
if ($szamlazando_id && !$szervezo_id) {
    $sza = $db->prepare('SELECT szervező_id FROM finance_billing_items WHERE id = ? AND (COALESCE(törölve,0) = 0)');
    $sza->execute([$szamlazando_id]);
    $row = $sza->fetch();
    if ($row) {
        $szervezo_id = (int) $row['szervező_id'];
    }
}
if (!$szervezo_id) {
    flash('error', 'Hiányzó szervező.');
    redirect(nextgen_url('organizers/'));
}

$sz = $db->prepare('SELECT id, név FROM finance_organizers WHERE id = ?');
$sz->execute([$szervezo_id]);
$szervezo = $sz->fetch(PDO::FETCH_ASSOC);
if (!$szervezo) {
    flash('error', 'Szervező nem található.');
    redirect(nextgen_url('organizers/'));
}

// Számlázandó előtöltés (összeg): ha szamlazando_id megadva és érvényes
$elotolt_osszeg = '';
$elotolt_szamlazando_ids = [];
if ($szamlazando_id) {
    $sza = $db->prepare('SELECT id, összeg FROM finance_billing_items WHERE id = ? AND szervező_id = ? AND számla_id IS NULL AND (COALESCE(törölve,0) = 0)');
    $sza->execute([$szamlazando_id, $szervezo_id]);
    $sza_row = $sza->fetch();
    if ($sza_row) {
        $elotolt_osszeg = number_format((float)$sza_row['összeg'], 0, ',', ' ');
        $elotolt_szamlazando_ids[] = (int)$sza_row['id'];
    }
}

// Számlázási címek a szervezőhöz (primary előrébb)
$cimek = $db->prepare('SELECT * FROM finance_billing_addresses WHERE szervező_id = ? ORDER BY alapértelmezett DESC, név');
$cimek->execute([$szervezo_id]);
$cimek = $cimek->fetchAll(PDO::FETCH_ASSOC);

// Számlázandó tételek ehhez a szervezőhöz, még nincs számlához rendelve
$szamlazando_lista = $db->prepare("
    SELECT s.id, s.összeg, s.megjegyzés,
           (SELECT GROUP_CONCAT(CONCAT(si.év, '-', LPAD(si.hónap, 2, '0')) ORDER BY si.év, si.hónap)
            FROM finance_billing_periods si WHERE si.számlázandó_id = s.id) AS idoszakok
    FROM finance_billing_items s
    WHERE s.szervező_id = ? AND s.számla_id IS NULL AND (COALESCE(s.törölve,0) = 0)
    ORDER BY s.létrehozva DESC
");
$szamlazando_lista->execute([$szervezo_id]);
$szamlazando_lista = $szamlazando_lista->fetchAll(PDO::FETCH_ASSOC);

$hiba = '';
$form_osszeg = $_POST['összeg'] ?? $elotolt_osszeg;
$form_szamlazando_ids = isset($_POST['szamlazando_ids']) && is_array($_POST['szamlazando_ids']) ? array_map('intval', $_POST['szamlazando_ids']) : $elotolt_szamlazando_ids;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $szamla_szam = trim($_POST['számla_szám'] ?? '');
    $datum = trim($_POST['dátum'] ?? '');
    $osszeg = str_replace([' ', ','], ['', '.'], $_POST['összeg'] ?? '0');
    $belso = trim($_POST['belső_megjegyzés'] ?? '');
    $statusz = in_array($_POST['státusz'] ?? '', ['generált', 'kiküldve', 'kiegyenlítve', 'egyéb', 'KP', 'sztornó'], true) ? $_POST['státusz'] : 'generált';

    if ($szamla_szam === '' || $datum === '') {
        $hiba = 'Szám és dátum megadása kötelező.';
    } else {
        $db->beginTransaction();
        try {
            $db->prepare('INSERT INTO finance_invoices (szervező_id, számla_szám, dátum, összeg, belső_megjegyzés, státusz) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$szervezo_id, $szamla_szam, $datum, $osszeg, $belso ?: null, $statusz]);
            $szamla_id = (int) $db->lastInsertId();
            rendszer_log('számla', $szamla_id, 'Létrehozva', null);

            // Számlázandó tételek csatolása
            $csatol_ids = isset($_POST['szamlazando_ids']) && is_array($_POST['szamlazando_ids'])
                ? array_map('intval', $_POST['szamlazando_ids']) : [];
            if (!empty($csatol_ids)) {
                $placeholders = implode(',', array_fill(0, count($csatol_ids), '?'));
                $stmt = $db->prepare("UPDATE finance_billing_items SET számla_id = ? WHERE id IN ($placeholders) AND szervező_id = ? AND számla_id IS NULL AND (COALESCE(törölve,0) = 0)");
                $stmt->execute(array_merge([$szamla_id], $csatol_ids, [$szervezo_id]));
            }

            if (!is_dir(UPLOAD_PATH)) {
                @mkdir(UPLOAD_PATH, 0755, true);
            }
            $upload_dir = UPLOAD_PATH . '/' . $szamla_id;
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0755, true);
            }
            $fajlok = $_FILES['fajlok'] ?? [];
            if (!empty($fajlok['name'][0])) {
                foreach ($fajlok['name'] as $i => $name) {
                    if ($fajlok['error'][$i] === UPLOAD_ERR_OK && $name) {
                        $ext = pathinfo($name, PATHINFO_EXTENSION) ?: 'bin';
                        $ujnev = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($name)) ?: 'file_' . $i . '.' . $ext;
                        $cel = $upload_dir . '/' . $ujnev;
                        if (move_uploaded_file($fajlok['tmp_name'][$i], $cel)) {
                            $db->prepare('INSERT INTO finance_invoice_files (számla_id, eredeti_név, fájl_útvonal) VALUES (?, ?, ?)')
                                ->execute([$szamla_id, $name, $szamla_id . '/' . $ujnev]);
                        }
                    }
                }
            }
            $db->commit();
            flash('success', 'Számla mentve.');
            redirect(nextgen_url('finance/szamlak/szerkeszt.php?id=') . $szamla_id);
        } catch (Exception $e) {
            $db->rollBack();
            $hiba = 'Hiba: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Új számla';
require_once __DIR__ . '/../../partials/header.php';
?>
<div class="card">
    <h2>Új számla</h2>
    <p><a href="<?= h(nextgen_url('organizers/megtekint.php?id=')) ?><?= $szervezo_id ?>">← <?= h($szervezo['név']) ?></a></p>
    <?php if (!empty($cimek)): ?>
    <div class="szamlazasi-cimek-blokk">
        <h3>Számlázási címek</h3>
        <ul class="szamlazasi-cimek-lista">
            <?php foreach ($cimek as $c): ?>
            <li class="szamlazasi-cim-item <?= $c['alapértelmezett'] ? 'primary' : '' ?>">
                <span class="cim-item-content">
                    <strong><?= h($c['név']) ?></strong><br>
                    <?= h($c['irsz']) ?> <?= h($c['település']) ?>, <?= h($c['cím']) ?><br>
                    <?= h($c['ország']) ?><?= !empty($c['adószám']) ? ' · Adószám: ' . h($c['adószám']) : '' ?>
                </span>
                <?php if ($c['alapértelmezett']): ?><span class="cim-badge">Alapértelmezett</span><?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    <?php if ($hiba): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post" enctype="multipart/form-data" action="">
        <input type="hidden" name="szervezo_id" value="<?= (int)$szervezo_id ?>">
        <input type="hidden" name="szamlazando_id" value="<?= (int)$szamlazando_id ?>">
        <div class="form-group"><label>Számlaszám *</label><input type="text" id="szamla_szam" name="számla_szám" value="<?= h($_POST['számla_szám'] ?? '') ?>" required></div>
        <div class="form-group"><label>Dátum *</label><input type="date" name="dátum" value="<?= h($_POST['dátum'] ?? date('Y-m-d')) ?>" required></div>
        <div class="form-group"><label>Összeg *</label><input type="text" name="összeg" value="<?= h($form_osszeg) ?>" placeholder="0" inputmode="decimal" required></div>
        <div class="form-group"><label>Belső megjegyzés</label><textarea name="belső_megjegyzés" rows="2"><?= h($_POST['belső_megjegyzés'] ?? '') ?></textarea></div>
        <div class="form-group">
            <label>Státusz</label>
            <select name="státusz">
                <option value="generált" <?= ($_POST['státusz'] ?? '') === 'generált' ? 'selected' : '' ?>>Generált</option>
                <option value="kiküldve" <?= ($_POST['státusz'] ?? '') === 'kiküldve' ? 'selected' : '' ?>>Kiküldve</option>
                <option value="kiegyenlítve" <?= ($_POST['státusz'] ?? '') === 'kiegyenlítve' ? 'selected' : '' ?>>Kiegyenlítve</option>
                <option value="egyéb" <?= ($_POST['státusz'] ?? '') === 'egyéb' ? 'selected' : '' ?>>Egyéb</option>
                <option value="KP" <?= ($_POST['státusz'] ?? '') === 'KP' ? 'selected' : '' ?>>KP</option>
                <option value="sztornó" <?= ($_POST['státusz'] ?? '') === 'sztornó' ? 'selected' : '' ?>>Sztornó</option>
            </select>
        </div>
        <div class="form-group">
            <label>Fájl csatolmányok (több is)</label>
            <div class="file-upload-wrap file-drop-zone" id="drop-zone-letrehoz">
                <input type="file" name="fajlok[]" id="fajlok" class="file-input-native" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                <label for="fajlok" class="file-input-btn">Fájl kiválasztása</label>
                <span class="file-name" id="fajlok_nev">Vagy húzd ide a fájl(oka)t</span>
            </div>
        </div>
        <?php if (!empty($szamlazando_lista)): ?>
        <div class="form-group">
            <label>Számlázandó tétel(ek) csatolása</label>
            <p class="form-hint">Kijelölheted, mely finance_billing_items tételek tartoznak ehhez a számlához.</p>
            <div class="checkbox-group">
                <?php foreach ($szamlazando_lista as $sz): ?>
                <label class="checkbox-label">
                    <input type="checkbox" name="szamlazando_ids[]" value="<?= (int)$sz['id'] ?>" <?= in_array((int)$sz['id'], $form_szamlazando_ids, true) ? 'checked' : '' ?>>
                    <span><?= h($sz['idoszakok'] ?? '–') ?> – <?= number_format((float)$sz['összeg'], 0, ',', ' ') ?> Ft<?= !empty($sz['megjegyzés']) ? ' (' . h(mb_substr($sz['megjegyzés'], 0, 40)) . (mb_strlen($sz['megjegyzés']) > 40 ? '…' : '') . ')' : '' ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Mentés</button>
            <a href="<?= h(nextgen_url('organizers/megtekint.php?id=')) ?><?= $szervezo_id ?>" class="btn btn-secondary">Mégse</a>
        </div>
    </form>
</div>
<script>
(function() {
    var form = document.querySelector('form[enctype="multipart/form-data"]');
    var szamlaInput = document.getElementById('szamla_szam');
    var fileInput = document.getElementById('fajlok');
    var nevEl = document.getElementById('fajlok_nev');
    var zone = document.getElementById('drop-zone-letrehoz');

    function updateLabel() {
        var total = (fileInput && fileInput.files ? fileInput.files.length : 0);
        if (nevEl) {
            if (total > 0) {
                nevEl.textContent = total === 1 ? fileInput.files[0].name : total + ' fájl kiválasztva';
                nevEl.classList.add('has-file');
            } else {
                nevEl.textContent = 'Vagy húzd ide a fájl(oka)t';
                nevEl.classList.remove('has-file');
            }
        }
    }
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (szamlaInput && !szamlaInput.value.trim() && this.files && this.files.length > 0) {
                var name = this.files[0].name;
                var lastDot = name.lastIndexOf('.');
                szamlaInput.value = lastDot > 0 ? name.substring(0, lastDot) : name;
            }
            updateLabel();
        });
    }
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
            // A ráhúzott fájlokat tegyük bele a natív inputba, hogy normál POST-tal menjen.
            if (fileInput && window.DataTransfer) {
                var dt = new DataTransfer();
                for (var i = 0; i < files.length; i++) dt.items.add(files[i]);
                fileInput.files = dt.files;
            }
            if (szamlaInput && !szamlaInput.value.trim() && files[0]) {
                var name = files[0].name;
                var lastDot = name.lastIndexOf('.');
                szamlaInput.value = lastDot > 0 ? name.substring(0, lastDot) : name;
            }
            updateLabel();
        });
    }
})();
</script>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
