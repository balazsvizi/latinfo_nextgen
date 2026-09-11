<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/tag_type.php';
require_once __DIR__ . '/lib/tag_profile.php';
require_once __DIR__ . '/lib/djpics.php';
require_once __DIR__ . '/lib/event_public_lang.php';
requireLogin();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Hiányzó azonosító.');
    redirect(events_url('djs_admin.php'));
}

$db = getDb();
events_tags_ensure_slug_column($db);
events_tags_ensure_profile_columns($db);

if (!events_tags_tables_available($db) || !events_tag_types_tables_available($db)) {
    flash('error', 'Hiányoznak a címke táblák.');
    redirect(events_url('djs_admin.php'));
}

$slugSelect = events_tags_slug_column_available($db) ? ', `slug`' : '';
$st = $db->prepare('SELECT `id`, `name`' . $slugSelect . ' FROM `events_tags` WHERE `id` = ? LIMIT 1');
$st->execute([$id]);
$tag = $st->fetch(PDO::FETCH_ASSOC);
if (!$tag) {
    flash('error', 'DJ / címke nem található.');
    redirect(events_url('djs_admin.php'));
}

if (!in_array('dj', events_load_tag_type_codes($db, $id), true)) {
    events_save_tag_types($db, $id, array_values(array_unique(array_merge(events_load_tag_type_codes($db, $id), ['dj']))));
}

$name = (string) ($tag['name'] ?? '');
$slug = trim((string) ($tag['slug'] ?? ''));
$profile = events_tag_profile_load($db, $id);
$hiba = '';
$djPhotoPick = '';
$djLogoPick = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('dj_szerkeszt')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet.';
    } elseif ((string) ($_POST['form_action'] ?? 'save') === 'delete') {
        $stUse = $db->prepare('SELECT COUNT(*) FROM `events_calendar_event_tags` WHERE `tag_id` = ?');
        $stUse->execute([$id]);
        $useCnt = (int) $stUse->fetchColumn();
        if ($useCnt > 0) {
            $hiba = 'A DJ nem törölhető, mert ' . $useCnt . ' eseményhez van rendelve.';
        } else {
            try {
                $db->beginTransaction();
                if (events_tag_types_tables_available($db)) {
                    $db->prepare('DELETE FROM `events_tag_type_links` WHERE `tag_id` = ?')->execute([$id]);
                }
                $db->prepare('DELETE FROM `events_tags` WHERE `id` = ?')->execute([$id]);
                $db->commit();
                rendszer_log('tag', $id, 'DJ törölve', $name);
                flash('success', 'DJ törölve.');
                redirect(events_url('djs_admin.php'));
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log('dj_szerkeszt torles: ' . $e->getMessage());
                $hiba = 'A törlés nem sikerült. Kérlek próbáld újra.';
            }
        }
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $slug = trim((string) ($_POST['slug'] ?? ''));
        [$profileIn, $profileErr] = events_tag_profile_from_post_without_photo();
        if ($name === '') {
            $hiba = 'A név megadása kötelező.';
        } elseif ($profileErr !== null) {
            $hiba = $profileErr;
        } else {
            $currentPhoto = (string) ($profile['photo_url'] ?? '');
            $currentLogo = (string) ($profile['logo_url'] ?? '');
            [$photoUrl, $photoErr] = events_djpics_resolve_photo_for_save($currentPhoto, !empty($_POST['dj_photo_clear']));
            [$logoUrl, $logoErr] = $photoErr === null
                ? events_djpics_resolve_logo_for_save($currentLogo, !empty($_POST['dj_logo_clear']))
                : [null, $photoErr];
            if ($photoErr !== null) {
                $hiba = $photoErr;
            } elseif ($logoErr !== null) {
                $hiba = $logoErr;
            } else {
                $profileIn['photo_url'] = $photoUrl ?? '';
                $profileIn['logo_url'] = $logoUrl ?? '';
                $dup = $db->prepare('SELECT `id` FROM `events_tags` WHERE `name` = ? AND `id` <> ? LIMIT 1');
                $dup->execute([$name, $id]);
                if ($dup->fetchColumn() !== false) {
                    $hiba = 'Már létezik ilyen nevű címke / DJ.';
                } else {
                    try {
                        $ensure = events_tags_ensure_profile_columns($db);
                        $blockErr = events_tag_profile_ensure_blocking_error($ensure, $profileIn);
                        if ($blockErr !== null) {
                            $hiba = $blockErr;
                        } else {
                            $db->beginTransaction();
                            $upd = $db->prepare('UPDATE `events_tags` SET `name` = ? WHERE `id` = ?');
                            $upd->execute([$name, $id]);
                            $typeCodes = events_load_tag_type_codes($db, $id);
                            if (!in_array('dj', $typeCodes, true)) {
                                $typeCodes[] = 'dj';
                            }
                            events_save_tag_types($db, $id, $typeCodes);
                            $slug = events_tag_set_dj_slug($db, $id, $name, $slug) ?? $slug;
                            $saveErr = events_tag_profile_save($db, $id, $profileIn);
                            if ($saveErr !== null) {
                                throw new RuntimeException($saveErr);
                            }
                            if ($db->inTransaction()) {
                                $db->commit();
                            }
                            rendszer_log('tag', $id, 'DJ módosítva', $name);
                            flash('success', 'Mentve.');
                            redirect(events_url('dj_szerkeszt.php?id=') . $id);
                        }
                    } catch (Throwable $e) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        error_log('dj_szerkeszt: ' . $e->getMessage());
                        $hiba = $e instanceof RuntimeException
                            ? $e->getMessage()
                            : 'Mentési hiba történt. Kérlek próbáld újra.';
                    }
                }
            }
        }
        $profile = $profileIn;
    }
}

if ($hiba === '') {
    $stSlug = $db->prepare('SELECT `slug` FROM `events_tags` WHERE `id` = ? LIMIT 1');
    $stSlug->execute([$id]);
    $slug = trim((string) ($stSlug->fetchColumn() ?: $slug));
    $profile = events_tag_profile_load($db, $id);
} else {
    $djPhotoPick = trim((string) ($_POST['dj_photo_pick'] ?? ''));
    $djLogoPick = trim((string) ($_POST['dj_logo_pick'] ?? ''));
}

$publicUrl = $slug !== ''
    ? events_public_dj_page_url($slug, 'hu')
    : events_public_tag_page_url($id, 'hu');

$djEventCount = 0;
if (events_tags_tables_available($db)) {
    $stUse = $db->prepare('SELECT COUNT(*) FROM `events_calendar_event_tags` WHERE `tag_id` = ?');
    $stUse->execute([$id]);
    $djEventCount = (int) $stUse->fetchColumn();
}
$djEventsListUrl = events_url('events_admin.php') . '?' . http_build_query(['f_dj' => (string) $id], '', '&', PHP_QUERY_RFC3986);

$adminFloatTools = [
    [
        'submit_form' => 'dj-edit-form',
        'title' => 'Mentés',
        'aria' => 'Mentés',
        'icon' => 'save',
    ],
    [
        'href' => $publicUrl,
        'title' => 'Megnyitás megtekintésre',
        'aria' => 'Nyilvános DJ oldal megnyitása megtekintésre',
        'icon' => 'eye',
        'target' => '_blank',
    ],
    [
        'href' => events_url('djs_admin.php'),
        'title' => 'Vissza a DJ-k listájához',
        'aria' => 'Vissza a DJ-k listájához',
        'icon' => 'back',
    ],
];
$adminFloatToolsRequireLogin = false;

$pageTitle = 'DJ szerkesztése: ' . $name;
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>
<?php require __DIR__ . '/partials/admin_float_tools.php'; ?>
<div class="card events-admin-card">
    <div class="events-list-head">
        <h1 class="card-title" style="margin:0;">DJ szerkesztése</h1>
        <div class="events-list-actions">
            <a href="<?= h($publicUrl) ?>" class="btn btn-secondary" target="_blank" rel="noopener">Nyilvános oldal</a>
            <a href="<?= h(events_url('djs_admin.php')) ?>" class="btn btn-secondary">← DJ-k listája</a>
        </div>
    </div>
    <?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="venue-form events-dj-edit-form" id="dj-edit-form">
        <?= csrf_input('dj_szerkeszt') ?>
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <div class="events-edit-layout">
            <div class="events-edit-main">
                <div class="events-edit-panel">
                    <h3 class="events-edit-panel__title">Alapadatok</h3>
                    <div class="events-edit-title-row venue-edit-title-row">
                        <div class="form-group venue-edit-name-field">
                            <label for="dj_name">Név *</label>
                            <input type="text" id="dj_name" name="name" value="<?= h($name) ?>" required maxlength="255" autofocus>
                        </div>
                        <button type="button" class="btn btn-secondary events-edit-slug-refresh" id="dj-slug-refresh" title="Slug frissítése a névből" aria-label="Slug frissítése a névből">🔄</button>
                        <div class="form-group venue-edit-slug-field">
                            <label for="dj_slug">Slug (URL)</label>
                            <input type="text" id="dj_slug" name="slug" value="<?= h($slug) ?>" maxlength="255" pattern="[a-z0-9_]*" title="Kisbetű, szám és aláhúzás" placeholder="dj_pelda">
                        </div>
                    </div>
                    <p class="help">Nyilvános URL: <code>/DJ/<?= h($slug !== '' ? $slug : '…') ?>/</code></p>
                </div>
                <div class="events-edit-panel">
                    <h3 class="events-edit-panel__title">Profil</h3>
                    <?php
                    $tagProfile = $profile;
                    $tagFormId = $id;
                    $tagProfileStandalone = true;
                    require __DIR__ . '/partials/tag_dj_profile_fields.php';
                    ?>
                </div>
            </div>
            <aside class="events-edit-sidebar">
                <div class="toolbar events-dj-edit-toolbar--top">
                    <button type="submit" class="btn btn-primary events-dj-edit-save-wide" name="form_action" value="save">Mentés</button>
                </div>
                <div class="events-edit-panel">
                    <?php
                    $djPhotoUrl = (string) ($profile['photo_url'] ?? '');
                    $djPhotoPick = $djPhotoPick ?? '';
                    require __DIR__ . '/partials/dj_photo_fields.php';
                    ?>
                </div>
                <div class="events-edit-panel">
                    <?php
                    $djLogoUrl = (string) ($profile['logo_url'] ?? '');
                    $djLogoPick = $djLogoPick ?? '';
                    require __DIR__ . '/partials/dj_logo_fields.php';
                    ?>
                </div>
                <div class="toolbar events-dj-edit-toolbar--bottom">
                    <button type="submit" class="btn btn-primary events-dj-edit-save-wide" name="form_action" value="save">Mentés</button>
                    <?php if ($djEventCount === 0): ?>
                        <button
                            type="submit"
                            class="btn btn-danger events-dj-edit-save-wide"
                            name="form_action"
                            value="delete"
                            formnovalidate
                            onclick="return confirm('Biztosan törlöd ezt a DJ-t? A művelet nem vonható vissza.');"
                        >Törlés</button>
                    <?php else: ?>
                        <p class="help events-dj-edit-delete-hint">
                            Nem törölhető:
                            <a href="<?= h($djEventsListUrl) ?>"><?= (int) $djEventCount ?> esemény</a>
                            használja.
                        </p>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </form>
</div>
<script>
(function () {
    var nameEl = document.getElementById('dj_name');
    var slugEl = document.getElementById('dj_slug');
    var refreshBtn = document.getElementById('dj-slug-refresh');
    if (!nameEl || !slugEl) return;
    var ajaxPath = <?= json_encode(events_url('ajax_dj_unique_slug.php'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var excludeId = <?= (int) $id ?>;

    function fetchSlugFromName(force) {
        if (!force && slugEl.value.trim() !== '') return;
        var nm = nameEl.value.trim();
        if (nm === '') return;
        var u = new URL(ajaxPath, window.location.href);
        u.searchParams.set('name', nm);
        if (excludeId > 0) u.searchParams.set('exclude_id', String(excludeId));
        fetch(u.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!force && slugEl.value.trim() !== '') return;
                if (data && data.ok && typeof data.slug === 'string') slugEl.value = data.slug;
            })
            .catch(function () {});
    }

    nameEl.addEventListener('blur', function () { fetchSlugFromName(false); });
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () { fetchSlugFromName(true); });
    }
})();
</script>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
