<?php
declare(strict_types=1);

/**
 * Megújult naptár – kompakt felhívás a logó és a nyelvváltó között.
 *
 * @var array<string, string> $S
 * @var array{visible: bool, text: string, aria: string, url: string, style: string}|null $renewalNotice
 */
$renewalNotice = $renewalNotice ?? null;
if (!is_array($renewalNotice) || empty($renewalNotice['visible'])) {
    return;
}
$noticeText = (string) ($renewalNotice['text'] ?? '');
$noticeAria = (string) ($renewalNotice['aria'] ?? $noticeText);
$noticeUrl = (string) ($renewalNotice['url'] ?? '');
$noticeStyle = (string) ($renewalNotice['style'] ?? '');
?>
<div class="home-public__renewal-notice">
    <?php if ($noticeUrl !== ''): ?>
        <a
            class="home-public__renewal-notice-link"
            href="<?= h($noticeUrl) ?>"
            aria-label="<?= h($noticeAria) ?>"
            <?php if ($noticeStyle !== ''): ?>style="<?= h($noticeStyle) ?>"<?php endif; ?>
        ><?= h($noticeText) ?></a>
    <?php else: ?>
        <span
            class="home-public__renewal-notice-link"
            <?php if ($noticeStyle !== ''): ?>style="<?= h($noticeStyle) ?>"<?php endif; ?>
        ><?= h($noticeText) ?></span>
    <?php endif; ?>
</div>
