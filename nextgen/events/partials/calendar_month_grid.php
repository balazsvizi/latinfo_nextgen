<?php
declare(strict_types=1);

if (!function_exists('events_public_calendar_event_url')) {
    require_once __DIR__ . '/../lib/public_event_calendar.php';
}

/**
 * @var string $monthLabel
 * @var list<string> $weekdayHeaders
 * @var list<array{
 *   days: list<array{date: DateTimeImmutable, inMonth: bool, isToday: bool, isPast: bool, key: string}>,
 *   laneCount: int,
 *   segments: list<array{event: array<string, mixed>, colStart: int, span: int, lane: int, roundLeft: bool, roundRight: bool, showTime: bool, isPast: bool}>,
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
            $singlesByDay = $week['singlesByDay'] ?? [];
            ?>
            <div class="events-cal__week" role="row" style="--cal-lane-count: <?= $laneCount ?>">
                <?php foreach ($week['days'] as $day): ?>
                    <?php
                    $dayKey = $day['key'];
                    $dayNum = (int) $day['date']->format('j');
                    $daySingles = $singlesByDay[$dayKey] ?? [];
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
                                    $eventStyle = events_admin_calendar_event_block_style($categoriesByEventId, $eid, $isPublished);
                                    $eventUrl = $calendarPublicPreview
                                        ? events_public_calendar_event_url($ev)
                                        : events_admin_calendar_event_public_url($ev);
                                    $linkClass = 'events-cal__event-link';
                                    if ($calendarPublicPreview) {
                                        $linkClass .= ' js-cal-event-preview';
                                    }
                                    if (!$isPublished) {
                                        $linkClass .= ' events-cal__event-link--unpublished';
                                    }
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
                                            <?php if ($timeLabel !== ''): ?>
                                                <span class="events-cal__event-time"><?= h($timeLabel) ?></span>
                                            <?php endif; ?>
                                            <span class="events-cal__event-name"><?= h((string) ($ev['event_name'] ?? '')) ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if ($laneCount > 0 && $segments !== []): ?>
                    <div class="events-cal__week-bars">
                        <?php foreach ($segments as $segment): ?>
                            <?php
                            $ev = $segment['event'];
                            $eid = (int) ($ev['id'] ?? 0);
                            $isPublished = events_admin_calendar_event_is_published($ev);
                            $eventStyle = events_admin_calendar_event_block_style($categoriesByEventId, $eid, $isPublished);
                            $eventUrl = $calendarPublicPreview
                                ? events_public_calendar_event_url($ev)
                                : events_admin_calendar_event_public_url($ev);
                            $timeLabel = $segment['showTime'] ? events_admin_calendar_event_time_label($ev) : '';
                            $barClasses = 'events-cal__event-link events-cal__event-link--bar';
                            if ($calendarPublicPreview) {
                                $barClasses .= ' js-cal-event-preview';
                            }
                            if (!$isPublished) {
                                $barClasses .= ' events-cal__event-link--unpublished';
                            }
                            if ($segment['isPast']) {
                                $barClasses .= ' events-cal__event-link--past';
                            }
                            if ($segment['roundLeft']) {
                                $barClasses .= ' events-cal__event-link--round-left';
                            }
                            if ($segment['roundRight']) {
                                $barClasses .= ' events-cal__event-link--round-right';
                            }
                            $eventStatus = (string) ($ev['event_status'] ?? '');
                            $statusBadgeClass = events_post_status_badge_class($eventStatus);
                            $statusLabel = events_post_status_label($eventStatus);
                            $barTarget = $calendarPublicPreview || $isPublished ? '_blank' : '_self';
                            $barRel = $barTarget === '_blank' ? 'noopener' : '';
                            $barTitle = (string) ($ev['event_name'] ?? '');
                            $colStart = (int) $segment['colStart'] + 1;
                            $colSpan = max(1, (int) $segment['span']);
                            $lane = (int) $segment['lane'];
                            ?>
                            <a
                                class="<?= h($barClasses) ?>"
                                style="<?= h($eventStyle) ?>;--cal-col-start:<?= $colStart ?>;--cal-span:<?= $colSpan ?>;--cal-lane:<?= $lane ?>"
                                href="<?= h($eventUrl) ?>"
                                <?= $calendarPublicPreview ? 'data-preview-id="' . $eid . '" aria-haspopup="dialog"' : '' ?>
                                target="<?= h($barTarget) ?>"
                                <?= $barRel !== '' ? 'rel="' . h($barRel) . '"' : '' ?>
                                title="<?= h($barTitle) ?><?= $isPublished ? '' : ' (' . $statusLabel . ')' ?>"
                            >
                                <?php if (!$isPublished && !$calendarPublicPreview): ?>
                                    <span class="events-cal__event-status event-status-badge <?= h($statusBadgeClass) ?>"><?= h($statusLabel) ?></span>
                                <?php endif; ?>
                                <?php if ($timeLabel !== ''): ?>
                                    <span class="events-cal__event-time"><?= h($timeLabel) ?></span>
                                <?php endif; ?>
                                <span class="events-cal__event-name"><?= h($barTitle) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
