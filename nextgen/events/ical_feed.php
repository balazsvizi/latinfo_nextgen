<?php
declare(strict_types=1);

ob_start();

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/event_public_lang.php';
require_once __DIR__ . '/lib/public_event_filters.php';
require_once __DIR__ . '/lib/ical_export.php';

$lang = events_public_resolve_megjelenit_lang();
$db = getDb();
$filters = events_public_filters_from_request($db);
$rows = events_public_fetch_filtered_events($db, $filters);

$outlook = isset($_GET['outlook']) && (string) $_GET['outlook'] === '1';
$download = isset($_GET['download']) && (string) $_GET['download'] === '1';

$ics = events_ical_build_calendar($rows, null, $lang, $outlook);

if (ob_get_length()) {
    ob_clean();
}

$filename = $outlook ? 'Latinfo.hu-outlook.ics' : 'Latinfo.hu.ics';
header('Content-Type: text/calendar; charset=utf-8');
if ($download) {
    header('Content-Disposition: attachment; filename="' . $filename . '"');
} else {
    header('Content-Disposition: inline; filename="' . $filename . '"');
}
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Robots-Tag: noindex');

echo $ics;
