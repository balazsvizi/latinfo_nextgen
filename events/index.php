<?php
declare(strict_types=1);

/**
 * /events/ gyökér — egyelőre nem publikus főoldal.
 * Felülírja a régi WordPress /events tartalmat (ha az events/ mappa fut).
 * A naptáras főoldal később: public_home.php
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/nextgen/includes/auth.php';
require_once __DIR__ . '/lib/legacy_wp_guard.php';

if (events_is_legacy_wp_events_request()) {
    events_handle_events_root_request();
}

events_handle_events_root_request();
