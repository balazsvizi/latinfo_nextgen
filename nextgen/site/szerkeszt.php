<?php
declare(strict_types=1);

/**
 * Latinfo.hu kezdőoldal – modulok sorrendje és be/ki kapcsolása.
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
            $keys = $_POST['module_key'] ?? [];
            $enabled = $_POST['is_enabled'] ?? [];
            if (!is_array($keys)) {
                $keys = [];
            }
            $rows = [];
            foreach ($keys as $i => $key) {
                $key = trim((string) $key);
                if ($key === '') {
                    continue;
                }
                $rows[] = [
                    'module_key' => $key,
                    'is_enabled' => isset($enabled[$key]) && (string) $enabled[$key] === '1',
                    'sort_order' => ($i + 1) * 10,
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

$modules = $schemaOk ? latinfo_home_modules_all($db) : [];

$pageTitle = 'Kezdőoldal modulok';
$mainContentClass = 'main-content main-content--fullwidth';
$extraHead = '<style>
.lh-mod-table tbody tr{cursor:grab}
.lh-mod-table tbody tr.is-dragging{opacity:.55;cursor:grabbing}
.lh-mod-table tbody tr.is-drag-over{outline:2px solid var(--primary,#6d8f63);outline-offset:-2px}
.lh-mod-handle{display:inline-flex;align-items:center;justify-content:center;width:2rem;height:2rem;border-radius:.45rem;color:var(--muted,#667);user-select:none;font-size:1.15rem;letter-spacing:-.08em;line-height:1}
.lh-mod-handle:hover{background:rgba(109,143,99,.12);color:inherit}
.lh-mod-table .lh-admin-check{display:flex;align-items:center;gap:.5rem;font-weight:500}
.lh-mod-table .lh-admin-check input{width:auto;max-width:none}
.lh-mod-hint{margin:.25rem 0 0;color:var(--muted,#667);font-size:.9rem}
.lh-mod-pos{display:inline-block;min-width:1.25rem;color:var(--muted,#667);font-variant-numeric:tabular-nums}
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
        Húzd a sorokat a fogantyúnál a kívánt sorrendbe, majd mentsd.
        A tartalom szerkesztése a menüben, a Kezdőoldal alatt érhető el.
    </p>

    <?php if (!$schemaOk): ?>
        <p class="alert alert-error">A kezdőoldal táblái nem érhetők el.</p>
    <?php else: ?>
        <form method="post" action="<?= h(latinfo_home_edit_url()) ?>" id="lh-modules-form">
            <?= csrf_input('latinfo_home_modules') ?>
            <input type="hidden" name="action" value="save_modules">
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
                    <tbody id="lh-modules-sortable">
                        <?php foreach ($modules as $i => $mod): ?>
                            <?php $key = (string) $mod['module_key']; ?>
                            <tr draggable="true" data-module-key="<?= h($key) ?>">
                                <td>
                                    <span class="lh-mod-handle" title="Húzd a sor átrendezéséhez" aria-hidden="true">⋮⋮</span>
                                    <span class="lh-mod-pos"><?= (int) $i + 1 ?></span>
                                    <input type="hidden" name="module_key[]" value="<?= h($key) ?>">
                                </td>
                                <td>
                                    <strong><?= h((string) $mod['label']) ?></strong>
                                    <?php if (empty($mod['editable'])): ?>
                                        <p class="lh-mod-hint">Naptár adat – nincs külön szerkesztő.</p>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <input type="hidden" name="is_enabled[<?= h($key) ?>]" value="0">
                                    <label class="lh-admin-check">
                                        <input type="checkbox" name="is_enabled[<?= h($key) ?>]" value="1"<?= !empty($mod['is_enabled']) ? ' checked' : '' ?>>
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
            <div style="margin-top:1rem">
                <button type="submit" class="btn btn-primary">Mentés</button>
            </div>
        </form>
        <script>
        (function () {
            var tbody = document.getElementById('lh-modules-sortable');
            if (!tbody) return;

            var dragRow = null;

            function refreshPositions() {
                var rows = tbody.querySelectorAll('tr');
                rows.forEach(function (row, idx) {
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
                // Ne indítson húzást linkről / checkboxról.
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
                if (before) {
                    tbody.insertBefore(dragRow, target);
                } else {
                    tbody.insertBefore(dragRow, target.nextSibling);
                }
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
