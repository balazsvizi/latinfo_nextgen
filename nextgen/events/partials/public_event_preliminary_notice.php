<?php
declare(strict_types=1);

/**
 * Előzetes esemény tájékoztató a nyilvános eseményoldalon.
 *
 * @var array<string, mixed> $event
 * @var array<string, string> $T
 */

if (!events_is_preliminary_post_status((string) ($event['event_status'] ?? ''))) {
    return;
}
$notice = (string) ($T['preliminary_notice'] ?? 'Ez még csak egy előzetes információ. A részletek később lesznek elérhetők erről az eseményről.');
?>
<p class="event-preliminary-notice" role="note"><?= h($notice) ?></p>
