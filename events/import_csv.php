<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/nextgen/includes/auth.php';
require_once __DIR__ . '/lib/csv_import_schema.php';
require_once __DIR__ . '/lib/csv_import_engine.php';
require_once __DIR__ . '/lib/import_settings.php';
require_once __DIR__ . '/lib/import_presets.php';
require_once __DIR__ . '/lib/csv_import_types.php';
requireLogin();

$schema = events_csv_import_schema();
$importTypes = events_csv_import_types();
$db = getDb();
events_import_seed_builtin_presets($db);
$presets = events_import_presets_merged($db);
$sampleCsvFiles = events_import_sample_csv_files();

$sampleKey = trim((string) ($_GET['sample'] ?? ''));
if ($sampleKey !== '' && isset($sampleCsvFiles[$sampleKey])) {
    $sample = $sampleCsvFiles[$sampleKey];
    header('Content-Type: ' . (string) ($sample['mime'] ?? 'text/csv'));
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', (string) ($sample['filename'] ?? 'minta.csv')) . '"');
    echo (string) ($sample['content'] ?? '');
    exit;
}

$hiba = '';
$eredmeny = null;
$typeId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('events_import_csv')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet. Töltsd újra az oldalt.';
    } else {
    $action = (string) ($_POST['action'] ?? 'import');
    $typeId = trim((string) ($_POST['target_table'] ?? ''));
    $typeInfo = events_csv_import_resolve_type($typeId);
    $dbTable = is_array($typeInfo) ? (string) ($typeInfo['target_table'] ?? '') : '';

    if ($action === 'purge_preview') {
        if ($typeInfo === null || $dbTable === '' || !isset($schema[$dbTable])) {
            $hiba = 'Érvénytelen import típus.';
        } else {
            try {
                $cnt = events_csv_import_count_rows($db, $dbTable);
                $_SESSION['csv_import_purge'] = [
                    'type_id' => $typeId,
                    'table' => $dbTable,
                    'count' => $cnt,
                    'token' => bin2hex(random_bytes(16)),
                ];
                redirect(events_url('import_csv.php?target_table=' . rawurlencode($typeId) . '&purge_step=confirm'));
            } catch (Throwable $e) {
                error_log('events import_csv purge_preview hiba: ' . $e->getMessage());
                $hiba = 'Törlés előnézet hiba történt.';
            }
        }
    } elseif ($action === 'purge_cancel') {
        unset($_SESSION['csv_import_purge']);
        $redir = events_url('import_csv.php');
        if ($typeInfo !== null) {
            $redir .= '?target_table=' . rawurlencode($typeId);
        }
        redirect($redir);
    } elseif ($action === 'purge_execute') {
        $token = (string) ($_POST['purge_token'] ?? '');
        $sess = $_SESSION['csv_import_purge'] ?? null;
        if ($typeInfo === null || $dbTable === '' || !isset($schema[$dbTable])) {
            $hiba = 'Érvénytelen import típus.';
        } elseif (!is_array($sess) || ($sess['type_id'] ?? '') !== $typeId || !isset($sess['token']) || !hash_equals((string) $sess['token'], $token)) {
            $hiba = 'A törlési jóváhagyás érvénytelen vagy lejárt. Indítsd újra az „Összes sor törlése…” lépést.';
            unset($_SESSION['csv_import_purge']);
        } else {
            try {
                $purgeTable = (string) ($sess['table'] ?? $dbTable);
                $n = events_csv_import_delete_all_rows($db, $purgeTable);
                unset($_SESSION['csv_import_purge']);
                rendszer_log('csv_import', null, 'CSV tábla teljes ürítés', $purgeTable . ': törölve ' . $n . ' sor');
                flash('success', 'Törölve: ' . $n . ' sor a „' . $purgeTable . '” táblából.');
                redirect(events_url('import_csv.php?target_table=' . rawurlencode($typeId)));
            } catch (Throwable $e) {
                error_log('events import_csv purge_execute hiba: ' . $e->getMessage());
                $hiba = 'Törlés hiba történt.';
            }
        }
    } elseif ($typeInfo === null || $dbTable === '' || !isset($schema[$dbTable])) {
        $hiba = 'Érvénytelen import típus.';
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
        foreach ($schema[$dbTable]['columns'] as $col => $_meta) {
            if (isset($mapPost[$typeId][$col])) {
                $map[$col] = (string) $mapPost[$typeId][$col];
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
                events_import_settings_save($db, $typeId, $delimiter, $requiredSubstring, $columnMap);
                $presets = events_import_presets_merged($db);
                $typeLabel = (string) ($typeInfo['option_label'] ?? $typeId);
                flash('success', 'Import beállítások elmentve: ' . $typeLabel . '.');
                redirect(events_url('import_csv.php?target_table=' . rawurlencode($typeId)));
            } catch (Throwable $e) {
                error_log('events import_csv save_preset hiba: ' . $e->getMessage());
                $hiba = 'Mentési hiba történt.';
            }
        } elseif ($action === 'import') {
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
                    $eredmeny = events_csv_import_run($db, $dbTable, $tmp, $delimiter, $requiredSubstring, $uploadName, $map, $typeId);
                    $ins = (int) ($eredmeny['inserted'] ?? 0);
                    $upd = (int) ($eredmeny['updated'] ?? 0);
                    $errs = $eredmeny['errors'] ?? [];
                    $skipped = $eredmeny['skipped'] ?? [];
                    if ($ins + $upd > 0 || $skipped !== [] || $errs !== []) {
                        rendszer_log('csv_import', null, 'CSV import', $typeId . ' → ' . $dbTable . ': +' . $ins . ' / ~' . $upd . ' sor, kihagyva: ' . count($skipped) . ', hibák: ' . count($errs));
                    }
                }
            }
        } else {
            $hiba = 'Ismeretlen művelet.';
        }
    }
    }
}

$formTargetType = trim((string) ($_GET['target_table'] ?? ''));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && events_csv_import_resolve_type($typeId) !== null) {
    $formTargetType = $typeId;
}
if ($formTargetType === '' || events_csv_import_resolve_type($formTargetType) === null) {
    foreach ($importTypes as $k => $_) {
        $formTargetType = $k;
        break;
    }
}
$formTypeInfo = events_csv_import_resolve_type($formTargetType) ?? [];
$formDbTable = (string) ($formTypeInfo['target_table'] ?? '');
$pForm = $presets[$formTargetType] ?? [];
$delRaw = (string) ($pForm['delimiter'] ?? ';');
$delVal = in_array($delRaw, [',', ';', 'tab'], true) ? $delRaw : ';';
$subVal = (string) ($pForm['required_substring'] ?? '');
if ($subVal === '') {
    $subVal = events_csv_import_default_required_substring($formTargetType);
}

$purgeSess = $_SESSION['csv_import_purge'] ?? null;
if (isset($_GET['purge_step']) && (string) $_GET['purge_step'] === 'confirm') {
    if (!is_array($purgeSess) || ($purgeSess['type_id'] ?? '') !== $formTargetType) {
        unset($_SESSION['csv_import_purge']);
        redirect(events_url('import_csv.php?target_table=' . rawurlencode($formTargetType)));
    }
}
$purgeSess = $_SESSION['csv_import_purge'] ?? null;
$showPurgeConfirm = isset($_GET['purge_step']) && (string) $_GET['purge_step'] === 'confirm'
    && is_array($purgeSess)
    && ($purgeSess['type_id'] ?? '') === $formTargetType
    && isset($purgeSess['count'], $purgeSess['token']);

$pageTitle = 'CSV import';
require_once dirname(__DIR__) . '/nextgen/partials/header.php';

$presetsJson = json_encode($presets, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if ($presetsJson === false) {
    $presetsJson = '{}';
}
$importTypesJson = json_encode($importTypes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if ($importTypesJson === false) {
    $importTypesJson = '{}';
}
?>
<div class="card">
    <h2>CSV import (események, címkék, szervezők, helyszínek, kategóriák, kapcsolótáblák)</h2>
    <?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
    <?php if ($hiba): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>

    <?php if (!empty($showPurgeConfirm) && is_array($purgeSess)): ?>
        <?php
        $purgeTbl = (string) $purgeSess['table'];
        $purgeCnt = (int) $purgeSess['count'];
        $purgeTypeId = (string) ($purgeSess['type_id'] ?? $formTargetType);
        $purgeType = events_csv_import_resolve_type($purgeTypeId);
        $purgeLbl = is_array($purgeType) ? (string) ($purgeType['option_label'] ?? $purgeTbl) : (string) ($schema[$purgeTbl]['label'] ?? $purgeTbl);
        ?>
        <div class="alert alert-error csv-import-purge-confirm" role="alert">
            <p><strong>Figyelem – visszavonhatatlan törlés</strong></p>
            <p>
                A kiválasztott cél tábla: <strong><?= h($purgeLbl) ?></strong> (<code><?= h($purgeTbl) ?></code>).<br>
                Jelenleg <strong><?= $purgeCnt ?></strong> sor van a táblában; a végleges törlés <strong>mindegyiket</strong> eltávolítja.
            </p>
            <div class="csv-import-purge-actions" style="margin-top:1rem;display:flex;flex-wrap:wrap;gap:0.75rem;align-items:center;">
                <form method="post" style="display:inline;">
                    <?= csrf_input('events_import_csv') ?>
                    <input type="hidden" name="action" value="purge_execute">
                    <input type="hidden" name="target_table" value="<?= h($purgeTypeId) ?>">
                    <input type="hidden" name="purge_token" value="<?= h((string) $purgeSess['token']) ?>">
                    <button type="submit" class="btn btn-danger" data-purge-count="<?= $purgeCnt ?>">Igen, töröld mind a <?= $purgeCnt ?> sort</button>
                </form>
                <form method="post" style="display:inline;">
                    <?= csrf_input('events_import_csv') ?>
                    <input type="hidden" name="action" value="purge_cancel">
                    <input type="hidden" name="target_table" value="<?= h($purgeTypeId) ?>">
                    <button type="submit" class="btn btn-secondary">Mégse</button>
                </form>
            </div>
        </div>
        <script>
        (function () {
            var b = document.querySelector('.csv-import-purge-confirm .btn-danger[data-purge-count]');
            if (!b) return;
            b.addEventListener('click', function (e) {
                var n = b.getAttribute('data-purge-count') || '0';
                if (!confirm('VÉGLEGES törlés: ' + n + ' sor törlődik. Biztosan folytatod?')) {
                    e.preventDefault();
                }
            });
        })();
        </script>
    <?php endif; ?>

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
        <?= csrf_input('events_import_csv') ?>
        <input type="hidden" name="action" id="import_action" value="import">
        <div class="form-group">
            <label for="target_table">Import típus *</label>
            <select id="target_table" name="target_table" required>
                <?php foreach ($importTypes as $tid => $tinfo): ?>
                    <option value="<?= h($tid) ?>" <?= $formTargetType === $tid ? ' selected' : '' ?>><?= h((string) ($tinfo['option_label'] ?? $tid)) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="help">Minden típushoz 3 jegyű azonosító tartozik (pl. <code>012</code>). A fájlnévben alapból ez a szám szerepel.</p>
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
            <input type="text" id="required_substring" name="required_substring" value="<?= h($subVal) ?>" maxlength="500" placeholder="pl. 012 – üres = nincs ellenőrzés">
            <p class="help">Alapértelmezés: az import típus 3 jegyű kódja. Üres mező = nincs fájlnév-ellenőrzés.</p>
        </div>
        <div class="form-group">
            <label for="csv_file">CSV fájl</label>
            <input type="file" id="csv_file" name="csv_file" accept=".csv,text/csv,text/plain">
            <p class="help">Import futtatásához kötelező; a beállítások mentéséhez nem kell fájl. A fájlnév követelmény bekapcsolva esetén érvénytelen fájl kiválasztása nem marad meg.</p>
        </div>

        <?php foreach ($importTypes as $tid => $tinfo): ?>
        <?php
            $tbl = (string) ($tinfo['target_table'] ?? '');
            if ($tbl === '' || !isset($schema[$tbl])) {
                continue;
            }
            $info = $schema[$tbl];
            $p = $presets[$tid] ?? null;
            $pMap = is_array($p) ? ($p['map'] ?? []) : [];
            $samplePresetId = (string) ($tinfo['preset_id'] ?? $tid);
        ?>
        <div class="csv-mapping-block" data-type-id="<?= h($tid) ?>" style="display:none;">
            <h3>Oszlop mapping: <?= h((string) ($tinfo['option_label'] ?? $tid)) ?></h3>
            <p class="help">A „CSV oszlop neve” pontosan egyezzen a fájl első sorában lévő fejléccel.</p>
            <?php if (isset($sampleCsvFiles[$samplePresetId])): ?>
                <p class="csv-import-sample-link">
                    <a class="btn btn-secondary btn-sm" href="<?= h(events_url('import_csv.php?sample=' . rawurlencode($samplePresetId))) ?>">Minta CSV letöltése</a>
                </p>
            <?php endif; ?>
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
                                <input type="text" class="csv-map-input" data-type-id="<?= h($tid) ?>" data-col="<?= h($col) ?>" name="map[<?= h($tid) ?>][<?= h($col) ?>]" value="<?= h($pMap[$col] ?? '') ?>" placeholder="pl. EventTitle" autocomplete="off">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="form-actions">
            <button type="submit" class="btn btn-secondary" id="btn-save-preset">Beállítások mentése (import típus szerint)</button>
            <button type="submit" class="btn btn-primary" id="btn-import">Import futtatása</button>
            <a href="<?= h(events_url('events_admin.php')) ?>" class="btn btn-secondary">Vissza a listához</a>
        </div>
    </form>

    <div class="csv-import-purge-panel" style="margin-top:1.75rem;padding-top:1.25rem;border-top:1px solid var(--border);">
        <h3 style="margin-top:0;">Teljes tábla ürítése</h3>
        <p class="help" style="margin-bottom:0.75rem;">A kiválasztott import típushoz tartozó <strong>adatbázis táblából</strong> minden sor törlődik (FK-k miatt kapcsolt sorok is törlődhetnek / nullázódhatnak). Először megjelenik a törlendő sorok száma, majd megerősítheted.</p>
        <form method="post" id="csv-purge-preview-form">
            <?= csrf_input('events_import_csv') ?>
            <input type="hidden" name="action" value="purge_preview">
            <input type="hidden" name="target_table" id="purge_preview_target_table" value="">
            <button type="submit" class="btn btn-danger">Összes sor törlése…</button>
        </form>
    </div>
</div>
<script>
(function () {
    var PRESETS = <?= $presetsJson ?>;
    var IMPORT_TYPES = <?= $importTypesJson ?>;
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

    function applyPreset(typeId) {
        var p = PRESETS[typeId] || {};
        var t = IMPORT_TYPES[typeId] || {};
        var allowed = { ';': 1, ',': 1, tab: 1 };
        if (del) {
            del.value = (p.delimiter && allowed[p.delimiter]) ? p.delimiter : ';';
        }
        if (sub) {
            var subVal = typeof p.required_substring === 'string' ? p.required_substring : '';
            if (!subVal && t.import_code) {
                subVal = String(t.import_code);
            }
            sub.value = subVal;
        }
        var m = p.map || {};
        document.querySelectorAll('.csv-map-input[data-type-id="' + typeId + '"]').forEach(function (inp) {
            var col = inp.getAttribute('data-col');
            if (!col) return;
            inp.value = m[col] != null ? String(m[col]) : '';
        });
    }

    function syncBlocks() {
        var t = sel.value;
        blocks.forEach(function (b) {
            b.style.display = b.getAttribute('data-type-id') === t ? 'block' : 'none';
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

    var purgeForm = document.getElementById('csv-purge-preview-form');
    var purgeTarget = document.getElementById('purge_preview_target_table');
    if (purgeForm && purgeTarget && sel) {
        purgeForm.addEventListener('submit', function () {
            purgeTarget.value = sel.value;
        });
    }
})();
</script>
<?php require_once dirname(__DIR__) . '/nextgen/partials/footer.php'; ?>
