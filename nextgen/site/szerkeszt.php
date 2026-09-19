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
$tab = (string) ($_GET['tab'] ?? 'desktop');
if (!in_array($tab, ['desktop', 'mobile'], true)) {
    $tab = 'desktop';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require('latinfo_home_modules', '_csrf', latinfo_home_edit_url('tab=' . rawurlencode($tab)));
    $action = (string) ($_POST['action'] ?? '');
    $postedTab = (string) ($_POST['tab'] ?? $tab);
    if (!in_array($postedTab, ['desktop', 'mobile'], true)) {
        $postedTab = 'desktop';
    }

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
                rendszer_log('kezdőoldal_modulok', 0, 'Sorrend mentve', $postedTab);
            }
            flash('success', 'A modulok sorrendje és láthatósága mentve.');
            redirect(latinfo_home_edit_url('tab=' . rawurlencode($postedTab)));
        } catch (Throwable $e) {
            error_log('latinfo home modules save: ' . $e->getMessage());
            $hiba = 'A mentés nem sikerült.';
            $tab = $postedTab;
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
.lh-mod-tabs{display:flex;flex-wrap:wrap;gap:.4rem;margin:0 0 1.25rem}
.lh-mod-tabs a{display:inline-block;padding:.45rem .9rem;border:1px solid var(--border);border-radius:999px;text-decoration:none;color:inherit}
.lh-mod-tabs a.is-active{background:var(--primary,#6d8f63);color:#fff;border-color:transparent}
.lh-mod-table tbody tr{cursor:grab}
.lh-mod-table tbody tr.is-dragging{opacity:.55;cursor:grabbing}
.lh-mod-table tbody tr.is-drag-over{outline:2px solid var(--primary,#6d8f63);outline-offset:-2px}
.lh-mod-handle{display:inline-flex;align-items:center;justify-content:center;width:2rem;height:2rem;border-radius:.45rem;color:var(--muted,#667);user-select:none;font-size:1.15rem;letter-spacing:-.08em;line-height:1}
.lh-mod-handle:hover{background:rgba(109,143,99,.12);color:inherit}
.lh-mod-table .lh-admin-check{display:flex;align-items:center;gap:.5rem;font-weight:500}
.lh-mod-table .lh-admin-check input{width:auto;max-width:none}
.lh-mod-hint{margin:.25rem 0 0;color:var(--muted,#667);font-size:.9rem}
.lh-mod-pos{display:inline-block;min-width:1.25rem;color:var(--muted,#667);font-variant-numeric:tabular-nums}
.lh-mod-col{display:inline-block;margin-left:.4rem;padding:.1rem .45rem;border-radius:999px;font-size:.75rem;background:rgba(109,143,99,.12);color:var(--muted,#445)}
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
        Közös be/ki kapcsolás, külön sorrend asztali (2 oszlop) és mobil (1 oszlop) nézethez.
        Asztalon a sorrend oszlopon belül érvényesül; mobilon a teljes lista sorrendje.
    </p>

    <?php if (!$schemaOk): ?>
        <p class="alert alert-error">A kezdőoldal táblái nem érhetők el.</p>
    <?php else: ?>
        <nav class="lh-mod-tabs" aria-label="Nézet">
            <a href="<?= h(latinfo_home_edit_url('tab=desktop')) ?>" class="<?= $tab === 'desktop' ? 'is-active' : '' ?>"<?= $tab === 'desktop' ? ' aria-current="page"' : '' ?>>Asztali</a>
            <a href="<?= h(latinfo_home_edit_url('tab=mobile')) ?>" class="<?= $tab === 'mobile' ? 'is-active' : '' ?>"<?= $tab === 'mobile' ? ' aria-current="page"' : '' ?>>Mobil</a>
        </nav>

        <form method="post" action="<?= h(latinfo_home_edit_url('tab=' . rawurlencode($tab))) ?>" id="lh-modules-form">
            <?= csrf_input('latinfo_home_modules') ?>
            <input type="hidden" name="action" value="save_modules">
            <input type="hidden" name="tab" value="<?= h($tab) ?>">

            <?php if ($tab === 'desktop'): ?>
                <p class="lh-mod-hint" style="margin-top:0">Húzd a sorokat: a bal / naptár oszlopon belüli sorrendet állítja. A láthatóság közös mindkét nézetre.</p>
                <div class="table-wrap">
                    <table class="lh-mod-table">
                        <thead>
                            <tr>
                                <th style="width:3.5rem">Sorrend</th>
                                <th>Modul</th>
                                <th style="width:7rem">Látható</th>
                                <th style="width:7rem"></th>
                            </tr>
                        </thead>
                        <tbody id="lh-modules-sortable" data-sortable>
                            <?php foreach ($modulesDesktop as $i => $mod): ?>
                                <?php $key = (string) $mod['module_key']; ?>
                                <tr draggable="true" data-module-key="<?= h($key) ?>">
                                    <td>
                                        <span class="lh-mod-handle" title="Húzd a sor átrendezéséhez" aria-hidden="true">⋮⋮</span>
                                        <span class="lh-mod-pos"><?= (int) $i + 1 ?></span>
                                        <input type="hidden" name="module_key_desktop[]" value="<?= h($key) ?>">
                                    </td>
                                    <td>
                                        <strong><?= h((string) $mod['label']) ?></strong>
                                        <span class="lh-mod-col"><?= ((string) $mod['column'] === 'calendar') ? 'Naptár oszlop' : 'Bal oszlop' ?></span>
                                        <?php if (empty($mod['editable'])): ?>
                                            <p class="lh-mod-hint">Naptár adat – nincs külön szerkesztő.</p>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <input type="hidden" name="is_enabled[<?= h($key) ?>]" value="0">
                                        <label class="lh-admin-check">
                                            <input type="checkbox" name="is_enabled[<?= h($key) ?>]" value="1"<?= !empty($enabledByKey[$key]) ? ' checked' : '' ?>>
                                            Be
                                        </label>
                                    </td>
                                    <td>
                                        <?php if (!empty($mod['editable'])): ?>
                                            <a class="btn btn-secondary btn-sm" href="<?= h(latinfo_home_module_edit_url($key)) ?>">Szerkeszt</a>
                                        <?php else: ?>
                                            <span class="text-muted">–</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php foreach ($modulesMobile as $mod): ?>
                    <input type="hidden" name="module_key_mobile[]" value="<?= h((string) $mod['module_key']) ?>">
                <?php endforeach; ?>
            <?php else: ?>
                <p class="lh-mod-hint" style="margin-top:0">Húzd a sorokat a mobil egyoszlopos sorrendhez (fentről lefelé). A be/ki kapcsolást az Asztali fülön állíthatod.</p>
                <div class="table-wrap">
                    <table class="lh-mod-table">
                        <thead>
                            <tr>
                                <th style="width:3.5rem">Sorrend</th>
                                <th>Modul</th>
                                <th style="width:7rem"></th>
                            </tr>
                        </thead>
                        <tbody id="lh-modules-sortable" data-sortable>
                            <?php foreach ($modulesMobile as $i => $mod): ?>
                                <?php $key = (string) $mod['module_key']; ?>
                                <tr draggable="true" data-module-key="<?= h($key) ?>">
                                    <td>
                                        <span class="lh-mod-handle" title="Húzd a sor átrendezéséhez" aria-hidden="true">⋮⋮</span>
                                        <span class="lh-mod-pos"><?= (int) $i + 1 ?></span>
                                        <input type="hidden" name="module_key_mobile[]" value="<?= h($key) ?>">
                                    </td>
                                    <td>
                                        <strong><?= h((string) $mod['label']) ?></strong>
                                        <?php if (empty($enabledByKey[$key])): ?>
                                            <span class="lh-mod-col">Ki van kapcsolva</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($mod['editable'])): ?>
                                            <a class="btn btn-secondary btn-sm" href="<?= h(latinfo_home_module_edit_url($key)) ?>">Szerkeszt</a>
                                        <?php else: ?>
                                            <span class="text-muted">–</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php foreach ($modulesDesktop as $mod): ?>
                    <?php $key = (string) $mod['module_key']; ?>
                    <input type="hidden" name="module_key_desktop[]" value="<?= h($key) ?>">
                    <input type="hidden" name="is_enabled[<?= h($key) ?>]" value="<?= !empty($enabledByKey[$key]) ? '1' : '0' ?>">
                <?php endforeach; ?>
            <?php endif; ?>

            <div style="margin-top:1rem">
                <button type="submit" class="btn btn-primary">Mentés</button>
            </div>
        </form>
        <script>
        (function () {
            var tbody = document.querySelector('[data-sortable]');
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
        })();
        </script>
    <?php endif; ?>
</div>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
