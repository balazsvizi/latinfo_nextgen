<?php
declare(strict_types=1);
/** @var array<string, mixed> $event */
/** @var array<string, string> $T */
/** @var string $lang */
if (!function_exists('events_event_change_active')) {
    require_once __DIR__ . '/../lib/event_change.php';
}
if (!events_event_change_active($event)) {
    return;
}
$changeHeading = events_event_change_public_heading($event, $T, $lang);
$changeNote = events_event_change_public_note($event);
$changeModifier = events_event_change_notice_css_modifier($event);
$changeIcon = events_event_change_notice_icon($event);
$changeBadge = events_event_change_calendar_badge_label($event, $lang);
if ($changeHeading === '' && $changeNote === '' && $changeBadge === '') {
    return;
}
?>
<aside
    class="event-change-notice event-change-notice--hero <?= h($changeModifier) ?>"
    role="note"
    aria-label="<?= h((string) ($T['change_notice_aria'] ?? 'Fontos tájékoztatás')) ?>"
>
    <div class="event-change-notice__inner">
        <span class="event-change-notice__icon" aria-hidden="true"><?= h($changeIcon) ?></span>
        <div class="event-change-notice__body">
            <?php if ($changeBadge !== ''): ?>
                <span class="event-change-notice__badge"><?= h($changeBadge) ?></span>
            <?php endif; ?>
            <?php if ($changeHeading !== ''): ?>
                <p class="event-change-notice__title"><?= h($changeHeading) ?></p>
            <?php endif; ?>
            <?php if ($changeNote !== ''): ?>
                <p class="event-change-notice__text"><?= nl2br(h($changeNote)) ?></p>
            <?php endif; ?>
        </div>
    </div>
</aside>
