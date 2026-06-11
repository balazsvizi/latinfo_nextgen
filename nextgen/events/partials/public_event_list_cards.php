<?php
declare(strict_types=1);

if (!defined('EVENTS_VIEW_SOURCE_LIST')) {
    require_once __DIR__ . '/../lib/event_view_tracking.php';
}

/** @var list<array<string, mixed>> $sectionRows */
/** @var array<int, list<array{color: string, name: string}>> $categoriesByEventId */
?>
<?php if ($sectionRows === []): ?>
    <p class="home-public__empty home-public__empty--section"><?= h((string) ($sectionEmpty ?? '')) ?></p>
<?php else: ?>
    <ul class="home-public__list" role="list">
        <?php foreach ($sectionRows as $ev): ?>
            <?php
            $eid = (int) ($ev['id'] ?? 0);
            $eventUrl = events_public_calendar_event_url($ev, EVENTS_VIEW_SOURCE_LIST);
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
            $featRaw = trim(html_entity_decode(trim((string) ($ev['event_featured_image_url'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $featRaw = preg_replace('/^\x{FEFF}|\x{200B}/u', '', $featRaw) ?? $featRaw;
            $featAbs = $featRaw !== '' ? events_absolute_url($featRaw) : '';
            if (!function_exists('events_event_change_active')) {
                require_once __DIR__ . '/../lib/event_change.php';
            }
            $listLang = $listLang ?? ($lang ?? 'hu');
            $hasChange = events_event_change_active($ev);
            $listCardClass = 'home-public__list-card';
            if ($hasChange) {
                $listCardClass .= events_event_change_list_card_class($ev);
                $changeStyle = events_event_change_calendar_block_style($ev);
                if (preg_match('/--events-cal-accent:([^;]+)/', $changeStyle, $accentMatch) === 1) {
                    $accent = trim($accentMatch[1]);
                }
            }
            $listNameClass = 'home-public__list-name';
            if ($hasChange && events_event_change_type($ev) === events_event_change_type_cancelled()) {
                $listNameClass .= ' home-public__list-name--cancelled';
            }
            ?>
            <li class="home-public__list-item" role="listitem">
                <a class="<?= h($listCardClass) ?>" href="<?= h($eventUrl) ?>" style="--home-event-accent: <?= h($accent) ?>">
                    <div class="home-public__list-media">
                        <?php if ($featAbs !== ''): ?>
                            <img
                                class="home-public__list-img"
                                src="<?= h($featAbs) ?>"
                                alt=""
                                width="640"
                                height="360"
                                loading="lazy"
                                decoding="async"
                            >
                        <?php else: ?>
                            <div class="home-public__list-placeholder" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.25"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 16l5-5 4 4 5-6 5 7"/></svg>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="home-public__list-body">
                        <?php require __DIR__ . '/public_event_list_change.php'; ?>
                        <span class="home-public__list-date"><?= h($dateLabel) ?></span>
                        <span class="<?= h($listNameClass) ?>"><?= h((string) ($ev['event_name'] ?? '')) ?></span>
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
                    </div>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
