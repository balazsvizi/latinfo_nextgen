<?php
declare(strict_types=1);
/** @var list<array<string, mixed>> $rows */
/** @var array<int, list<array{color: string, name: string}>> $categoriesByEventId */
/** @var array<string, string> $D */
?>
<?php if ($rows === []): ?>
    <p class="home-public__empty"><?= h((string) ($D['list_empty'] ?? 'Nincs találat a szűrésre.')) ?></p>
<?php else: ?>
    <ul class="home-public__list" role="list">
        <?php foreach ($rows as $ev): ?>
            <?php
            $eid = (int) ($ev['id'] ?? 0);
            $eventUrl = events_public_calendar_event_url($ev);
            $dateLabel = events_admin_format_datum_cell($ev);
            $venueName = trim((string) ($ev['venue_name'] ?? ''));
            $venueCity = trim((string) ($ev['venue_city'] ?? ''));
            $venueLine = $venueName;
            if ($venueCity !== '') {
                $venueLine = $venueLine !== '' ? $venueLine . ', ' . $venueCity : $venueCity;
            }
            $cats = $categoriesByEventId[$eid] ?? [];
            $accent = $cats !== [] ? trim((string) ($cats[0]['color'] ?? '#6d8f63')) : '#6d8f63';
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $accent)) {
                $accent = '#6d8f63';
            }
            ?>
            <li class="home-public__list-item" role="listitem">
                <a class="home-public__list-card" href="<?= h($eventUrl) ?>" style="--home-event-accent: <?= h($accent) ?>">
                    <span class="home-public__list-date"><?= h($dateLabel) ?></span>
                    <span class="home-public__list-name"><?= h((string) ($ev['event_name'] ?? '')) ?></span>
                    <?php if ($venueLine !== ''): ?>
                        <span class="home-public__list-venue"><?= h($venueLine) ?></span>
                    <?php endif; ?>
                    <?php if ($cats !== []): ?>
                        <span class="home-public__list-cats">
                            <?php foreach ($cats as $cat): ?>
                                <span class="home-public__list-cat"><?= h((string) $cat['name']) ?></span>
                            <?php endforeach; ?>
                        </span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
