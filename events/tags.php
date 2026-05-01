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
            redirect(events_url('tags.php?open_special=') . ($id > 0 ? (string) $id : 'new'));
        }
        if ($id > 0) {
            $st = $db->prepare('UPDATE `events_specialtags` SET `name` = ? WHERE `id` = ?');
            $st->execute([$name, $id]);
            flash('success', 'Speciális csoport mentve.');
            rendszer_log('spec_tag', $id, 'Módosítva', $name);
            redirect(events_url('tags.php'));
        }
        $ins = $db->prepare('INSERT INTO `events_specialtags` (`name`) VALUES (?)');
        $ins->execute([$name]);
        $newId = (int) $db->lastInsertId();
        flash('success', 'Speciális csoport létrehozva.');
        rendszer_log('spec_tag', $newId, 'Létrehozva', $name);
        redirect(events_url('tags.php'));
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
            redirect(events_url('tags.php?open_tag=') . ($id > 0 ? (string) $id : 'new'));
        }
        sort($specIds);
        if ($specIds !== []) {
            $ph = implode(',', array_fill(0, count($specIds), '?'));
            $chk = $db->prepare("SELECT COUNT(*) FROM `events_specialtags` WHERE `id` IN ({$ph})");
            $chk->execute($specIds);
            if ((int) $chk->fetchColumn() !== count($specIds)) {
                flash('error', 'Érvénytelen speciális csoport lett kijelölve.');
                redirect(events_url('tags.php?open_tag=') . ($id > 0 ? (string) $id : 'new'));
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
            redirect(events_url('tags.php'));
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
        redirect(events_url('tags.php'));
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
            redirect(events_url('tags.php?open_tag=') . $id);
        }
        $db->prepare('DELETE FROM `events_tags` WHERE `id` = ?')->execute([$id]);
        flash('success', 'Címke törölve.');
        rendszer_log('tag', $id, 'Törölve', $nm);
        redirect(events_url('tags.php'));
    }

    if ($action === 'bulk_add_specials_to_tags') {
        $tagRaw = $_POST['tag_ids'] ?? [];
        $specRaw = $_POST['bulk_special_tag_ids'] ?? [];
        $tagIds = [];
        if (is_array($tagRaw)) {
            foreach ($tagRaw as $v) {
                $t = (int) $v;
                if ($t > 0 && !in_array($t, $tagIds, true)) {
                    $tagIds[] = $t;
                }
            }
        }
        $specIds = [];
        if (is_array($specRaw)) {
            foreach ($specRaw as $v) {
                $s = (int) $v;
                if ($s > 0 && !in_array($s, $specIds, true)) {
                    $specIds[] = $s;
                }
            }
        }
        if ($tagIds === []) {
            flash('error', 'Válassz ki legalább egy címkét a táblázatban.');
            redirect(events_url('tags.php'));
        }
        if ($specIds === []) {
            flash('error', 'Válassz ki legalább egy speciális csoportot a hozzáadáshoz.');
            redirect(events_url('tags.php'));
        }
        $ph = implode(',', array_fill(0, count($specIds), '?'));
        $chk = $db->prepare("SELECT COUNT(*) FROM `events_specialtags` WHERE `id` IN ({$ph})");
        $chk->execute($specIds);
        if ((int) $chk->fetchColumn() !== count($specIds)) {
            flash('error', 'Érvénytelen speciális csoport lett kijelölve.');
            redirect(events_url('tags.php'));
        }
        $phTags = implode(',', array_fill(0, count($tagIds), '?'));
        $chkTags = $db->prepare("SELECT COUNT(*) FROM `events_tags` WHERE `id` IN ({$phTags})");
        $chkTags->execute($tagIds);
        if ((int) $chkTags->fetchColumn() !== count($tagIds)) {
            flash('error', 'Érvénytelen címke lett kijelölve.');
            redirect(events_url('tags.php'));
        }
        $db->beginTransaction();
        try {
            foreach ($tagIds as $tid) {
                events_merge_tag_special_memberships($db, $tid, $specIds);
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        flash('success', 'A kijelölt speciális csoportok hozzá lettek adva ' . count($tagIds) . ' címkéhez.');
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

$tagSpecialIdsByTag = [];
$stMap = $db->query('SELECT `tag_id`, `special_tag_id` FROM `events_special_tags` ORDER BY `tag_id` ASC, `special_tag_id` ASC');
while ($row = $stMap->fetch(PDO::FETCH_ASSOC)) {
    $tid = (int) $row['tag_id'];
    $tagSpecialIdsByTag[$tid][] = (int) $row['special_tag_id'];
}

$openTagRaw = (string) ($_GET['open_tag'] ?? '');
$openSpecialRaw = (string) ($_GET['open_special'] ?? '');
$openTagGroup = '';
if ($openTagRaw === 'new') {
    $openTagGroup = 'new';
} elseif ($openTagRaw !== '' && ctype_digit($openTagRaw)) {
    $openTagGroup = (string) (int) $openTagRaw;
}
$openSpecialGroup = '';
if ($openSpecialRaw === 'new') {
    $openSpecialGroup = 'new';
} elseif ($openSpecialRaw !== '' && ctype_digit($openSpecialRaw)) {
    $openSpecialGroup = (string) (int) $openSpecialRaw;
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

    <div class="table-wrap events-admin-table-wrap events-inline-expand-wrap">
        <table
            class="events-admin-table events-inline-expand-table"
            id="events-tags-inline-table"
            data-sticky-group="new"
            data-initial-open="<?= h($openTagGroup) ?>"
        >
            <thead>
                <tr>
                    <th scope="col" class="events-tags-bulk-th">
                        <label class="events-tags-bulk-selectall visually-hidden" for="tags-bulk-select-all">Összes címke kijelölése</label>
                        <input type="checkbox" id="tags-bulk-select-all" title="Összes címke kijelölése / törlése" aria-label="Összes címke kijelölése">
                    </th>
                    <th scope="col">
                        <span class="events-inline-th-label">ID</span>
                        <button type="button" class="events-inline-sort-btn" data-sort-col="1" data-sort-type="int" aria-label="Rendezés ID szerint">↕</button>
                    </th>
                    <th scope="col">
                        <span class="events-inline-th-label">Név</span>
                        <button type="button" class="events-inline-sort-btn" data-sort-col="2" data-sort-type="text" aria-label="Rendezés név szerint">↕</button>
                    </th>
                    <th scope="col">
                        <span class="events-inline-th-label">Speciális csoportok</span>
                        <button type="button" class="events-inline-sort-btn" data-sort-col="3" data-sort-type="text" aria-label="Rendezés csoport szerint">↕</button>
                    </th>
                </tr>
                <tr class="events-inline-filter-row">
                    <th class="events-tags-bulk-th"></th>
                    <th><input type="search" class="events-inline-filter-input" data-filter-col="1" placeholder="Szűrés…" aria-label="Szűrés ID"></th>
                    <th><input type="search" class="events-inline-filter-input" data-filter-col="2" placeholder="Szűrés…" aria-label="Szűrés név"></th>
                    <th><input type="search" class="events-inline-filter-input" data-filter-col="3" placeholder="Szűrés…" aria-label="Szűrés csoport"></th>
                </tr>
            </thead>
            <tbody>
                <tr
                    class="events-inline-summary<?= $openTagGroup === 'new' ? ' is-active' : '' ?>"
                    data-expand-group="new"
                    tabindex="0"
                    role="button"
                    aria-expanded="<?= $openTagGroup === 'new' ? 'true' : 'false' ?>"
                >
                    <td class="events-tags-bulk-td"></td>
                    <td class="events-inline-summary-muted">—</td>
                    <td colspan="2"><strong>Új címke</strong> <span class="events-inline-summary-hint">(kattints a szerkesztéshez)</span></td>
                </tr>
                <tr class="events-inline-detail" data-expand-group="new" <?= $openTagGroup === 'new' ? '' : 'hidden' ?>>
                    <td colspan="4">
                        <div class="events-tags-admin__form-panel events-tags-admin__form-panel--inline">
                            <form method="post" action="<?= h(events_url('tags.php')) ?>">
                                <?= csrf_input('events_tags') ?>
                                <input type="hidden" name="action" value="save_tag">
                                <input type="hidden" name="id" value="0">
                                <div class="form-group">
                                    <label for="tag_name_new">Név *</label>
                                    <input type="text" id="tag_name_new" name="name" required maxlength="255" value="">
                                </div>
                                <fieldset class="form-group events-tags-special-fieldset">
                                    <legend class="events-tags-special-legend">Speciális csoport(ok)</legend>
                                    <?php if ($specials === []): ?>
                                        <p class="help">Előbb hozz létre speciális csoportot lent, majd mentsd.</p>
                                    <?php else: ?>
                                        <div class="events-tags-special-checkboxes">
                                            <?php foreach ($specials as $sp): ?>
                                                <?php $sid = (int) $sp['id']; ?>
                                                <label class="events-tags-special-check-label">
                                                    <input type="checkbox" name="special_tag_ids[]" value="<?= $sid ?>">
                                                    <span class="events-tags-special-check-text"><?= h((string) $sp['name']) ?> <span class="help">(#<?= $sid ?>)</span></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </fieldset>
                                <div class="toolbar">
                                    <button type="submit" class="btn btn-primary">Mentés</button>
                                </div>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php foreach ($tagsWithSpecials as $tr): ?>
                    <?php
                    $tid = (int) $tr['id'];
                    $specIds = $tagSpecialIdsByTag[$tid] ?? [];
                    $slabel = trim((string) ($tr['specials_label'] ?? ''));
                    $isOpen = $openTagGroup !== '' && $openTagGroup === (string) $tid;
                    ?>
                    <tr
                        class="events-inline-summary<?= $isOpen ? ' is-active' : '' ?>"
                        data-expand-group="<?= $tid ?>"
                        tabindex="0"
                        role="button"
                        aria-expanded="<?= $isOpen ? 'true' : 'false' ?>"
                    >
                        <td class="events-tags-bulk-td">
                            <input
                                type="checkbox"
                                name="tag_ids[]"
                                value="<?= $tid ?>"
                                form="tags-bulk-special-form"
                                aria-label="Kijelölés: <?= h((string) $tr['name']) ?>"
                            >
                        </td>
                        <td><?= $tid ?></td>
                        <td><?= h((string) $tr['name']) ?></td>
                        <td><?= $slabel !== '' ? h($slabel) : '—' ?></td>
                    </tr>
                    <tr class="events-inline-detail" data-expand-group="<?= $tid ?>" <?= $isOpen ? '' : 'hidden' ?>>
                        <td colspan="4">
                            <div class="events-tags-admin__form-panel events-tags-admin__form-panel--inline">
                                <form method="post" action="<?= h(events_url('tags.php')) ?>">
                                    <?= csrf_input('events_tags') ?>
                                    <input type="hidden" name="action" value="save_tag">
                                    <input type="hidden" name="id" value="<?= $tid ?>">
                                    <div class="form-group">
                                        <label for="tag_name_<?= $tid ?>">Név *</label>
                                        <input type="text" id="tag_name_<?= $tid ?>" name="name" required maxlength="255" value="<?= h((string) $tr['name']) ?>">
                                    </div>
                                    <fieldset class="form-group events-tags-special-fieldset">
                                        <legend class="events-tags-special-legend">Speciális csoport(ok)</legend>
                                        <?php if ($specials === []): ?>
                                            <p class="help">Nincs definiált speciális csoport.</p>
                                        <?php else: ?>
                                            <div class="events-tags-special-checkboxes">
                                                <?php foreach ($specials as $sp): ?>
                                                    <?php $sid = (int) $sp['id']; ?>
                                                    <label class="events-tags-special-check-label">
                                                        <input type="checkbox" name="special_tag_ids[]" value="<?= $sid ?>" <?= in_array($sid, $specIds, true) ? 'checked' : '' ?>>
                                                        <span class="events-tags-special-check-text"><?= h((string) $sp['name']) ?> <span class="help">(#<?= $sid ?>)</span></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </fieldset>
                                    <div class="toolbar">
                                        <button type="submit" class="btn btn-primary">Mentés</button>
                                    </div>
                                </form>
                                <form method="post" action="<?= h(events_url('tags.php')) ?>" class="events-tags-delete-form" onsubmit="return confirm('Biztosan törlöd ezt a címkét?');">
                                    <?= csrf_input('events_tags') ?>
                                    <input type="hidden" name="action" value="delete_tag">
                                    <input type="hidden" name="id" value="<?= $tid ?>">
                                    <button type="submit" class="btn btn-secondary">Címke törlése</button>
                                </form>
                                <p class="help">Törlés csak akkor lehetséges, ha egy esemény sem használja.</p>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="events-tags-bulk-panel">
        <form id="tags-bulk-special-form" method="post" action="<?= h(events_url('tags.php')) ?>" class="events-tags-bulk-form">
            <?= csrf_input('events_tags') ?>
            <input type="hidden" name="action" value="bulk_add_specials_to_tags">
            <fieldset class="events-tags-bulk-fieldset">
                <legend class="events-tags-bulk-legend">Csoportos művelet: speciális csoportok hozzáadása</legend>
                <p class="help events-tags-bulk-help">Jelöld ki a címkéket a fenti táblázatban, válaszd ki a hozzáadandó speciális csoportokat, majd futtasd a műveletet. A meglévő csoport-hozzárendelések megmaradnak.</p>
                <?php if ($specials === []): ?>
                    <p class="help">Ehhez legalább egy speciális csoport szükséges (lent létrehozható).</p>
                <?php else: ?>
                    <div class="events-tags-special-checkboxes events-tags-bulk-checkboxes">
                        <?php foreach ($specials as $sp): ?>
                            <?php $sid = (int) $sp['id']; ?>
                            <label class="events-tags-special-check-label">
                                <input type="checkbox" name="bulk_special_tag_ids[]" value="<?= $sid ?>">
                                <span class="events-tags-special-check-text"><?= h((string) $sp['name']) ?> <span class="help">(#<?= $sid ?>)</span></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="toolbar">
                        <button type="submit" class="btn btn-primary">Hozzáadás a kijelöltekhez</button>
                    </div>
                <?php endif; ?>
            </fieldset>
        </form>
    </div>

    <h3 class="events-tags-section-title">Speciális csoportok szerkesztése</h3>
    <div class="table-wrap events-admin-table-wrap events-inline-expand-wrap">
        <table
            class="events-admin-table events-inline-expand-table"
            id="events-specialtags-inline-table"
            data-sticky-group="new"
            data-initial-open="<?= h($openSpecialGroup) ?>"
        >
            <thead>
                <tr>
                    <th scope="col">
                        <span class="events-inline-th-label">ID</span>
                        <button type="button" class="events-inline-sort-btn" data-sort-col="0" data-sort-type="int" aria-label="Rendezés ID szerint">↕</button>
                    </th>
                    <th scope="col">
                        <span class="events-inline-th-label">Név</span>
                        <button type="button" class="events-inline-sort-btn" data-sort-col="1" data-sort-type="text" aria-label="Rendezés név szerint">↕</button>
                    </th>
                </tr>
                <tr class="events-inline-filter-row">
                    <th><input type="search" class="events-inline-filter-input" data-filter-col="0" placeholder="Szűrés…" aria-label="Szűrés ID"></th>
                    <th><input type="search" class="events-inline-filter-input" data-filter-col="1" placeholder="Szűrés…" aria-label="Szűrés név"></th>
                </tr>
            </thead>
            <tbody>
                <tr
                    class="events-inline-summary<?= $openSpecialGroup === 'new' ? ' is-active' : '' ?>"
                    data-expand-group="new"
                    tabindex="0"
                    role="button"
                    aria-expanded="<?= $openSpecialGroup === 'new' ? 'true' : 'false' ?>"
                >
                    <td class="events-inline-summary-muted">—</td>
                    <td><strong>Új speciális csoport</strong> <span class="events-inline-summary-hint">(kattints a szerkesztéshez)</span></td>
                </tr>
                <tr class="events-inline-detail" data-expand-group="new" <?= $openSpecialGroup === 'new' ? '' : 'hidden' ?>>
                    <td colspan="2">
                        <div class="events-tags-admin__form-panel events-tags-admin__form-panel--inline">
                            <form method="post" action="<?= h(events_url('tags.php')) ?>">
                                <?= csrf_input('events_tags') ?>
                                <input type="hidden" name="action" value="save_specialtag">
                                <input type="hidden" name="id" value="0">
                                <div class="form-group">
                                    <label for="spec_name_new">Név *</label>
                                    <input type="text" id="spec_name_new" name="name" required maxlength="255" value="" placeholder="pl. DJ-k, stílusok">
                                </div>
                                <div class="toolbar">
                                    <button type="submit" class="btn btn-primary">Mentés</button>
                                </div>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php foreach ($specials as $sp): ?>
                    <?php
                    $sid = (int) $sp['id'];
                    $isOpenSp = $openSpecialGroup !== '' && $openSpecialGroup === (string) $sid;
                    ?>
                    <tr
                        class="events-inline-summary<?= $isOpenSp ? ' is-active' : '' ?>"
                        data-expand-group="<?= $sid ?>"
                        tabindex="0"
                        role="button"
                        aria-expanded="<?= $isOpenSp ? 'true' : 'false' ?>"
                    >
                        <td><?= $sid ?></td>
                        <td><?= h((string) $sp['name']) ?></td>
                    </tr>
                    <tr class="events-inline-detail" data-expand-group="<?= $sid ?>" <?= $isOpenSp ? '' : 'hidden' ?>>
                        <td colspan="2">
                            <div class="events-tags-admin__form-panel events-tags-admin__form-panel--inline">
                                <form method="post" action="<?= h(events_url('tags.php')) ?>">
                                    <?= csrf_input('events_tags') ?>
                                    <input type="hidden" name="action" value="save_specialtag">
                                    <input type="hidden" name="id" value="<?= $sid ?>">
                                    <div class="form-group">
                                        <label for="spec_name_<?= $sid ?>">Név *</label>
                                        <input type="text" id="spec_name_<?= $sid ?>" name="name" required maxlength="255" value="<?= h((string) $sp['name']) ?>" placeholder="pl. DJ-k, stílusok">
                                    </div>
                                    <div class="toolbar">
                                        <button type="submit" class="btn btn-primary">Mentés</button>
                                    </div>
                                </form>
                                <form method="post" action="<?= h(events_url('tags.php')) ?>" class="events-tags-delete-form" onsubmit="return confirm('Biztosan törlöd ezt a speciális csoportot? A címkék kapcsolatai ehhez a csoporthoz törlődnek.');">
                                    <?= csrf_input('events_tags') ?>
                                    <input type="hidden" name="action" value="delete_specialtag">
                                    <input type="hidden" name="id" value="<?= $sid ?>">
                                    <button type="submit" class="btn btn-secondary">Csoport törlése</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    /** data-expand-group értékek: csak `new` vagy pozitív egész (biztonságos querySelector). */
    function validExpandGroup(g) {
        return g === 'new' || /^\d+$/.test(String(g));
    }

    function queryByGroup(tbody, sel, g) {
        if (!validExpandGroup(g)) return null;
        return tbody.querySelector(sel + '[data-expand-group="' + g + '"]');
    }

    function getCellText(tr, colIndex) {
        var td = tr.cells[colIndex];
        return td ? td.textContent.trim() : '';
    }

    function parseSortValue(type, text) {
        if (type === 'int') {
            var n = parseInt(text.replace(/\D/g, ''), 10);
            return isNaN(n) ? 0 : n;
        }
        return text.toLowerCase();
    }

    function collectPairs(tbody, stickyGroup) {
        var summaries = tbody.querySelectorAll('.events-inline-summary');
        var pairs = [];
        summaries.forEach(function (sum) {
            var g = sum.getAttribute('data-expand-group');
            if (!g || !validExpandGroup(g)) return;
            var det = queryByGroup(tbody, '.events-inline-detail', g);
            if (!det) return;
            pairs.push({ group: g, summary: sum, detail: det, sticky: g === stickyGroup });
        });
        return pairs;
    }

    function sortTable(table, colIndex, sortType) {
        var tbody = table.querySelector('tbody');
        if (!tbody) return;
        var stickyGroup = table.getAttribute('data-sticky-group') || 'new';
        var key = 'sortcol-' + colIndex;
        var curDir = table.getAttribute('data-' + key) === 'asc' ? 'asc' : 'desc';
        var nextDir = curDir === 'asc' ? 'desc' : 'asc';
        table.setAttribute('data-' + key, nextDir);
        var dir = nextDir;

        var pairs = collectPairs(tbody, stickyGroup);
        var sticky = pairs.filter(function (p) { return p.sticky; });
        var movable = pairs.filter(function (p) { return !p.sticky; });

        movable.sort(function (a, b) {
            var va = parseSortValue(sortType, getCellText(a.summary, colIndex));
            var vb = parseSortValue(sortType, getCellText(b.summary, colIndex));
            var c = 0;
            if (sortType === 'int') {
                c = va - vb;
            } else {
                c = String(va).localeCompare(String(vb), 'hu', { sensitivity: 'base' });
            }
            return dir === 'asc' ? c : -c;
        });

        var ordered = sticky.concat(movable);
        ordered.forEach(function (p) {
            tbody.appendChild(p.summary);
            tbody.appendChild(p.detail);
        });
    }

    function applyFilters(table) {
        var tbody = table.querySelector('tbody');
        if (!tbody) return;
        var inputs = table.querySelectorAll('.events-inline-filter-input');
        var filters = [];
        inputs.forEach(function (inp) {
            var c = parseInt(inp.getAttribute('data-filter-col'), 10);
            filters[c] = (inp.value || '').trim().toLowerCase();
        });

        var summaries = tbody.querySelectorAll('.events-inline-summary');
        summaries.forEach(function (sum) {
            var g = sum.getAttribute('data-expand-group');
            if (!validExpandGroup(g)) return;
            var det = queryByGroup(tbody, '.events-inline-detail', g);
            var show = true;
            for (var col = 0; col < filters.length; col++) {
                if (!filters[col]) continue;
                var txt = getCellText(sum, col).toLowerCase();
                if (txt.indexOf(filters[col]) === -1) {
                    show = false;
                    break;
                }
            }
            sum.style.display = show ? '' : 'none';
            if (det) det.style.display = show ? '' : 'none';
        });
    }

    function closeAllDetails(tbody, exceptGroup) {
        tbody.querySelectorAll('.events-inline-detail').forEach(function (det) {
            var g = det.getAttribute('data-expand-group');
            if (exceptGroup !== null && g === exceptGroup) return;
            det.hidden = true;
        });
        tbody.querySelectorAll('.events-inline-summary').forEach(function (sum) {
            var g = sum.getAttribute('data-expand-group');
            if (exceptGroup !== null && g === exceptGroup) return;
            sum.classList.remove('is-active');
            sum.setAttribute('aria-expanded', 'false');
        });
    }

    function setOpen(tbody, group, open) {
        if (!validExpandGroup(group)) return;
        var sum = queryByGroup(tbody, '.events-inline-summary', group);
        var det = queryByGroup(tbody, '.events-inline-detail', group);
        if (!sum || !det) return;
        if (open) {
            closeAllDetails(tbody, group);
            det.hidden = false;
            sum.classList.add('is-active');
            sum.setAttribute('aria-expanded', 'true');
        } else {
            det.hidden = true;
            sum.classList.remove('is-active');
            sum.setAttribute('aria-expanded', 'false');
        }
    }

    function toggleGroup(table, group) {
        var tbody = table.querySelector('tbody');
        if (!tbody) return;
        if (!validExpandGroup(group)) return;
        var det = queryByGroup(tbody, '.events-inline-detail', group);
        if (!det) return;
        var willOpen = det.hidden;
        if (willOpen) {
            setOpen(tbody, group, true);
        } else {
            setOpen(tbody, group, false);
        }
    }

    function bindExpandTable(table) {
        var tbody = table.querySelector('tbody');
        if (!tbody) return;

        var initial = table.getAttribute('data-initial-open') || '';
        if (initial) {
            setOpen(tbody, initial, true);
        }

        tbody.addEventListener('click', function (e) {
            var sum = e.target.closest('.events-inline-summary');
            if (!sum || !tbody.contains(sum)) return;
            if (e.target.closest('a, button, input, textarea, select, label')) return;
            var g = sum.getAttribute('data-expand-group');
            if (!g) return;
            var det = queryByGroup(tbody, '.events-inline-detail', g);
            if (!det) return;
            toggleGroup(table, g);
        });

        tbody.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            if (e.target.closest('input[type="checkbox"], input[type="search"], button')) return;
            var sum = e.target.closest('.events-inline-summary');
            if (!sum || !tbody.contains(sum)) return;
            e.preventDefault();
            var g = sum.getAttribute('data-expand-group');
            if (g) toggleGroup(table, g);
        });

        table.querySelectorAll('.events-inline-sort-btn').forEach(function (btn) {
            btn.addEventListener('click', function (ev) {
                ev.stopPropagation();
                var col = parseInt(btn.getAttribute('data-sort-col'), 10);
                var typ = btn.getAttribute('data-sort-type') || 'text';
                sortTable(table, col, typ);
            });
        });

        table.querySelectorAll('.events-inline-filter-input').forEach(function (inp) {
            inp.addEventListener('input', function () {
                applyFilters(table);
            });
        });
    }

    ['events-tags-inline-table', 'events-specialtags-inline-table'].forEach(function (id) {
        var t = document.getElementById(id);
        if (t) bindExpandTable(t);
    });

    var bulkMaster = document.getElementById('tags-bulk-select-all');
    if (bulkMaster) {
        bulkMaster.addEventListener('change', function () {
            document.querySelectorAll('input[form="tags-bulk-special-form"][name="tag_ids[]"]').forEach(function (cb) {
                cb.checked = bulkMaster.checked;
            });
        });
    }
})();
</script>

<?php require_once dirname(__DIR__) . '/nextgen/partials/footer.php'; ?>
