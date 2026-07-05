<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/admin_event_filters.php';
require_once __DIR__ . '/lib/category_request.php';
requireLogin();

$db = getDb();
$categoriesNameEnOk = events_categories_name_en_available($db);

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

/**
 * @return array<int, int> category_id => események száma
 */
function events_categories_event_count_map(PDO $db): array {
    $map = [];
    try {
        $st = $db->query('
            SELECT `category_id`, COUNT(*) AS cnt
            FROM `events_calendar_event_categories`
            GROUP BY `category_id`
        ');
        if ($st === false) {
            return [];
        }
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $map[(int) ($row['category_id'] ?? 0)] = (int) ($row['cnt'] ?? 0);
        }
    } catch (Throwable $e) {
        error_log('events_categories_event_count_map: ' . $e->getMessage());
    }

    return $map;
}

/**
 * @return array<int, int> parent_id => közvetlen alkategóriák száma
 */
function events_categories_child_count_map(PDO $db): array {
    $map = [];
    try {
        $st = $db->query('
            SELECT `parent_id`, COUNT(*) AS cnt
            FROM `events_categories`
            WHERE `parent_id` IS NOT NULL
            GROUP BY `parent_id`
        ');
        if ($st === false) {
            return [];
        }
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $pid = (int) ($row['parent_id'] ?? 0);
            if ($pid > 0) {
                $map[$pid] = (int) ($row['cnt'] ?? 0);
            }
        }
    } catch (Throwable $e) {
        error_log('events_categories_child_count_map: ' . $e->getMessage());
    }

    return $map;
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
        $nameEn = $categoriesNameEnOk ? trim((string) ($_POST['name_en'] ?? '')) : '';
        $parentRaw = trim((string) ($_POST['parent_id'] ?? ''));
        $parentId = $parentRaw === '' ? null : (int) $parentRaw;
        $color = strtoupper(trim((string) ($_POST['color'] ?? '#6D8F63')));
        $sortOrderRaw = trim((string) ($_POST['sort_order'] ?? '0'));
        $sortOrder = ctype_digit($sortOrderRaw) ? (int) $sortOrderRaw : 0;

        if ($name === '') {
            flash('error', 'A kategória neve kötelező.');
            redirect(events_url('categories.php') . ($id > 0 ? '?edit=' . $id : ''));
        }
        if ($categoriesNameEnOk && strlen($nameEn) > 255) {
            flash('error', 'Az angol név legfeljebb 255 karakter lehet.');
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
            if ($categoriesNameEnOk) {
                $st = $db->prepare('UPDATE `events_categories` SET `name` = ?, `name_en` = ?, `parent_id` = ?, `color` = ?, `sort_order` = ? WHERE `id` = ?');
                $st->execute([$name, $nameEn, $parentId, $color, $sortOrder, $id]);
            } else {
                $st = $db->prepare('UPDATE `events_categories` SET `name` = ?, `parent_id` = ?, `color` = ?, `sort_order` = ? WHERE `id` = ?');
                $st->execute([$name, $parentId, $color, $sortOrder, $id]);
            }
            flash('success', 'Kategória mentve.');
            rendszer_log('kategória', $id, 'Módosítva', $name . ' (' . $color . ')');
            redirect(events_url('categories.php?edit=') . $id);
        }

        if ($categoriesNameEnOk) {
            $ins = $db->prepare('INSERT INTO `events_categories` (`name`, `name_en`, `parent_id`, `color`, `sort_order`) VALUES (?, ?, ?, ?, ?)');
            $ins->execute([$name, $nameEn, $parentId, $color, $sortOrder]);
        } else {
            $ins = $db->prepare('INSERT INTO `events_categories` (`name`, `parent_id`, `color`, `sort_order`) VALUES (?, ?, ?, ?)');
            $ins->execute([$name, $parentId, $color, $sortOrder]);
        }
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

    if ($action === 'migrate_category_events') {
        $fromId = (int) ($_POST['from_category_id'] ?? 0);
        $toId = (int) ($_POST['to_category_id'] ?? 0);
        if (!isset($_POST['confirm_migrate']) || (string) $_POST['confirm_migrate'] !== '1') {
            flash('error', 'Az áthelyezéshez jelöld be a megerősítő négyzetet.');
            $back = $fromId > 0 ? events_url('categories.php?edit=' . $fromId) : events_url('categories.php?tool=swap');
            redirect($back);
        }

        [$ok, $msg, $count] = events_category_migrate_all_events($db, $fromId, $toId);
        if ($ok) {
            flash('success', $msg);
            rendszer_log('kategória', $fromId, 'Események áthelyezve', $count . ' kapcsolat → #' . $toId);
            redirect(events_url('categories.php?edit=') . $fromId);
        }
        flash('error', $msg);
        $back = $fromId > 0 ? events_url('categories.php?edit=' . $fromId) : events_url('categories.php?tool=swap');
        redirect($back);
    }

    redirect(events_url('categories.php'));
}

$listLimitParsed = events_admin_list_limit_from_get();
$list_limit = $listLimitParsed['sql_limit'];
$listLimitValue = $listLimitParsed['value'];
$listTotalInDb = events_admin_table_total_count($db, 'events_categories');
$poolFrom = events_admin_table_pool_from_sql('events_categories', 'c', $list_limit);

$allowedOrder = ['id', 'name', 'name_en', 'parent', 'color', 'sort_order', 'events', 'modified'];
if (isset($_GET['order']) && in_array((string) $_GET['order'], $allowedOrder, true)) {
    $order = (string) $_GET['order'];
    $dir_param = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'asc' : 'desc';
} else {
    $order = 'parent';
    $dir_param = 'asc';
}

$showSwapTool = isset($_GET['tool']) && (string) $_GET['tool'] === 'swap';
$swapFromPreset = (int) ($_GET['from'] ?? 0);
$editId = (int) ($_GET['edit'] ?? 0);

$eventCountMap = events_categories_event_count_map($db);
$childCountMap = events_categories_child_count_map($db);

$dirSql = $dir_param === 'asc' ? 'ASC' : 'DESC';
$orderSql = match ($order) {
    'id' => "c.`id` $dirSql",
    'name' => "c.`name` $dirSql, c.`id` ASC",
    'name_en' => "c.`name_en` IS NULL, c.`name_en` $dirSql, c.`name` ASC, c.`id` ASC",
    'parent' => "p.`name` IS NULL, p.`name` $dirSql, c.`name` ASC, c.`id` ASC",
    'color' => "c.`color` $dirSql, c.`name` ASC, c.`id` ASC",
    'sort_order' => "c.`sort_order` $dirSql, c.`name` ASC, c.`id` ASC",
    'modified' => "c.`modified` $dirSql, c.`id` ASC",
    default => 'p.`name` IS NULL, p.`name` ASC, c.`name` ASC, c.`id` ASC',
};

$listSelect = $categoriesNameEnOk
    ? 'c.`id`, c.`name`, c.`name_en`, c.`parent_id`, c.`color`, c.`sort_order`, c.`modified`, p.`name` AS `parent_name`'
    : 'c.`id`, c.`name`, c.`parent_id`, c.`color`, c.`sort_order`, c.`modified`, p.`name` AS `parent_name`';

$listSql = "
    SELECT {$listSelect}
    FROM {$poolFrom}
    LEFT JOIN `events_categories` p ON p.`id` = c.`parent_id`
    ORDER BY {$orderSql}
";
$listRows = $db->query($listSql)->fetchAll(PDO::FETCH_ASSOC);
if (!$categoriesNameEnOk) {
    foreach ($listRows as &$lr) {
        $lr['name_en'] = '';
    }
    unset($lr);
}

if ($order === 'events') {
    usort($listRows, static function (array $a, array $b) use ($eventCountMap, $dir_param): int {
        $ca = $eventCountMap[(int) ($a['id'] ?? 0)] ?? 0;
        $cb = $eventCountMap[(int) ($b['id'] ?? 0)] ?? 0;
        if ($ca !== $cb) {
            return $dir_param === 'asc' ? $ca <=> $cb : $cb <=> $ca;
        }

        return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });
}

$listDisplayedCount = count($listRows);

$get_params = events_admin_list_limit_merge_get_params([], $listLimitValue);
if ($showSwapTool) {
    $get_params['tool'] = 'swap';
    if ($swapFromPreset > 0) {
        $get_params['from'] = (string) $swapFromPreset;
    }
}
if ($editId > 0) {
    $get_params['edit'] = (string) $editId;
}
if ($order !== 'parent' || $dir_param !== 'asc') {
    $get_params['order'] = $order;
    $get_params['dir'] = $dir_param;
}

if ($categoriesNameEnOk) {
    $allCategoryRows = $db->query('
        SELECT `id`, `name`, `name_en`, `parent_id`, `color`, `sort_order`, `modified`
        FROM `events_categories`
        ORDER BY `sort_order` ASC, `name` ASC, `id` ASC
    ')->fetchAll(PDO::FETCH_ASSOC);
} else {
    $allCategoryRows = $db->query('
        SELECT `id`, `name`, `parent_id`, `color`, `sort_order`, `modified`
        FROM `events_categories`
        ORDER BY `sort_order` ASC, `name` ASC, `id` ASC
    ')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($allCategoryRows as &$acr) {
        $acr['name_en'] = '';
    }
    unset($acr);
}
$flatAll = events_categories_flatten_tree($allCategoryRows);

$editRow = null;
if ($editId > 0) {
    if ($categoriesNameEnOk) {
        $stEdit = $db->prepare('SELECT `id`, `name`, `name_en`, `parent_id`, `color`, `sort_order`, `modified` FROM `events_categories` WHERE `id` = ? LIMIT 1');
    } else {
        $stEdit = $db->prepare('SELECT `id`, `name`, `parent_id`, `color`, `sort_order`, `modified` FROM `events_categories` WHERE `id` = ? LIMIT 1');
    }
    $stEdit->execute([$editId]);
    $editRow = $stEdit->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($editRow !== null && !$categoriesNameEnOk) {
        $editRow['name_en'] = '';
    }
    if ($editRow === null) {
        flash('error', 'A kategória nem található.');
        redirect(events_url('categories.php'));
    }
}

$form = [
    'id' => $editRow ? (int) $editRow['id'] : 0,
    'name' => $editRow ? (string) $editRow['name'] : '',
    'name_en' => $editRow ? (string) ($editRow['name_en'] ?? '') : '',
    'parent_id' => $editRow && $editRow['parent_id'] !== null ? (int) $editRow['parent_id'] : 0,
    'color' => $editRow ? (string) $editRow['color'] : '#6D8F63',
    'sort_order' => $editRow ? (int) $editRow['sort_order'] : 0,
];

$editEventCount = $form['id'] > 0 ? ($eventCountMap[$form['id']] ?? 0) : 0;
$editChildCount = $form['id'] > 0 ? ($childCountMap[$form['id']] ?? 0) : 0;
$editCanDelete = $form['id'] > 0 && $editEventCount === 0 && $editChildCount === 0;
$editLinkedEvents = $form['id'] > 0 ? events_category_linked_events($db, $form['id']) : [];
$editBase = events_url('szerkeszt.php?id=');

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Kategóriák';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>
<?php if (!$categoriesNameEnOk): ?>
    <p class="alert alert-warning">Az adatbázisban még nincs <code>name_en</code> oszlop a kategóriáknál. Az oldal így is működik (csak magyar név). A kétnyelvű megjelenítéshez futtasd a szervertől: <code>events/sql/migration_categories_name_en.sql</code></p>
<?php endif; ?>

<div class="card events-admin-card">
    <div class="events-list-head">
        <div class="events-list-head__start">
            <h2 class="events-list-title">Esemény kategóriák</h2>
            <?php
            $listLimitInForm = false;
            $listLimitStandalone = true;
            require __DIR__ . '/partials/admin_list_display_limit.php';
            ?>
        </div>
        <div class="events-list-actions">
            <a href="<?= h(events_url('categories.php?tool=swap')) ?>" class="btn btn-secondary">Kategória cserélő</a>
            <a href="<?= h(events_url('categories.php')) ?>" class="btn btn-secondary">Új kategória űrlap</a>
            <a href="<?= h(events_url('events_admin.php')) ?>" class="btn btn-secondary">Események</a>
        </div>
    </div>

    <?php if ($showSwapTool): ?>
        <section class="events-categories-swap" aria-labelledby="categories-swap-title">
            <h3 id="categories-swap-title" class="events-categories-swap__title">Kategória cserélő</h3>
            <p class="help">A forrás kategóriához rendelt összes esemény kapcsolata átkerül a cél kategóriába. Ha egy esemény már a cél kategóriában is szerepel, a forrás kapcsolat törlődik (duplikátum nélkül).</p>
            <form method="post" action="<?= h(events_url('categories.php?tool=swap')) ?>" class="events-categories-swap-form" onsubmit="return confirm('Biztosan áthelyezed az összes eseményt a forrás kategóriából a cél kategóriába?');">
                <?= csrf_input('events_categories') ?>
                <input type="hidden" name="action" value="migrate_category_events">
                <div class="events-categories-swap-form__grid">
                    <div class="form-group">
                        <label for="swap_from">Forrás kategória *</label>
                        <select id="swap_from" name="from_category_id" required>
                            <option value="">— válassz —</option>
                            <?php foreach ($flatAll as $r): ?>
                                <?php
                                $rid = (int) $r['id'];
                                $depth = (int) ($r['_depth'] ?? 0);
                                $prefix = str_repeat('— ', max(0, $depth));
                                $cnt = $eventCountMap[$rid] ?? 0;
                                ?>
                                <option value="<?= $rid ?>" <?= ($swapFromPreset === $rid || ($form['id'] === $rid && $swapFromPreset === 0)) ? 'selected' : '' ?>>
                                    <?= h($prefix . (string) $r['name'] . ' (#' . $rid . ', ' . $cnt . ' esemény)') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="swap_to">Cél kategória *</label>
                        <select id="swap_to" name="to_category_id" required>
                            <option value="">— válassz —</option>
                            <?php foreach ($flatAll as $r): ?>
                                <?php
                                $rid = (int) $r['id'];
                                $depth = (int) ($r['_depth'] ?? 0);
                                $prefix = str_repeat('— ', max(0, $depth));
                                ?>
                                <option value="<?= $rid ?>"><?= h($prefix . (string) $r['name'] . ' (#' . $rid . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <label class="events-categories-swap-form__confirm">
                    <input type="checkbox" name="confirm_migrate" value="1" required>
                    Megerősítem az összes kapcsolat áthelyezését
                </label>
                <div class="toolbar">
                    <button type="submit" class="btn btn-primary">Összes esemény áthelyezése</button>
                    <a href="<?= h(events_url('categories.php')) ?>" class="btn btn-secondary">Bezárás</a>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <div class="events-categories-layout">
        <section class="events-categories-table-wrap">
            <div class="table-wrap events-admin-table-wrap">
                <table class="sortable-table events-admin-table">
                    <thead>
                        <tr>
                            <th><?= sort_th('ID', 'id', $order, $dir_param, $get_params) ?></th>
                            <th><?= sort_th('Szülő', 'parent', $order, $dir_param, $get_params) ?></th>
                            <th><?= sort_th('Név (HU)', 'name', $order, $dir_param, $get_params) ?></th>
                            <th><?= sort_th('Név (EN)', 'name_en', $order, $dir_param, $get_params) ?></th>
                            <th><?= sort_th('Szín', 'color', $order, $dir_param, $get_params) ?></th>
                            <th><?= sort_th('Sorrend', 'sort_order', $order, $dir_param, $get_params) ?></th>
                            <th><?= sort_th('Események', 'events', $order, $dir_param, $get_params) ?></th>
                            <th><?= sort_th('Módosítva', 'modified', $order, $dir_param, $get_params) ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($listRows === []): ?>
                        <tr>
                            <td colspan="9">Még nincs kategória. Hozz létre egyet a jobb oldalon.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($listRows as $r): ?>
                            <?php
                            $rid = (int) $r['id'];
                            $color = (string) ($r['color'] ?? '#6D8F63');
                            $nameEnCell = trim((string) ($r['name_en'] ?? ''));
                            $rowEventCount = $eventCountMap[$rid] ?? 0;
                            $isEditing = $form['id'] === $rid;
                            $parentId = isset($r['parent_id']) && $r['parent_id'] !== null ? (int) $r['parent_id'] : 0;
                            $parentName = trim((string) ($r['parent_name'] ?? ''));
                            ?>
                            <tr<?= $isEditing ? ' class="is-selected"' : '' ?>>
                                <td><?= $rid ?></td>
                                <td>
                                    <?php if ($parentId > 0 && $parentName !== ''): ?>
                                        <a href="<?= h(events_url('categories.php?edit=' . $parentId)) ?>"><?= h($parentName) ?></a>
                                        <span class="events-categories-parent-id">#<?= $parentId ?></span>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><?= h((string) $r['name']) ?></td>
                                <td><?= $nameEnCell !== '' ? h($nameEnCell) : '—' ?></td>
                                <td>
                                    <span class="events-category-color-chip">
                                        <span class="events-category-color-chip__dot" style="background: <?= h($color) ?>;"></span>
                                        <?= h($color) ?>
                                    </span>
                                </td>
                                <td><?= (int) ($r['sort_order'] ?? 0) ?></td>
                                <td><?= (int) $rowEventCount ?></td>
                                <td><?= h((string) ($r['modified'] ?? '')) ?></td>
                                <td class="events-admin-table__actions">
                                    <a class="btn btn-secondary btn-sm" href="<?= h(events_url('categories.php?edit=' . $rid)) ?>">Szerkesztés</a>
                                </td>
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
                    <label for="cat_name">Név (magyar) *</label>
                    <input type="text" id="cat_name" name="name" required maxlength="255" value="<?= h((string) $form['name']) ?>">
                </div>

                <?php if ($categoriesNameEnOk): ?>
                <div class="form-group">
                    <label for="cat_name_en">Név (angol)</label>
                    <input type="text" id="cat_name_en" name="name_en" maxlength="255" value="<?= h((string) $form['name_en']) ?>" placeholder="Opcionális; üres → EN nézetben is magyar név">
                    <p class="help">Nyilvános esemény oldal: angol nézet csak akkor mutatja, ha ez ki van töltve (különben a magyar).</p>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="cat_parent">Szülő kategória</label>
                    <select id="cat_parent" name="parent_id">
                        <option value="">— nincs (gyökér szint) —</option>
                        <?php foreach ($flatAll as $r): ?>
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
                <p class="help events-categories-form-meta">
                    Kapcsolt események: <strong><?= (int) $editEventCount ?></strong>
                    <?php if ($editChildCount > 0): ?>
                        · Alkategóriák: <strong><?= (int) $editChildCount ?></strong>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <?php if ($form['id'] > 0 && $editLinkedEvents !== []): ?>
                <section class="events-categories-events" aria-labelledby="cat-events-title">
                    <h4 id="cat-events-title" class="events-categories-events__title">Kapcsolt események</h4>
                    <ul class="events-categories-events__list" role="list">
                        <?php foreach ($editLinkedEvents as $ev): ?>
                            <?php
                            $evId = (int) ($ev['id'] ?? 0);
                            $evStatus = (string) ($ev['event_status'] ?? '');
                            $evStart = $ev['event_start'] ?? null;
                            ?>
                            <li>
                                <a href="<?= h($editBase . $evId) ?>"><?= h((string) ($ev['event_name'] ?? '')) ?></a>
                                <span class="events-categories-events__meta">
                                    #<?= $evId ?>
                                    · <span class="event-status-badge <?= h(events_post_status_badge_class($evStatus)) ?>"><?= h(events_post_status_label($evStatus)) ?></span>
                                    <?php if ($evStart !== null && $evStart !== ''): ?>
                                        · <?= h(substr($evStart, 0, 16)) ?>
                                    <?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php elseif ($form['id'] > 0): ?>
                <p class="help events-categories-events__empty">Ehhez a kategóriához nincs esemény rendelve.</p>
            <?php endif; ?>

            <?php if ($form['id'] > 0 && $editEventCount > 0): ?>
                <section class="events-categories-migrate" aria-labelledby="cat-migrate-title">
                    <h4 id="cat-migrate-title" class="events-categories-migrate__title">Kategória cserélő</h4>
                    <p class="help">Az ehhez a kategóriához rendelt <strong><?= (int) $editEventCount ?></strong> esemény kapcsolata átkerül a cél kategóriába.</p>
                    <form method="post" action="<?= h(events_url('categories.php?edit=' . (int) $form['id'])) ?>" class="events-categories-migrate-form" onsubmit="return confirm('Biztosan áthelyezed az összes eseményt erről a kategóriáról a kiválasztottra?');">
                        <?= csrf_input('events_categories') ?>
                        <input type="hidden" name="action" value="migrate_category_events">
                        <input type="hidden" name="from_category_id" value="<?= (int) $form['id'] ?>">
                        <div class="form-group">
                            <label for="cat_migrate_to">Cél kategória *</label>
                            <select id="cat_migrate_to" name="to_category_id" required>
                                <option value="">— válassz —</option>
                                <?php foreach ($flatAll as $r): ?>
                                    <?php
                                    $rid = (int) $r['id'];
                                    if ($rid === (int) $form['id']) {
                                        continue;
                                    }
                                    $depth = (int) ($r['_depth'] ?? 0);
                                    $prefix = str_repeat('— ', max(0, $depth));
                                    ?>
                                    <option value="<?= $rid ?>"><?= h($prefix . (string) $r['name'] . ' (#' . $rid . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <label class="events-categories-migrate-form__confirm">
                            <input type="checkbox" name="confirm_migrate" value="1" required>
                            Megerősítem az áthelyezést
                        </label>
                        <div class="toolbar">
                            <button type="submit" class="btn btn-secondary">Összes esemény áthelyezése</button>
                            <a class="btn btn-secondary btn-sm" href="<?= h(events_url('categories.php?tool=swap&from=' . (int) $form['id'])) ?>">Nyitás a cserélő eszközben</a>
                        </div>
                    </form>
                </section>
            <?php endif; ?>

            <?php if ($editCanDelete): ?>
                <form method="post" action="<?= h(events_url('categories.php?edit=' . (int) $form['id'])) ?>" class="events-categories-delete" onsubmit="return confirm('Biztosan törlöd ezt a kategóriát?');">
                    <?= csrf_input('events_categories') ?>
                    <input type="hidden" name="action" value="delete_category">
                    <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
                    <button type="submit" class="btn btn-secondary">Kategória törlése</button>
                </form>
            <?php elseif ($form['id'] > 0): ?>
                <p class="help events-categories-delete-blocked">
                    <?php if ($editEventCount > 0 && $editChildCount > 0): ?>
                        A kategória nem törölhető: <?= (int) $editEventCount ?> esemény és <?= (int) $editChildCount ?> alkategória kapcsolódik hozzá.
                    <?php elseif ($editEventCount > 0): ?>
                        A kategória nem törölhető: <?= (int) $editEventCount ?> esemény használja.
                    <?php else: ?>
                        A kategória nem törölhető: <?= (int) $editChildCount ?> alkategória tartozik hozzá.
                    <?php endif; ?>
                </p>
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

<?php require __DIR__ . '/partials/admin_list_display_limit_script.php'; ?>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
