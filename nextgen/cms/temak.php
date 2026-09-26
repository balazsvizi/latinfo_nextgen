<?php
declare(strict_types=1);

/**
 * CMS témák szótár.
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
requireLogin();

$db = getDb();
cms_ensure_schema($db);
$hiba = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('cms_temak')) {
        flash('error', 'Lejárt vagy érvénytelen munkamenet.');
        redirect(cms_url('temak.php'));
    }
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $slugRaw = trim((string) ($_POST['slug'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '') {
            $hiba = 'A téma neve kötelező.';
        } else {
            $base = $slugRaw !== '' ? cms_theme_slugify($slugRaw) : cms_theme_slugify($name);
            $slug = cms_ensure_unique_theme_slug($db, $base, $id > 0 ? $id : null);
            try {
                if ($id > 0) {
                    $db->prepare('
                        UPDATE `cms_themes`
                        SET `name` = ?, `slug` = ?, `description` = ?, `sort_order` = ?, `is_active` = ?
                        WHERE `id` = ?
                    ')->execute([$name, $slug, $description, $sortOrder, $isActive, $id]);
                    rendszer_log('cms_theme', $id, 'Módosítva', 'Név: ' . $name);
                    flash('success', 'Téma módosítva.');
                } else {
                    $db->prepare('
                        INSERT INTO `cms_themes` (`name`, `slug`, `description`, `sort_order`, `is_active`)
                        VALUES (?, ?, ?, ?, ?)
                    ')->execute([$name, $slug, $description, $sortOrder, $isActive]);
                    $newId = (int) $db->lastInsertId();
                    rendszer_log('cms_theme', $newId, 'Létrehozva', 'Név: ' . $name);
                    flash('success', 'Téma létrehozva.');
                }
                redirect(cms_url('temak.php'));
            } catch (Throwable $e) {
                error_log('cms temak save: ' . $e->getMessage());
                $hiba = 'Mentés sikertelen.';
            }
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $counts = cms_themes_post_count_map($db);
            if (($counts[$id] ?? 0) > 0) {
                $hiba = 'A téma használatban van, ezért nem törölhető. Előbb válassz más témát a cikkeknél, vagy tiltsd le.';
            } else {
                $db->prepare('DELETE FROM `cms_themes` WHERE `id` = ?')->execute([$id]);
                rendszer_log('cms_theme', $id, 'Törölve', null);
                flash('success', 'Téma törölve.');
                redirect(cms_url('temak.php'));
            }
        }
    }
}

$themes = cms_themes_list($db, false);
$counts = cms_themes_post_count_map($db);
$editId = (int) ($_GET['edit'] ?? 0);
$editRow = null;
if ($editId > 0) {
    foreach ($themes as $t) {
        if ((int) ($t['id'] ?? 0) === $editId) {
            $editRow = $t;
            break;
        }
    }
}

$pageTitle = 'CMS témák';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>

<div class="card">
    <h2><?= $editRow ? 'Téma szerkesztése' : 'Új téma' ?></h2>
    <form method="post" action="<?= h(cms_url('temak.php')) ?>">
        <?= csrf_input('cms_temak') ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= $editRow ? (int) $editRow['id'] : 0 ?>">
        <div class="form-row">
            <label for="theme-name">Név *</label>
            <input type="text" id="theme-name" name="name" required maxlength="120" value="<?= h((string) ($editRow['name'] ?? '')) ?>">
        </div>
        <div class="form-row">
            <label for="theme-slug">Slug</label>
            <input type="text" id="theme-slug" name="slug" maxlength="140" value="<?= h((string) ($editRow['slug'] ?? '')) ?>">
        </div>
        <div class="form-row">
            <label for="theme-desc">Leírás</label>
            <input type="text" id="theme-desc" name="description" maxlength="400" value="<?= h((string) ($editRow['description'] ?? '')) ?>">
        </div>
        <div class="form-row">
            <label for="theme-sort">Sorrend</label>
            <input type="number" id="theme-sort" name="sort_order" value="<?= (int) ($editRow['sort_order'] ?? 0) ?>">
        </div>
        <div class="form-row">
            <label><input type="checkbox" name="is_active" value="1"<?= !$editRow || !empty($editRow['is_active']) ? ' checked' : '' ?>> Aktív</label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $editRow ? 'Mentés' : 'Felvétel' ?></button>
            <?php if ($editRow): ?>
            <a href="<?= h(cms_url('temak.php')) ?>" class="btn btn-secondary">Mégsem</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <h2>Témák</h2>
    <table class="table">
        <thead>
            <tr>
                <th>Név</th>
                <th>Slug</th>
                <th>Sorrend</th>
                <th>Cikkek</th>
                <th>Aktív</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($themes as $t): ?>
            <?php $tid = (int) ($t['id'] ?? 0); ?>
            <tr>
                <td><strong><?= h((string) ($t['name'] ?? '')) ?></strong>
                    <?php if (trim((string) ($t['description'] ?? '')) !== ''): ?>
                        <div class="help"><?= h((string) $t['description']) ?></div>
                    <?php endif; ?>
                </td>
                <td><code><?= h((string) ($t['slug'] ?? '')) ?></code></td>
                <td><?= (int) ($t['sort_order'] ?? 0) ?></td>
                <td><?= (int) ($counts[$tid] ?? 0) ?></td>
                <td><?= !empty($t['is_active']) ? 'Igen' : 'Nem' ?></td>
                <td class="table-actions">
                    <a class="btn btn-secondary btn-sm" href="<?= h(cms_url('temak.php?edit=' . $tid)) ?>">Szerkeszt</a>
                    <?php if (($counts[$tid] ?? 0) === 0): ?>
                    <form method="post" action="<?= h(cms_url('temak.php')) ?>" style="display:inline;" onsubmit="return confirm('Törölhető a téma?');">
                        <?= csrf_input('cms_temak') ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $tid ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Törlés</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($themes === []): ?>
            <tr><td colspan="6" class="help">Még nincs téma.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
