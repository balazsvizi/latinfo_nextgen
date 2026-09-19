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
            $sorts = $_POST['sort_order'] ?? [];
            if (!is_array($keys)) {
                $keys = [];
            }
            $rows = [];
            foreach ($keys as $i => $key) {
                $key = trim((string) $key);
                $rows[] = [
                    'module_key' => $key,
                    'is_enabled' => isset($enabled[$key]) && (string) $enabled[$key] === '1',
                    'sort_order' => is_array($sorts) ? ($sorts[$key] ?? (($i + 1) * 10)) : (($i + 1) * 10),
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
.lh-mod-table .lh-mod-order{width:5.5rem}
.lh-mod-table .lh-admin-check{display:flex;align-items:center;gap:.5rem;font-weight:500}
.lh-mod-table .lh-admin-check input{width:auto;max-width:none}
.lh-mod-hint{margin:.25rem 0 0;color:var(--muted,#667);font-size:.9rem}
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
        Kapcsold be/ki a modulokat, és állítsd a sorrendet (kisebb szám = feljebb).
        A tartalom szerkesztése a menüben, a Kezdőoldal alatt érhető el.
    </p>

    <?php if (!$schemaOk): ?>
        <p class="alert alert-error">A kezdőoldal táblái nem érhetők el.</p>
    <?php else: ?>
        <form method="post" action="<?= h(latinfo_home_edit_url()) ?>">
            <?= csrf_input('latinfo_home_modules') ?>
            <input type="hidden" name="action" value="save_modules">
            <div class="table-wrap">
                <table class="sortable-table lh-mod-table">
                    <thead>
                        <tr>
                            <th>Sorrend</th>
                            <th>Modul</th>
                            <th>Látható</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($modules as $mod): ?>
                            <?php $key = (string) $mod['module_key']; ?>
                            <tr>
                                <td>
                                    <input type="hidden" name="module_key[]" value="<?= h($key) ?>">
                                    <input class="lh-mod-order" type="number" name="sort_order[<?= h($key) ?>]" value="<?= (int) $mod['sort_order'] ?>">
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
    <?php endif; ?>
</div>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
