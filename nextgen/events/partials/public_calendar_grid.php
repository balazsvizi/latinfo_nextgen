<?php
declare(strict_types=1);
/** @var string $monthLabel */
/** @var list<string> $weekdayHeaders */
/** @var list<array{date: DateTimeImmutable, inMonth: bool, isToday: bool, isPast: bool, key: string}> $gridDays */
/** @var array<string, list<array<string, mixed>>> $byDay */
/** @var list<array<string, mixed>> $undated */
/** @var array<int, list<array{color: string}>> $categoriesByEventId */
/** @var array<string, string> $D */
?>
<div class="events-cal" role="grid" aria-label="<?= h($monthLabel) ?>">
    <div class="events-cal__weekdays" role="row">
        <?php foreach ($weekdayHeaders as $wd): ?>
            <div class="events-cal__weekday" role="columnheader" title="<?= h($wd) ?>"><?= h($wd) ?></div>
        <?php endforeach; ?>
    </div>
    <div class="events-cal__body">
        <?php foreach (array_chunk($gridDays, 7) as $week): ?>
            <div class="events-cal__week" role="row">
                <?php foreach ($week as $day): ?>
                    <?php
                    $dayKey = $day['key'];
                    $dayNum = (int) $day['date']->format('j');
                    $dayEvents = $byDay[$dayKey] ?? [];
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
                        <?php if ($dayEvents !== []): ?>
                            <ul class="events-cal__events" role="list">
                                <?php foreach ($dayEvents as $ev): ?>
                                    <?php
                                    $eid = (int) ($ev['id'] ?? 0);
                                    $timeLabel = events_admin_calendar_event_time_label($ev);
                                    $eventStyle = events_admin_calendar_event_block_style($categoriesByEventId, $eid, true);
                                    $eventUrl = events_public_calendar_event_url($ev);
                                    ?>
                                    <li class="events-cal__event" role="listitem">
                                        <a
                                            class="events-cal__event-link"
                                            style="<?= h($eventStyle) ?>"
                                            href="<?= h($eventUrl) ?>"
                                            title="<?= h((string) ($ev['event_name'] ?? '')) ?>"
                                        >
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
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($undated !== []): ?>
    <section class="events-cal-undated" aria-label="<?= h((string) ($D['undated_aria'] ?? 'Dátum nélküli események')) ?>">
        <h3 class="events-cal-undated__title"><?= h((string) ($D['undated_title'] ?? 'Dátum nélküli események')) ?> (<?= count($undated) ?>)</h3>
        <ul class="events-cal-undated__list" role="list">
            <?php foreach ($undated as $ev): ?>
                <?php
                $eid = (int) ($ev['id'] ?? 0);
                $eventStyle = events_admin_calendar_event_block_style($categoriesByEventId, $eid, true);
                $eventUrl = events_public_calendar_event_url($ev);
                ?>
                <li role="listitem">
                    <a class="events-cal-undated__link events-cal__event-link" style="<?= h($eventStyle) ?>" href="<?= h($eventUrl) ?>">
                        <span class="events-cal__event-name"><?= h((string) ($ev['event_name'] ?? '')) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
