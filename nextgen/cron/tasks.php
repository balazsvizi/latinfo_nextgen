<?php
declare(strict_types=1);

/**
 * Regisztrált cron feladatok. Új feladathoz: implementáld a CronTaskInterface-t, add hozzá ide.
 */
require_once dirname(__DIR__) . '/lib/cron/tasks/VenueGeocodeTask.php';

return [
    new VenueGeocodeTask(),
];
