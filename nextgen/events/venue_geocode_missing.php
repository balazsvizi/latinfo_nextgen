<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/venue_request.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate('venues_geocode')) {
    flash('error', 'Érvénytelen kérés.');
    redirect(events_url('venues.php'));
}

$db = getDb();
$batch = events_venues_geocode_batch($db, 12);

if ($batch['ok'] === 0 && $batch['fail'] === 0) {
    flash('success', 'Minden geokódolható helyszínnek már van GPS koordinátája.');
    redirect(events_url('venues.php'));
}

$remaining = (int) $batch['remaining'];
if ($remaining > 0) {
    $msg = $batch['ok'] . ' helyszín GPS koordinátája mentve.';
    if ($batch['fail'] > 0) {
        $msg .= ' ' . $batch['fail'] . ' helyszínnél nem sikerült a geokódolás.';
    }
    $msg .= ' Még ' . $remaining . ' helyszín vár – használd az „Összes geokódolása” funkciót, vagy futtasd újra.';
    flash('success', $msg);
} elseif ($batch['fail'] > 0) {
    flash('error', $batch['ok'] . ' helyszín frissítve, ' . $batch['fail'] . ' helyszínnél nem sikerült a geokódolás.');
} else {
    flash('success', $batch['ok'] . ' helyszín GPS koordinátája beállítva és mentve.');
}

redirect(events_url('venues.php'));
