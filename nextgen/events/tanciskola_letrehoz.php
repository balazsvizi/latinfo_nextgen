<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/dance_schools.php';
require_once __DIR__ . '/lib/djpics.php';
requireLogin();

$db = getDb();
dance_schools_ensure_schema($db);

$hiba = '';
$row = dance_school_empty_row();
$djPhotoPick = '';
$djLogoPick = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('tanciskola_letrehoz')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet.';
    } else {
        [$row, $rowErr] = dance_school_from_post($_POST);
        if ($rowErr !== null) {
            $hiba = $rowErr;
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
                $row['photo_url'] = $photoUrl ?? '';
                $row['logo_url'] = $logoUrl ?? '';
                $row['is_published'] = 0;
                $save = dance_school_save($db, $row, null);
                if (!$save['ok']) {
                    $hiba = (string) ($save['error'] ?? 'Mentési hiba történt.');
                } else {
                    $newId = (int) ($save['id'] ?? 0);
                    if (function_exists('rendszer_log')) {
                        rendszer_log('dance_school', $newId, 'Tánciskola létrehozva', (string) $row['name']);
                    }
                    flash('success', 'Tánciskola létrehozva.');
                    redirect(events_url('tanciskola_szerkeszt.php?id=') . $newId);
                }
            }
        }
        $djPhotoPick = trim((string) ($_POST['dj_photo_pick'] ?? ''));
        $djLogoPick = trim((string) ($_POST['dj_logo_pick'] ?? ''));
    }
}

$pageTitle = 'Új tánciskola';

$adminFloatTools = [
    [
        'submit_form' => 'dance-school-edit-form',
        'title' => 'Létrehozás',
        'aria' => 'Tánciskola létrehozása',
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

$emailIsPrivate = !empty($row['email_is_private']);
$phoneIsPrivate = !empty($row['phone_is_private']);

require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php require __DIR__ . '/partials/admin_float_tools.php'; ?>
<div class="card">
    <div class="events-list-head">
        <h1 class="card-title" style="margin:0;">Új tánciskola</h1>
        <div class="events-list-actions">
            <a href="<?= h(events_url('tanciskolak_admin.php')) ?>" class="btn btn-secondary">Vissza a listához</a>
        </div>
    </div>
    <?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="venue-form events-dj-edit-form" id="dance-school-edit-form">
        <?= csrf_input('tanciskola_letrehoz') ?>
        <div class="events-edit-layout">
            <div class="events-edit-main">
                <div class="events-edit-panel">
                    <h3 class="events-edit-panel__title">Alapadatok</h3>
                    <div class="events-edit-title-row venue-edit-title-row">
                        <div class="form-group venue-edit-name-field">
                            <label for="school_name">Név *</label>
                            <input type="text" id="school_name" name="name" value="<?= h((string) $row['name']) ?>" required maxlength="255" autofocus placeholder="Tánciskola neve">
                        </div>
                        <button type="button" class="btn btn-secondary events-edit-slug-refresh" id="school-slug-refresh" title="Slug frissítése a névből" aria-label="Slug frissítése a névből">🔄</button>
                        <div class="form-group venue-edit-slug-field">
                            <label for="school_slug">Slug (URL)</label>
                            <input type="text" id="school_slug" name="slug" value="<?= h((string) $row['slug']) ?>" maxlength="255" pattern="[a-z0-9_]*" title="Kisbetű, szám és aláhúzás" placeholder="tanciskola_neve">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="school_city">Város</label>
                        <input type="text" id="school_city" name="city" value="<?= h((string) $row['city']) ?>" maxlength="128" placeholder="pl. Budapest">
                    </div>
                    <p class="help">A nyilvános megjelenéshez később külön „publikus” jelölés kell. Új iskola alapból még nem publikus.</p>
                </div>
                <div class="events-edit-panel">
                    <h3 class="events-edit-panel__title">Bemutatkozás</h3>
                    <div class="form-group">
                        <label for="school_description">Leírás (HTML)</label>
                        <textarea id="school_description" name="description" class="js-html-editor-source" rows="12"><?= h((string) $row['description']) ?></textarea>
                    </div>
                </div>
                <div class="events-edit-panel">
                    <h3 class="events-edit-panel__title">Kapcsolat</h3>
                    <div class="events-tag-dj-profile__grid">
                        <div class="form-group">
                            <label for="website_url">Weboldal</label>
                            <input type="text" id="website_url" name="website_url" maxlength="2000" value="<?= h((string) $row['website_url']) ?>" placeholder="https://…" inputmode="url" autocomplete="url">
                        </div>
                        <div class="form-group">
                            <label for="facebook_url">Facebook</label>
                            <input type="text" id="facebook_url" name="facebook_url" maxlength="2000" value="<?= h((string) $row['facebook_url']) ?>" placeholder="https://facebook.com/…" inputmode="url">
                        </div>
                        <div class="form-group">
                            <label for="instagram_url">Instagram</label>
                            <input type="text" id="instagram_url" name="instagram_url" maxlength="2000" value="<?= h((string) $row['instagram_url']) ?>" placeholder="https://instagram.com/…" inputmode="url">
                        </div>
                        <div class="form-group">
                            <label for="tiktok_url">TikTok</label>
                            <input type="text" id="tiktok_url" name="tiktok_url" maxlength="2000" value="<?= h((string) $row['tiktok_url']) ?>" placeholder="https://tiktok.com/@…" inputmode="url">
                        </div>
                        <div class="form-group">
                            <label for="youtube_url">YouTube</label>
                            <input type="text" id="youtube_url" name="youtube_url" maxlength="2000" value="<?= h((string) $row['youtube_url']) ?>" placeholder="https://youtube.com/…" inputmode="url">
                        </div>
                        <div class="form-group events-tag-dj-privacy<?= $emailIsPrivate ? ' is-private' : '' ?>" data-dj-privacy-field>
                            <div class="events-tag-dj-profile__field-head">
                                <label for="email">E-mail</label>
                                <label class="events-tag-dj-privacy__chip" for="email_is_private">
                                    <input type="checkbox" name="email_is_private" value="1" id="email_is_private" class="events-tag-dj-privacy__input" data-dj-privacy-toggle<?= $emailIsPrivate ? ' checked' : '' ?>>
                                    <span class="events-tag-dj-privacy__chip-label">Privát</span>
                                </label>
                            </div>
                            <input type="email" id="email" name="email" maxlength="255" value="<?= h((string) $row['email']) ?>" placeholder="info@pelda.hu">
                        </div>
                        <div class="form-group events-tag-dj-privacy<?= $phoneIsPrivate ? ' is-private' : '' ?>" data-dj-privacy-field>
                            <div class="events-tag-dj-profile__field-head">
                                <label for="phone">Telefon</label>
                                <label class="events-tag-dj-privacy__chip" for="phone_is_private">
                                    <input type="checkbox" name="phone_is_private" value="1" id="phone_is_private" class="events-tag-dj-privacy__input" data-dj-privacy-toggle<?= $phoneIsPrivate ? ' checked' : '' ?>>
                                    <span class="events-tag-dj-privacy__chip-label">Privát</span>
                                </label>
                            </div>
                            <input type="tel" id="phone" name="phone" maxlength="64" value="<?= h((string) $row['phone']) ?>" placeholder="+36 …">
                        </div>
                    </div>
                </div>
                <div class="events-edit-panel">
                    <h3 class="events-edit-panel__title">Jelzők</h3>
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
                </div>
            </div>
            <aside class="events-edit-sidebar">
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
                <div class="toolbar">
                    <button type="submit" class="btn btn-primary">Létrehozás</button>
                </div>
            </aside>
        </div>
    </form>
</div>
<script>
(function () {
    var nameEl = document.getElementById('school_name');
    var slugEl = document.getElementById('school_slug');
    var refreshBtn = document.getElementById('school-slug-refresh');
    if (!nameEl || !slugEl) return;

    function slugify(s) {
        var map = {á:'a',é:'e',í:'i',ó:'o',ö:'o',ő:'o',ú:'u',ü:'u',ű:'u',Á:'a',É:'e',Í:'i',Ó:'o',Ö:'o',Ő:'o',Ú:'u',Ü:'u',Ű:'u'};
        return String(s || '').split('').map(function (c) { return map[c] || c; }).join('')
            .toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').replace(/_+/g, '_');
    }

    function applySlug(force) {
        if (!force && slugEl.value.trim() !== '') return;
        var nm = nameEl.value.trim();
        if (nm === '') return;
        slugEl.value = slugify(nm);
    }

    nameEl.addEventListener('blur', function () { applySlug(false); });
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () { applySlug(true); });
    }
})();
</script>
<?php require __DIR__ . '/partials/html_editor_script.php'; ?>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
