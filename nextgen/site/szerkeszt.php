<?php
declare(strict_types=1);

/**
 * Latinfo.hu kezdőoldal – modulok sorrendje (asztali + mobil) és be/ki.
 */

require_once dirname(__DIR__) . '/init.php';
requireLogin();
require_once dirname(__DIR__) . '/events/bootstrap.php';
require_once __DIR__ . '/lib/site_modules.php';

$db = getDb();
$schemaOk = latinfo_home_modules_ensure_schema($db);
$hiba = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require('latinfo_home_modules', '_csrf', latinfo_home_edit_url());
    $action = (string) ($_POST['action'] ?? '');

    if (!$schemaOk) {
        $hiba = 'A kezdőoldal táblái nem hozhatók létre. Ellenőrizd az adatbázis-jogosultságokat.';
    } elseif ($action === 'save_modules') {
        try {
            $enabled = $_POST['is_enabled'] ?? [];
            $desktopKeys = $_POST['module_key_desktop'] ?? [];
            $mobileKeys = $_POST['module_key_mobile'] ?? [];
            if (!is_array($desktopKeys)) {
                $desktopKeys = [];
            }
            if (!is_array($mobileKeys)) {
                $mobileKeys = [];
            }

            $desktopOrder = [];
            foreach ($desktopKeys as $i => $key) {
                $key = trim((string) $key);
                if ($key === '' || isset($desktopOrder[$key])) {
                    continue;
                }
                $desktopOrder[$key] = ($i + 1) * 10;
            }
            $mobileOrder = [];
            foreach ($mobileKeys as $i => $key) {
                $key = trim((string) $key);
                if ($key === '' || isset($mobileOrder[$key])) {
                    continue;
                }
                $mobileOrder[$key] = ($i + 1) * 10;
            }

            $rows = [];
            foreach (latinfo_home_module_catalog() as $key => $meta) {
                $rows[] = [
                    'module_key' => $key,
                    'is_enabled' => isset($enabled[$key]) && (string) $enabled[$key] === '1',
                    'sort_order' => $desktopOrder[$key] ?? (int) $meta['default_order'],
                    'sort_order_mobile' => $mobileOrder[$key] ?? (int) $meta['default_order_mobile'],
                ];
            }
            latinfo_home_modules_save_order($db, $rows);
            if (function_exists('rendszer_log')) {
                rendszer_log('kezdőoldal_modulok', 0, 'Sorrend mentve', '');
            }
            flash('success', 'A modulok sorrendje és láthatósága mentve.');
            redirect(latinfo_home_edit_url());
        } catch (Throwable $e) {
            error_log('latinfo home modules save: ' . $e->getMessage());
            $hiba = 'A mentés nem sikerült.';
        }
    } else {
        $hiba = 'Ismeretlen művelet.';
    }
}

$modulesDesktop = $schemaOk ? latinfo_home_modules_all($db, 'desktop') : [];
$modulesMobile = $schemaOk ? latinfo_home_modules_all($db, 'mobile') : [];
$enabledByKey = [];
foreach ($modulesDesktop as $mod) {
    $enabledByKey[(string) $mod['module_key']] = !empty($mod['is_enabled']);
}

$pageTitle = 'Kezdőoldal modulok';
$mainContentClass = 'main-content main-content--fullwidth';
$extraHead = '<style>
.lh-mod-grid{display:grid;grid-template-columns:1fr;gap:1.25rem;align-items:start}
@media (min-width:900px){.lh-mod-grid{grid-template-columns:1fr 1fr;gap:1.5rem}}
.lh-mod-pane{min-width:0;padding:1rem;border:1px solid var(--border);border-radius:.75rem;background:rgba(255,255,255,.35)}
.lh-mod-pane h3{margin:0 0 .35rem;font-size:1.05rem}
.lh-mod-table{width:100%}
.lh-mod-table tbody tr{cursor:grab}
.lh-mod-table tbody tr.is-dragging{opacity:.55;cursor:grabbing}
.lh-mod-table tbody tr.is-drag-over{outline:2px solid var(--primary,#6d8f63);outline-offset:-2px}
.lh-mod-handle{display:inline-flex;align-items:center;justify-content:center;width:2rem;height:2rem;border-radius:.45rem;color:var(--muted,#667);user-select:none;font-size:1.15rem;letter-spacing:-.08em;line-height:1}
.lh-mod-handle:hover{background:rgba(109,143,99,.12);color:inherit}
.lh-mod-table .lh-admin-check{display:flex;align-items:center;gap:.5rem;font-weight:500}
.lh-mod-table .lh-admin-check input{width:auto;max-width:none}
.lh-mod-hint{margin:.25rem 0 .85rem;color:var(--muted,#667);font-size:.9rem}
.lh-mod-pos{display:inline-block;min-width:1.25rem;color:var(--muted,#667);font-variant-numeric:tabular-nums}
.lh-mod-col{display:inline-block;margin-left:.35rem;padding:.1rem .45rem;border-radius:999px;font-size:.75rem;background:rgba(109,143,99,.12);color:var(--muted,#445)}
.lh-mod-off{opacity:.55}
</style>';

require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>

<div class="card lh-admin">
    <div class="events-list-head">
        <h2 class="events-list-title">Kezdőoldal modulok</h2>
        <div class="events-list-actions">
            <a href="<?= h(latinfo_home_preview_url()) ?>" class="btn btn-secondary btn-sm">Előnézet</a>
            <a href="<?= h(latinfo_home_modules_stat_url()) ?>" class="btn btn-secondary btn-sm">Stat</a>
        </div>
    </div>
    <p class="text-muted" style="margin-top:0">
        Közös be/ki kapcsolás, külön sorrend asztali és mobil nézethez. Húzd a sorokat, majd mentsd.
    </p>

    <?php if (!$schemaOk): ?>
        <p class="alert alert-error">A kezdőoldal táblái nem érhetők el.</p>
    <?php else: ?>
        <form method="post" action="<?= h(latinfo_home_edit_url()) ?>" id="lh-modules-form">
            <?= csrf_input('latinfo_home_modules') ?>
            <input type="hidden" name="action" value="save_modules">

            <div class="lh-mod-grid">
                <section class="lh-mod-pane" aria-labelledby="lh-mod-desktop-title">
                    <h3 id="lh-mod-desktop-title">Asztali (2 oszlop)</h3>
                    <p class="lh-mod-hint">Oszlopon belüli sorrend. A láthatóság mindkét nézetre közös.</p>
                    <div class="table-wrap">
                        <table class="lh-mod-table">
                            <thead>
                                <tr>
                                    <th style="width:3.25rem">#</th>
                                    <th>Modul</th>
                                    <th style="width:4.5rem">Be</th>
                                </tr>
                            </thead>
                            <tbody data-sortable>
                                <?php foreach ($modulesDesktop as $i => $mod): ?>
                                    <?php $key = (string) $mod['module_key']; ?>
                                    <tr draggable="true" data-module-key="<?= h($key) ?>">
                                        <td>
                                            <span class="lh-mod-handle" title="Húzd átrendezéshez" aria-hidden="true">⋮⋮</span>
                                            <span class="lh-mod-pos"><?= (int) $i + 1 ?></span>
                                            <input type="hidden" name="module_key_desktop[]" value="<?= h($key) ?>">
                                        </td>
                                        <td>
                                            <strong><?= h((string) $mod['label']) ?></strong>
                                            <span class="lh-mod-col"><?= ((string) $mod['column'] === 'calendar') ? 'Naptár' : 'Bal' ?></span>
                                            <?php if (!empty($mod['editable'])): ?>
                                                <div style="margin-top:.35rem">
                                                    <a class="btn btn-secondary btn-sm" href="<?= h(latinfo_home_module_edit_url($key)) ?>">Szerkeszt</a>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <input type="hidden" name="is_enabled[<?= h($key) ?>]" value="0">
                                            <label class="lh-admin-check">
                                                <input type="checkbox" name="is_enabled[<?= h($key) ?>]" value="1"<?= !empty($enabledByKey[$key]) ? ' checked' : '' ?> aria-label="<?= h((string) $mod['label'] . ' látható') ?>">
                                            </label>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="lh-mod-pane" aria-labelledby="lh-mod-mobile-title">
                    <h3 id="lh-mod-mobile-title">Mobil (1 oszlop)</h3>
                    <p class="lh-mod-hint">Fentről lefelé megjelenő sorrend keskeny képernyőn.</p>
                    <div class="table-wrap">
                        <table class="lh-mod-table">
                            <thead>
                                <tr>
                                    <th style="width:3.25rem">#</th>
                                    <th>Modul</th>
                                </tr>
                            </thead>
                            <tbody data-sortable>
                                <?php foreach ($modulesMobile as $i => $mod): ?>
                                    <?php
                                    $key = (string) $mod['module_key'];
                                    $isOn = !empty($enabledByKey[$key]);
                                    ?>
                                    <tr draggable="true" data-module-key="<?= h($key) ?>" class="<?= $isOn ? '' : 'lh-mod-off' ?>">
                                        <td>
                                            <span class="lh-mod-handle" title="Húzd átrendezéshez" aria-hidden="true">⋮⋮</span>
                                            <span class="lh-mod-pos"><?= (int) $i + 1 ?></span>
                                            <input type="hidden" name="module_key_mobile[]" value="<?= h($key) ?>">
                                        </td>
                                        <td>
                                            <strong><?= h((string) $mod['label']) ?></strong>
                                            <?php if (!$isOn): ?>
                                                <span class="lh-mod-col">Ki</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div style="margin-top:1.25rem">
                <button type="submit" class="btn btn-primary">Mentés</button>
            </div>
        </form>
        <script>
        (function () {
            function bindSortable(tbody) {
                if (!tbody) return;
                var dragRow = null;

                function refreshPositions() {
                    tbody.querySelectorAll('tr').forEach(function (row, idx) {
                        var pos = row.querySelector('.lh-mod-pos');
                        if (pos) pos.textContent = String(idx + 1);
                    });
                }
                function clearDragOver() {
                    tbody.querySelectorAll('tr.is-drag-over').forEach(function (el) {
                        el.classList.remove('is-drag-over');
                    });
                }

                tbody.addEventListener('dragstart', function (e) {
                    var row = e.target && e.target.closest ? e.target.closest('tr') : null;
                    if (!row || !tbody.contains(row)) return;
                    if (e.target.closest('a, input, label, button')) {
                        e.preventDefault();
                        return;
                    }
                    dragRow = row;
                    row.classList.add('is-dragging');
                    try {
                        e.dataTransfer.effectAllowed = 'move';
                        e.dataTransfer.setData('text/plain', row.getAttribute('data-module-key') || '');
                    } catch (err) {}
                });
                tbody.addEventListener('dragend', function () {
                    if (dragRow) dragRow.classList.remove('is-dragging');
                    dragRow = null;
                    clearDragOver();
                    refreshPositions();
                });
                tbody.addEventListener('dragover', function (e) {
                    if (!dragRow) return;
                    e.preventDefault();
                    try { e.dataTransfer.dropEffect = 'move'; } catch (err) {}
                    var target = e.target && e.target.closest ? e.target.closest('tr') : null;
                    if (!target || target === dragRow || !tbody.contains(target)) return;
                    clearDragOver();
                    target.classList.add('is-drag-over');
                    var rect = target.getBoundingClientRect();
                    var before = (e.clientY - rect.top) < rect.height / 2;
                    tbody.insertBefore(dragRow, before ? target : target.nextSibling);
                });
                tbody.addEventListener('drop', function (e) {
                    e.preventDefault();
                    clearDragOver();
                    refreshPositions();
                });
            }

            document.querySelectorAll('[data-sortable]').forEach(bindSortable);
        })();
        </script>
    <?php endif; ?>
</div>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
