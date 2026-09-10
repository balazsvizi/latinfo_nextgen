<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/tag_type.php';
require_once __DIR__ . '/lib/tag_profile.php';
require_once __DIR__ . '/lib/djpics.php';
require_once __DIR__ . '/lib/html_security.php';
require_once __DIR__ . '/lib/event_public_lang.php';
requireLogin();

$db = getDb();
events_tags_ensure_slug_column($db);
events_tags_ensure_profile_columns($db);

if (!events_tags_tables_available($db) || !events_tag_types_tables_available($db)) {
    flash('error', 'Hiányoznak a címke táblák.');
    redirect(events_url('djs_admin.php'));
}

$hiba = '';
$name = '';
$profile = events_tag_profile_empty();
$djPhotoPick = '';
$djLogoPick = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('dj_letrehoz')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet.';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        [$profile, $profileErr] = events_tag_profile_from_post_without_photo();
        if ($name === '') {
            $hiba = 'A név megadása kötelező.';
        } elseif ($profileErr !== null) {
            $hiba = $profileErr;
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
                    $hiba = 'Már létezik ilyen nevű címke / DJ.';
                } else {
                    try {
                        $db->beginTransaction();
                        $ins = $db->prepare('INSERT INTO `events_tags` (`name`) VALUES (?)');
                        $ins->execute([$name]);
                        $newId = (int) $db->lastInsertId();
                        events_save_tag_types($db, $newId, ['dj']);
                        events_tag_sync_dj_slug($db, $newId, $name, ['dj']);
                        events_tag_profile_save($db, $newId, $profile);
                        $db->commit();
                        rendszer_log('tag', $newId, 'DJ létrehozva', $name);
                        flash('success', 'DJ létrehozva.');
                        redirect(events_url('dj_szerkeszt.php?id=') . $newId);
                    } catch (Throwable $e) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        error_log('dj_letrehoz: ' . $e->getMessage());
                        $hiba = 'Mentési hiba történt. Kérlek próbáld újra.';
                    }
                }
            }
        }
        $djPhotoPick = trim((string) ($_POST['dj_photo_pick'] ?? ''));
        $djLogoPick = trim((string) ($_POST['dj_logo_pick'] ?? ''));
    }
}

$pageTitle = 'Új DJ';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<div class="card">
    <div class="events-list-head">
        <h1 class="card-title" style="margin:0;">Új DJ</h1>
        <div class="events-list-actions">
            <a href="<?= h(events_url('djs_admin.php')) ?>" class="btn btn-secondary">Vissza a listához</a>
        </div>
    </div>
    <?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="venue-form events-dj-edit-form">
        <?= csrf_input('dj_letrehoz') ?>
        <div class="events-edit-layout">
            <div class="events-edit-main">
                <div class="events-edit-panel">
                    <h3 class="events-edit-panel__title">Alapadatok</h3>
                    <div class="form-group">
                        <label for="dj_name">Név *</label>
                        <input type="text" id="dj_name" name="name" value="<?= h($name) ?>" required maxlength="255" autofocus placeholder="DJ Példa">
                    </div>
                </div>
                <div class="events-edit-panel">
                    <h3 class="events-edit-panel__title">Profil</h3>
                    <?php
                    $tagProfile = $profile;
                    $tagFormId = 0;
                    $tagProfileStandalone = true;
                    require __DIR__ . '/partials/tag_dj_profile_fields.php';
                    ?>
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
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
