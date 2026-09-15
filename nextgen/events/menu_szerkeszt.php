<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/public_nav_items.php';
require_once __DIR__ . '/lib/event_public_lang.php';
requireLogin();

$db = getDb();
$selfUrl = events_url('menu_szerkeszt.php');

if (!events_public_nav_items_ensure_schema($db)) {
    $mainContentClass = 'main-content main-content--fullwidth';
    $pageTitle = 'Főmenü';
    require_once dirname(__DIR__) . '/partials/header.php';
    echo '<div class="card events-admin-card">';
    echo '<p class="alert alert-error">A nyilvános menü táblája nem jött létre. Próbáld újra, vagy ellenőrizd az adatbázis jogosultságokat.</p>';
    echo '</div>';
    require_once dirname(__DIR__) . '/partials/footer.php';
    exit;
}

$itemUrl = static function (int $id) use ($selfUrl): string {
    return $id > 0 ? $selfUrl . '?open=' . $id : $selfUrl;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('events_public_nav')) {
        flash('error', 'Lejárt vagy érvénytelen munkamenet. Töltsd újra az oldalt.');
        redirect($selfUrl);
    }

    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'create') {
        try {
            $newId = events_public_nav_items_create($db);
            rendszer_log('foomenu', $newId, 'Létrehozva', 'Új menüpont');
            flash('success', 'Új menüpont létrehozva. Töltsd ki, majd mentsd.');
            redirect($itemUrl($newId));
        } catch (Throwable $e) {
            error_log('public nav create: ' . $e->getMessage());
            flash('error', 'A menüpont létrehozása nem sikerült.');
            redirect($selfUrl);
        }
    }

    if ($action === 'save') {
        $item = events_public_nav_items_find($db, $id);
        if ($item === null) {
            flash('error', 'A menüpont nem található.');
            redirect($selfUrl);
        }

        try {
            events_public_nav_items_save($db, $id, [
                'label_hu' => (string) ($_POST['label_hu'] ?? ''),
                'label_en' => (string) ($_POST['label_en'] ?? ''),
                'link_type' => (string) ($_POST['link_type'] ?? 'custom'),
                'preset_key' => (string) ($_POST['preset_key'] ?? ''),
                'href' => (string) ($_POST['href'] ?? ''),
                'parent_id' => ($_POST['parent_id'] ?? '') === '' ? null : (int) $_POST['parent_id'],
                'is_visible' => !empty($_POST['is_visible']),
                'open_in_new_tab' => !empty($_POST['open_in_new_tab']),
                'is_external' => !empty($_POST['is_external']),
            ]);
            $saved = events_public_nav_items_find($db, $id);
            rendszer_log('foomenu', $id, 'Mentve', trim((string) ($saved['label_hu'] ?? '')));
            flash('success', 'A menüpont mentve.');
            redirect($itemUrl($id));
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect($itemUrl($id));
        } catch (Throwable $e) {
            error_log('public nav save: ' . $e->getMessage());
            flash('error', 'A mentés nem sikerült.');
            redirect($itemUrl($id));
        }
    }

    if ($action === 'delete') {
        $item = events_public_nav_items_find($db, $id);
        if ($item === null) {
            flash('error', 'A menüpont nem található.');
            redirect($selfUrl);
        }
        try {
            events_public_nav_items_delete($db, $id);
            rendszer_log('foomenu', $id, 'Törölve', trim((string) ($item['label_hu'] ?? '')));
            flash('success', 'A menüpont törölve.');
            redirect($selfUrl);
        } catch (InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirect($itemUrl($id));
        } catch (Throwable $e) {
            error_log('public nav delete: ' . $e->getMessage());
            flash('error', 'A törlés nem sikerült.');
            redirect($itemUrl($id));
        }
    }

    if ($action === 'move_up' || $action === 'move_down') {
        if (!events_public_nav_items_move($db, $id, $action === 'move_up' ? -1 : 1)) {
            flash('error', 'A menüpont nem mozgatható tovább ebbe az irányba.');
        }
        redirect($itemUrl($id));
    }

    if ($action === 'restore_defaults') {
        try {
            events_public_nav_items_restore_defaults($db);
            rendszer_log('foomenu', null, 'Alapértelmezett menü visszaállítva', '');
            flash('success', 'Az eredeti menü visszaállítva.');
        } catch (Throwable $e) {
            error_log('public nav restore: ' . $e->getMessage());
            flash('error', 'A visszaállítás nem sikerült.');
        }
        redirect($selfUrl);
    }

    redirect($selfUrl);
}

$rows = events_public_nav_items_all($db);
$flat = events_public_nav_items_flatten_tree($rows);
$itemCount = count($flat);
$visibleCount = 0;
foreach ($flat as $row) {
    if ((int) ($row['is_visible'] ?? 0) === 1) {
        $visibleCount++;
    }
}

$siblingIndex = [];
$siblingCount = [];
foreach ($flat as $row) {
    $pid = isset($row['parent_id']) && $row['parent_id'] !== null ? (int) $row['parent_id'] : 0;
    $siblingCount[$pid] = ($siblingCount[$pid] ?? 0) + 1;
    $siblingIndex[(int) $row['id']] = ($siblingCount[$pid] ?? 1) - 1;
}

$openRaw = (string) ($_GET['open'] ?? '');
$openId = ctype_digit($openRaw) ? (int) $openRaw : 0;
$publicUrl = events_public_home_page_url('hu');
$presetLabels = events_public_nav_preset_labels();
$byParent = events_public_nav_items_group_by_parent($rows);

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Főmenü szerkesztése';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="card events-admin-card nav-items-admin">
    <div class="events-list-head">
        <div class="events-list-head__start">
            <h2 class="events-list-title">Nyilvános főmenü</h2>
            <span class="help"><?= $itemCount ?> menüpont · <?= $visibleCount ?> látható</span>
        </div>
        <div class="events-list-actions">
            <a href="<?= h($publicUrl) ?>" class="btn btn-secondary btn-sm" target="_blank" rel="noopener">Előnézet</a>
            <a href="<?= h(events_url('fooldal_szerkeszt.php')) ?>" class="btn btn-secondary btn-sm">Főoldal szövegei</a>
        </div>
    </div>

    <p class="help">
        Ez a naptár, a DJ-k, a szervezők és a partnereink oldal tetején megjelenő menü.
        A <strong>belső oldal</strong> a meglévő nyilvános oldalakra visz (a nyelvváltás automatikus).
        Az <strong>egyéni URL</strong> bármely http(s) vagy honlapon belüli cím lehet; a <code>#</code> csak almenü-szülőhöz kell.
        Az angol felirat üresen hagyható: ilyenkor angolul is a magyar szöveg jelenik meg.
    </p>

    <form method="post" class="nav-items-admin__create">
        <?= csrf_input('events_public_nav') ?>
        <input type="hidden" name="action" value="create">
        <button type="submit" class="btn btn-primary btn-sm">+ Új menüpont</button>
    </form>

    <?php if ($flat === []): ?>
        <p class="alert alert-warning">A menü üres. Adj hozzá egy menüpontot, vagy állítsd vissza az alapértelmezettet.</p>
    <?php endif; ?>

    <div class="nav-items-admin__list" id="nav-items-list">
        <?php foreach ($flat as $row): ?>
            <?php
            $nid = (int) $row['id'];
            $depth = (int) ($row['_depth'] ?? 0);
            $isOpen = $openId === $nid;
            $isVisible = (int) ($row['is_visible'] ?? 0) === 1;
            $labelHu = trim((string) ($row['label_hu'] ?? ''));
            $labelEn = trim((string) ($row['label_en'] ?? ''));
            $linkType = events_public_nav_normalize_link_type((string) ($row['link_type'] ?? 'custom'));
            $presetKey = events_public_nav_normalize_preset((string) ($row['preset_key'] ?? ''));
            $href = trim((string) ($row['href'] ?? ''));
            $resolved = events_public_nav_items_resolve_href($row, 'hu');
            $parentId = isset($row['parent_id']) && $row['parent_id'] !== null ? (int) $row['parent_id'] : 0;
            $sibPos = $siblingIndex[$nid] ?? 0;
            $sibTotal = $siblingCount[$parentId] ?? 1;
            $summary = $labelHu !== '' ? $labelHu : '(üres)';
            $blockedParents = array_fill_keys(events_public_nav_items_descendant_ids($byParent, $nid), true);
            $typeLabel = $linkType === 'preset'
                ? ($presetLabels[$presetKey] ?? 'Belső oldal')
                : ($href === '#' ? 'Csak almenü' : 'Egyéni URL');
            ?>
            <article class="nav-items-admin__item<?= $isVisible ? '' : ' is-hidden-block' ?>" id="nav-item-row-<?= $nid ?>" data-depth="<?= $depth ?>">
                <header class="nav-items-admin__bar">
                    <span class="nav-items-admin__badge nav-items-admin__badge--<?= h($linkType) ?>"><?= h($typeLabel) ?></span>
                    <span class="nav-items-admin__summary"><?= h($summary) ?></span>
                    <?php if ($labelEn !== ''): ?>
                        <span class="nav-items-admin__en"><?= h($labelEn) ?></span>
                    <?php endif; ?>
                    <?php if (!$isVisible): ?>
                        <span class="nav-items-admin__flag">Rejtett</span>
                    <?php endif; ?>
                    <div class="nav-items-admin__bar-actions">
                        <form method="post" class="nav-items-admin__mini-form">
                            <?= csrf_input('events_public_nav') ?>
                            <input type="hidden" name="action" value="move_up">
                            <input type="hidden" name="id" value="<?= $nid ?>">
                            <button type="submit" class="btn btn-secondary btn-sm nav-items-admin__move" title="Feljebb" aria-label="Feljebb" <?= $sibPos === 0 ? 'disabled' : '' ?>>↑</button>
                        </form>
                        <form method="post" class="nav-items-admin__mini-form">
                            <?= csrf_input('events_public_nav') ?>
                            <input type="hidden" name="action" value="move_down">
                            <input type="hidden" name="id" value="<?= $nid ?>">
                            <button type="submit" class="btn btn-secondary btn-sm nav-items-admin__move" title="Lejjebb" aria-label="Lejjebb" <?= $sibPos >= $sibTotal - 1 ? 'disabled' : '' ?>>↓</button>
                        </form>
                        <button type="button" class="btn btn-secondary btn-sm" data-nav-toggle="<?= $nid ?>" aria-expanded="<?= $isOpen ? 'true' : 'false' ?>" aria-controls="nav-item-<?= $nid ?>">Szerkesztés</button>
                    </div>
                </header>

                <div class="nav-items-admin__body" id="nav-item-<?= $nid ?>" <?= $isOpen ? '' : 'hidden' ?>>
                    <form method="post" class="events-admin-form" data-nav-form>
                        <?= csrf_input('events_public_nav') ?>
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" value="<?= $nid ?>">

                        <div class="form-row">
                            <div class="form-group">
                                <label for="label_hu_<?= $nid ?>">Felirat (HU) *</label>
                                <input type="text" id="label_hu_<?= $nid ?>" name="label_hu" maxlength="120" required value="<?= h($labelHu) ?>">
                            </div>
                            <div class="form-group">
                                <label for="label_en_<?= $nid ?>">Felirat (EN)</label>
                                <input type="text" id="label_en_<?= $nid ?>" name="label_en" maxlength="120" value="<?= h($labelEn) ?>" placeholder="Üresen a magyar felirat jelenik meg">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="parent_id_<?= $nid ?>">Szülő menüpont</label>
                                <select id="parent_id_<?= $nid ?>" name="parent_id">
                                    <option value="">— Felső szint —</option>
                                    <?php foreach ($flat as $parentOpt): ?>
                                        <?php
                                        $pid = (int) $parentOpt['id'];
                                        if ($pid === $nid || isset($blockedParents[$pid])) {
                                            continue;
                                        }
                                        $pDepth = (int) ($parentOpt['_depth'] ?? 0);
                                        if ($pDepth >= EVENTS_PUBLIC_NAV_MAX_DEPTH) {
                                            continue;
                                        }
                                        $pLabel = trim((string) ($parentOpt['label_hu'] ?? ''));
                                        if ($pLabel === '') {
                                            $pLabel = '#' . $pid;
                                        }
                                        $prefix = str_repeat('— ', $pDepth);
                                        ?>
                                    <option value="<?= $pid ?>"<?= $parentId === $pid ? ' selected' : '' ?>><?= h($prefix . $pLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="link_type_<?= $nid ?>">Cél</label>
                                <select id="link_type_<?= $nid ?>" name="link_type" data-nav-link-type>
                                    <option value="preset"<?= $linkType === 'preset' ? ' selected' : '' ?>>Belső oldal</option>
                                    <option value="custom"<?= $linkType === 'custom' ? ' selected' : '' ?>>Egyéni URL</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group" data-nav-preset-wrap<?= $linkType === 'custom' ? ' hidden' : '' ?>>
                            <label for="preset_key_<?= $nid ?>">Belső oldal</label>
                            <select id="preset_key_<?= $nid ?>" name="preset_key">
                                <?php foreach ($presetLabels as $pKey => $pLabel): ?>
                                    <option value="<?= h($pKey) ?>"<?= $presetKey === $pKey ? ' selected' : '' ?>><?= h($pLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group" data-nav-href-wrap<?= $linkType === 'preset' ? ' hidden' : '' ?>>
                            <label for="href_<?= $nid ?>">URL</label>
                            <input type="text" id="href_<?= $nid ?>" name="href" maxlength="500" value="<?= h($href) ?>" placeholder="https://… vagy /esemenyek/ vagy #">
                            <p class="help">Relatív útvonal (pl. <code>/partnereink/</code>), teljes http(s) cím, vagy <code>#</code> ha csak almenü.</p>
                        </div>

                        <?php if ($resolved !== ''): ?>
                            <p class="help">Jelenlegi cél (HU): <code><?= h($resolved) ?></code></p>
                        <?php endif; ?>

                        <label class="nav-items-admin__check">
                            <input type="checkbox" name="is_visible" value="1"<?= $isVisible ? ' checked' : '' ?>>
                            Megjelenik a nyilvános menüben
                        </label>
                        <label class="nav-items-admin__check">
                            <input type="checkbox" name="open_in_new_tab" value="1"<?= (int) ($row['open_in_new_tab'] ?? 0) === 1 ? ' checked' : '' ?>>
                            Új lapon nyílik
                        </label>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Mentés</button>
                        </div>
                    </form>

                    <form method="post" class="nav-items-admin__delete" onsubmit="return confirm('Biztosan törlöd ezt a menüpontot?');">
                        <?= csrf_input('events_public_nav') ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $nid ?>">
                        <button type="submit" class="btn btn-secondary btn-sm">Menüpont törlése</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <form method="post" class="nav-items-admin__restore" onsubmit="return confirm('Visszaállítod az eredeti menüt? A mostani menüpontok elvesznek.');">
        <?= csrf_input('events_public_nav') ?>
        <input type="hidden" name="action" value="restore_defaults">
        <button type="submit" class="btn btn-secondary btn-sm">Alapértelmezett menü visszaállítása</button>
    </form>
</div>

<script>
(function () {
    var list = document.getElementById('nav-items-list');
    if (!list) return;

    list.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('[data-nav-toggle]') : null;
        if (!btn || !list.contains(btn)) return;
        var body = document.getElementById('nav-item-' + btn.getAttribute('data-nav-toggle'));
        if (!body) return;
        var open = body.hidden;
        body.hidden = !open;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    function syncLinkType(select) {
        var form = select.closest('[data-nav-form]');
        if (!form) return;
        var isPreset = select.value === 'preset';
        var presetWrap = form.querySelector('[data-nav-preset-wrap]');
        var hrefWrap = form.querySelector('[data-nav-href-wrap]');
        if (presetWrap) presetWrap.hidden = !isPreset;
        if (hrefWrap) hrefWrap.hidden = isPreset;
    }

    list.querySelectorAll('[data-nav-link-type]').forEach(function (select) {
        select.addEventListener('change', function () { syncLinkType(select); });
    });
})();
</script>

<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
