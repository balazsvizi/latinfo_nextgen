<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/nextgen/includes/auth.php';
requireLogin();

$db = getDb();

/**
 * @param list<array<string,mixed>> $rows
 * @return list<array<string,mixed>>
 */
function events_categories_flatten_tree(array $rows): array {
    $byParent = [];
    foreach ($rows as $r) {
        $pid = isset($r['parent_id']) && $r['parent_id'] !== null ? (int) $r['parent_id'] : 0;
        if (!isset($byParent[$pid])) {
            $byParent[$pid] = [];
        }
        $byParent[$pid][] = $r;
    }
    foreach ($byParent as $pid => $children) {
        usort($children, static function (array $a, array $b): int {
            $sa = (int) ($a['sort_order'] ?? 0);
            $sb = (int) ($b['sort_order'] ?? 0);
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }
            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });
        $byParent[$pid] = $children;
    }

    $seen = [];
    $out = [];
    $walk = static function (int $parent, int $depth) use (&$walk, &$byParent, &$seen, &$out): void {
        $children = $byParent[$parent] ?? [];
        foreach ($children as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $row['_depth'] = $depth;
            $out[] = $row;
            $walk($id, $depth + 1);
        }
    };
    $walk(0, 0);

    foreach ($rows as $r) {
        $id = (int) ($r['id'] ?? 0);
        if ($id > 0 && !isset($seen[$id])) {
            $r['_depth'] = 0;
            $r['_orphan'] = 1;
            $out[] = $r;
        }
    }

    return $out;
}

/**
 * @param array<int,int|null> $parentById
 */
function events_categories_parent_is_valid(array $parentById, int $categoryId, ?int $candidateParent): bool {
    if ($candidateParent === null || $candidateParent <= 0) {
        return true;
    }
    if ($candidateParent === $categoryId) {
        return false;
    }
    $guard = 0;
    $cur = $candidateParent;
    while ($cur !== null && $cur > 0) {
        if ($cur === $categoryId) {
            return false;
        }
        $cur = $parentById[$cur] ?? null;
        $guard++;
        if ($guard > 1000) {
            return false;
        }
    }
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!csrf_validate('events_categories')) {
        flash('error', 'Lejárt vagy érvénytelen munkamenet. Töltsd újra az oldalt.');
        redirect(events_url('categories.php'));
    }

    if ($action === 'save_category') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $parentRaw = trim((string) ($_POST['parent_id'] ?? ''));
        $parentId = $parentRaw === '' ? null : (int) $parentRaw;
        $color = strtoupper(trim((string) ($_POST['color'] ?? '#6D8F63')));
        $sortOrderRaw = trim((string) ($_POST['sort_order'] ?? '0'));
        $sortOrder = ctype_digit($sortOrderRaw) ? (int) $sortOrderRaw : 0;

        if ($name === '') {
            flash('error', 'A kategória neve kötelező.');
            redirect(events_url('categories.php') . ($id > 0 ? '?edit=' . $id : ''));
        }
        if ($sortOrder < 0 || $sortOrder > 65535) {
            flash('error', 'A sorrend 0 és 65535 közé essen.');
            redirect(events_url('categories.php') . ($id > 0 ? '?edit=' . $id : ''));
        }
        if (!preg_match('/^#[0-9A-F]{6}$/', $color)) {
            flash('error', 'A szín formátuma legyen pl. #6D8F63.');
            redirect(events_url('categories.php') . ($id > 0 ? '?edit=' . $id : ''));
        }
        if ($parentId !== null && $parentId <= 0) {
            $parentId = null;
        }

        $parentRows = $db->query('SELECT `id`, `parent_id` FROM `events_categories`')->fetchAll(PDO::FETCH_ASSOC);
        $parentById = [];
        foreach ($parentRows as $pr) {
            $pid = isset($pr['parent_id']) && $pr['parent_id'] !== null ? (int) $pr['parent_id'] : null;
            $parentById[(int) $pr['id']] = $pid;
        }

        if ($parentId !== null && !array_key_exists($parentId, $parentById)) {
            flash('error', 'A kiválasztott szülő kategória nem létezik.');
            redirect(events_url('categories.php') . ($id > 0 ? '?edit=' . $id : ''));
        }
        if ($id > 0 && !events_categories_parent_is_valid($parentById, $id, $parentId)) {
            flash('error', 'A kiválasztott szülő körkörös hierarchiát okozna.');
            redirect(events_url('categories.php?edit=') . $id);
        }

        if ($id > 0) {
            $st = $db->prepare('UPDATE `events_categories` SET `name` = ?, `parent_id` = ?, `color` = ?, `sort_order` = ? WHERE `id` = ?');
            $st->execute([$name, $parentId, $color, $sortOrder, $id]);
            flash('success', 'Kategória mentve.');
            rendszer_log('kategória', $id, 'Módosítva', $name . ' (' . $color . ')');
            redirect(events_url('categories.php?edit=') . $id);
        }

        $ins = $db->prepare('INSERT INTO `events_categories` (`name`, `parent_id`, `color`, `sort_order`) VALUES (?, ?, ?, ?)');
        $ins->execute([$name, $parentId, $color, $sortOrder]);
        $newId = (int) $db->lastInsertId();
        flash('success', 'Kategória létrehozva.');
        rendszer_log('kategória', $newId, 'Létrehozva', $name . ' (' . $color . ')');
        redirect(events_url('categories.php?edit=') . $newId);
    }

    if ($action === 'delete_category') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash('error', 'Érvénytelen kategória azonosító.');
            redirect(events_url('categories.php'));
        }
        $stSelf = $db->prepare('SELECT `name` FROM `events_categories` WHERE `id` = ? LIMIT 1');
        $stSelf->execute([$id]);
        $self = $stSelf->fetch(PDO::FETCH_ASSOC);
        if (!$self) {
            flash('error', 'A kategória nem található.');
            redirect(events_url('categories.php'));
        }
        $name = (string) ($self['name'] ?? ('#' . $id));

        $stChild = $db->prepare('SELECT COUNT(*) FROM `events_categories` WHERE `parent_id` = ?');
        $stChild->execute([$id]);
        $childCount = (int) $stChild->fetchColumn();
        if ($childCount > 0) {
            flash('error', 'A kategória nem törölhető, mert van ' . $childCount . ' alkategóriája.');
            redirect(events_url('categories.php?edit=') . $id);
        }

        $stUse = $db->prepare('SELECT COUNT(*) FROM `events_calendar_event_categories` WHERE `category_id` = ?');
        $stUse->execute([$id]);
        $useCount = (int) $stUse->fetchColumn();
        if ($useCount > 0) {
            flash('error', 'A kategória nem törölhető, mert ' . $useCount . ' esemény használja.');
            redirect(events_url('categories.php?edit=') . $id);
        }

        $del = $db->prepare('DELETE FROM `events_categories` WHERE `id` = ?');
        $del->execute([$id]);
        flash('success', 'Kategória törölve.');
        rendszer_log('kategória', $id, 'Törölve', $name);
        redirect(events_url('categories.php'));
    }

    redirect(events_url('categories.php'));
}

$rows = $db->query('SELECT `id`, `name`, `parent_id`, `color`, `sort_order`, `modified` FROM `events_categories` ORDER BY `sort_order` ASC, `name` ASC, `id` ASC')->fetchAll(PDO::FETCH_ASSOC);
$flat = events_categories_flatten_tree($rows);
$byId = [];
foreach ($rows as $r) {
    $byId[(int) $r['id']] = $r;
}

$editId = (int) ($_GET['edit'] ?? 0);
$editRow = null;
if ($editId > 0 && isset($byId[$editId])) {
    $editRow = $byId[$editId];
}

$form = [
    'id' => $editRow ? (int) $editRow['id'] : 0,
    'name' => $editRow ? (string) $editRow['name'] : '',
    'parent_id' => $editRow && $editRow['parent_id'] !== null ? (int) $editRow['parent_id'] : 0,
    'color' => $editRow ? (string) $editRow['color'] : '#6D8F63',
    'sort_order' => $editRow ? (int) $editRow['sort_order'] : 0,
];

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Kategóriák';
require_once dirname(__DIR__) . '/nextgen/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="card events-admin-card">
    <div class="events-list-head">
        <h2 class="events-list-title">Esemény kategóriák</h2>
        <div class="events-list-actions">
            <a href="<?= h(events_url('categories.php')) ?>" class="btn btn-secondary">Új kategória űrlap</a>
            <a href="<?= h(events_url('events_admin.php')) ?>" class="btn btn-secondary">Események</a>
        </div>
    </div>

    <p class="help" style="margin-top:0;">A kategóriák több szintűek lehetnek (szülő → gyermek). Az új rekordok ID-je 10000-től indul (AUTO_INCREMENT).</p>

    <div class="events-categories-layout">
        <section class="events-categories-table-wrap">
            <div class="table-wrap events-admin-table-wrap">
                <table class="sortable-table events-admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Név</th>
                            <th>Szín</th>
                            <th>Sorrend</th>
                            <th>Módosítva</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($flat === []): ?>
                        <tr>
                            <td colspan="5">Még nincs kategória. Hozz létre egyet a jobb oldalon.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($flat as $r): ?>
                            <?php
                            $rid = (int) $r['id'];
                            $depth = (int) ($r['_depth'] ?? 0);
                            $indent = str_repeat('— ', max(0, $depth));
                            $color = (string) ($r['color'] ?? '#6D8F63');
                            ?>
                            <tr>
                                <td><?= $rid ?></td>
                                <td>
                                    <a class="events-cell-edit" href="<?= h(events_url('categories.php?edit=' . $rid)) ?>">
                                        <?= h($indent . (string) $r['name']) ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="events-category-color-chip">
                                        <span class="events-category-color-chip__dot" style="background: <?= h($color) ?>;"></span>
                                        <?= h($color) ?>
                                    </span>
                                </td>
                                <td><?= (int) ($r['sort_order'] ?? 0) ?></td>
                                <td><?= h((string) ($r['modified'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="events-categories-form-wrap">
            <h3 style="margin-top:0;"><?= $form['id'] > 0 ? 'Kategória szerkesztése' : 'Új kategória' ?></h3>
            <form method="post" action="<?= h(events_url('categories.php') . ($form['id'] > 0 ? '?edit=' . (int) $form['id'] : '')) ?>">
                <?= csrf_input('events_categories') ?>
                <input type="hidden" name="action" value="save_category">
                <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">

                <div class="form-group">
                    <label for="cat_name">Név *</label>
                    <input type="text" id="cat_name" name="name" required maxlength="255" value="<?= h((string) $form['name']) ?>">
                </div>

                <div class="form-group">
                    <label for="cat_parent">Szülő kategória</label>
                    <select id="cat_parent" name="parent_id">
                        <option value="">— nincs (gyökér szint) —</option>
                        <?php foreach ($flat as $r): ?>
                            <?php
                            $rid = (int) $r['id'];
                            if ($form['id'] > 0 && $rid === (int) $form['id']) {
                                continue;
                            }
                            $depth = (int) ($r['_depth'] ?? 0);
                            $prefix = str_repeat('— ', max(0, $depth));
                            ?>
                            <option value="<?= $rid ?>" <?= ((int) $form['parent_id'] === $rid) ? 'selected' : '' ?>>
                                <?= h($prefix . (string) $r['name'] . ' (#' . $rid . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="cat_color">Szín (hex)</label>
                    <div class="events-category-color-input-row">
                        <input type="color" id="cat_color_picker" value="<?= h((string) $form['color']) ?>" aria-label="Kategória színe">
                        <input type="text" id="cat_color" name="color" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$" value="<?= h((string) $form['color']) ?>" placeholder="#6D8F63">
                    </div>
                </div>

                <div class="form-group">
                    <label for="cat_sort_order">Sorrend</label>
                    <input type="number" id="cat_sort_order" name="sort_order" min="0" max="65535" step="1" value="<?= (int) $form['sort_order'] ?>">
                </div>

                <div class="toolbar">
                    <button type="submit" class="btn btn-primary">Mentés</button>
                    <?php if ($form['id'] > 0): ?>
                        <a class="btn btn-secondary" href="<?= h(events_url('categories.php')) ?>">Új kategória</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($form['id'] > 0): ?>
                <form method="post" action="<?= h(events_url('categories.php?edit=' . (int) $form['id'])) ?>" style="margin-top:1rem;" onsubmit="return confirm('Biztosan törlöd ezt a kategóriát?');">
                    <?= csrf_input('events_categories') ?>
                    <input type="hidden" name="action" value="delete_category">
                    <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
                    <button type="submit" class="btn btn-secondary">Kategória törlése</button>
                </form>
                <p class="help" style="margin-top:0.5rem;">Törlés csak akkor lehetséges, ha nincs alkategória és nincs esemény-hozzárendelés.</p>
            <?php endif; ?>
        </aside>
    </div>
</div>

<script>
(function () {
    var picker = document.getElementById('cat_color_picker');
    var text = document.getElementById('cat_color');
    if (!picker || !text) return;
    picker.addEventListener('input', function () {
        text.value = (picker.value || '').toUpperCase();
    });
    text.addEventListener('input', function () {
        var v = (text.value || '').trim();
        if (/^#[0-9A-Fa-f]{6}$/.test(v)) {
            picker.value = v;
        }
    });
})();
</script>

<?php require_once dirname(__DIR__) . '/nextgen/partials/footer.php'; ?>
