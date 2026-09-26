<?php
declare(strict_types=1);

/**
 * CMS képtár (külön az eventpics-től).
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
requireLogin();

$db = getDb();
cms_ensure_schema($db);
$hiba = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('cms_kepek')) {
        flash('error', 'Lejárt vagy érvénytelen munkamenet.');
        redirect(cms_url('kepek.php'));
    }
    $action = (string) ($_POST['action'] ?? 'upload');
    if ($action === 'upload') {
        [$webPath, $err] = cms_uploads_handle_upload($_FILES['file'] ?? null);
        if ($err !== null) {
            $hiba = $err;
        } elseif ($webPath === null || $webPath === '') {
            $hiba = 'Válassz ki egy képfájlt.';
        } else {
            rendszer_log('cms_image', null, 'Feltöltve', $webPath);
            flash('success', 'Kép feltöltve.');
            redirect(cms_url('kepek.php'));
        }
    }
}

$files = cms_uploads_list_files();

$pageTitle = 'CMS képek';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>

<div class="card">
    <h2>Kép feltöltése</h2>
    <p class="help">A CMS képek a <code>nextgen/cms/uploads/</code> mappába kerülnek – elkülönülnek az esemény borítóképektől.</p>
    <form method="post" enctype="multipart/form-data" action="<?= h(cms_url('kepek.php')) ?>">
        <?= csrf_input('cms_kepek') ?>
        <input type="hidden" name="action" value="upload">
        <div class="form-group">
            <label for="cms-file">Fájl (JPG, PNG, WEBP, GIF, max 8 MB)</label>
            <input type="file" id="cms-file" name="file" accept="image/jpeg,image/png,image/webp,image/gif" required>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Feltöltés</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>Feltöltött képek (<?= count($files) ?>)</h2>
    <?php if ($files === []): ?>
        <p class="help">Még nincs kép.</p>
    <?php else: ?>
        <div class="cms-media-grid">
            <?php foreach ($files as $f): ?>
                <?php
                    $web = cms_uploads_build_web_path($f);
                    $abs = cms_absolute_url($web);
                ?>
                <figure class="cms-media-item">
                    <img src="<?= h($web) ?>" alt="<?= h($f) ?>" loading="lazy">
                    <figcaption>
                        <code class="cms-media-path"><?= h($web) ?></code>
                        <button type="button" class="btn btn-secondary btn-sm js-copy-path" data-path="<?= h($web) ?>">Útvonal másolása</button>
                    </figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<script>
document.querySelectorAll('.js-copy-path').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var path = btn.getAttribute('data-path') || '';
        if (!path || !navigator.clipboard) return;
        navigator.clipboard.writeText(path).then(function () {
            btn.textContent = 'Másolva';
            setTimeout(function () { btn.textContent = 'Útvonal másolása'; }, 1200);
        });
    });
});
</script>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
