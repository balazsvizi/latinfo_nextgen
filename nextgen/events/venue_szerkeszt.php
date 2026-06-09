<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/venue_request.php';
requireLogin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Hiányzó azonosító.');
    redirect(events_url('venues.php'));
}

$db = getDb();
$stmt = $db->prepare('SELECT * FROM `events_venues` WHERE `id` = ?');
$stmt->execute([$id]);
$venue = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$venue) {
    flash('error', 'Helyszín nem található.');
    redirect(events_url('venues.php'));
}

$venuesLinkOptions = events_load_venue_options_excluding($db, $id);

$hiba = '';
$v = events_venue_row_for_form($venue);
$venueEventCount = events_venue_calendar_event_count($db, $id);
$venueEditId = $id;
$venuePublicUrl = events_helyszin_megjelenit_url((string) ($v['slug'] ?? ''));
$venueFormShowDelete = true;
$venueFormCancelUrl = events_url('venues.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('venue_szerkeszt')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet. Töltsd újra az oldalt.';
    } else {
        $action = (string) ($_POST['action'] ?? 'save');
        if ($action === 'delete') {
            $usage = events_venue_calendar_event_count($db, $id);
            if ($usage > 0) {
                $hiba = 'A helyszín nem törölhető: ' . $usage . ' eseményhez van rendelve. Előbb válaszd le a helyszínt az eseményeken.';
                $venueEventCount = $usage;
            } else {
                try {
                    $db->prepare('DELETE FROM `events_venues` WHERE `id` = ?')->execute([$id]);
                    rendszer_log('helyszín', $id, 'Törölve', (string) ($venue['name'] ?? ''));
                    flash('success', 'Helyszín törölve.');
                    redirect(events_url('venues.php'));
                } catch (Throwable $ex) {
                    error_log('events venue_szerkeszt torlesi hiba: ' . $ex->getMessage());
                    $hiba = 'Törlési hiba történt. Kérlek próbáld újra.';
                }
            }
        } else {
            [$row, $err] = events_venue_row_from_post($db, $venue, $id);
            if ($err !== null) {
                $hiba = $err;
                $v = events_venue_row_for_form($row);
            } else {
                try {
                    $upd = $db->prepare('
                        UPDATE `events_venues` SET
                            `name` = ?, `slug` = ?, `description` = ?,
                            `country` = ?, `city` = ?, `postal_code` = ?, `address` = ?,
                            `latitude` = ?, `longitude` = ?,
                            `website_url` = ?, `google_maps_url` = ?,
                            `linked_venue_id` = ?
                        WHERE `id` = ?
                    ');
                    $upd->execute([
                        $row['name'],
                        $row['slug'],
                        $row['description'] === '' ? null : $row['description'],
                        $row['country'],
                        $row['city'] === '' ? null : $row['city'],
                        $row['postal_code'] === '' ? null : $row['postal_code'],
                        $row['address'] === '' ? null : $row['address'],
                        $row['latitude'],
                        $row['longitude'],
                        $row['website_url'],
                        $row['google_maps_url'],
                        $row['linked_venue_id'],
                        $id,
                    ]);
                    rendszer_log('helyszín', $id, 'Módosítva', $row['name']);
                    flash('success', 'Mentve.');
                    redirect(events_url('venue_szerkeszt.php?id=') . $id);
                } catch (Throwable $ex) {
                    error_log('events venue_szerkeszt mentesi hiba: ' . $ex->getMessage());
                    $hiba = 'Mentési hiba történt. Kérlek próbáld újra.';
                    $v = events_venue_row_for_form($row);
                }
            }
        }
    }
}

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Helyszín szerkesztése: ' . ($venue['name'] ?? '');
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="events-edit-page venue-edit-page">
    <header class="events-edit-header">
        <div>
            <h1 class="events-edit-title"><?= h((string) ($v['name'] !== '' ? $v['name'] : 'Helyszín szerkesztése')) ?></h1>
            <?php if ($venueEventCount > 0): ?>
                <p class="help muted events-edit-subtitle"><?= (int) $venueEventCount ?> eseményhez rendelve — törlés előtt válaszd le az eseményeken.</p>
            <?php endif; ?>
        </div>
        <div class="events-edit-header__actions">
            <a href="<?= h(events_url('venues.php')) ?>" class="btn btn-secondary">Vissza a listához</a>
        </div>
    </header>

    <?php if ($hiba): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>

    <form method="post" class="events-edit-form venue-edit-form" id="venue-edit-form">
        <?= csrf_input('venue_szerkeszt') ?>
        <?php require __DIR__ . '/partials/venue_fields.php'; ?>
    </form>
</div>

<?php require __DIR__ . '/partials/venue_map_picker.php'; ?>
<?php require __DIR__ . '/partials/venue_edit_map_preview.php'; ?>
<?php require __DIR__ . '/partials/html_editor_script.php'; ?>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
