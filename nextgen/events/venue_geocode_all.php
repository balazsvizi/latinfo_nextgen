<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/venue_request.php';
require_once __DIR__ . '/lib/venue_geocode_runner.php';
require_once dirname(__DIR__) . '/lib/cron/CronAuth.php';
requireLogin();

const EVENTS_VENUES_GEOCODE_ALL_SESSION = 'events_venues_geocode_all_run';
const EVENTS_VENUES_GEOCODE_BATCH_SIZE = EVENTS_VENUE_GEOCODE_DEFAULT_BATCH;

$db = getDb();
$pendingTotal = events_venues_geocode_candidates_count($db);
$cronTokenConfigured = cron_token_from_config() !== '';
$cronUrl = nextgen_url('cron/run.php');
$cronLogUrl = nextgen_url('admin/cron_log.php');

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

    $batchResult = events_venues_geocode_run_batches(
        $db,
        EVENTS_VENUES_GEOCODE_BATCH_SIZE,
        1,
        $run['failed_ids']
    );
    $run['ok'] += $batchResult['ok'];
    $run['fail'] += $batchResult['fail'];
    $run['failed_ids'] = $batchResult['failed_ids'];
    $remaining = (int) $batchResult['remaining'];
    $done = (bool) $batchResult['done'];

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
        <p class="help">A helyszínek cím alapján kerülnek feldolgozásra (OpenStreetMap Nominatim), <?= (int) EVENTS_VENUES_GEOCODE_BATCH_SIZE ?>-es batch-ekben. Kérlek ne zárd be az oldalt – a folyamat automatikusan folytatódik.</p>
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
    <p class="help">Végigmegy az összes helyszínen, ahol van cím, de még nincs GPS koordináta. A találatok automatikusan mentésre kerülnek. A folyamat <?= (int) EVENTS_VENUES_GEOCODE_BATCH_SIZE ?>-es batch-ekben fut (Nominatim sebességkorlát miatt).</p>

    <?php if ($pendingTotal === 0): ?>
        <p class="alert alert-success">Minden geokódolható helyszínnek már van GPS koordinátája.</p>
        <p><a href="<?= h(events_url('venues.php')) ?>" class="btn btn-secondary">Vissza a helyszínekhez</a></p>
    <?php else: ?>
        <p><strong><?= (int) $pendingTotal ?></strong> helyszín vár geokódolásra (van cím, nincs GPS).</p>
        <p class="help">Becsült idő (böngészős futás): kb. <?= (int) ceil($pendingTotal * 1.2 / 60) ?> perc.</p>
        <form method="post" action="<?= h(events_url('venue_geocode_all.php')) ?>" onsubmit="return confirm('Elindítod az összes hiányzó helyszín geokódolását? Ez <?= (int) $pendingTotal ?> címet dolgoz fel, <?= (int) EVENTS_VENUES_GEOCODE_BATCH_SIZE ?>-esével.');">
            <?= csrf_input('venues_geocode_all') ?>
            <button type="submit" class="btn btn-primary">Összes geokódolása indítása (böngésző)</button>
            <a href="<?= h(events_url('venues.php')) ?>" class="btn btn-secondary">Mégse</a>
        </form>
    <?php endif; ?>

    <hr class="venues-geocode-run__sep">

    <h3 class="venues-geocode-run__subtitle">Cron / CLI (ütemezett futtatás)</h3>
    <p class="help">Központi cron futtató: egy hívás alapból <strong><?= (int) EVENTS_VENUES_GEOCODE_BATCH_SIZE ?> helyszínt</strong> dolgoz fel (5 percenként esedékes). Ismétlődő system cronnal végigmegy a teljes populáción.</p>

    <pre class="venues-geocode-run__code">php nextgen/cron/run.php --task=venue_geocode --force</pre>
    <p class="help">Teljes populáció egy CLI futásban (12-es batch-ekben):</p>
    <pre class="venues-geocode-run__code">php nextgen/cron/run.php --task=venue_geocode --force --all</pre>

    <?php if ($cronTokenConfigured): ?>
        <p class="help">HTTP cron példa (token a <code>config.local.php</code> <code>CRON_TOKEN</code> értéke):</p>
        <pre class="venues-geocode-run__code">curl -sf "<?= h($cronUrl) ?>?token=…&amp;task=venue_geocode&amp;force=1"</pre>
    <?php else: ?>
        <p class="help muted">HTTP cronhoz állíts be <code>CRON_TOKEN</code> értéket a <code>config.local.php</code>-ben (min. 32 karakter ajánlott).</p>
    <?php endif; ?>

    <p class="help">Példa crontab (percenként ellenőr, a feladat 5 percenként fut):</p>
    <pre class="venues-geocode-run__code">* * * * * cd /path/to/Alatinfo &amp;&amp; php nextgen/cron/run.php >> nextgen/data/cron/runner.out.log 2>&1</pre>
    <p class="help">Futás napló: <a href="<?= h($cronLogUrl) ?>">Admin → Cron log</a> (<code>nextgen/data/cron/cron.log</code>)</p>
</div>
<?php require_once dirname(__DIR__) . '/partials/footer.php'; ?>
