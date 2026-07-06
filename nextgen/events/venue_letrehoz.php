<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/venue_request.php';
requireLogin();

$db = getDb();

$defaults = [
    'name' => '',
    'slug' => '',
    'description' => '',
    'country' => events_venue_default_country(),
    'city' => '',
    'postal_code' => '',
    'address' => '',
    'latitude' => null,
    'longitude' => null,
    'website_url' => null,
    'google_maps_url' => null,
    'linked_venue_id' => null,
];

$venuesLinkOptions = events_load_venue_options($db);

$hiba = '';
$v = events_venue_row_for_form($defaults);
$venueEditId = 0;
$venuePublicUrl = null;
$venueFormShowDelete = false;
$venueFormCancelUrl = events_url('venues.php');
$venueEventCount = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('venue_letrehoz')) {
        $hiba = 'Lejárt vagy érvénytelen munkamenet. Töltsd újra az oldalt.';
    } else {
            [$row, $err] = events_venue_row_from_post($db, $defaults, null);
            if ($err !== null) {
                $hiba = $err;
                $v = events_venue_row_for_form($row);
            } else {
                $coordsBefore = events_venue_coordinates_from_row([
                    'latitude' => $row['latitude'] ?? null,
                    'longitude' => $row['longitude'] ?? null,
                ]);
                $row = events_venue_apply_geocode_if_needed($row);
                $geocodedOnSave = $coordsBefore === null && events_venue_coordinates_from_row([
                    'latitude' => $row['latitude'] ?? null,
                    'longitude' => $row['longitude'] ?? null,
                ]) !== null;
                try {
                $ins = $db->prepare('
                    INSERT INTO `events_venues` (`name`, `slug`, `description`, `country`, `city`, `postal_code`, `address`, `latitude`, `longitude`, `website_url`, `google_maps_url`, `linked_venue_id`)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
                ');
                $ins->execute([
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
                ]);
                $newId = (int) $db->lastInsertId();
                rendszer_log('helyszín', $newId, 'Létrehozva', $row['name']);
                flash('success', $geocodedOnSave
                    ? 'Helyszín létrehozva. A GPS koordináta cím alapján került beállításra.'
                    : 'Helyszín létrehozva.');
                redirect(events_url('venue_szerkeszt.php?id=') . $newId);
            } catch (Throwable $ex) {
                error_log('events venue_letrehoz mentesi hiba: ' . $ex->getMessage());
                $hiba = 'Mentési hiba történt. Kérlek próbáld újra.';
                $v = events_venue_row_for_form($row);
            }
        }
    }
}

$mainContentClass = 'main-content main-content--fullwidth';
$pageTitle = 'Új helyszín';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>

<div class="events-edit-page venue-edit-page">
    <header class="events-edit-header">
        <div>
            <h1 class="events-edit-title">Új helyszín</h1>
            <p class="help muted events-edit-subtitle">Adj meg nevet és címet; mentéskor a GPS automatikusan kitöltődhet a cím alapján, vagy a térképen állíthatod be.</p>
        </div>
        <div class="events-edit-header__actions">
            <a href="<?= h(events_url('venues.php')) ?>" class="btn btn-secondary">Vissza a listához</a>
        </div>
    </header>

    <?php if ($hiba): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>

    <form method="post" class="events-edit-form venue-edit-form" id="venue-edit-form">
        <?= csrf_input('venue_letrehoz') ?>
        <?php require __DIR__ . '/partials/venue_fields.php'; ?>
    </form>
</div>

<?php require __DIR__ . '/partials/venue_map_picker.php'; ?>
<?php require __DIR__ . '/partials/venue_edit_map_preview.php'; ?>
<?php require __DIR__ . '/partials/html_editor_script.php'; ?>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
