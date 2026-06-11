<?php
declare(strict_types=1);
/** @var array<string, mixed> $ev */
/** @var string $listLang */
if (!function_exists('events_event_change_active')) {
    require_once __DIR__ . '/../lib/event_change.php';
}
if (!events_event_change_active($ev)) {
    return;
}
$listLang = $listLang ?? ($lang ?? 'hu');
$listStrings = $listStrings ?? null;
if (!is_array($listStrings)) {
    require_once __DIR__ . '/../lib/event_public_lang.php';
    $listStrings = events_public_megjelenit_strings($listLang);
}
$changeModifier = events_event_change_notice_css_modifier($ev);
$changeBadge = events_event_change_calendar_badge_label($ev, $listLang);
$changeHeading = events_event_change_public_heading($ev, $listStrings, $listLang);
$changeNote = events_event_change_public_note_excerpt($ev);
?>
<div class="home-public__list-change <?= h($changeModifier) ?>">
    <?php if ($changeBadge !== ''): ?>
        <span class="home-public__list-change-badge"><?= h($changeBadge) ?></span>
    <?php endif; ?>
    <?php if ($changeHeading !== ''): ?>
        <span class="home-public__list-change-heading"><?= h($changeHeading) ?></span>
    <?php endif; ?>
    <?php if ($changeNote !== ''): ?>
        <p class="home-public__list-change-note"><?= h($changeNote) ?></p>
    <?php endif; ?>
</div>
