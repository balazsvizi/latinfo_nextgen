<?php
declare(strict_types=1);

/**
 * CMS cikk szerkesztés + napló + másolás.
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/events/lib/event_request.php';
requireLogin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Hiányzó azonosító.');
    redirect(cms_url('posts.php'));
}

$db = getDb();
cms_ensure_schema($db);

$post = cms_load_post($db, $id);
if ($post === null) {
    flash('error', 'A cikk nem található.');
    redirect(cms_url('posts.php'));
}

$themes = cms_themes_options($db, false);
$tags = function_exists('events_load_tag_options') ? events_load_tag_options($db) : [];
$tagOptions = [];
foreach ($tags as $tid => $tname) {
    $tagOptions[] = ['id' => (int) $tid, 'name' => (string) $tname];
}

$hiba = '';
$p = cms_post_for_form($post);
$p['tag_ids'] = cms_load_post_tag_ids($db, $id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('cms_szerkeszt')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet. Töltsd újra az oldalt.';
    } else {
        $formAction = (string) ($_POST['form_action'] ?? 'save');
        if ($formAction === 'delete') {
            try {
                $db->beginTransaction();
                $db->prepare('DELETE FROM `cms_post_tags` WHERE `post_id` = ?')->execute([$id]);
                $db->prepare('DELETE FROM `cms_posts` WHERE `id` = ?')->execute([$id]);
                $db->commit();
                rendszer_log('cms_post', $id, 'Törölve', 'Cím: ' . (string) ($post['title'] ?? ''));
                flash('success', 'Cikk törölve.');
                redirect(cms_url('posts.php'));
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log('cms delete: ' . $e->getMessage());
                $hiba = 'Törlés sikertelen.';
            }
        } else {
            [$row, $err, $tagIds] = cms_post_from_request($db, $post, $id);
            if ($err !== null) {
                $hiba = $err;
                $p = cms_post_for_form($row);
                $p['tag_ids'] = $tagIds;
            } else {
                try {
                    $db->beginTransaction();
                    $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
                    $st = $db->prepare('
                        UPDATE `cms_posts` SET
                            `theme_id` = ?, `title` = ?, `slug` = ?, `excerpt` = ?, `content_html` = ?,
                            `status` = ?, `published_at` = ?, `featured_image_url` = ?,
                            `seo_title` = ?, `seo_description` = ?, `updated_by_admin_id` = ?
                        WHERE `id` = ?
                    ');
                    $st->execute([
                        $row['theme_id'],
                        $row['title'],
                        $row['slug'],
                        $row['excerpt'],
                        $row['content_html'],
                        $row['status'],
                        $row['published_at'],
                        $row['featured_image_url'],
                        $row['seo_title'],
                        $row['seo_description'],
                        $adminId,
                        $id,
                    ]);
                    cms_save_post_tags($db, $id, $tagIds);
                    $db->commit();
                    rendszer_log('cms_post', $id, 'Módosítva', cms_build_log_details($row, $tagIds));
                    flash('success', 'Cikk mentve.');
                    redirect(cms_url('szerkeszt.php?id=' . $id));
                } catch (Throwable $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    error_log('cms szerkeszt: ' . $e->getMessage());
                    $hiba = 'Mentés sikertelen. Próbáld újra.';
                    $p = cms_post_for_form($row);
                    $p['tag_ids'] = $tagIds;
                }
            }
        }
    }
}

$logStmt = $db->prepare('
    SELECT r.*, a.név AS admin_név
    FROM nextgen_system_log r
    LEFT JOIN nextgen_admins a ON a.id = r.admin_id
    WHERE r.entitás = ? AND r.entitás_id = ?
    ORDER BY r.létrehozva DESC
    LIMIT 30
');
$logStmt->execute(['cms_post', $id]);
$sablonLogok = $logStmt->fetchAll();
$viewCount = cms_stats_views_for_post($db, $id, true);

$formAction = cms_url('szerkeszt.php?id=' . $id);
$csrfScope = 'cms_szerkeszt';
$isCopy = false;
$copyFromId = 0;
$pageTitle = 'CMS cikk szerkesztése';

$cmsEditCopyUrl = cms_url('letrehoz.php?copy_from=' . $id);
$cmsEditPublicUrl = null;
$slug = trim((string) ($post['slug'] ?? ''));
if ($slug !== '') {
    $cmsEditPublicUrl = cms_public_post_url($slug);
    if ((string) ($post['status'] ?? '') !== cms_status_publish()) {
        $cmsEditPublicUrl .= (str_contains($cmsEditPublicUrl, '?') ? '&' : '?') . 'preview=1';
    }
}

$mainContentClass = 'main-content main-content--fullwidth';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>

<?php require __DIR__ . '/partials/admin_edit_float_tools.php'; ?>

<p class="help" style="margin-bottom:1rem;">Megtekintések (ember): <?= (int) $viewCount ?></p>

<?php require __DIR__ . '/partials/post_form.php'; ?>

<div class="card" style="margin-top:1rem;">
    <h2>Törlés</h2>
    <form method="post" action="<?= h($formAction) ?>" onsubmit="return confirm('Biztosan törlöd ezt a cikket?');">
        <?= csrf_input('cms_szerkeszt') ?>
        <input type="hidden" name="form_action" value="delete">
        <button type="submit" class="btn btn-danger">Cikk törlése</button>
    </form>
</div>

<?php
$sablonLogok = $sablonLogok ?? [];
require dirname(__DIR__) . '/events/partials/admin_event_edit_log.php';
?>

<?php
$tinymceImageUploadUrl = cms_url('ajax_image_upload.php');
$tinymceImageUploadCsrf = csrf_token('cms_image');
require dirname(__DIR__) . '/events/partials/tinymce_script.php';
?>
<?php require dirname(__DIR__) . '/events/partials/wp_token_input_script.php'; ?>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
