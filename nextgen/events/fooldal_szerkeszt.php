<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/public_home_content.php';
requireLogin();

$db = getDb();
$tableOk = events_public_home_table_available($db);
$hiba = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('events_fooldal')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet. Töltsd újra az oldalt.';
    } elseif (!$tableOk) {
        $hiba = 'Hiányzik az events_public_home tábla. Futtasd: events/sql/migration_public_home.sql';
    } else {
        $top = (string) ($_POST['content_top'] ?? '');
        $bottom = (string) ($_POST['content_bottom'] ?? '');
        try {
            events_public_home_save($db, $top, $bottom);
            flash('success', 'A főoldal szövegei mentve.');
            redirect(events_url('fooldal_szerkeszt.php'));
        } catch (Throwable $e) {
            error_log('events fooldal_szerkeszt save: ' . $e->getMessage());
            $hiba = 'A mentés nem sikerült.';
        }
    }
}

$content = events_public_home_load($db);

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Publikus főoldal szövegei';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>

<div class="card">
    <div class="events-list-head">
        <h2 class="events-list-title">Publikus főoldal szövegei</h2>
        <div class="events-list-actions">
            <a href="<?= h(events_public_home_url('hu')) ?>" class="btn btn-secondary btn-sm" target="_blank" rel="noopener">Előnézet</a>
        </div>
    </div>

    <?php if (!$tableOk): ?>
        <p class="alert alert-error">Hiányzik az <code>events_public_home</code> tábla. Futtasd: <code>events/sql/migration_public_home.sql</code></p>
    <?php else: ?>
        <p class="text-muted" style="margin-top:0">A szövegek a nyilvános esemény főoldalon jelennek meg a naptár felett és alatt. Csak közzétett események látszanak a naptárban.</p>
        <form method="post" action="<?= h(events_url('fooldal_szerkeszt.php')) ?>" class="events-admin-form">
            <?= csrf_input('events_fooldal') ?>

            <div class="form-group">
                <label for="content_top">Szöveg felül (HTML)</label>
                <textarea class="js-tinymce" id="content_top" name="content_top" rows="14"><?= h($content['content_top']) ?></textarea>
            </div>

            <div class="form-group">
                <label for="content_bottom">Szöveg alul (HTML)</label>
                <textarea class="js-tinymce" id="content_bottom" name="content_bottom" rows="14"><?= h($content['content_bottom']) ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Mentés</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/tinymce_script.php'; ?>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
