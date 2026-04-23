<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/nextgen/includes/auth.php';
require_once __DIR__ . '/lib/csv_import_schema.php';
require_once __DIR__ . '/lib/csv_import_engine.php';
require_once __DIR__ . '/lib/import_settings.php';
requireLogin();

$schema = events_csv_import_schema();
$db = getDb();
$presets = events_import_settings_load_all($db);

$hiba = '';
$eredmeny = null;
$table = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'import');
    $table = trim((string) ($_POST['target_table'] ?? ''));
    if (!isset($schema[$table])) {
        $hiba = 'Érvénytelen cél tábla.';
    } else {
        $delimiter = (string) ($_POST['delimiter'] ?? ';');
        if (!in_array($delimiter, [',', ';', 'tab'], true)) {
            $delimiter = ';';
        }
        $requiredSubstring = trim((string) ($_POST['required_substring'] ?? ''));
        $mapPost = $_POST['map'] ?? [];
        if (!is_array($mapPost)) {
            $mapPost = [];
        }
        $map = [];
        foreach ($schema[$table]['columns'] as $col => $_meta) {
            if (isset($mapPost[$table][$col])) {
                $map[$col] = (string) $mapPost[$table][$col];
            }
        }

        if ($action === 'save_preset') {
            $columnMap = [];
            foreach ($map as $dbCol => $csvHeader) {
                $csvHeader = trim($csvHeader);
                if ($csvHeader !== '') {
                    $columnMap[$dbCol] = $csvHeader;
                }
            }
            try {
                events_import_settings_save($db, $table, $delimiter, $requiredSubstring, $columnMap);
                $presets = events_import_settings_load_all($db);
                flash('success', 'Import beállítások elmentve ehhez a cél táblához: ' . $table . '.');
                redirect(events_url('import_csv.php?target_table=' . rawurlencode($table)));
            } catch (Throwable $e) {
                $hiba = 'Mentési hiba: ' . $e->getMessage();
            }
        } else {
            if (!isset($_FILES['csv_file']) || !is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
                $hiba = 'Válassz CSV fájlt.';
            } elseif (($_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $hiba = 'Fájlfeltöltési hiba.';
            } elseif (($_FILES['csv_file']['size'] ?? 0) > 15 * 1024 * 1024) {
                $hiba = 'A fájl maximum 15 MB lehet.';
            } else {
                $uploadName = (string) ($_FILES['csv_file']['name'] ?? '');
                $tmp = (string) $_FILES['csv_file']['tmp_name'];
                if ($requiredSubstring !== '') {
                    $bn = basename(str_replace('\\', '/', $uploadName));
                    if (mb_strpos($bn, $requiredSubstring, 0, 'UTF-8') === false) {
                        $hiba = 'A fájlnévnek tartalmaznia kell ezt a szövegrészletet: "' . $requiredSubstring . '". Válassz másik fájlt vagy módosítsd a követelményt.';
                    }
                }
                if ($hiba === '') {
                    $eredmeny = events_csv_import_run($db, $table, $tmp, $delimiter, $requiredSubstring, $uploadName, $map);
                    $ins = (int) ($eredmeny['inserted'] ?? 0);
                    $upd = (int) ($eredmeny['updated'] ?? 0);
                    $errs = $eredmeny['errors'] ?? [];
                    $skipped = $eredmeny['skipped'] ?? [];
                    if ($ins + $upd > 0 || $skipped !== [] || $errs !== []) {
                        rendszer_log('csv_import', null, 'CSV import', $table . ': +' . $ins . ' / ~' . $upd . ' sor, kihagyva: ' . count($skipped) . ', hibák: ' . count($errs));
                    }
                }
            }
        }
    }
}

$formTargetTable = trim((string) ($_GET['target_table'] ?? ''));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($schema[$table])) {
    $formTargetTable = $table;
}
if ($formTargetTable === '' || !isset($schema[$formTargetTable])) {
    foreach ($schema as $k => $_) {
        $formTargetTable = $k;
        break;
    }
}
$pForm = $presets[$formTargetTable] ?? [];
$delRaw = (string) ($pForm['delimiter'] ?? ';');
$delVal = in_array($delRaw, [',', ';', 'tab'], true) ? $delRaw : ';';
$subVal = (string) ($pForm['required_substring'] ?? '');

$pageTitle = 'CSV import';
require_once dirname(__DIR__) . '/nextgen/partials/header.php';

$presetsJson = json_encode($presets, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if ($presetsJson === false) {
    $presetsJson = '{}';
}
?>
<div class="card">
    <h2>CSV import (események, esemény–szervező, szervezők)</h2>
    <p class="card-lead">
        Alapértelmezett elválasztó: <strong>pontosvessző (;)</strong>. A mentett mapping és egyéb beállítások <strong>cél táblánként</strong> tárolódnak – válaszd a táblát, állítsd be, majd „Beállítások mentése”.
        Tölts fel UTF‑8 CSV-t. <strong>Események / szervezők táblánál:</strong> ha a sorban van ID (≤ <?= (int) ($schema['events_calendar_events']['id_max_import'] ?? 100000) ?>) és már létezik a sor → UPDATE, egyébként INSERT.
        <strong>Esemény–szervező táblánál:</strong> minden sor <code>event_id</code> + <code>organizer_id</code> (mindkettő létező ID); ha a pár már létezik → <code>sort_order</code> frissül, egyébként új kapcsolat.
    </p>
    <?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
    <?php if ($hiba): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>

    <?php if ($eredmeny !== null): ?>
        <div class="alert alert-success">
            <p><strong>Kész.</strong> Beszúrva: <?= (int) ($eredmeny['inserted'] ?? 0) ?>, frissítve: <?= (int) ($eredmeny['updated'] ?? 0) ?>.</p>
            <?php
            $errs = $eredmeny['errors'] ?? [];
            $skipped = $eredmeny['skipped'] ?? [];
            ?>
            <?php if ($skipped !== []): ?>
                <p><strong>Kihagyott tételek (<?= count($skipped) ?>)</strong> – a sor nem került be / nem módosult; ok:</p>
                <ul class="csv-import-report-list" style="max-height:18rem;overflow:auto;">
                    <?php foreach (array_slice($skipped, 0, 500) as $s): ?>
                        <li><?= h($s) ?></li>
                    <?php endforeach; ?>
                    <?php if (count($skipped) > 500): ?>
                        <li>… további <?= count($skipped) - 500 ?> tétel.</li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
            <?php if ($errs !== []): ?>
                <p><strong>Hibák (<?= count($errs) ?>)</strong> – az importot megállító vagy globális probléma:</p>
                <ul class="csv-import-report-list" style="max-height:12rem;overflow:auto;">
                    <?php foreach (array_slice($errs, 0, 200) as $e): ?>
                        <li><?= h($e) ?></li>
                    <?php endforeach; ?>
                    <?php if (count($errs) > 200): ?>
                        <li>… további <?= count($errs) - 200 ?> üzenet.</li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="csv-import-form" id="csv-import-form">
        <input type="hidden" name="action" id="import_action" value="import">
        <div class="form-group">
            <label for="target_table">Cél tábla *</label>
            <select id="target_table" name="target_table" required>
                <?php foreach ($schema as $tbl => $info): ?>
                    <option value="<?= h($tbl) ?>" <?= $formTargetTable === $tbl ? ' selected' : '' ?>><?= h($info['label'] ?? $tbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="delimiter">Elválasztó</label>
            <select id="delimiter" name="delimiter">
                <option value=";"<?= $delVal === ';' ? ' selected' : '' ?>>pontosvessző (;)</option>
                <option value="<?= h(',') ?>"<?= $delVal === ',' ? ' selected' : '' ?>>vessző (,)</option>
                <option value="tab"<?= $delVal === 'tab' ? ' selected' : '' ?>>tab</option>
            </select>
        </div>
        <div class="form-group">
            <label for="required_substring">Kötelező szövegrészlet a fájlnévben</label>
            <input type="text" id="required_substring" name="required_substring" value="<?= h($subVal) ?>" maxlength="500" placeholder="pl. import vagy _esemeny_ – üres = nincs ellenőrzés">
            <p class="help">Ha kitöltöd, csak olyan fájl választható / tölthető fel, amelynek a nevében szerepel ez a szöveg (UTF‑8, a kliensen is ellenőrizzük).</p>
        </div>
        <div class="form-group">
            <label for="csv_file">CSV fájl</label>
            <input type="file" id="csv_file" name="csv_file" accept=".csv,text/csv,text/plain">
            <p class="help">Import futtatásához kötelező; a beállítások mentéséhez nem kell fájl. A fájlnév követelmény bekapcsolva esetén érvénytelen fájl kiválasztása nem marad meg.</p>
        </div>

        <?php foreach ($schema as $tbl => $info): ?>
        <?php
            $p = $presets[$tbl] ?? null;
            $pMap = is_array($p) ? ($p['map'] ?? []) : [];
        ?>
        <div class="csv-mapping-block" data-table="<?= h($tbl) ?>" style="display:none;">
            <h3>Oszlop mapping: <?= h($info['label'] ?? $tbl) ?></h3>
            <p class="help">A „CSV oszlop neve” pontosan egyezzen a fájl első sorában lévő fejléccel.</p>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Adatbázis oszlop</th><th>Típus / megjegyzés</th><th>CSV oszlop neve (fejléc)</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($info['columns'] as $col => $meta): ?>
                        <tr>
                            <td><code><?= h($col) ?></code></td>
                            <td>
                                <?= h($meta['type'] ?? '') ?>
                                <?php if (!empty($meta['note'])): ?><br><span class="help"><?= h($meta['note']) ?></span><?php endif; ?>
                            </td>
                            <td>
                                <input type="text" class="csv-map-input" data-tbl="<?= h($tbl) ?>" data-col="<?= h($col) ?>" name="map[<?= h($tbl) ?>][<?= h($col) ?>]" value="<?= h($pMap[$col] ?? '') ?>" placeholder="pl. EventTitle" autocomplete="off">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="form-actions">
            <button type="submit" class="btn btn-secondary" id="btn-save-preset">Beállítások mentése (cél tábla szerint)</button>
            <button type="submit" class="btn btn-primary" id="btn-import">Import futtatása</button>
            <a href="<?= h(events_url('events_admin.php')) ?>" class="btn btn-secondary">Vissza a listához</a>
        </div>
    </form>
</div>
<script>
(function () {
    var PRESETS = <?= $presetsJson ?>;
    var form = document.getElementById('csv-import-form');
    var sel = document.getElementById('target_table');
    var del = document.getElementById('delimiter');
    var sub = document.getElementById('required_substring');
    var blocks = document.querySelectorAll('.csv-mapping-block');
    var actionInput = document.getElementById('import_action');
    var btnSave = document.getElementById('btn-save-preset');
    var btnImport = document.getElementById('btn-import');
    var fileInput = document.getElementById('csv_file');

    function filenameMatchesRequirement() {
        var req = sub && sub.value ? sub.value.trim() : '';
        if (!req) return true;
        var f = fileInput && fileInput.files && fileInput.files[0];
        if (!f) return true;
        return f.name.indexOf(req) !== -1;
    }

    function validateFileNameOrClear() {
        var req = sub && sub.value ? sub.value.trim() : '';
        if (!req || !fileInput || !fileInput.files || !fileInput.files[0]) return true;
        var name = fileInput.files[0].name;
        if (name.indexOf(req) === -1) {
            alert('A fájlnévnek tartalmaznia kell: "' + req + '"\nKiválasztott fájl: ' + name);
            fileInput.value = '';
            return false;
        }
        return true;
    }

    function applyPreset(table) {
        var p = PRESETS[table] || {};
        var allowed = { ';': 1, ',': 1, tab: 1 };
        if (del) {
            del.value = (p.delimiter && allowed[p.delimiter]) ? p.delimiter : ';';
        }
        if (sub) {
            sub.value = typeof p.required_substring === 'string' ? p.required_substring : '';
        }
        var m = p.map || {};
        document.querySelectorAll('.csv-map-input[data-tbl="' + table + '"]').forEach(function (inp) {
            var col = inp.getAttribute('data-col');
            if (!col) return;
            inp.value = m[col] != null ? String(m[col]) : '';
        });
    }

    function syncBlocks() {
        var t = sel.value;
        blocks.forEach(function (b) {
            b.style.display = b.getAttribute('data-table') === t ? 'block' : 'none';
        });
        applyPreset(t);
    }

    sel.addEventListener('change', syncBlocks);
    syncBlocks();

    if (fileInput) {
        fileInput.addEventListener('change', validateFileNameOrClear);
    }
    if (sub) {
        sub.addEventListener('input', function () {
            if (fileInput && fileInput.files && fileInput.files[0]) {
                validateFileNameOrClear();
            }
        });
    }
    if (form) {
        form.addEventListener('submit', function (e) {
            if (actionInput.value !== 'import') return;
            if (!filenameMatchesRequirement()) {
                e.preventDefault();
                alert('A fájlnév nem felel meg a kötelező szövegrészletnek. Válassz megfelelő fájlt, vagy ürítsd a követelmény mezőt.');
            }
        });
    }

    btnSave.addEventListener('click', function () {
        actionInput.value = 'save_preset';
        fileInput.removeAttribute('required');
    });
    btnImport.addEventListener('click', function () {
        actionInput.value = 'import';
        fileInput.setAttribute('required', 'required');
    });
})();
</script>
<?php require_once dirname(__DIR__) . '/nextgen/partials/footer.php'; ?>
