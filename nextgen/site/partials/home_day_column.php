<?php
declare(strict_types=1);

/**
 * Egy nap eseménylistája a Latinfo kezdőoldal naptár oszlopában.
 *
 * @var string $dayWord Nap megnevezése (Ma / Holnap)
 * @var string $dayDate Dátum (pl. szeptember 16. · szerda)
 * @var string $sectionId
 * @var list<array<string, mixed>> $events
 * @var bool $hasMore
 * @var string $empty
 * @var string $calendarUrl
 * @var string $moreLabel
 * @var string $lang
 * @var array<int, list<array{color?: string}>> $categoriesByEventId
 */
$dayWord = trim((string) ($dayWord ?? ''));
$dayDate = trim((string) ($dayDate ?? ''));
$sectionId = (string) ($sectionId ?? 'lh-day');
$events = is_array($events ?? null) ? $events : [];
$hasMore = !empty($hasMore);
$empty = (string) ($empty ?? '');
$calendarUrl = (string) ($calendarUrl ?? '#');
$moreLabel = (string) ($moreLabel ?? '');
$categoriesByEventId = is_array($categoriesByEventId ?? null) ? $categoriesByEventId : [];
?>
<section class="latinfo-home__day" aria-labelledby="<?= h($sectionId) ?>-title">
    <header class="latinfo-home__day-head">
        <h2 class="latinfo-home__day-title" id="<?= h($sectionId) ?>-title">
            <span class="latinfo-home__day-word"><?= h($dayWord) ?></span>
            <?php if ($dayDate !== ''): ?>
                <span class="latinfo-home__day-date"><?= h($dayDate) ?></span>
            <?php endif; ?>
        </h2>
        <?php if ($events !== []): ?>
            <span class="latinfo-home__day-count"><?= count($events) ?></span>
        <?php endif; ?>
    </header>
    <?php if ($events === []): ?>
        <p class="latinfo-home__day-empty"><?= h($empty) ?></p>
    <?php else: ?>
        <ul class="latinfo-home__events" role="list">
            <?php foreach ($events as $ev): ?>
                <?php
                if (!function_exists('events_event_change_active')) {
                    require_once dirname(__DIR__, 2) . '/events/lib/event_change.php';
                }
                $evUrl = latinfo_home_event_url($ev);
                $place = latinfo_home_event_place_parts($ev);
                $accent = latinfo_home_event_accent($ev, $categoriesByEventId);
                $changeLang = $lang ?? 'hu';
                $hasChange = events_event_change_active($ev);
                $eventClass = 'latinfo-home__event';
                $nameClass = 'latinfo-home__event-name';
                $changeBadge = '';
                $changeNote = '';
                if ($hasChange) {
                    $changeType = events_event_change_type($ev);
                    if ($changeType === events_event_change_type_cancelled()) {
                        $eventClass .= ' latinfo-home__event--change-cancelled';
                        $nameClass .= ' latinfo-home__event-name--cancelled';
                    } elseif ($changeType === events_event_change_type_modified()) {
                        $eventClass .= ' latinfo-home__event--change-modified';
                    }
                    $changeStyle = events_event_change_calendar_block_style($ev);
                    if (preg_match('/--events-cal-accent:([^;]+)/', $changeStyle, $accentMatch) === 1) {
                        $accent = trim($accentMatch[1]);
                    }
                    $changeBadge = events_event_change_calendar_badge_label($ev, $changeLang);
                    $changeNote = events_event_change_public_note_excerpt($ev, 90);
                }
                ?>
                <li role="listitem">
                    <a class="<?= h($eventClass) ?>" href="<?= h($evUrl) ?>" style="--home-event-accent: <?= h($accent) ?>">
                        <span class="latinfo-home__event-stripe" aria-hidden="true"></span>
                        <span class="latinfo-home__event-body">
                            <?php if ($changeBadge !== ''): ?>
                                <span class="latinfo-home__event-change"><?= h($changeBadge) ?></span>
                            <?php endif; ?>
                            <span class="<?= h($nameClass) ?>"><?= h((string) ($ev['event_name'] ?? '')) ?></span>
                            <?php if ($place['venue'] !== '' || ($place['outside_budapest'] && $place['city'] !== '')): ?>
                                <span class="latinfo-home__event-meta">
                                    <?php if ($place['venue'] !== ''): ?>
                                        <span class="latinfo-home__event-place"><?= h($place['venue']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($place['outside_budapest'] && $place['city'] !== ''): ?>
                                        <span class="latinfo-home__event-city"><?= h($place['city']) ?></span>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($changeNote !== ''): ?>
                                <span class="latinfo-home__event-change-note"><?= h($changeNote) ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="latinfo-home__event-go" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h13M12 5l7 7-7 7"/></svg>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($hasMore): ?>
            <a class="latinfo-home__day-more" href="<?= h($calendarUrl) ?>">
                <span><?= h($moreLabel) ?></span>
                <span class="latinfo-home__day-more-arrow" aria-hidden="true">→</span>
            </a>
        <?php endif; ?>
    <?php endif; ?>
</section>
