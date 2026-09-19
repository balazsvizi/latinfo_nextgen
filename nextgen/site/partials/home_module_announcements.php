<?php
declare(strict_types=1);

/**
 * Bejelentések (gyorshírek) modul.
 *
 * @var array<string, string> $H
 * @var list<array<string, mixed>> $quickNews
 * @var string $calendarUrl
 */
$quickNews = is_array($quickNews ?? null) ? $quickNews : [];
$calendarUrl = (string) ($calendarUrl ?? '#');
$H = is_array($H ?? null) ? $H : [];
?>
<section class="latinfo-home__flashes-wrap" id="hirek" aria-label="<?= h((string) ($H['quick_news'] ?? 'Bejelentések')) ?>">
    <?php if ($quickNews === []): ?>
        <p class="latinfo-home__day-empty"><?= h((string) ($H['empty_news'] ?? '')) ?></p>
    <?php else: ?>
        <ul class="latinfo-home__flashes" role="list" aria-label="<?= h((string) ($H['quick_news_aria'] ?? '')) ?>">
            <?php foreach ($quickNews as $item): ?>
                <?php
                $itemId = (int) ($item['id'] ?? 0);
                $itemUrl = trim((string) ($item['url'] ?? ''));
                if ($itemUrl === '') {
                    $itemUrl = $calendarUrl;
                }
                $itemKicker = trim((string) ($item['kicker'] ?? ''));
                $itemDek = trim((string) ($item['dek'] ?? ''));
                $itemTitle = trim((string) ($item['title'] ?? ''));
                ?>
                <li role="listitem">
                    <a
                        class="latinfo-home__flash"
                        href="<?= h($itemUrl) ?>"
                        data-lh-module-track="announcements"
                        data-lh-item-key="<?= h($itemId > 0 ? 'news:' . $itemId : '') ?>"
                        data-lh-item-label="<?= h($itemTitle) ?>"
                    >
                        <?php if ($itemKicker !== ''): ?>
                            <span class="latinfo-home__flash-kicker"><?= h($itemKicker) ?></span>
                        <?php endif; ?>
                        <span class="latinfo-home__flash-title"><?= h($itemTitle) ?></span>
                        <?php if ($itemDek !== ''): ?>
                            <span class="latinfo-home__flash-dek"><?= h($itemDek) ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
