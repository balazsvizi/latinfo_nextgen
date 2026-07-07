<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/admin_event_filters.php';
require_once __DIR__ . '/lib/event_edit_stats.php';
requireLogin();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Hiányzó azonosító.');
    redirect(events_url('organizers.php'));
}

$db = getDb();
$stmt = $db->prepare('SELECT `id`, `name` FROM `events_organizers` WHERE `id` = ? LIMIT 1');
$stmt->execute([$id]);
$organizer = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$organizer) {
    flash('error', 'Szervező nem található.');
    redirect(events_url('organizers.php'));
}

$hiba = '';
$name = (string) ($organizer['name'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('organizer_szerkeszt')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet. Töltsd újra az oldalt.';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            $hiba = 'A név megadása kötelező.';
        } else {
            $dup = $db->prepare('SELECT `id` FROM `events_organizers` WHERE `name` = ? AND `id` <> ? LIMIT 1');
            $dup->execute([$name, $id]);
            if ($dup->fetchColumn() !== false) {
                $hiba = 'Már létezik ilyen nevű szervező.';
            } else {
                try {
                    $upd = $db->prepare('UPDATE `events_organizers` SET `name` = ? WHERE `id` = ?');
                    $upd->execute([$name, $id]);
                    rendszer_log('szervező', $id, 'Módosítva', $name);
                    flash('success', 'Mentve.');
                    redirect(events_url('organizer_szerkeszt.php?id=') . $id);
                } catch (Throwable $ex) {
                    error_log('organizer_szerkeszt: ' . $ex->getMessage());
                    $hiba = 'Mentési hiba történt. Kérlek próbáld újra.';
                }
            }
        }
    }
}

$publicUrl = events_url('organizer.php?id=') . $id;
$statsParams = events_edit_stats_params_from_request($_GET);
$statsData = events_edit_stats_for_organizer($db, $id, $statsParams);
$statsEventRows = $statsData['event_rows'] ?? [];
$draftRows = $statsData['draft_rows'] ?? [];
$pageTitle = 'Szervező szerkesztése: ' . $name;
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>
<div class="card">
    <h1 class="card-title">Szervező szerkesztése</h1>
    <?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post" class="venue-form">
        <?= csrf_input('organizer_szerkeszt') ?>
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <div class="form-group">
            <label for="organizer_name">Név *</label>
            <input type="text" id="organizer_name" name="name" value="<?= h($name) ?>" required maxlength="500" autofocus>
        </div>
        <p class="toolbar">
            <button type="submit" class="btn btn-primary">Mentés</button>
            <a href="<?= h($publicUrl) ?>" class="btn btn-secondary" target="_blank" rel="noopener">Nyilvános oldal</a>
            <a href="<?= h(events_url('organizers.php')) ?>" class="btn btn-secondary">Vissza a listához</a>
        </p>
    </form>
</div>

<?php require __DIR__ . '/partials/admin_organizer_portal_account.php'; ?>
<?php require __DIR__ . '/partials/admin_organizer_drafts.php'; ?>
<?php require __DIR__ . '/partials/admin_organizer_edit_stats.php'; ?>

<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
