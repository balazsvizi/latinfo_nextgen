<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/dance_schools.php';
require_once __DIR__ . '/lib/dance_teachers.php';
require_once __DIR__ . '/lib/dance_entity_views.php';
require_once __DIR__ . '/lib/djpics.php';
require_once __DIR__ . '/lib/style_request.php';
requireLogin();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Hiányzó azonosító.');
    redirect(events_url('tanciskolak_admin.php'));
}

$db = getDb();
dance_schools_ensure_schema($db);

$existing = dance_school_by_id($db, $id);
if ($existing === null) {
    flash('error', 'Tánciskola nem található.');
    redirect(events_url('tanciskolak_admin.php'));
}

$row = $existing;
$hiba = '';
$djPhotoPick = '';
$djLogoPick = '';

$locationsForm = [];
foreach (dance_school_venues($db, $id) as $loc) {
    $linkId = (int) $loc['id'];
    $loc['offerings'] = dance_school_offerings_for_school_venue($db, $linkId);
    $locationsForm[] = $loc;
}
$eventsForm = dance_school_events_list($db, $id);
$teachersForm = dance_school_teachers_list($db, $id);
$venueOptions = events_load_venue_options($db);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    dance_entity_view_record($db, 'school', $id, 'admin_view');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('tanciskola_szerkeszt')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet.';
    } elseif ((string) ($_POST['form_action'] ?? 'save') === 'delete') {
        $del = dance_school_delete($db, $id);
        if (!$del['ok']) {
            $hiba = (string) ($del['error'] ?? 'Törlés sikertelen.');
        } else {
            if (function_exists('rendszer_log')) {
                rendszer_log('dance_school', $id, 'Tánciskola törölve', (string) ($existing['name'] ?? ''));
            }
            flash('success', 'Tánciskola törölve.');
            redirect(events_url('tanciskolak_admin.php'));
        }
    } else {
        [$rowIn, $rowErr] = dance_school_from_post($_POST);
        $locationsForm = dance_school_venues_from_post($_POST['venues'] ?? ($_POST['locations'] ?? null));
        $eventsForm = dance_school_events_from_post($_POST['events'] ?? null);
        $teachersForm = dance_school_teachers_from_post($_POST['teachers'] ?? null);

        if ($rowErr !== null) {
            $hiba = $rowErr;
            $row = $rowIn;
        } else {
            $currentPhoto = (string) ($existing['photo_url'] ?? '');
            $currentLogo = (string) ($existing['logo_url'] ?? '');
            [$photoUrl, $photoErr] = events_djpics_resolve_photo_for_save($currentPhoto, !empty($_POST['dj_photo_clear']));
            [$logoUrl, $logoErr] = $photoErr === null
                ? events_djpics_resolve_logo_for_save($currentLogo, !empty($_POST['dj_logo_clear']))
                : [null, $photoErr];
            if ($photoErr !== null) {
                $hiba = $photoErr;
                $row = $rowIn;
            } elseif ($logoErr !== null) {
                $hiba = $logoErr;
                $row = $rowIn;
            } else {
                $rowIn['photo_url'] = $photoUrl ?? '';
                $rowIn['logo_url'] = $logoUrl ?? '';
                try {
                    $db->beginTransaction();
                    $save = dance_school_save($db, $rowIn, $id);
                    if (!$save['ok']) {
                        throw new RuntimeException((string) ($save['error'] ?? 'Mentési hiba.'));
                    }
                    $syncLoc = dance_school_sync_venues($db, $id, $locationsForm);
                    if (!$syncLoc['ok']) {
                        throw new RuntimeException((string) ($syncLoc['error'] ?? 'Helyszínek mentése sikertelen.'));
                    }
                    $syncEv = dance_school_sync_events($db, $id, $eventsForm);
                    if (!$syncEv['ok']) {
                        throw new RuntimeException((string) ($syncEv['error'] ?? 'Naptár mentése sikertelen.'));
                    }
                    $syncTch = dance_school_sync_teachers($db, $id, $teachersForm);
                    if (!$syncTch['ok']) {
                        throw new RuntimeException((string) ($syncTch['error'] ?? 'Tanárok mentése sikertelen.'));
                    }
                    if ($db->inTransaction()) {
                        $db->commit();
                    }
                    if (function_exists('rendszer_log')) {
                        rendszer_log('dance_school', $id, 'Tánciskola módosítva', (string) $rowIn['name']);
                    }
                    flash('success', 'Mentve.');
                    redirect(events_url('tanciskola_szerkeszt.php?id=') . $id);
                } catch (Throwable $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    error_log('tanciskola_szerkeszt: ' . $e->getMessage());
                    $hiba = $e instanceof RuntimeException
                        ? $e->getMessage()
                        : 'Mentési hiba történt. Kérlek próbáld újra.';
                    $row = $rowIn;
                }
            }
        }
        $djPhotoPick = trim((string) ($_POST['dj_photo_pick'] ?? ''));
        $djLogoPick = trim((string) ($_POST['dj_logo_pick'] ?? ''));
    }
}

if ($hiba === '') {
    $row = dance_school_by_id($db, $id) ?? $row;
    $locationsForm = [];
    foreach (dance_school_locations($db, $id) as $loc) {
        $loc['offerings'] = dance_school_offerings_for_location($db, (int) $loc['id']);
        $locationsForm[] = $loc;
    }
    $eventsForm = dance_school_events_list($db, $id);
    $teachersForm = dance_school_teachers_list($db, $id);
}

$styleOptions = events_load_style_options($db);
$ageLabels = dance_school_age_group_labels();
$levelLabels = dance_school_level_labels();
$classTypeLabels = dance_school_class_type_labels();
$eventTypeLabels = dance_school_event_type_labels();
$roleLabels = dance_school_teacher_role_labels();
$teacherOptions = dance_teachers_selectable_list($db);

$locationOptions = [];
foreach ($locationsForm as $loc) {
    $vid = (int) ($loc['venue_id'] ?? 0);
    $lname = trim((string) ($loc['venue_name'] ?? ''));
    if ($lname === '' && $vid > 0 && isset($venueOptions[$vid])) {
        $lname = (string) $venueOptions[$vid];
    }
    if ($vid > 0 && $lname !== '') {
        $locationOptions[] = ['id' => $vid, 'name' => $lname];
    }
}

$emailIsPrivate = !empty($row['email_is_private']);
$phoneIsPrivate = !empty($row['phone_is_private']);
$name = (string) ($row['name'] ?? '');

$pageTitle = 'Tánciskola szerkesztése: ' . $name;

$adminFloatTools = [
    [
        'submit_form' => 'dance-school-edit-form',
        'title' => 'Mentés',
        'aria' => 'Mentés',
        'icon' => 'save',
    ],
    [
        'href' => events_url('tanciskolak_admin.php'),
        'title' => 'Vissza a tánciskolák listájához',
        'aria' => 'Vissza a tánciskolák listájához',
        'icon' => 'back',
    ],
];
$adminFloatToolsRequireLogin = false;

require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>
<?php require __DIR__ . '/partials/admin_float_tools.php'; ?>
<div class="card events-admin-card">
    <div class="events-list-head">
        <h1 class="card-title" style="margin:0;">Tánciskola szerkesztése</h1>
        <div class="events-list-actions">
            <a href="<?= h(events_url('tanciskolak_admin.php')) ?>" class="btn btn-secondary">← Tánciskolák listája</a>
        </div>
    </div>
    <?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <?php if (empty($row['is_published'])): ?>
        <p class="alert alert-warning">Ez a tánciskola még nem publikus.</p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="venue-form events-dj-edit-form" id="dance-school-edit-form">
        <?= csrf_input('tanciskola_szerkeszt') ?>
        <input type="hidden" name="id" value="<?= (int) $id ?>">

        <div class="events-edit-layout">
            <div class="events-edit-main">
                <div class="events-edit-panel">
                    <h3 class="events-edit-panel__title">A) Alapadatok</h3>
                    <div class="events-edit-title-row venue-edit-title-row">
                        <div class="form-group venue-edit-name-field">
                            <label for="school_name">Név *</label>
                            <input type="text" id="school_name" name="name" value="<?= h((string) $row['name']) ?>" required maxlength="255" autofocus>
                        </div>
                        <button type="button" class="btn btn-secondary events-edit-slug-refresh" id="school-slug-refresh" title="Slug frissítése a névből" aria-label="Slug frissítése a névből">🔄</button>
                        <div class="form-group venue-edit-slug-field">
                            <label for="school_slug">Slug (URL)</label>
                            <input type="text" id="school_slug" name="slug" value="<?= h((string) $row['slug']) ?>" maxlength="255" pattern="[a-z0-9_]*" title="Kisbetű, szám és aláhúzás">
                        </div>
                    </div>
                    <div class="events-tag-dj-profile__grid">
                        <div class="form-group">
                            <label for="school_city">Város</label>
                            <input type="text" id="school_city" name="city" value="<?= h((string) ($row['city'] ?? '')) ?>" maxlength="128">
                        </div>
                        <div class="form-group">
                            <label for="founded_year">Alapítás éve</label>
                            <input type="number" id="founded_year" name="founded_year" value="<?= h($row['founded_year'] !== null && $row['founded_year'] !== '' ? (string) $row['founded_year'] : '') ?>" min="1900" max="<?= (int) date('Y') + 1 ?>">
                        </div>
                        <div class="form-group">
                            <label for="languages">Nyelvek</label>
                            <input type="text" id="languages" name="languages" value="<?= h((string) ($row['languages'] ?? '')) ?>" maxlength="255" placeholder="pl. magyar, angol">
                        </div>
                    </div>
                </div>

                <div class="events-edit-panel">
                    <h3 class="events-edit-panel__title">Bemutatkozás</h3>
                    <div class="form-group">
                        <label for="school_description">Leírás (HTML)</label>
                        <textarea id="school_description" name="description" class="js-html-editor-source" rows="12"><?= h((string) ($row['description'] ?? '')) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="trial_lesson_info">Próbaóra info</label>
                        <textarea id="trial_lesson_info" name="trial_lesson_info" rows="3"><?= h((string) ($row['trial_lesson_info'] ?? '')) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="pricing_info">Árazás info</label>
                        <textarea id="pricing_info" name="pricing_info" rows="3"><?= h((string) ($row['pricing_info'] ?? '')) ?></textarea>
                    </div>
                    <div class="events-tag-dj-profile__grid">
                        <div class="form-group">
                            <label for="schedule_url">Órarend URL<?= events_url_open_button((string) ($row['schedule_url'] ?? '')) ?></label>
                            <input type="text" id="schedule_url" name="schedule_url" value="<?= h((string) ($row['schedule_url'] ?? '')) ?>" maxlength="2000" placeholder="https://…">
                        </div>
                        <div class="form-group">
                            <label for="registration_url">Jelentkezés URL<?= events_url_open_button((string) ($row['registration_url'] ?? '')) ?></label>
                            <input type="text" id="registration_url" name="registration_url" value="<?= h((string) ($row['registration_url'] ?? '')) ?>" maxlength="2000" placeholder="https://…">
                        </div>
                    </div>
                </div>

                <div class="events-edit-panel">
                    <h3 class="events-edit-panel__title">Kapcsolatok</h3>
                    <div class="events-tag-dj-profile__grid">
                        <div class="form-group">
                            <label for="website_url">Weboldal<?= events_url_open_button((string) ($row['website_url'] ?? '')) ?></label>
                            <input type="text" id="website_url" name="website_url" maxlength="2000" value="<?= h((string) ($row['website_url'] ?? '')) ?>" placeholder="https://…">
                        </div>
                        <div class="form-group">
                            <label for="facebook_url">Facebook<?= events_url_open_button((string) ($row['facebook_url'] ?? '')) ?></label>
                            <input type="text" id="facebook_url" name="facebook_url" maxlength="2000" value="<?= h((string) ($row['facebook_url'] ?? '')) ?>">
                        </div>
                        <div class="form-group">
                            <label for="instagram_url">Instagram<?= events_url_open_button((string) ($row['instagram_url'] ?? '')) ?></label>
                            <input type="text" id="instagram_url" name="instagram_url" maxlength="2000" value="<?= h((string) ($row['instagram_url'] ?? '')) ?>">
                        </div>
                        <div class="form-group">
                            <label for="tiktok_url">TikTok<?= events_url_open_button((string) ($row['tiktok_url'] ?? '')) ?></label>
                            <input type="text" id="tiktok_url" name="tiktok_url" maxlength="2000" value="<?= h((string) ($row['tiktok_url'] ?? '')) ?>">
                        </div>
                        <div class="form-group">
                            <label for="youtube_url">YouTube<?= events_url_open_button((string) ($row['youtube_url'] ?? '')) ?></label>
                            <input type="text" id="youtube_url" name="youtube_url" maxlength="2000" value="<?= h((string) ($row['youtube_url'] ?? '')) ?>">
                        </div>
                        <div class="form-group events-tag-dj-privacy<?= $emailIsPrivate ? ' is-private' : '' ?>" data-dj-privacy-field>
                            <div class="events-tag-dj-profile__field-head">
                                <label for="email">E-mail<?= events_mailto_open_button((string) ($row['email'] ?? '')) ?></label>
                                <label class="events-tag-dj-privacy__chip" for="email_is_private">
                                    <input type="checkbox" name="email_is_private" value="1" id="email_is_private" class="events-tag-dj-privacy__input" data-dj-privacy-toggle<?= $emailIsPrivate ? ' checked' : '' ?>>
                                    <span class="events-tag-dj-privacy__chip-label">Privát</span>
                                </label>
                            </div>
                            <input type="email" id="email" name="email" maxlength="255" value="<?= h((string) ($row['email'] ?? '')) ?>">
                        </div>
                        <div class="form-group events-tag-dj-privacy<?= $phoneIsPrivate ? ' is-private' : '' ?>" data-dj-privacy-field>
                            <div class="events-tag-dj-profile__field-head">
                                <label for="phone">Telefon<?= events_tel_open_button((string) ($row['phone'] ?? '')) ?></label>
                                <label class="events-tag-dj-privacy__chip" for="phone_is_private">
                                    <input type="checkbox" name="phone_is_private" value="1" id="phone_is_private" class="events-tag-dj-privacy__input" data-dj-privacy-toggle<?= $phoneIsPrivate ? ' checked' : '' ?>>
                                    <span class="events-tag-dj-privacy__chip-label">Privát</span>
                                </label>
                            </div>
                            <input type="tel" id="phone" name="phone" maxlength="64" value="<?= h((string) ($row['phone'] ?? '')) ?>">
                        </div>
                    </div>
                </div>

                <div class="events-edit-panel">
                    <h3 class="events-edit-panel__title">Jelzők és admin</h3>
                    <div class="form-group">
                        <label><input type="checkbox" name="accepts_beginners" value="1"<?= !empty($row['accepts_beginners']) ? ' checked' : '' ?>> Fogad kezdőket</label>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" name="has_kids_classes" value="1"<?= !empty($row['has_kids_classes']) ? ' checked' : '' ?>> Van gyerekóra</label>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" name="has_performance_team" value="1"<?= !empty($row['has_performance_team']) ? ' checked' : '' ?>> Van fellépő csoport</label>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" name="is_active" value="1"<?= !empty($row['is_active']) ? ' checked' : '' ?>> Aktív</label>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" name="is_published" value="1"<?= !empty($row['is_published']) ? ' checked' : '' ?>> Publikus (nyilvános listán)</label>
                    </div>
                    <div class="form-group">
                        <label for="admin_notes">Admin megjegyzés</label>
                        <textarea id="admin_notes" name="admin_notes" rows="3"><?= h((string) ($row['admin_notes'] ?? '')) ?></textarea>
                    </div>
                </div>

                <div class="events-edit-panel" id="dance-locations-panel">
                    <h3 class="events-edit-panel__title">B) Helyszínek (bulihelyszínek)</h3>
                    <p class="help">Válassz a meglévő bulihelyszínek közül. Cím, térkép és GPS a helyszín saját oldalán szerkeszthető; itt az iskolához tartozó kínálat / megjegyzés kerül.</p>
                    <div id="dance-locations-list">
                        <?php
                        if ($locationsForm === []) {
                            $locIndex = 0;
                            $location = ['is_active' => 1, 'venue_id' => 0];
                            $offerings = [];
                            require __DIR__ . '/partials/dance_school_location_row.php';
                        } else {
                            foreach ($locationsForm as $locIndex => $location) {
                                $offerings = is_array($location['offerings'] ?? null) ? $location['offerings'] : [];
                                require __DIR__ . '/partials/dance_school_location_row.php';
                            }
                        }
                        ?>
                    </div>
                    <button type="button" class="btn btn-secondary" id="dance-location-add">+ Helyszín a listából</button>
                    <a href="<?= h(events_url('venue_letrehoz.php')) ?>" class="btn btn-secondary" target="_blank" rel="noopener">Új bulihelyszín</a>
                </div>

                <div class="events-edit-panel" id="dance-events-panel">
                    <h3 class="events-edit-panel__title">C) Naptár / workshopok</h3>
                    <div id="dance-events-list">
                        <?php foreach ($eventsForm as $evIndex => $eventRow): ?>
                            <?php require __DIR__ . '/partials/dance_school_event_row.php'; ?>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-secondary" id="dance-event-add">+ Esemény hozzáadása</button>
                </div>

                <div class="events-edit-panel" id="dance-teachers-panel">
                    <h3 class="events-edit-panel__title">D) Tánctanárok</h3>
                    <p class="help">A tánctanárok a DJ-khez hasonlóan címkeként (tag) kezeltek: saját profil, fotó, kontakt. Először vedd fel őket a <a href="<?= h(events_url('tanarok_admin.php')) ?>">tánctanárok</a> között, majd rendelj hozzá itt.</p>
                    <div id="dance-teachers-list">
                        <?php foreach ($teachersForm as $tchIndex => $teacherRow): ?>
                            <?php require __DIR__ . '/partials/dance_school_teacher_row.php'; ?>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-secondary" id="dance-teacher-add">+ Tanár a listából</button>
                    <a href="<?= h(events_url('tanar_letrehoz.php')) ?>" class="btn btn-secondary" target="_blank" rel="noopener">Új tánctanár</a>
                </div>
            </div>

            <aside class="events-edit-sidebar">
                <div class="toolbar events-dj-edit-toolbar--top">
                    <button type="submit" class="btn btn-primary events-dj-edit-save-wide" name="form_action" value="save">Mentés</button>
                </div>
                <div class="events-edit-panel">
                    <?php
                    $djPhotoUrl = (string) ($row['photo_url'] ?? '');
                    require __DIR__ . '/partials/dj_photo_fields.php';
                    ?>
                </div>
                <div class="events-edit-panel">
                    <?php
                    $djLogoUrl = (string) ($row['logo_url'] ?? '');
                    require __DIR__ . '/partials/dj_logo_fields.php';
                    ?>
                </div>
                <div class="toolbar events-dj-edit-toolbar--bottom">
                    <button type="submit" class="btn btn-primary events-dj-edit-save-wide" name="form_action" value="save">Mentés</button>
                    <button
                        type="submit"
                        class="btn btn-danger events-dj-edit-save-wide"
                        name="form_action"
                        value="delete"
                        formnovalidate
                        onclick="return confirm('Biztosan törlöd ezt a tánciskolát? A művelet nem vonható vissza.');"
                    >Törlés</button>
                </div>
            </aside>
        </div>
    </form>
</div>

<template id="dance-location-template">
<?php
$locIndex = 9990;
$location = ['is_active' => 1, 'venue_id' => 0];
$offerings = [];
require __DIR__ . '/partials/dance_school_location_row.php';
?>
</template>
<template id="dance-event-template">
<?php
$evIndex = 9990;
$eventRow = [];
require __DIR__ . '/partials/dance_school_event_row.php';
?>
</template>
<template id="dance-teacher-template">
<?php
$tchIndex = 9990;
$teacherRow = [];
require __DIR__ . '/partials/dance_school_teacher_row.php';
?>
</template>
<template id="dance-offering-template">
<?php
$locIndex = 9990;
$offIndex = 9990;
$offering = [];
require __DIR__ . '/partials/dance_school_offering_row.php';
?>
</template>

<script>
(function () {
    var nameEl = document.getElementById('school_name');
    var slugEl = document.getElementById('school_slug');
    var refreshBtn = document.getElementById('school-slug-refresh');
    function slugify(s) {
        var map = {á:'a',é:'e',í:'i',ó:'o',ö:'o',ő:'o',ú:'u',ü:'u',ű:'u',Á:'a',É:'e',Í:'i',Ó:'o',Ö:'o',Ő:'o',Ú:'u',Ü:'u',Ű:'u'};
        return String(s || '').split('').map(function (c) { return map[c] || c; }).join('')
            .toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').replace(/_+/g, '_');
    }
    function applySlug(force) {
        if (!nameEl || !slugEl) return;
        if (!force && slugEl.value.trim() !== '') return;
        var nm = nameEl.value.trim();
        if (nm === '') return;
        slugEl.value = slugify(nm);
    }
    if (nameEl && slugEl) {
        nameEl.addEventListener('blur', function () { applySlug(false); });
        if (refreshBtn) refreshBtn.addEventListener('click', function () { applySlug(true); });
    }

    function nextIndex(listEl, rowSel) {
        return listEl.querySelectorAll(rowSel).length;
    }

    function reindexNames(root, base, index) {
        root.querySelectorAll('[name]').forEach(function (el) {
            var n = el.getAttribute('name') || '';
            el.setAttribute('name', n
                .replace(/^venues\[\d+]/, 'venues[' + index + ']')
                .replace(/^locations\[\d+]/, 'venues[' + index + ']')
                .replace(/^events\[\d+]/, 'events[' + index + ']')
                .replace(/^teachers\[\d+]/, 'teachers[' + index + ']')
            );
        });
        if (base === 'locations' || base === 'venues') {
            root.setAttribute('data-loc-index', String(index));
            var title = root.querySelector('.events-edit-panel__title');
            if (title) title.textContent = 'Helyszín #' + (index + 1);
        }
        if (base === 'events') {
            var et = root.querySelector('.events-edit-panel__title');
            if (et) et.textContent = 'Esemény #' + (index + 1);
        }
        if (base === 'teachers') {
            var tt = root.querySelector('.events-edit-panel__title');
            if (tt) tt.textContent = 'Tanár #' + (index + 1);
        }
    }

    function reindexOfferings(locRow) {
        var locIdx = locRow.getAttribute('data-loc-index') || '0';
        var list = locRow.querySelector('[data-dance-offerings-list]');
        if (!list) return;
        list.querySelectorAll('[data-dance-offering-row]').forEach(function (off, oi) {
            off.querySelectorAll('[name]').forEach(function (el) {
                var n = el.getAttribute('name') || '';
                el.setAttribute('name', n
                    .replace(/venues\[\d+\]\[offerings\]\[\d+\]/, 'venues[' + locIdx + '][offerings][' + oi + ']')
                    .replace(/locations\[\d+\]\[offerings\]\[\d+\]/, 'venues[' + locIdx + '][offerings][' + oi + ']')
                );
            });
        });
    }

    var locList = document.getElementById('dance-locations-list');
    var locTpl = document.getElementById('dance-location-template');
    var offTpl = document.getElementById('dance-offering-template');
    document.getElementById('dance-location-add')?.addEventListener('click', function () {
        if (!locList || !locTpl) return;
        var node = locTpl.content.cloneNode(true).querySelector('[data-dance-location-row]');
        if (!node) return;
        var idx = nextIndex(locList, '[data-dance-location-row]');
        reindexNames(node, 'locations', idx);
        reindexOfferings(node);
        locList.appendChild(node);
    });
    document.addEventListener('click', function (e) {
        var remLoc = e.target.closest('[data-dance-location-remove]');
        if (remLoc) {
            var row = remLoc.closest('[data-dance-location-row]');
            if (row) row.remove();
            if (locList) {
                locList.querySelectorAll('[data-dance-location-row]').forEach(function (r, i) {
                    reindexNames(r, 'locations', i);
                    reindexOfferings(r);
                });
            }
            return;
        }
        var addOff = e.target.closest('[data-dance-offering-add]');
        if (addOff && offTpl) {
            var locRow = addOff.closest('[data-dance-location-row]');
            var list = locRow && locRow.querySelector('[data-dance-offerings-list]');
            if (!list) return;
            var node = offTpl.content.cloneNode(true).querySelector('[data-dance-offering-row]');
            if (!node) return;
            list.appendChild(node);
            reindexOfferings(locRow);
            return;
        }
        var remOff = e.target.closest('[data-dance-offering-remove]');
        if (remOff) {
            var offRow = remOff.closest('[data-dance-offering-row]');
            var locRow2 = remOff.closest('[data-dance-location-row]');
            if (offRow) offRow.remove();
            if (locRow2) reindexOfferings(locRow2);
            return;
        }
        var remEv = e.target.closest('[data-dance-event-remove]');
        if (remEv) {
            var er = remEv.closest('[data-dance-event-row]');
            if (er) er.remove();
            var el = document.getElementById('dance-events-list');
            if (el) el.querySelectorAll('[data-dance-event-row]').forEach(function (r, i) { reindexNames(r, 'events', i); });
            return;
        }
        var remTch = e.target.closest('[data-dance-teacher-remove]');
        if (remTch) {
            var tr = remTch.closest('[data-dance-teacher-row]');
            if (tr) tr.remove();
            var tl = document.getElementById('dance-teachers-list');
            if (tl) tl.querySelectorAll('[data-dance-teacher-row]').forEach(function (r, i) { reindexNames(r, 'teachers', i); });
        }
    });

    var evList = document.getElementById('dance-events-list');
    var evTpl = document.getElementById('dance-event-template');
    document.getElementById('dance-event-add')?.addEventListener('click', function () {
        if (!evList || !evTpl) return;
        var node = evTpl.content.cloneNode(true).querySelector('[data-dance-event-row]');
        if (!node) return;
        var idx = nextIndex(evList, '[data-dance-event-row]');
        reindexNames(node, 'events', idx);
        evList.appendChild(node);
    });

    var tchList = document.getElementById('dance-teachers-list');
    var tchTpl = document.getElementById('dance-teacher-template');
    document.getElementById('dance-teacher-add')?.addEventListener('click', function () {
        if (!tchList || !tchTpl) return;
        var node = tchTpl.content.cloneNode(true).querySelector('[data-dance-teacher-row]');
        if (!node) return;
        var idx = nextIndex(tchList, '[data-dance-teacher-row]');
        reindexNames(node, 'teachers', idx);
        tchList.appendChild(node);
    });
})();
</script>
<?php require __DIR__ . '/partials/html_editor_script.php'; ?>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
