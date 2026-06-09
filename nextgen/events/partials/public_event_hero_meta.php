<?php
declare(strict_types=1);

/**
 * Esemény hero meta — címkézett chip rács.
 *
 * @var array<string, string> $T
 * @var string $lang
 * @var list<array{id:int,name:string}> $eventOrganizers
 * @var list<array{name:string}> $eventMainStyles
 * @var list<array{name:string}> $eventSupplementaryStyles
 * @var list<array{color:string,name:string}> $eventCategories
 * @var list<array{id:int,name:string}> $eventTags
 * @var list<array{id:int,name:string}> $eventDjs
 */
$hasOrganizers = $eventOrganizers !== [];
$hasStyles = $eventMainStyles !== [] || $eventSupplementaryStyles !== [];
$hasCategories = $eventCategories !== [];
$hasTags = $eventTags !== [];
$hasDjs = $eventDjs !== [];

if (!$hasOrganizers && !$hasStyles && !$hasCategories && !$hasTags && !$hasDjs) {
    return;
}
?>
<div class="event-hero-meta" role="group" aria-label="<?= h($lang === 'en' ? 'Event details' : 'Esemény adatok') ?>">
    <?php if ($hasOrganizers): ?>
        <div class="event-hero-meta__item">
            <span class="event-hero-meta__label"><?= h($T['section_organizers']) ?></span>
            <div class="event-hero-meta__chips">
                <?php foreach ($eventOrganizers as $org): ?>
                    <a class="event-hero-chip event-hero-chip--link" href="<?= h(events_public_organizer_page_url((int) $org['id'], $lang)) ?>"><?= h((string) $org['name']) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($hasStyles): ?>
        <div class="event-hero-meta__item">
            <span class="event-hero-meta__label"><?= h($T['section_main_styles']) ?></span>
            <div class="event-hero-meta__chips">
                <?php foreach ($eventMainStyles as $styleRow): ?>
                    <span class="event-hero-chip event-hero-chip--style"><?= h((string) $styleRow['name']) ?></span>
                <?php endforeach; ?>
                <?php foreach ($eventSupplementaryStyles as $styleRow): ?>
                    <span class="event-hero-chip event-hero-chip--style event-hero-chip--style-supplementary"><?= h((string) $styleRow['name']) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($hasCategories): ?>
        <div class="event-hero-meta__item">
            <span class="event-hero-meta__label"><?= h($T['section_categories']) ?></span>
            <div class="event-hero-meta__chips">
                <?php foreach ($eventCategories as $catRow): ?>
                    <?php
                    $chipLabel = events_public_category_chip_label($lang, $catRow);
                    $chipColor = trim((string) ($catRow['color'] ?? '#6d8f63'));
                    $textStyle = events_public_category_text_inline_style($chipColor);
                    ?>
                    <span class="event-hero-chip event-hero-chip--category" style="<?= h($textStyle) ?>"><?= h($chipLabel) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($hasTags): ?>
        <div class="event-hero-meta__item">
            <span class="event-hero-meta__label"><?= h($T['section_tags']) ?></span>
            <div class="event-hero-meta__chips">
                <?php foreach ($eventTags as $tagRow): ?>
                    <a class="event-hero-chip event-hero-chip--tag" href="<?= h(events_public_tag_page_url((int) $tagRow['id'], $lang)) ?>"><?= h((string) $tagRow['name']) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($hasDjs): ?>
        <div class="event-hero-meta__item event-hero-meta__item--wide">
            <span class="event-hero-meta__label"><?= h($T['section_djs']) ?></span>
            <div class="event-hero-meta__chips">
                <?php foreach ($eventDjs as $djRow): ?>
                    <a class="event-hero-chip event-hero-chip--link event-hero-chip--dj" href="<?= h(events_public_tag_page_url((int) $djRow['id'], $lang)) ?>"><?= h((string) $djRow['name']) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
