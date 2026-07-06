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
$batchSize = 12;
$candidates = events_venues_fetch_geocode_candidates($db, $batchSize);
if ($candidates === []) {
    flash('success', 'Minden geokódolható helyszínnek már van GPS koordinátája.');
    redirect(events_url('venues.php'));
}

$ok = 0;
$fail = 0;
foreach ($candidates as $candidate) {
    $venueId = (int) ($candidate['id'] ?? 0);
    if ($venueId <= 0) {
        continue;
    }
    if (events_venue_geocode_and_save($db, $venueId)) {
        $ok++;
        rendszer_log('helyszín', $venueId, 'GPS geokódolás', (string) ($candidate['name'] ?? ''));
    } else {
        $fail++;
    }
}

$remaining = events_venues_geocode_candidates_count($db);
if ($remaining > 0) {
    $msg = $ok . ' helyszín GPS koordinátája mentve.';
    if ($fail > 0) {
        $msg .= ' ' . $fail . ' helyszínnél nem sikerült a geokódolás.';
    }
    $msg .= ' Még ' . $remaining . ' helyszín vár feldolgozásra – futtasd újra a gombot.';
    flash('success', $msg);
} elseif ($fail > 0) {
    flash('error', $ok . ' helyszín frissítve, ' . $fail . ' helyszínnél nem sikerült a geokódolás.');
} else {
    flash('success', $ok . ' helyszín GPS koordinátája beállítva és mentve.');
}

redirect(events_url('venues.php'));
