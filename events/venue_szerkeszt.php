<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/nextgen/includes/auth.php';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                $hiba = 'Törlési hiba: ' . $ex->getMessage();
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
                        `country` = ?, `city` = ?, `postal_code` = ?, `address` = ?, `linked_venue_id` = ?
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
                    $row['linked_venue_id'],
                    $id,
                ]);
                rendszer_log('helyszín', $id, 'Módosítva', $row['name']);
                flash('success', 'Mentve.');
                redirect(events_url('venue_szerkeszt.php?id=') . $id);
            } catch (Throwable $ex) {
                $hiba = 'Mentési hiba: ' . $ex->getMessage();
                $v = events_venue_row_for_form($row);
            }
        }
    }
}

$pageTitle = 'Helyszín: ' . ($venue['name'] ?? '');
require_once dirname(__DIR__) . '/nextgen/partials/header.php';
?>
<?php if ($s = flash('success')): ?><p class="alert alert-success"><?= h($s) ?></p><?php endif; ?>
<?php if ($s = flash('error')): ?><p class="alert alert-error"><?= h($s) ?></p><?php endif; ?>
<div class="card">
    <h2>Helyszín szerkesztése</h2>
    <p class="help">Nyilvános oldal: <a href="<?= h(events_helyszin_megjelenit_url((string) ($v['slug'] ?? ''))) ?>" target="_blank" rel="noopener"><?= h(events_helyszin_megjelenit_url((string) ($v['slug'] ?? ''))) ?></a></p>
    <?php if ($hiba): ?><p class="alert alert-error"><?= h($hiba) ?></p><?php endif; ?>
    <?php if ($venueEventCount > 0): ?>
        <p class="help muted" style="margin-bottom:1rem;">A helyszín nem törölhető: <strong><?= (int) $venueEventCount ?></strong> eseményhez van rendelve. Előbb válaszd le a helyszínt az esemény szerkesztőben.</p>
    <?php endif; ?>
    <form method="post" class="venue-form venue-form--edit">
        <?php require __DIR__ . '/partials/venue_fields.php'; ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary" name="action" value="save">Mentés</button>
            <a href="<?= h(events_url('venues.php')) ?>" class="btn btn-secondary">Vissza a listához</a>
            <?php if ($venueEventCount === 0): ?>
                <button type="submit" class="btn btn-danger" style="margin-left:auto" name="action" value="delete" onclick="return confirm('Biztosan törlöd ezt a helyszínt?');">Törlés</button>
            <?php endif; ?>
        </div>
    </form>
</div>
<script>
(function () {
    var nameEl = document.getElementById('venue_name');
    var slugEl = document.getElementById('venue_slug');
    if (!nameEl || !slugEl) return;
    var ajaxPath = <?= json_encode(events_url('ajax_venue_unique_slug.php'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var excludeId = <?= (int) $id ?>;
    nameEl.addEventListener('blur', function () {
        if (slugEl.value.trim() !== '') return;
        var nm = nameEl.value.trim();
        if (nm === '') return;
        var u = new URL(ajaxPath, window.location.href);
        u.searchParams.set('name', nm);
        u.searchParams.set('exclude_id', String(excludeId));
        fetch(u.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (slugEl.value.trim() !== '') return;
                if (data && data.ok && typeof data.slug === 'string') slugEl.value = data.slug;
            })
            .catch(function () {});
    });
})();
</script>
<?php require_once dirname(__DIR__) . '/nextgen/partials/footer.php'; ?>
