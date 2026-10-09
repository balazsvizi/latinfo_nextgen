<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/tag_type.php';
require_once __DIR__ . '/lib/tag_profile.php';
require_once __DIR__ . '/lib/djpics.php';
require_once __DIR__ . '/lib/dance_teachers.php';
require_once __DIR__ . '/lib/style_request.php';
requireLogin();

$db = getDb();
events_tags_ensure_slug_column($db);
events_tags_ensure_profile_columns($db);
dance_schools_ensure_schema($db);

if (!events_tags_tables_available($db) || !events_tag_types_tables_available($db)) {
    flash('error', 'Hiányoznak a címke táblák.');
    redirect(events_url('tanarok_admin.php'));
}

$hiba = '';
$name = '';
$slug = '';
$profile = events_tag_profile_empty();
$teacherProfile = dance_teacher_profile_empty();
$djPhotoPick = '';
$djLogoPick = '';
$styleOptions = events_load_style_options($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('tanar_letrehoz')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet.';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $slug = trim((string) ($_POST['slug'] ?? ''));
        [$profile, $profileErr] = events_tag_profile_from_post_without_photo();
        [$teacherProfile, $tpErr] = dance_teacher_profile_from_post($_POST);
        if ($name === '') {
            $hiba = 'A név megadása kötelező.';
        } elseif ($profileErr !== null) {
            $hiba = $profileErr;
        } elseif ($tpErr !== null) {
            $hiba = $tpErr;
        } else {
            [$photoUrl, $photoErr] = events_djpics_resolve_photo_for_save('', !empty($_POST['dj_photo_clear']));
            [$logoUrl, $logoErr] = $photoErr === null
                ? events_djpics_resolve_logo_for_save('', !empty($_POST['dj_logo_clear']))
                : [null, $photoErr];
            if ($photoErr !== null) {
                $hiba = $photoErr;
            } elseif ($logoErr !== null) {
                $hiba = $logoErr;
            } else {
                $profile['photo_url'] = $photoUrl ?? '';
                $profile['logo_url'] = $logoUrl ?? '';
                $dup = $db->prepare('SELECT `id` FROM `events_tags` WHERE `name` = ? LIMIT 1');
                $dup->execute([$name]);
                if ($dup->fetchColumn() !== false) {
                    $hiba = 'Már létezik ilyen nevű címke / tanár.';
                } else {
                    try {
                        $ensure = events_tags_ensure_profile_columns($db);
                        $blockErr = events_tag_profile_ensure_blocking_error($ensure, $profile);
                        if ($blockErr !== null) {
                            $hiba = $blockErr;
                        } else {
                            $db->beginTransaction();
                            $ins = $db->prepare('INSERT INTO `events_tags` (`name`) VALUES (?)');
                            $ins->execute([$name]);
                            $newId = (int) $db->lastInsertId();
                            events_save_tag_types($db, $newId, ['tanar']);
                            $slug = dance_teacher_set_slug($db, $newId, $name, $slug) ?? $slug;
                            $saveErr = events_tag_profile_save($db, $newId, $profile);
                            if ($saveErr !== null) {
                                throw new RuntimeException($saveErr);
                            }
                            $tpSaveErr = dance_teacher_profile_save($db, $newId, $teacherProfile);
                            if ($tpSaveErr !== null) {
                                throw new RuntimeException($tpSaveErr);
                            }
                            if ($db->inTransaction()) {
                                $db->commit();
                            }
                            if (function_exists('rendszer_log')) {
                                rendszer_log('tag', $newId, 'Tánctanár létrehozva', $name);
                            }
                            flash('success', 'Tánctanár létrehozva.');
                            redirect(events_url('tanar_szerkeszt.php?id=') . $newId);
                        }
                    } catch (Throwable $e) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        error_log('tanar_letrehoz: ' . $e->getMessage());
                        $hiba = $e instanceof RuntimeException
                            ? $e->getMessage()
                            : 'Mentési hiba történt. Kérlek próbáld újra.';
                    }
                }
            }
        }
        $djPhotoPick = trim((string) ($_POST['dj_photo_pick'] ?? ''));
        $djLogoPick = trim((string) ($_POST['dj_logo_pick'] ?? ''));
    }
}

$pageTitle = 'Új tánctanár';

$adminFloatTools = [
    [
        'submit_form' => 'dj-edit-form',
        'title' => 'Létrehozás',
        'aria' => 'Tánctanár létrehozása',
        'icon' => 'save',
    ],
    [
        'href' => events_url('tanarok_admin.php'),
        'title' => 'Vissza a tánctanárok listájához',
        'aria' => 'Vissza a tánctanárok listájához',
        'icon' => 'back',
    ],
];
$adminFloatToolsRequireLogin = false;

$selectedStyles = $teacherProfile['style_ids'] ?? [];
if (!is_array($selectedStyles)) {
    $selectedStyles = [];
}

require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php require __DIR__ . '/partials/admin_float_tools.php'; ?>
<div class="card">
    <div class="events-list-head">
        <h1 class="card-title" style="margin:0;">Új tánctanár</h1>
        <div class="events-list-actions">
            <a href="<?= h(events_url('tanarok_admin.php')) ?>" class="btn btn-secondary">Vissza a listához</a>
        </div>
    </div>
    <?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="venue-form events-dj-edit-form" id="dj-edit-form">
        <?= csrf_input('tanar_letrehoz') ?>
        <div class="events-edit-layout">
            <div class="events-edit-main">
                <div class="events-edit-panel">
                    <h3 class="events-edit-panel__title">Alapadatok</h3>
                    <div class="events-edit-title-row venue-edit-title-row">
                        <div class="form-group venue-edit-name-field">
                            <label for="dj_name">Név *</label>
                            <input type="text" id="dj_name" name="name" value="<?= h($name) ?>" required maxlength="255" autofocus placeholder="Tánctanár neve">
                        </div>
                        <button type="button" class="btn btn-secondary events-edit-slug-refresh" id="dj-slug-refresh" title="Slug frissítése a névből" aria-label="Slug frissítése a névből">🔄</button>
                        <div class="form-group venue-edit-slug-field">
                            <label for="dj_slug">Slug (URL)</label>
                            <input type="text" id="dj_slug" name="slug" value="<?= h($slug) ?>" maxlength="255" pattern="[a-z0-9_]*" title="Kisbetű, szám és aláhúzás" placeholder="tanar_neve">
                        </div>
                    </div>
                    <p class="help">Üres slug esetén mentéskor a névből generálódik.</p>
                </div>
                <div class="events-edit-panel">
                    <h3 class="events-edit-panel__title">Profil / kontakt</h3>
                    <?php
                    $tagProfile = $profile;
                    $tagFormId = 0;
                    $tagProfileStandalone = true;
                    require __DIR__ . '/partials/tag_dj_profile_fields.php';
                    ?>
                </div>
                <div class="events-edit-panel">
                    <h3 class="events-edit-panel__title">Tánctanár adatok</h3>
                    <div class="form-group">
                        <label for="bio_short">Rövid bemutatkozás (max. 500 karakter)</label>
                        <textarea id="bio_short" name="bio_short" rows="3" maxlength="500"><?= h((string) ($teacherProfile['bio_short'] ?? '')) ?></textarea>
                    </div>
                    <div class="events-tag-dj-profile__grid">
                        <div class="form-group">
                            <label for="teacher_city">Város</label>
                            <input type="text" id="teacher_city" name="city" value="<?= h((string) ($teacherProfile['city'] ?? '')) ?>" maxlength="128">
                        </div>
                        <div class="form-group">
                            <label for="years_experience">Oktatási tapasztalat (év)</label>
                            <input type="number" id="years_experience" name="years_experience" min="0" max="80" value="<?= h($teacherProfile['years_experience'] !== null ? (string) $teacherProfile['years_experience'] : '') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" name="offers_private_lessons" value="1"<?= !empty($teacherProfile['offers_private_lessons']) ? ' checked' : '' ?>> Magánórát tart</label>
                    </div>
                    <div class="form-group">
                        <label for="private_lesson_note">Magánóra megjegyzés</label>
                        <textarea id="private_lesson_note" name="private_lesson_note" rows="2"><?= h((string) ($teacherProfile['private_lesson_note'] ?? '')) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" name="teaches_online" value="1"<?= !empty($teacherProfile['teaches_online']) ? ' checked' : '' ?>> Online is oktat</label>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" name="available_for_events" value="1"<?= !empty($teacherProfile['available_for_events']) ? ' checked' : '' ?>> Elérhető eseményekre / workshopokra</label>
                    </div>
                    <div class="form-group">
                        <label for="achievements">Eredmények (HTML)</label>
                        <textarea id="achievements" name="achievements" class="js-html-editor-source" rows="8"><?= h((string) ($teacherProfile['achievements'] ?? '')) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="certifications">Képesítések</label>
                        <textarea id="certifications" name="certifications" rows="3"><?= h((string) ($teacherProfile['certifications'] ?? '')) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="style_ids">Stílusok</label>
                        <select id="style_ids" name="style_ids[]" multiple size="<?= min(12, max(4, count($styleOptions))) ?>">
                            <?php foreach ($styleOptions as $sid => $sname): ?>
                                <option value="<?= (int) $sid ?>"<?= in_array((int) $sid, array_map('intval', $selectedStyles), true) ? ' selected' : '' ?>><?= h($sname) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="help">Ctrl / Cmd + kattintással több stílus is kiválasztható.</p>
                    </div>
                </div>
            </div>
            <aside class="events-edit-sidebar">
                <div class="events-edit-panel">
                    <?php
                    $djPhotoUrl = (string) ($profile['photo_url'] ?? '');
                    require __DIR__ . '/partials/dj_photo_fields.php';
                    ?>
                </div>
                <div class="events-edit-panel">
                    <?php
                    $djLogoUrl = (string) ($profile['logo_url'] ?? '');
                    require __DIR__ . '/partials/dj_logo_fields.php';
                    ?>
                </div>
                <div class="toolbar">
                    <button type="submit" class="btn btn-primary">Létrehozás</button>
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

    function fetchSlugFromName(force) {
        if (!force && slugEl.value.trim() !== '') return;
        var nm = nameEl.value.trim();
        if (nm === '') return;
        var u = new URL(ajaxPath, window.location.href);
        u.searchParams.set('name', nm);
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
<?php require __DIR__ . '/partials/html_editor_script.php'; ?>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
