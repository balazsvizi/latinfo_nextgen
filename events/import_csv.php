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
    if (!csrf_validate('events_import_csv')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet. Töltsd újra az oldalt.';
    } else {
    $action = (string) ($_POST['action'] ?? 'import');
    $table = trim((string) ($_POST['target_table'] ?? ''));

    if ($action === 'purge_preview') {
        if (!isset($schema[$table])) {
            $hiba = 'Érvénytelen cél tábla.';
        } else {
            try {
                $cnt = events_csv_import_count_rows($db, $table);
                $_SESSION['csv_import_purge'] = [
                    'table' => $table,
                    'count' => $cnt,
                    'token' => bin2hex(random_bytes(16)),
                ];
                redirect(events_url('import_csv.php?target_table=' . rawurlencode($table) . '&purge_step=confirm'));
            } catch (Throwable $e) {
                error_log('events import_csv purge_preview hiba: ' . $e->getMessage());
                $hiba = 'Törlés előnézet hiba történt.';
            }
        }
    } elseif ($action === 'purge_cancel') {
        unset($_SESSION['csv_import_purge']);
        $redir = events_url('import_csv.php');
        if (isset($schema[$table])) {
            $redir .= '?target_table=' . rawurlencode($table);
        }
        redirect($redir);
    } elseif ($action === 'purge_execute') {
        $token = (string) ($_POST['purge_token'] ?? '');
        $sess = $_SESSION['csv_import_purge'] ?? null;
        if (!isset($schema[$table])) {
            $hiba = 'Érvénytelen cél tábla.';
        } elseif (!is_array($sess) || ($sess['table'] ?? '') !== $table || !isset($sess['token']) || !hash_equals((string) $sess['token'], $token)) {
            $hiba = 'A törlési jóváhagyás érvénytelen vagy lejárt. Indítsd újra az „Összes sor törlése…” lépést.';
            unset($_SESSION['csv_import_purge']);
        } else {
            try {
                $n = events_csv_import_delete_all_rows($db, $table);
                unset($_SESSION['csv_import_purge']);
                rendszer_log('csv_import', null, 'CSV tábla teljes ürítés', $table . ': törölve ' . $n . ' sor');
                flash('success', 'Törölve: ' . $n . ' sor a „' . $table . '” táblából.');
                redirect(events_url('import_csv.php?target_table=' . rawurlencode($table)));
            } catch (Throwable $e) {
                error_log('events import_csv purge_execute hiba: ' . $e->getMessage());
                $hiba = 'Törlés hiba történt.';
            }
        }
    } elseif (!isset($schema[$table])) {
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
        } else {
            $hiba = 'Ismeretlen művelet.';
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

$purgeSess = $_SESSION['csv_import_purge'] ?? null;
if (isset($_GET['purge_step']) && (string) $_GET['purge_step'] === 'confirm') {
    if (!is_array($purgeSess) || ($purgeSess['table'] ?? '') !== $formTargetTable) {
        unset($_SESSION['csv_import_purge']);
        redirect(events_url('import_csv.php?target_table=' . rawurlencode($formTargetTable)));
    }
}
$purgeSess = $_SESSION['csv_import_purge'] ?? null;
$showPurgeConfirm = isset($_GET['purge_step']) && (string) $_GET['purge_step'] === 'confirm'
    && is_array($purgeSess)
    && ($purgeSess['table'] ?? '') === $formTargetTable
    && isset($purgeSess['count'], $purgeSess['token']);

$pageTitle = 'CSV import';
require_once dirname(__DIR__) . '/nextgen/partials/header.php';

$presetsJson = json_encode($presets, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if ($presetsJson === false) {
    $presetsJson = '{}';
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
        $purgeLbl = (string) ($schema[$purgeTbl]['label'] ?? $purgeTbl);
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
                    <input type="hidden" name="target_table" value="<?= h($purgeTbl) ?>">
                    <input type="hidden" name="purge_token" value="<?= h((string) $purgeSess['token']) ?>">
                    <button type="submit" class="btn btn-danger" data-purge-count="<?= $purgeCnt ?>">Igen, töröld mind a <?= $purgeCnt ?> sort</button>
                </form>
                <form method="post" style="display:inline;">
                    <?= csrf_input('events_import_csv') ?>
                    <input type="hidden" name="action" value="purge_cancel">
                    <input type="hidden" name="target_table" value="<?= h($purgeTbl) ?>">
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

    <div class="csv-import-purge-panel" style="margin-top:1.75rem;padding-top:1.25rem;border-top:1px solid var(--border);">
        <h3 style="margin-top:0;">Teljes tábla ürítése</h3>
        <p class="help" style="margin-bottom:0.75rem;">A fenti <strong>cél tábla</strong> legördülőben kiválasztott táblából minden sor törlődik (FK-k miatt kapcsolt sorok is törlődhetnek / nullázódhatnak). Először megjelenik a törlendő sorok száma, majd megerősítheted.</p>
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
