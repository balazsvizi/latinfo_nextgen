<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/venue_request.php';
require_once __DIR__ . '/lib/venue_geocode_runner.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate('venues_geocode')) {
    flash('error', 'Érvénytelen kérés.');
    redirect(events_url('venues.php'));
}

$db = getDb();
$result = events_venues_geocode_run_batches($db, EVENTS_VENUE_GEOCODE_DEFAULT_BATCH, 1);

if ($result['ok'] === 0 && $result['fail'] === 0) {
    flash('success', 'Minden geokódolható helyszínnek már van GPS koordinátája.');
    redirect(events_url('venues.php'));
}

$remaining = (int) $result['remaining'];
if ($remaining > 0) {
    $msg = $result['ok'] . ' helyszín GPS koordinátája mentve.';
    if ($result['fail'] > 0) {
        $msg .= ' ' . $result['fail'] . ' helyszínnél nem sikerült a geokódolás.';
    }
    $msg .= ' Még ' . $remaining . ' helyszín vár – használd az „Összes geokódolása” vagy a cron scriptet.';
    flash('success', $msg);
} elseif ($result['fail'] > 0) {
    flash('error', $result['ok'] . ' helyszín frissítve, ' . $result['fail'] . ' helyszínnél nem sikerült a geokódolás.');
} else {
    flash('success', $result['ok'] . ' helyszín GPS koordinátája beállítva és mentve.');
}

redirect(events_url('venues.php'));
