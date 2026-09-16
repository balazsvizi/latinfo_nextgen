<?php
declare(strict_types=1);

/**
 * Egy nap eseménylistája a Latinfo kezdőoldal naptár oszlopában.
 *
 * @var string $heading
 * @var string $sectionId
 * @var list<array<string, mixed>> $events
 * @var bool $hasMore
 * @var string $empty
 * @var string $calendarUrl
 * @var string $moreLabel
 * @var string $allDayLabel
 */
$heading = (string) ($heading ?? '');
$sectionId = (string) ($sectionId ?? 'lh-day');
$events = is_array($events ?? null) ? $events : [];
$hasMore = !empty($hasMore);
$empty = (string) ($empty ?? '');
$calendarUrl = (string) ($calendarUrl ?? '#');
$moreLabel = (string) ($moreLabel ?? '');
$allDayLabel = (string) ($allDayLabel ?? 'Egész nap');
?>
<section class="latinfo-home__day" aria-labelledby="<?= h($sectionId) ?>-title">
    <h2 class="latinfo-home__day-title" id="<?= h($sectionId) ?>-title"><?= h($heading) ?></h2>
    <?php if ($events === []): ?>
        <p class="latinfo-home__day-empty"><?= h($empty) ?></p>
    <?php else: ?>
        <ul class="latinfo-home__events" role="list">
            <?php foreach ($events as $ev): ?>
                <?php
                $evUrl = latinfo_home_event_url($ev);
                $when = latinfo_home_format_event_time($ev, $allDayLabel);
                $place = latinfo_home_event_place($ev);
                ?>
                <li role="listitem">
                    <a class="latinfo-home__event" href="<?= h($evUrl) ?>">
                        <span class="latinfo-home__event-time"><?= h($when !== '' ? $when : '—') ?></span>
                        <span class="latinfo-home__event-body">
                            <span class="latinfo-home__event-name"><?= h((string) ($ev['event_name'] ?? '')) ?></span>
                            <?php if ($place !== ''): ?>
                                <span class="latinfo-home__event-place"><?= h($place) ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="latinfo-home__event-go" aria-hidden="true">→</span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($hasMore): ?>
            <a class="latinfo-home__day-more" href="<?= h($calendarUrl) ?>"><?= h($moreLabel) ?></a>
        <?php endif; ?>
    <?php endif; ?>
</section>
