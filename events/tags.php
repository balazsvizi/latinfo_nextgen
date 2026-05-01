<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/nextgen/includes/auth.php';
require_once __DIR__ . '/lib/event_request.php';
requireLogin();

$db = getDb();

if (!events_tags_tables_available($db)) {
    $mainContentClass = 'main-content main-content--fullwidth';
    $pageTitle = 'Címkék';
    require_once dirname(__DIR__) . '/nextgen/partials/header.php';
    echo '<div class="card events-admin-card">';
    echo '<p class="alert alert-error">Hiányoznak a címke táblák. Futtasd: <code>events/sql/migration_tags.sql</code></p>';
    echo '<p><a href="' . h(events_url('events_admin.php')) . '" class="btn btn-secondary">Vissza az eseményekhez</a></p>';
    echo '</div>';
    require_once dirname(__DIR__) . '/nextgen/partials/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!csrf_validate('events_tags')) {
        flash('error', 'Lejárt vagy érvénytelen munkamenet.');
        redirect(events_url('tags.php'));
    }

    if ($action === 'save_specialtag') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            flash('error', 'A speciális csoport neve kötelező.');
            redirect(events_url('tags.php') . ($id > 0 ? '?edit_special=' . $id : ''));
        }
        if ($id > 0) {
            $st = $db->prepare('UPDATE `events_specialtags` SET `name` = ? WHERE `id` = ?');
            $st->execute([$name, $id]);
            flash('success', 'Speciális csoport mentve.');
            rendszer_log('spec_tag', $id, 'Módosítva', $name);
            redirect(events_url('tags.php?edit_special=') . $id);
        }
        $ins = $db->prepare('INSERT INTO `events_specialtags` (`name`) VALUES (?)');
        $ins->execute([$name]);
        $newId = (int) $db->lastInsertId();
        flash('success', 'Speciális csoport létrehozva.');
        rendszer_log('spec_tag', $newId, 'Létrehozva', $name);
        redirect(events_url('tags.php?edit_special=') . $newId);
    }

    if ($action === 'delete_specialtag') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash('error', 'Érvénytelen azonosító.');
            redirect(events_url('tags.php'));
        }
        $st = $db->prepare('SELECT `name` FROM `events_specialtags` WHERE `id` = ?');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            flash('error', 'Nem található.');
            redirect(events_url('tags.php'));
        }
        $nm = (string) $row['name'];
        $db->prepare('DELETE FROM `events_specialtags` WHERE `id` = ?')->execute([$id]);
        flash('success', 'Speciális csoport törölve.');
        rendszer_log('spec_tag', $id, 'Törölve', $nm);
        redirect(events_url('tags.php'));
    }

    if ($action === 'save_tag') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $specRaw = $_POST['special_tag_ids'] ?? [];
        $specIds = [];
        if (is_array($specRaw)) {
            foreach ($specRaw as $v) {
                $s = (int) $v;
                if ($s > 0 && !in_array($s, $specIds, true)) {
                    $specIds[] = $s;
                }
            }
        }
        if ($name === '') {
            flash('error', 'A címke neve kötelező.');
            redirect(events_url('tags.php') . ($id > 0 ? '?edit_tag=' . $id : ''));
        }
        sort($specIds);
        if ($specIds !== []) {
            $ph = implode(',', array_fill(0, count($specIds), '?'));
            $chk = $db->prepare("SELECT COUNT(*) FROM `events_specialtags` WHERE `id` IN ({$ph})");
            $chk->execute($specIds);
            if ((int) $chk->fetchColumn() !== count($specIds)) {
                flash('error', 'Érvénytelen speciális csoport lett kijelölve.');
                redirect(events_url('tags.php') . ($id > 0 ? '?edit_tag=' . $id : ''));
            }
        }

        if ($id > 0) {
            $db->beginTransaction();
            try {
                $st = $db->prepare('UPDATE `events_tags` SET `name` = ? WHERE `id` = ?');
                $st->execute([$name, $id]);
                events_save_tag_special_memberships($db, $id, $specIds);
                $db->commit();
            } catch (Throwable $e) {
                $db->rollBack();
                throw $e;
            }
            flash('success', 'Címke mentve.');
            rendszer_log('tag', $id, 'Módosítva', $name);
            redirect(events_url('tags.php?edit_tag=') . $id);
        }

        $db->beginTransaction();
        try {
            $ins = $db->prepare('INSERT INTO `events_tags` (`name`) VALUES (?)');
            $ins->execute([$name]);
            $newId = (int) $db->lastInsertId();
            events_save_tag_special_memberships($db, $newId, $specIds);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        flash('success', 'Címke létrehozva.');
        rendszer_log('tag', $newId, 'Létrehozva', $name);
        redirect(events_url('tags.php?edit_tag=') . $newId);
    }

    if ($action === 'delete_tag') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash('error', 'Érvénytelen címke azonosító.');
            redirect(events_url('tags.php'));
        }
        $st = $db->prepare('SELECT `name` FROM `events_tags` WHERE `id` = ?');
        $st->execute([$id]);
        $self = $st->fetch(PDO::FETCH_ASSOC);
        if (!$self) {
            flash('error', 'A címke nem található.');
            redirect(events_url('tags.php'));
        }
        $nm = (string) $self['name'];
        $stUse = $db->prepare('SELECT COUNT(*) FROM `events_calendar_event_tags` WHERE `tag_id` = ?');
        $stUse->execute([$id]);
        $useCnt = (int) $stUse->fetchColumn();
        if ($useCnt > 0) {
            flash('error', 'A címke nem törölhető, mert ' . $useCnt . ' esemény használja.');
            redirect(events_url('tags.php?edit_tag=') . $id);
        }
        $db->prepare('DELETE FROM `events_tags` WHERE `id` = ?')->execute([$id]);
        flash('success', 'Címke törölve.');
        rendszer_log('tag', $id, 'Törölve', $nm);
        redirect(events_url('tags.php'));
    }

    redirect(events_url('tags.php'));
}

$specials = $db->query('SELECT `id`, `name` FROM `events_specialtags` ORDER BY `name` ASC, `id` ASC')->fetchAll(PDO::FETCH_ASSOC);

$tagsWithSpecials = $db->query('
    SELECT t.`id`, t.`name`,
           GROUP_CONCAT(s.`name` ORDER BY s.`name` SEPARATOR ", ") AS `specials_label`
    FROM `events_tags` t
    LEFT JOIN `events_special_tags` st ON st.`tag_id` = t.`id`
    LEFT JOIN `events_specialtags` s ON s.`id` = st.`special_tag_id`
    GROUP BY t.`id`, t.`name`
    ORDER BY t.`name` ASC, t.`id` ASC
')->fetchAll(PDO::FETCH_ASSOC);

$editSpecialId = (int) ($_GET['edit_special'] ?? 0);
$formSpecial = ['id' => 0, 'name' => ''];
if ($editSpecialId > 0) {
    $st = $db->prepare('SELECT `id`, `name` FROM `events_specialtags` WHERE `id` = ?');
    $st->execute([$editSpecialId]);
    $sr = $st->fetch(PDO::FETCH_ASSOC);
    if ($sr) {
        $formSpecial = ['id' => (int) $sr['id'], 'name' => (string) $sr['name']];
    }
}

$editTagId = (int) ($_GET['edit_tag'] ?? 0);
$formTag = ['id' => 0, 'name' => '', 'special_ids' => []];
if ($editTagId > 0) {
    $st = $db->prepare('SELECT `id`, `name` FROM `events_tags` WHERE `id` = ?');
    $st->execute([$editTagId]);
    $tr = $st->fetch(PDO::FETCH_ASSOC);
    if ($tr) {
        $formTag = [
            'id' => (int) $tr['id'],
            'name' => (string) $tr['name'],
            'special_ids' => events_load_special_ids_for_tag($db, $editTagId),
        ];
    }
}

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Címkék';
require_once dirname(__DIR__) . '/nextgen/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="card events-admin-card events-tags-admin">
    <div class="events-list-head">
        <h2 class="events-list-title">Esemény címkék</h2>
        <div class="events-list-actions">
            <a href="<?= h(events_url('events_admin.php')) ?>" class="btn btn-secondary">Események listája</a>
        </div>
    </div>

    <div class="events-tags-admin__forms-top">
        <div class="events-tags-admin__form-panel events-tags-admin__form-panel--tag">
            <h3 style="margin-top:0;"><?= $formTag['id'] > 0 ? 'Címke szerkesztése' : 'Új címke' ?></h3>
            <form method="post" action="<?= h(events_url('tags.php') . ($formTag['id'] > 0 ? '?edit_tag=' . (int) $formTag['id'] : '')) ?>">
                <?= csrf_input('events_tags') ?>
                <input type="hidden" name="action" value="save_tag">
                <input type="hidden" name="id" value="<?= (int) $formTag['id'] ?>">
                <div class="form-group">
                    <label for="tag_name">Név *</label>
                    <input type="text" id="tag_name" name="name" required maxlength="255" value="<?= h($formTag['name']) ?>">
                </div>
                <fieldset class="form-group events-tags-special-fieldset">
                    <legend style="font-size:0.9rem;font-weight:600;margin-bottom:0.35rem;">Speciális csoport(ok)</legend>
                    <p class="help" style="margin-top:0;">Jelöld be, ha a címke tartozik valamelyik csoportba.</p>
                    <?php if ($specials === []): ?>
                        <p class="help">Előbb add meg jobbra a speciális csoport nevét, majd mentsd.</p>
                    <?php else: ?>
                        <div class="events-tags-special-checkboxes">
                            <?php foreach ($specials as $sp): ?>
                                <?php $sid = (int) $sp['id']; ?>
                                <label class="events-tags-special-check-label">
                                    <input type="checkbox" name="special_tag_ids[]" value="<?= $sid ?>" <?= in_array($sid, $formTag['special_ids'], true) ? 'checked' : '' ?>>
                                    <?= h((string) $sp['name']) ?> <span class="help">(#<?= $sid ?>)</span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </fieldset>
                <div class="toolbar">
                    <button type="submit" class="btn btn-primary">Mentés</button>
                    <?php if ($formTag['id'] > 0): ?>
                        <a class="btn btn-secondary" href="<?= h(events_url('tags.php')) ?>">Új címke</a>
                    <?php endif; ?>
                </div>
            </form>
            <?php if ($formTag['id'] > 0): ?>
                <form method="post" action="<?= h(events_url('tags.php?edit_tag=' . (int) $formTag['id'])) ?>" style="margin-top:1rem;" onsubmit="return confirm('Biztosan törlöd ezt a címkét?');">
                    <?= csrf_input('events_tags') ?>
                    <input type="hidden" name="action" value="delete_tag">
                    <input type="hidden" name="id" value="<?= (int) $formTag['id'] ?>">
                    <button type="submit" class="btn btn-secondary">Címke törlése</button>
                </form>
                <p class="help" style="margin-top:0.5rem;">Törlés csak akkor lehetséges, ha egy esemény sem használja.</p>
            <?php endif; ?>
        </div>
        <div class="events-tags-admin__form-panel events-tags-admin__form-panel--special">
            <h3 style="margin-top:0;"><?= $formSpecial['id'] > 0 ? 'Speciális csoport szerkesztése' : 'Új speciális csoport' ?></h3>
            <form method="post" action="<?= h(events_url('tags.php') . ($formSpecial['id'] > 0 ? '?edit_special=' . (int) $formSpecial['id'] : '')) ?>">
                <?= csrf_input('events_tags') ?>
                <input type="hidden" name="action" value="save_specialtag">
                <input type="hidden" name="id" value="<?= (int) $formSpecial['id'] ?>">
                <div class="form-group">
                    <label for="spec_name">Név *</label>
                    <input type="text" id="spec_name" name="name" required maxlength="255" value="<?= h($formSpecial['name']) ?>" placeholder="pl. DJ-k, stílusok">
                </div>
                <div class="toolbar">
                    <button type="submit" class="btn btn-primary">Mentés</button>
                    <?php if ($formSpecial['id'] > 0): ?>
                        <a class="btn btn-secondary" href="<?= h(events_url('tags.php')) ?>">Új csoport</a>
                    <?php endif; ?>
                </div>
            </form>
            <?php if ($formSpecial['id'] > 0): ?>
                <form method="post" action="<?= h(events_url('tags.php?edit_special=' . (int) $formSpecial['id'])) ?>" style="margin-top:1rem;" onsubmit="return confirm('Biztosan törlöd ezt a speciális csoportot? A címkék kapcsolatai ehhez a csoporthoz törlődnek.');">
                    <?= csrf_input('events_tags') ?>
                    <input type="hidden" name="action" value="delete_specialtag">
                    <input type="hidden" name="id" value="<?= (int) $formSpecial['id'] ?>">
                    <button type="submit" class="btn btn-secondary">Csoport törlése</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <section class="events-tags-admin__tables">
        <h3 style="margin-top:0;">Speciális tag csoportok</h3>
        <div class="table-wrap events-admin-table-wrap" style="margin-bottom:2rem;">
            <table class="sortable-table events-admin-table">
                <thead>
                    <tr><th>ID</th><th>Név</th><th></th></tr>
                </thead>
                <tbody>
                <?php if ($specials === []): ?>
                    <tr><td colspan="3">Még nincs speciális csoport.</td></tr>
                <?php else: ?>
                    <?php foreach ($specials as $sp): ?>
                        <?php $sid = (int) $sp['id']; ?>
                        <tr>
                            <td><?= $sid ?></td>
                            <td><a class="events-cell-edit" href="<?= h(events_url('tags.php?edit_special=' . $sid)) ?>"><?= h((string) $sp['name']) ?></a></td>
                            <td><a href="<?= h(events_url('tags.php?edit_special=' . $sid)) ?>" class="btn btn-secondary btn-sm">Szerkesztés</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <h3>Tag lista</h3>
        <div class="table-wrap events-admin-table-wrap">
            <table class="sortable-table events-admin-table">
                <thead>
                    <tr><th>ID</th><th>Név</th><th>Speciális csoportok</th><th></th></tr>
                </thead>
                <tbody>
                <?php if ($tagsWithSpecials === []): ?>
                    <tr><td colspan="4">Még nincs címke.</td></tr>
                <?php else: ?>
                    <?php foreach ($tagsWithSpecials as $tr): ?>
                        <?php $tid = (int) $tr['id']; ?>
                        <tr>
                            <td><?= $tid ?></td>
                            <td><a class="events-cell-edit" href="<?= h(events_url('tags.php?edit_tag=' . $tid)) ?>"><?= h((string) $tr['name']) ?></a></td>
                            <td><?= trim((string) ($tr['specials_label'] ?? '')) !== '' ? h((string) $tr['specials_label']) : '—' ?></td>
                            <td><a href="<?= h(events_url('tags.php?edit_tag=' . $tid)) ?>" class="btn btn-secondary btn-sm">Szerkesztés</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php require_once dirname(__DIR__) . '/nextgen/partials/footer.php'; ?>
