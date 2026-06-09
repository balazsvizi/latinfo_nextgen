<?php
declare(strict_types=1);
/** @var string $monthLabel */
/** @var list<string> $weekdayHeaders */
/** @var list<array<string, mixed>> $calendarWeeks */
/** @var list<array<string, mixed>> $undated */
/** @var array<int, list<array{color: string}>> $categoriesByEventId */
/** @var array<string, string> $D */
$calendarPublicPreview = true;
$D['calendar_grid_aria'] = $monthLabel;
require __DIR__ . '/calendar_month_grid.php';
?>

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
                    <a class="events-cal-undated__link events-cal__event-link js-cal-event-preview" style="<?= h($eventStyle) ?>" href="<?= h($eventUrl) ?>" data-preview-id="<?= $eid ?>" aria-haspopup="dialog">
                        <span class="events-cal__event-name"><?= h((string) ($ev['event_name'] ?? '')) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
