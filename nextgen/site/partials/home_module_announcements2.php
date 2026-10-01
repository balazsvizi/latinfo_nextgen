<?php
declare(strict_types=1);

/**
 * Bejelentések 2 – rotáló modul (egyszerre 2, DJ ajánló mintára).
 *
 * @var array<string, string> $H
 * @var list<array<string, mixed>> $announcements2Cards
 * @var int $announcements2VisibleCount
 * @var string $calendarUrl
 */
$announcements2Cards = is_array($announcements2Cards ?? null) ? $announcements2Cards : [];
$announcements2VisibleCount = max(1, (int) ($announcements2VisibleCount ?? 2));
$calendarUrl = (string) ($calendarUrl ?? '#');
$H = is_array($H ?? null) ? $H : [];
$visibleCards = array_slice($announcements2Cards, 0, $announcements2VisibleCount);
?>
<section
    class="latinfo-home__flashes-wrap latinfo-home__flashes-wrap--rotate"
    id="hirek2"
    aria-label="<?= h((string) ($H['quick_news2_aria'] ?? $H['quick_news_aria'] ?? 'Bejelentések')) ?>"
>
    <?php if ($announcements2Cards === []): ?>
        <p class="latinfo-home__day-empty"><?= h((string) ($H['empty_news2'] ?? $H['empty_news'] ?? '')) ?></p>
    <?php else: ?>
        <ul
            class="latinfo-home__flashes latinfo-home__flashes--rotate"
            id="lh-announcements2-list"
            role="list"
            aria-label="<?= h((string) ($H['quick_news2_aria'] ?? '')) ?>"
        >
            <?php foreach ($visibleCards as $item): ?>
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
                <li class="latinfo-home__flash-item" role="listitem">
                    <div class="latinfo-home__flash">
                        <span class="latinfo-home__flash-kicker"<?= $itemKicker === '' ? ' hidden' : '' ?>><?= h($itemKicker) ?></span>
                        <span class="latinfo-home__flash-title">
                            <a
                                class="latinfo-home__flash-link"
                                href="<?= h($itemUrl) ?>"
                                data-lh-module-track="announcements2"
                                data-lh-item-key="<?= h($itemId > 0 ? 'news2:' . $itemId : '') ?>"
                                data-lh-item-label="<?= h($itemTitle) ?>"
                            ><?= h($itemTitle) ?></a>
                        </span>
                        <div class="latinfo-home__flash-dek"<?= $itemDek === '' ? ' hidden' : '' ?>><?= events_sanitize_html_fragment($itemDek) ?></div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <script type="application/json" id="lh-announcements2-data">
            <?= json_encode($announcements2Cards, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
        </script>
    <?php endif; ?>
</section>
