<?php
declare(strict_types=1);

/**
 * CMS cikk létrehozás / másolás.
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/events/lib/event_request.php';
requireLogin();

$db = getDb();
cms_ensure_schema($db);

$themes = cms_themes_options($db, true);
$tags = function_exists('events_load_tag_options') ? events_load_tag_options($db) : [];
$tagOptions = [];
foreach ($tags as $tid => $tname) {
    $tagOptions[] = ['id' => (int) $tid, 'name' => (string) $tname];
}

$hiba = '';
$copyNotice = '';
$isCopy = false;
$copyFromId = (int) ($_GET['copy_from'] ?? 0);
$p = cms_post_for_form(cms_post_defaults());

if ($copyFromId > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $copied = cms_load_post_copy_template($db, $copyFromId);
    if ($copied !== null) {
        $p = cms_post_for_form($copied);
        $isCopy = true;
        $copyNotice = 'Cikk másolva draftként. Ellenőrizd az adatokat, majd mentsd.';
    } else {
        flash('error', 'A másolandó cikk nem található.');
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['is_copy'] ?? '') === '1') {
    $isCopy = true;
    $copyFromId = (int) ($_POST['copy_from_id'] ?? $copyFromId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('cms_letrehoz')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet. Töltsd újra az oldalt.';
    } else {
        [$row, $err, $tagIds] = cms_post_from_request($db, cms_post_defaults(), null);
        if ($err !== null) {
            $hiba = $err;
            $p = cms_post_for_form($row);
            $p['tag_ids'] = $tagIds;
        } else {
            try {
                $db->beginTransaction();
                $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
                $st = $db->prepare('
                    INSERT INTO `cms_posts` (
                        `theme_id`, `title`, `slug`, `excerpt`, `content_html`, `status`, `published_at`,
                        `featured_image_url`, `seo_title`, `seo_description`,
                        `created_by_admin_id`, `updated_by_admin_id`
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                    $adminId,
                ]);
                $newId = (int) $db->lastInsertId();
                cms_save_post_tags($db, $newId, $tagIds);
                $db->commit();
                rendszer_log('cms_post', $newId, $isCopy ? 'Másolva' : 'Létrehozva', cms_build_log_details($row, $tagIds));
                flash('success', $isCopy ? 'Cikk másolat mentve.' : 'Cikk létrehozva.');
                redirect(cms_url('szerkeszt.php?id=' . $newId));
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log('cms letrehoz: ' . $e->getMessage());
                $hiba = 'Mentés sikertelen. Próbáld újra.';
                $p = cms_post_for_form($row);
                $p['tag_ids'] = $tagIds;
            }
        }
    }
}

$formAction = cms_url('letrehoz.php');
$csrfScope = 'cms_letrehoz';
$pageTitle = $isCopy ? 'CMS cikk másolása' : 'Új CMS cikk';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($copyNotice !== ''): ?><p class="alert alert-success"><?= h($copyNotice) ?></p><?php endif; ?>
<?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
<?php require __DIR__ . '/partials/post_form.php'; ?>
<?php
$tinymceImageUploadUrl = cms_url('ajax_image_upload.php');
$tinymceImageUploadCsrf = csrf_token('cms_image');
require dirname(__DIR__) . '/events/partials/tinymce_script.php';
?>
<?php require dirname(__DIR__) . '/events/partials/wp_token_input_script.php'; ?>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
