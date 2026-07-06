<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/venue_request.php';
requireLogin();

const EVENTS_VENUES_GEOCODE_ALL_SESSION = 'events_venues_geocode_all_run';
const EVENTS_VENUES_GEOCODE_BATCH_SIZE = 12;

$db = getDb();
$pendingTotal = events_venues_geocode_candidates_count($db);

/**
 * @return array{ok: int, fail: int, failed_ids: list<int>}|null
 */
function events_venues_geocode_all_session(): ?array
{
    $run = $_SESSION[EVENTS_VENUES_GEOCODE_ALL_SESSION] ?? null;
    if (!is_array($run)) {
        return null;
    }

    return [
        'ok' => (int) ($run['ok'] ?? 0),
        'fail' => (int) ($run['fail'] ?? 0),
        'failed_ids' => array_values(array_map('intval', $run['failed_ids'] ?? [])),
    ];
}

function events_venues_geocode_all_session_save(array $run): void
{
    $_SESSION[EVENTS_VENUES_GEOCODE_ALL_SESSION] = [
        'ok' => (int) ($run['ok'] ?? 0),
        'fail' => (int) ($run['fail'] ?? 0),
        'failed_ids' => array_values(array_map('intval', $run['failed_ids'] ?? [])),
    ];
}

function events_venues_geocode_all_session_clear(): void
{
    unset($_SESSION[EVENTS_VENUES_GEOCODE_ALL_SESSION]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('venues_geocode_all')) {
        flash('error', 'Érvénytelen kérés.');
        redirect(events_url('venues.php'));
    }

    if (isset($_POST['cancel'])) {
        events_venues_geocode_all_session_clear();
        flash('success', 'GPS tömeges geokódolás megszakítva.');
        redirect(events_url('venues.php'));
    }

    $run = events_venues_geocode_all_session();
    if ($run === null) {
        if ($pendingTotal === 0) {
            flash('success', 'Minden geokódolható helyszínnek már van GPS koordinátája.');
            redirect(events_url('venues.php'));
        }
        $run = ['ok' => 0, 'fail' => 0, 'failed_ids' => []];
    }

    $batch = events_venues_geocode_batch($db, EVENTS_VENUES_GEOCODE_BATCH_SIZE, $run['failed_ids']);
    $run['ok'] += $batch['ok'];
    $run['fail'] += $batch['fail'];
    $run['failed_ids'] = array_values(array_unique(array_merge($run['failed_ids'], $batch['failed_ids'])));

    $remaining = (int) $batch['remaining'];
    $stillPending = events_venues_fetch_geocode_candidates($db, 1, $run['failed_ids']);
    $done = $remaining === 0 || $stillPending === [];

    if ($done) {
        events_venues_geocode_all_session_clear();
        $msg = $run['ok'] . ' helyszín GPS koordinátája mentve cím alapján.';
        if ($run['fail'] > 0) {
            $msg .= ' ' . $run['fail'] . ' helyszínnél nem sikerült (cím pontosítása vagy kézi térkép szükséges).';
        }
        if ($remaining > 0) {
            $msg .= ' Még ' . $remaining . ' helyszín maradt geokódolható címmel, de ezeknél most nem volt találat.';
        }
        flash('success', $msg);
        redirect(events_url('venues.php'));
    }

    events_venues_geocode_all_session_save($run);
    $pageTitle = 'GPS geokódolás folyamatban…';
    $mainContentClass = 'main-content main-content--fullwidth';
    require_once dirname(__DIR__) . '/partials/header.php';
    ?>
    <div class="card events-admin-card venues-geocode-run">
        <h2 class="events-list-title">GPS geokódolás folyamatban</h2>
        <p class="help">A helyszínek cím alapján kerülnek feldolgozásra (OpenStreetMap Nominatim). Kérlek ne zárd be az oldalt – a folyamat automatikusan folytatódik.</p>
        <ul class="venues-geocode-run__stats">
            <li><strong><?= (int) $run['ok'] ?></strong> sikeres mentés</li>
            <li><strong><?= (int) $run['fail'] ?></strong> sikertelen</li>
            <li><strong><?= (int) $remaining ?></strong> hátralévő (GPS nélkül, címmel)</li>
        </ul>
        <p class="venues-geocode-run__hint">Következő batch: legfeljebb <?= (int) EVENTS_VENUES_GEOCODE_BATCH_SIZE ?> helyszín…</p>
        <form method="post" action="<?= h(events_url('venue_geocode_all.php')) ?>" id="venues-geocode-continue-form">
            <?= csrf_input('venues_geocode_all') ?>
            <input type="hidden" name="continue" value="1">
            <button type="submit" class="btn btn-primary">Folytatás</button>
            <button type="submit" name="cancel" value="1" class="btn btn-secondary">Megszakítás</button>
        </form>
    </div>
    <script>
    (function () {
        var form = document.getElementById('venues-geocode-continue-form');
        if (!form) return;
        setTimeout(function () {
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        }, 800);
    })();
    </script>
    <?php
    require_once dirname(__DIR__) . '/partials/footer.php';
    exit;
}

$pageTitle = 'GPS geokódolás – összes cím';
$mainContentClass = 'main-content main-content--fullwidth';
require_once dirname(__DIR__) . '/partials/header.php';
?>
<div class="card events-admin-card venues-geocode-run">
    <h2 class="events-list-title">GPS koordináták cím alapján</h2>
    <p class="help">Végigmegy az összes helyszínen, ahol van cím, de még nincs GPS koordináta. A találatok automatikusan mentésre kerülnek. A folyamat batch-ekben fut (Nominatim sebességkorlát miatt).</p>

    <?php if ($pendingTotal === 0): ?>
        <p class="alert alert-success">Minden geokódolható helyszínnek már van GPS koordinátája.</p>
        <p><a href="<?= h(events_url('venues.php')) ?>" class="btn btn-secondary">Vissza a helyszínekhez</a></p>
    <?php else: ?>
        <p><strong><?= (int) $pendingTotal ?></strong> helyszín vár geokódolásra (van cím, nincs GPS).</p>
        <p class="help">Becsült idő: kb. <?= (int) ceil($pendingTotal * 1.2 / 60) ?> perc (<?= (int) EVENTS_VENUES_GEOCODE_BATCH_SIZE ?> helyszín / batch).</p>
        <form method="post" action="<?= h(events_url('venue_geocode_all.php')) ?>" onsubmit="return confirm('Elindítod az összes hiányzó helyszín geokódolását? Ez <?= (int) $pendingTotal ?> címet dolgoz fel.');">
            <?= csrf_input('venues_geocode_all') ?>
            <button type="submit" class="btn btn-primary">Összes geokódolása indítása</button>
            <a href="<?= h(events_url('venues.php')) ?>" class="btn btn-secondary">Mégse</a>
        </form>
    <?php endif; ?>
</div>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
