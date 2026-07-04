<?php
declare(strict_types=1);

/**
 * Aktuális + lezajlott eseményrács (szervező, címke, helyszín).
 *
 * @var array<string, string> $PEszövegek events_heading, section_upcoming, section_past, upcoming_empty, past_empty, list_empty
 * @var list<array<string, mixed>> $eventsList
 * @var list<array<string, mixed>> $eventsUpcoming
 * @var list<array<string, mixed>> $eventsPast
 * @var string $lang
 * @var string $sectionIdPrefix pl. venue, organizer
 * @var bool $showVenueCity
 * @var string|null $listLimitValue
 * @var int|null $listTotalInDb
 * @var array<string, string>|null $D
 */
$sectionIdPrefix = $sectionIdPrefix ?? 'related';
$showVenueCity = $showVenueCity ?? true;
?>
<section class="organizer-public__events venue-public__events" aria-labelledby="<?= h($sectionIdPrefix) ?>-events-heading">
    <h2 class="organizer-public__events-title" id="<?= h($sectionIdPrefix) ?>-events-heading"><?= h((string) ($PEszovegek['events_heading'] ?? 'Események')) ?></h2>
    <?php if ($eventsList === []): ?>
        <p class="organizer-public__empty"><?= h((string) ($PEszovegek['list_empty'] ?? '')) ?></p>
    <?php else: ?>
        <?php if (isset($listLimitValue) && isset($listTotalInDb)): ?>
            <?php $D = $D ?? $PEszovegek; require __DIR__ . '/public_entity_events_display_limit.php'; ?>
        <?php endif; ?>
        <?php
        $eventBlocks = [
            ['id' => $sectionIdPrefix . '-upcoming', 'heading' => $PEszovegek['section_upcoming'] ?? '', 'rows' => $eventsUpcoming, 'empty' => $PEszovegek['upcoming_empty'] ?? ''],
            ['id' => $sectionIdPrefix . '-past', 'heading' => $PEszovegek['section_past'] ?? '', 'rows' => $eventsPast, 'empty' => $PEszovegek['past_empty'] ?? ''],
        ];
        ?>
        <?php foreach ($eventBlocks as $block): ?>
            <?php
            $isPastSection = str_ends_with((string) ($block['id'] ?? ''), '-past');
            $subsectionClass = 'organizer-public__subsection' . ($isPastSection ? ' organizer-public__subsection--past' : '');
            $subsectionTitleClass = 'organizer-public__subsection-title' . ($isPastSection ? ' organizer-public__subsection-title--past' : '');
            ?>
            <div class="<?= h($subsectionClass) ?>" id="<?= h((string) $block['id']) ?>">
                <h3 class="<?= h($subsectionTitleClass) ?>"><?= h((string) $block['heading']) ?></h3>
                <?php if ($block['rows'] === []): ?>
                    <p class="organizer-public__subsection-empty"><?= h((string) $block['empty']) ?></p>
                <?php else: ?>
                    <ul class="event-related-grid" role="list">
                        <?php foreach ($block['rows'] as $rel): ?>
                            <?php
                            $relSlug = (string) ($rel['event_slug'] ?? '');
                            $relTitle = (string) ($rel['event_name'] ?? '');
                            $relHref = events_public_event_page_url($relSlug, $lang);
                            $relAllday = !empty($rel['event_allday']);
                            $relTsStart = !empty($rel['event_start']) ? strtotime((string) $rel['event_start']) : false;
                            $dateDisplay = events_public_event_start_date_time_display($relAllday, $relTsStart, $lang);
                            $relFeatRaw = trim(html_entity_decode(trim((string) ($rel['event_featured_image_url'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                            $relFeatRaw = preg_replace('/^\x{FEFF}|\x{200B}/u', '', $relFeatRaw) ?? $relFeatRaw;
                            $relFeatAbs = $relFeatRaw !== '' ? events_absolute_url($relFeatRaw) : '';
                            $venueCity = trim((string) ($rel['venue_city'] ?? ''));
                            ?>
                            <li class="event-related-grid__cell">
                                <a class="event-related-card" href="<?= h($relHref) ?>">
                                    <div class="event-related-card__media">
                                        <?php if ($relFeatAbs !== ''): ?>
                                            <img
                                                class="event-related-card__img"
                                                src="<?= h($relFeatAbs) ?>"
                                                alt=""
                                                width="640"
                                                height="360"
                                                loading="lazy"
                                                decoding="async"
                                            >
                                        <?php else: ?>
                                            <div class="event-related-card__placeholder" aria-hidden="true">
                                                <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.25"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 16l5-5 4 4 5-6 5 7"/></svg>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="event-related-card__body">
                                        <span class="event-related-card__title"><?= h($relTitle) ?></span>
                                        <?php if ($dateDisplay !== '' || ($showVenueCity && $venueCity !== '')): ?>
                                            <div class="event-related-card__meta">
                                                <?php if ($dateDisplay !== ''): ?>
                                                    <span class="event-related-card__date"><?= h($dateDisplay) ?></span>
                                                <?php endif; ?>
                                                <?php if ($showVenueCity && $venueCity !== ''): ?>
                                                    <span class="event-related-card__city"><?= h($venueCity) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
