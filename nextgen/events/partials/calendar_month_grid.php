<?php
declare(strict_types=1);

if (!function_exists('events_public_calendar_event_url')) {
    require_once __DIR__ . '/../lib/public_event_calendar.php';
}
if (!defined('EVENTS_VIEW_SOURCE_CALENDAR')) {
    require_once __DIR__ . '/../lib/event_view_tracking.php';
}

/**
 * @var string $monthLabel
 * @var list<string> $weekdayHeaders
 * @var list<array{
 *   days: list<array{date: DateTimeImmutable, inMonth: bool, isToday: bool, isPast: bool, key: string}>,
 *   laneCount: int,
 *   segments: list<array{event: array<string, mixed>, colStart: int, span: int, lane: int, roundLeft: bool, roundRight: bool, showTime: bool, isPast: bool}>,
 *   partsByColLane: array<int, array<int, array{event: array<string, mixed>, lane: int, connectLeft: bool, connectRight: bool, roundLeft: bool, roundRight: bool, showTime: bool, showLabel: bool, labelSpan: int, isPast: bool}>>,
 *   singlesByDay: array<string, list<array<string, mixed>>>
 * }> $calendarWeeks
 * @var array<int, list<array{color: string}>> $categoriesByEventId
 * @var bool $calendarPublicPreview
 * @var array<string, string> $D
 */
$calendarPublicPreview = $calendarPublicPreview ?? false;
$gridAria = (string) ($D['calendar_grid_aria'] ?? $monthLabel);
?>
<div class="events-cal" role="grid" aria-label="<?= h($gridAria) ?>">
    <div class="events-cal__weekdays" role="row">
        <?php foreach ($weekdayHeaders as $wd): ?>
            <div class="events-cal__weekday" role="columnheader" title="<?= h($wd) ?>"><?= h($wd) ?></div>
        <?php endforeach; ?>
    </div>
    <div class="events-cal__body">
        <?php foreach ($calendarWeeks as $week): ?>
            <?php
            $laneCount = (int) ($week['laneCount'] ?? 0);
            $segments = $week['segments'] ?? [];
            $partsByColLane = $week['partsByColLane'] ?? [];
            $singlesByDay = $week['singlesByDay'] ?? [];
            ?>
            <div class="events-cal__week" role="row" style="--cal-lane-count: <?= $laneCount ?>">
                <?php foreach ($week['days'] as $colIndex => $day): ?>
                    <?php
                    $dayKey = $day['key'];
                    $dayNum = (int) $day['date']->format('j');
                    $daySingles = $singlesByDay[$dayKey] ?? [];
                    $dayParts = $partsByColLane[$colIndex] ?? [];
                    $dayClasses = 'events-cal__day';
                    if (!$day['inMonth']) {
                        $dayClasses .= ' events-cal__day--outside';
                    }
                    if ($day['isToday']) {
                        $dayClasses .= ' events-cal__day--today';
                    }
                    if (!empty($day['isPast'])) {
                        $dayClasses .= ' events-cal__day--past';
                    }
                    ?>
                    <div class="<?= h($dayClasses) ?>" role="gridcell" aria-label="<?= h($day['date']->format('Y. m. d.')) ?>">
                        <div class="events-cal__day-num"><?= $dayNum ?></div>
                        <?php if ($daySingles !== []): ?>
                            <ul class="events-cal__events" role="list">
                                <?php foreach ($daySingles as $ev): ?>
                                    <?php
                                    $eid = (int) ($ev['id'] ?? 0);
                                    $isPublished = events_admin_calendar_event_is_published($ev);
                                    $timeLabel = events_admin_calendar_event_time_label($ev);
                                    $eventStyle = events_admin_calendar_event_block_style_for_event($categoriesByEventId, $ev, $isPublished);
                                    $eventUrl = $calendarPublicPreview
                                        ? events_public_calendar_event_url($ev, EVENTS_VIEW_SOURCE_CALENDAR)
                                        : events_admin_calendar_event_public_url($ev);
                                    $linkClass = 'events-cal__event-link';
                                    if ($calendarPublicPreview) {
                                        $linkClass .= ' js-cal-event-preview';
                                    }
                                    if (!$isPublished) {
                                        $linkClass .= ' events-cal__event-link--unpublished';
                                    }
                                    $linkClass .= events_event_change_calendar_link_class($ev);
                                    $eventNameClass = 'events-cal__event-name' . events_event_change_event_name_class($ev);
                                    $eventStatus = (string) ($ev['event_status'] ?? '');
                                    $statusBadgeClass = events_post_status_badge_class($eventStatus);
                                    $statusLabel = events_post_status_label($eventStatus);
                                    ?>
                                    <li class="events-cal__event<?= $isPublished ? '' : ' events-cal__event--unpublished' ?>" role="listitem">
                                        <a
                                            class="<?= h($linkClass) ?>"
                                            style="<?= h($eventStyle) ?>"
                                            href="<?= h($eventUrl) ?>"
                                            <?= $calendarPublicPreview ? 'data-preview-id="' . $eid . '" aria-haspopup="dialog"' : '' ?>
                                            <?= $calendarPublicPreview || $isPublished ? 'target="_blank" rel="noopener"' : 'target="_self"' ?>
                                            title="<?= h((string) ($ev['event_name'] ?? '')) ?><?= $isPublished ? '' : ' (' . $statusLabel . ')' ?>"
                                        >
                                            <?php if (!$isPublished && !$calendarPublicPreview): ?>
                                                <span class="events-cal__event-status event-status-badge <?= h($statusBadgeClass) ?>"><?= h($statusLabel) ?></span>
                                            <?php endif; ?>
                                            <?php require __DIR__ . '/calendar_event_change_badge.php'; ?>
                                            <?php if ($timeLabel !== ''): ?>
                                                <span class="events-cal__event-time"><?= h($timeLabel) ?></span>
                                            <?php endif; ?>
                                            <span class="<?= h($eventNameClass) ?>"><?= h((string) ($ev['event_name'] ?? '')) ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if ($laneCount > 0): ?>
                            <div class="events-cal__multiday-lanes">
                                <?php for ($lane = 0; $lane < $laneCount; $lane++): ?>
                                    <?php $part = $dayParts[$lane] ?? null; ?>
                                    <div class="events-cal__multiday-lane">
                                        <?php if ($part !== null): ?>
                                            <?php
                                            $ev = $part['event'];
                                            $eid = (int) ($ev['id'] ?? 0);
                                            $isPublished = events_admin_calendar_event_is_published($ev);
                                            $eventStyle = events_admin_calendar_event_block_style_for_event($categoriesByEventId, $ev, $isPublished);
                                            $eventUrl = $calendarPublicPreview
                                                ? events_public_calendar_event_url($ev, EVENTS_VIEW_SOURCE_CALENDAR)
                                                : events_admin_calendar_event_public_url($ev);
                                            $timeLabel = $part['showTime'] ? events_admin_calendar_event_time_label($ev) : '';
                                            $barTitle = (string) ($ev['event_name'] ?? '');
                                            $barClasses = 'events-cal__event-link events-cal__event-link--bar';
                                            if ($calendarPublicPreview) {
                                                $barClasses .= ' js-cal-event-preview';
                                            }
                                            if (!$isPublished) {
                                                $barClasses .= ' events-cal__event-link--unpublished';
                                            }
                                            $barClasses .= events_event_change_calendar_link_class($ev);
                                            if ($part['isPast']) {
                                                $barClasses .= ' events-cal__event-link--past';
                                            }
                                            if ($part['roundLeft']) {
                                                $barClasses .= ' events-cal__event-link--round-left';
                                            }
                                            if ($part['roundRight']) {
                                                $barClasses .= ' events-cal__event-link--round-right';
                                            }
                                            if ($part['connectLeft']) {
                                                $barClasses .= ' events-cal__event-link--bar-connect-left';
                                            }
                                            if ($part['connectRight']) {
                                                $barClasses .= ' events-cal__event-link--bar-connect-right';
                                            }
                                            $eventStatus = (string) ($ev['event_status'] ?? '');
                                            $statusBadgeClass = events_post_status_badge_class($eventStatus);
                                            $statusLabel = events_post_status_label($eventStatus);
                                            $barTarget = $calendarPublicPreview || $isPublished ? '_blank' : '_self';
                                            $barRel = $barTarget === '_blank' ? 'noopener' : '';
                                            ?>
                                            <a
                                                class="<?= h($barClasses) ?>"
                                                style="<?= h($eventStyle) ?>"
                                                href="<?= h($eventUrl) ?>"
                                                <?= $calendarPublicPreview ? 'data-preview-id="' . $eid . '" aria-haspopup="dialog"' : '' ?>
                                                target="<?= h($barTarget) ?>"
                                                <?= $barRel !== '' ? 'rel="' . h($barRel) . '"' : '' ?>
                                                title="<?= h($barTitle) ?><?= $isPublished ? '' : ' (' . $statusLabel . ')' ?>"
                                                aria-label="<?= h($barTitle) ?>"
                                            ></a>
                                        <?php endif; ?>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if ($laneCount > 0 && $segments !== []): ?>
                    <div class="events-cal__week-labels" aria-hidden="true">
                        <?php foreach ($segments as $segment): ?>
                            <?php
                            $ev = $segment['event'];
                            $eid = (int) ($ev['id'] ?? 0);
                            $isPublished = events_admin_calendar_event_is_published($ev);
                            $eventStyle = events_admin_calendar_event_block_style_for_event($categoriesByEventId, $ev, $isPublished);
                            $barTitle = (string) ($ev['event_name'] ?? '');
                            $timeLabel = $segment['showTime'] ? events_admin_calendar_event_time_label($ev) : '';
                            $labelClasses = 'events-cal__week-bar-label';
                            $labelClasses .= events_event_change_calendar_link_class($ev);
                            $barEventNameClass = 'events-cal__event-name' . events_event_change_event_name_class($ev);
                            if ($segment['roundLeft']) {
                                $labelClasses .= ' events-cal__week-bar-label--round-left';
                            }
                            if ($segment['roundRight']) {
                                $labelClasses .= ' events-cal__week-bar-label--round-right';
                            }
                            if ($segment['isPast']) {
                                $labelClasses .= ' events-cal__week-bar-label--past';
                            }
                            $colStart = (int) $segment['colStart'] + 1;
                            $colSpan = max(1, (int) $segment['span']);
                            $lane = (int) $segment['lane'];
                            $eventStatus = (string) ($ev['event_status'] ?? '');
                            $statusBadgeClass = events_post_status_badge_class($eventStatus);
                            $statusLabel = events_post_status_label($eventStatus);
                            ?>
                            <span
                                class="<?= h($labelClasses) ?>"
                                style="<?= h($eventStyle) ?>;--cal-col-start:<?= $colStart ?>;--cal-span:<?= $colSpan ?>;--cal-lane:<?= $lane ?>"
                            >
                                <?php if (!$isPublished && !$calendarPublicPreview): ?>
                                    <span class="events-cal__event-status event-status-badge <?= h($statusBadgeClass) ?>"><?= h($statusLabel) ?></span>
                                <?php endif; ?>
                                <?php require __DIR__ . '/calendar_event_change_badge.php'; ?>
                                <?php if ($timeLabel !== ''): ?>
                                    <span class="events-cal__event-time"><?= h($timeLabel) ?></span>
                                <?php endif; ?>
                                <span class="<?= h($barEventNameClass) ?>"><?= h($barTitle) ?></span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
