<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/nextgen/includes/auth.php';
require_once __DIR__ . '/lib/entity_quick_create.php';
requireLogin();

$db = getDb();
$hiba = '';
$name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('organizer_letrehoz')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet. Töltsd újra az oldalt.';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        try {
            events_find_or_create_organizer_by_name($db, $name);
            flash('success', 'Szervező felvéve.');
            redirect(events_url('organizers.php'));
        } catch (InvalidArgumentException $ex) {
            $hiba = $ex->getMessage();
        } catch (Throwable $ex) {
            error_log('organizer_letrehoz: ' . $ex->getMessage());
            $hiba = 'Mentési hiba történt. Kérlek próbáld újra.';
        }
    }
}

$pageTitle = 'Új szervező';
require_once dirname(__DIR__) . '/nextgen/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<div class="card">
    <h1 class="card-title">Új szervező felvétele</h1>
    <?php if ($hiba !== ''): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post" class="venue-form">
        <?= csrf_input('organizer_letrehoz') ?>
        <div class="form-group">
            <label for="organizer_name">Név *</label>
            <input type="text" id="organizer_name" name="name" value="<?= h($name) ?>" required maxlength="500" autofocus>
        </div>
        <p class="toolbar">
            <button type="submit" class="btn btn-primary">Felvétel</button>
            <a href="<?= h(events_url('organizers.php')) ?>" class="btn btn-secondary">Mégse</a>
        </p>
    </form>
</div>
<?php require_once dirname(__DIR__) . '/nextgen/partials/footer.php'; ?>
