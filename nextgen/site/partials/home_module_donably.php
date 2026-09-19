<?php
declare(strict_types=1);

/**
 * Donably támogatás modul – lágy felhívás, egy CTA, külső fizetési oldal.
 *
 * @var array{title: string, lead: string, cta_label: string, cta_url: string, note: string, show_icon: bool, is_external: bool, configured: bool} $donablyView
 * @var string $lang
 */
$donablyView = is_array($donablyView ?? null) ? $donablyView : [];
$title = trim((string) ($donablyView['title'] ?? ''));
$lead = trim((string) ($donablyView['lead'] ?? ''));
$ctaLabel = trim((string) ($donablyView['cta_label'] ?? ''));
$ctaUrl = trim((string) ($donablyView['cta_url'] ?? ''));
$note = trim((string) ($donablyView['note'] ?? ''));
$showIcon = !empty($donablyView['show_icon']);
$isExternal = !empty($donablyView['is_external']);
$configured = $ctaUrl !== '' && $ctaLabel !== '';
$lang = (($lang ?? 'hu') === 'en') ? 'en' : 'hu';

if ($title === '' && $lead === '') {
    return;
}

$aria = $lang === 'en' ? 'Support Latinfo.hu' : 'Támogasd a Latinfo.hu-t';
$externalHint = $lang === 'en' ? 'Opens in a new tab' : 'Új lapon nyílik';
$hasCta = $configured || $ctaLabel !== '';
$donablyEmbed = !empty($donablyEmbed);
$donablyDomId = trim((string) ($donablyDomId ?? ''));
if ($donablyDomId === '') {
    $donablyDomId = $donablyEmbed ? 'ertekeles-tamogatas' : 'tamogatas';
}
$donablyTrackItemKey = trim((string) ($donablyTrackItemKey ?? ''));
if ($donablyTrackItemKey === '') {
    $donablyTrackItemKey = $donablyEmbed ? 'cta:rating' : 'cta';
}
$donablyClass = 'latinfo-home__donably' . ($donablyEmbed ? ' latinfo-home__donably--embed' : '');
?>
<section class="<?= h($donablyClass) ?>" id="<?= h($donablyDomId) ?>" aria-label="<?= h($aria) ?>">
    <?php if ($showIcon || $title !== ''): ?>
        <div class="latinfo-home__donably-head">
            <?php if ($showIcon): ?>
                <div class="latinfo-home__donably-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="22" height="22" focusable="false">
                        <path fill="currentColor" d="M12.1 20.55c-.2 0-.4-.06-.55-.18C7.2 17.2 4.4 14.7 2.9 12.3 1.2 9.55 1.55 6.4 3.85 4.85c1.95-1.3 4.55-.9 6.05 1.05L12 8.2l2.1-2.3c1.5-1.95 4.1-2.35 6.05-1.05 2.3 1.55 2.65 4.7.95 7.45-1.5 2.4-4.3 4.9-8.65 8.07-.15.12-.35.18-.55.18z"/>
                    </svg>
                </div>
            <?php endif; ?>
            <?php if ($title !== ''): ?>
                <h2 class="latinfo-home__donably-title"><?= h($title) ?></h2>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if ($lead !== ''): ?>
        <p class="latinfo-home__donably-lead"><?= h($lead) ?></p>
    <?php endif; ?>
    <?php if ($hasCta || $note !== ''): ?>
        <div class="latinfo-home__donably-actions">
            <?php if ($configured): ?>
                <a
                    class="latinfo-home__donably-cta"
                    href="<?= h($ctaUrl) ?>"
                    <?php if ($isExternal): ?>
                        target="_blank"
                        rel="noopener noreferrer"
                    <?php endif; ?>
                    data-lh-module-track="donably"
                    data-lh-item-key="<?= h($donablyTrackItemKey) ?>"
                    data-lh-item-label="<?= h($ctaLabel) ?>"
                >
                    <span><?= h($ctaLabel) ?></span>
                    <svg class="latinfo-home__donably-cta-arrow" viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false">
                        <path fill="currentColor" d="M3 8h8.2L8.1 4.9 9.2 3.8 13.4 8l-4.2 4.2-1.1-1.1L11.2 8H3z"/>
                    </svg>
                    <?php if ($isExternal): ?>
                        <span class="visually-hidden"><?= h($externalHint) ?></span>
                    <?php endif; ?>
                </a>
            <?php elseif ($ctaLabel !== ''): ?>
                <span class="latinfo-home__donably-cta latinfo-home__donably-cta--pending" aria-disabled="true"><?= h($ctaLabel) ?></span>
            <?php endif; ?>
            <?php if ($note !== ''): ?>
                <p class="latinfo-home__donably-note"><?= h($note) ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
