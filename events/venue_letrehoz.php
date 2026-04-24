<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/nextgen/includes/auth.php';
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
    'linked_venue_id' => null,
];

$venuesLinkOptions = events_load_venue_options($db);

$hiba = '';
$v = events_venue_row_for_form($defaults);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$row, $err] = events_venue_row_from_post($db, $defaults, null);
    if ($err !== null) {
        $hiba = $err;
        $v = events_venue_row_for_form($row);
    } else {
        try {
            $ins = $db->prepare('
                INSERT INTO `events_venues` (`name`, `slug`, `description`, `country`, `city`, `postal_code`, `address`, `linked_venue_id`)
                VALUES (?,?,?,?,?,?,?,?)
            ');
            $ins->execute([
                $row['name'],
                $row['slug'],
                $row['description'] === '' ? null : $row['description'],
                $row['country'],
                $row['city'] === '' ? null : $row['city'],
                $row['postal_code'] === '' ? null : $row['postal_code'],
                $row['address'] === '' ? null : $row['address'],
                $row['linked_venue_id'],
            ]);
            $newId = (int) $db->lastInsertId();
            rendszer_log('helyszín', $newId, 'Létrehozva', $row['name']);
            flash('success', 'Helyszín létrehozva.');
            redirect(events_url('venue_szerkeszt.php?id=') . $newId);
        } catch (Throwable $ex) {
            $hiba = 'Mentési hiba: ' . $ex->getMessage();
            $v = events_venue_row_for_form($row);
        }
    }
}

$pageTitle = 'Új helyszín';
require_once dirname(__DIR__) . '/nextgen/partials/header.php';
?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>
<div class="card">
    <h2>Új helyszín</h2>
    <?php if ($hiba): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <form method="post">
        <?php require __DIR__ . '/partials/venue_fields.php'; ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Mentés</button>
            <a href="<?= h(events_url('venues.php')) ?>" class="btn btn-secondary">Mégse</a>
        </div>
    </form>
</div>
<?php require_once dirname(__DIR__) . '/nextgen/partials/footer.php'; ?>
