<?php
declare(strict_types=1);
/** @var array<string, mixed> $ev */
if (!function_exists('events_event_change_active')) {
    require_once __DIR__ . '/../lib/event_change.php';
}
if (!events_event_change_active($ev)) {
    return;
}
$changeBadge = events_event_change_calendar_badge_label($ev, $calendarLang ?? 'hu');
if ($changeBadge === '') {
    return;
}
?>
<span class="events-cal__event-change-badge<?= events_event_change_calendar_badge_class($ev) ?>"><?= h($changeBadge) ?></span>
