<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/nextgen/includes/auth.php';
require_once __DIR__ . '/lib/tag_type.php';
requireLogin();

$db = getDb();

if (!events_tag_types_registry_table_available($db)) {
    $mainContentClass = 'main-content main-content--fullwidth';
    $pageTitle = 'Címke típusok';
    require_once dirname(__DIR__) . '/nextgen/partials/header.php';
    echo '<div class="card events-admin-card">';
    echo '<p class="alert alert-error">Hiányzik az <code>events_tag_types</code> tábla. Futtasd: <code>events/sql/migration_tag_types_registry.sql</code></p>';
    echo '<p><a href="' . h(events_url('tags.php')) . '" class="btn btn-secondary">Vissza a címkékhez</a></p>';
    echo '</div>';
    require_once dirname(__DIR__) . '/nextgen/partials/footer.php';
    exit;
}

events_tag_types_ensure_seeded($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('events_tag_types')) {
        flash('error', 'Lejárt vagy érvénytelen munkamenet.');
        redirect(events_url('tag_types.php'));
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save_type') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $codeRaw = trim((string) ($_POST['code'] ?? ''));
        $icon = trim((string) ($_POST['icon'] ?? '🏷️'));
        $tone = trim((string) ($_POST['tone'] ?? 'default'));
        $sortOrder = max(0, (int) ($_POST['sort_order'] ?? 0));

        if ($name === '') {
            flash('error', 'A megjelenített név kötelező.');
            redirect(events_url('tag_types.php?open=') . ($id > 0 ? (string) $id : 'new'));
        }
        if ($icon === '') {
            $icon = '🏷️';
        }
        if ($tone === '') {
            $tone = 'default';
        }
        if (strlen($icon) > 16) {
            flash('error', 'Az ikon legfeljebb 16 karakter lehet.');
            redirect(events_url('tag_types.php?open=') . ($id > 0 ? (string) $id : 'new'));
        }
        if (strlen($tone) > 32) {
            flash('error', 'A stílus azonosító legfeljebb 32 karakter lehet.');
            redirect(events_url('tag_types.php?open=') . ($id > 0 ? (string) $id : 'new'));
        }

        if ($id > 0) {
            $existing = events_tag_type_row_by_id($db, $id);
            if ($existing === null) {
                flash('error', 'A típus nem található.');
                redirect(events_url('tag_types.php'));
            }
            $code = $codeRaw !== '' ? events_tag_type_ensure_unique_code($db, $codeRaw, $id) : $existing['code'];
            $st = $db->prepare('UPDATE `events_tag_types` SET `code` = ?, `name` = ?, `icon` = ?, `tone` = ?, `sort_order` = ? WHERE `id` = ?');
            $st->execute([$code, $name, $icon, $tone, $sortOrder, $id]);
            events_tag_types_clear_cache();
            flash('success', 'Címke típus mentve.');
            rendszer_log('tag_type', $id, 'Módosítva', $name);
            redirect(events_url('tag_types.php'));
        }

        $code = $codeRaw !== '' ? events_tag_type_ensure_unique_code($db, $codeRaw, null) : events_tag_type_ensure_unique_code($db, $name, null);
        $ins = $db->prepare('INSERT INTO `events_tag_types` (`code`, `name`, `icon`, `tone`, `sort_order`) VALUES (?,?,?,?,?)');
        $ins->execute([$code, $name, $icon, $tone, $sortOrder]);
        $newId = (int) $db->lastInsertId();
        events_tag_types_clear_cache();
        flash('success', 'Címke típus létrehozva.');
        rendszer_log('tag_type', $newId, 'Létrehozva', $name);
        redirect(events_url('tag_types.php'));
    }

    if ($action === 'delete_type') {
        $id = (int) ($_POST['id'] ?? 0);
        $row = events_tag_type_row_by_id($db, $id);
        if ($row === null) {
            flash('error', 'A típus nem található.');
            redirect(events_url('tag_types.php'));
        }
        $linkCnt = events_tag_type_count_links($db, $id);
        if ($linkCnt > 0) {
            flash('error', 'A típus nem törölhető, mert ' . $linkCnt . ' címkéhez van rendelve.');
            redirect(events_url('tag_types.php?open=') . $id);
        }
        $db->prepare('DELETE FROM `events_tag_types` WHERE `id` = ?')->execute([$id]);
        events_tag_types_clear_cache();
        flash('success', 'Címke típus törölve.');
        rendszer_log('tag_type', $id, 'Törölve', (string) $row['name']);
        redirect(events_url('tag_types.php'));
    }

    redirect(events_url('tag_types.php'));
}

$typeRows = events_tag_types_load_registry($db, true);
$openRaw = (string) ($_GET['open'] ?? '');
$openGroup = '';
if ($openRaw === 'new') {
    $openGroup = 'new';
} elseif ($openRaw !== '' && ctype_digit($openRaw)) {
    $openGroup = (string) (int) $openRaw;
}

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Címke típusok';
require_once dirname(__DIR__) . '/nextgen/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="card events-admin-card events-tag-types-admin">
    <div class="events-list-head">
        <h2 class="events-list-title">Címke típusok</h2>
        <div class="events-list-actions">
            <a href="<?= h(events_url('tags.php')) ?>" class="btn btn-secondary">Címkék</a>
            <a href="<?= h(events_url('events_admin.php')) ?>" class="btn btn-secondary">Események</a>
        </div>
    </div>
    <p class="help">A típusok listája adatbázisban tárolódik. A <strong>kód</strong> stabil azonosító (CSV, szűrők); a <strong>név</strong> szabadon átírható.</p>

    <div class="table-wrap events-admin-table-wrap events-inline-expand-wrap">
        <table class="events-admin-table events-inline-expand-table" id="events-tag-types-table" data-sticky-group="new" data-initial-open="<?= h($openGroup) ?>">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Kód</th>
                    <th>Név</th>
                    <th>Ikon</th>
                    <th>Sorrend</th>
                </tr>
            </thead>
            <tbody>
                <tr class="events-inline-summary<?= $openGroup === 'new' ? ' is-active' : '' ?>" data-expand-group="new" tabindex="0" role="button" aria-expanded="<?= $openGroup === 'new' ? 'true' : 'false' ?>">
                    <td class="events-inline-summary-muted">—</td>
                    <td colspan="4"><strong>Új típus</strong></td>
                </tr>
                <tr class="events-inline-detail" data-expand-group="new" <?= $openGroup === 'new' ? '' : 'hidden' ?>>
                    <td colspan="5">
                        <form method="post" class="events-tags-admin__form-panel events-tags-admin__form-panel--inline">
                            <?= csrf_input('events_tag_types') ?>
                            <input type="hidden" name="action" value="save_type">
                            <input type="hidden" name="id" value="0">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="type_code_new">Kód</label>
                                    <input type="text" id="type_code_new" name="code" maxlength="64" placeholder="pl. dj (üres = névből)">
                                </div>
                                <div class="form-group">
                                    <label for="type_name_new">Megjelenített név *</label>
                                    <input type="text" id="type_name_new" name="name" required maxlength="255">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="type_icon_new">Ikon</label>
                                    <input type="text" id="type_icon_new" name="icon" maxlength="16" value="🏷️">
                                </div>
                                <div class="form-group">
                                    <label for="type_tone_new">Stílus azonosító</label>
                                    <input type="text" id="type_tone_new" name="tone" maxlength="32" value="default" placeholder="pl. dj, zenekar, default">
                                </div>
                                <div class="form-group">
                                    <label for="type_sort_new">Sorrend</label>
                                    <input type="number" id="type_sort_new" name="sort_order" min="0" value="0">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Mentés</button>
                        </form>
                    </td>
                </tr>
                <?php foreach ($typeRows as $row): ?>
                    <?php
                    $tid = (int) $row['id'];
                    $isOpen = $openGroup === (string) $tid;
                    $tone = (string) ($row['tone'] ?? 'default');
                    ?>
                    <tr class="events-inline-summary<?= $isOpen ? ' is-active' : '' ?>" data-expand-group="<?= $tid ?>" tabindex="0" role="button" aria-expanded="<?= $isOpen ? 'true' : 'false' ?>">
                        <td><?= $tid ?></td>
                        <td><code><?= h((string) $row['code']) ?></code></td>
                        <td>
                            <span class="events-tag-type-pill events-tag-type-pill--<?= h($tone) ?>">
                                <span class="events-tag-type-pill__icon" aria-hidden="true"><?= (string) ($row['icon'] ?? '🏷️') ?></span>
                                <span class="events-tag-type-pill__label"><?= h((string) $row['name']) ?></span>
                            </span>
                        </td>
                        <td><?= h((string) ($row['icon'] ?? '')) ?></td>
                        <td><?= (int) ($row['sort_order'] ?? 0) ?></td>
                    </tr>
                    <tr class="events-inline-detail" data-expand-group="<?= $tid ?>" <?= $isOpen ? '' : 'hidden' ?>>
                        <td colspan="5">
                            <form method="post" class="events-tags-admin__form-panel events-tags-admin__form-panel--inline">
                                <?= csrf_input('events_tag_types') ?>
                                <input type="hidden" name="action" value="save_type">
                                <input type="hidden" name="id" value="<?= $tid ?>">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="type_code_<?= $tid ?>">Kód</label>
                                        <input type="text" id="type_code_<?= $tid ?>" name="code" maxlength="64" value="<?= h((string) $row['code']) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="type_name_<?= $tid ?>">Megjelenített név *</label>
                                        <input type="text" id="type_name_<?= $tid ?>" name="name" required maxlength="255" value="<?= h((string) $row['name']) ?>">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="type_icon_<?= $tid ?>">Ikon</label>
                                        <input type="text" id="type_icon_<?= $tid ?>" name="icon" maxlength="16" value="<?= h((string) ($row['icon'] ?? '🏷️')) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="type_tone_<?= $tid ?>">Stílus azonosító</label>
                                        <input type="text" id="type_tone_<?= $tid ?>" name="tone" maxlength="32" value="<?= h($tone) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="type_sort_<?= $tid ?>">Sorrend</label>
                                        <input type="number" id="type_sort_<?= $tid ?>" name="sort_order" min="0" value="<?= (int) ($row['sort_order'] ?? 0) ?>">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">Mentés</button>
                            </form>
                            <form method="post" class="events-tags-delete-form" onsubmit="return confirm('Biztosan törlöd ezt a típust?');">
                                <?= csrf_input('events_tag_types') ?>
                                <input type="hidden" name="action" value="delete_type">
                                <input type="hidden" name="id" value="<?= $tid ?>">
                                <button type="submit" class="btn btn-secondary">Típus törlése</button>
                            </form>
                            <p class="help">Törlés csak akkor lehetséges, ha egy címke sem használja. Kapcsolt címkék: <?= events_tag_type_count_links($db, $tid) ?>.</p>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    var table = document.getElementById('events-tag-types-table');
    if (!table) return;
    var tbody = table.querySelector('tbody');
    if (!tbody) return;
    var initial = table.getAttribute('data-initial-open') || '';
    function setOpen(group, open) {
        var sum = tbody.querySelector('.events-inline-summary[data-expand-group="' + group + '"]');
        var det = tbody.querySelector('.events-inline-detail[data-expand-group="' + group + '"]');
        if (!sum || !det) return;
        det.hidden = !open;
        sum.classList.toggle('is-active', open);
        sum.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    if (initial) setOpen(initial, true);
    tbody.addEventListener('click', function (e) {
        var sum = e.target.closest('.events-inline-summary');
        if (!sum || !tbody.contains(sum) || e.target.closest('a, button, input, textarea, select, label')) return;
        var g = sum.getAttribute('data-expand-group');
        if (!g) return;
        var det = tbody.querySelector('.events-inline-detail[data-expand-group="' + g + '"]');
        if (!det) return;
        var open = det.hidden;
        tbody.querySelectorAll('.events-inline-detail').forEach(function (d) { d.hidden = true; });
        tbody.querySelectorAll('.events-inline-summary').forEach(function (s) {
            s.classList.remove('is-active');
            s.setAttribute('aria-expanded', 'false');
        });
        if (open) setOpen(g, true);
    });
})();
</script>

<?php require_once dirname(__DIR__) . '/nextgen/partials/footer.php'; ?>
