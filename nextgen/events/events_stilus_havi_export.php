<?php
declare(strict_types=1);

/**
 * Főstílus havi statisztika Excel-exportja (hónapok sorokban, stílusok oszlopokban).
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once __DIR__ . '/lib/event_style_monthly_stats.php';
requireLogin();

$db = getDb();
$statsParams = events_style_monthly_stats_params_from_request($_GET);
$matrix = events_style_monthly_stats_all_months($db, $statsParams);
events_style_monthly_stats_send_excel($matrix, $statsParams);
exit;
