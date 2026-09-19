<?php
declare(strict_types=1);

/**
 * Véletlenszerűen váltakozó DJ ajánló (nyilvános DJ oldal és Latinfo kezdőoldal).
 *
 * @var list<array<string, mixed>> $spotlightCards
 * @var int $spotlightMobileCount
 * @var string $cmsAnchorSpotlight
 * @var array<string, string> $D
 * @var string|null $spotlightMoreHref Opcionális „Összes DJ” link (kezdőoldal)
 * @var string|null $spotlightMoreLabel
 * @var string|null $spotlightTrackModule Opcionális kezdőoldal modul kulcs (kattintás-követés)
 */
$spotlightCards = is_array($spotlightCards ?? null) ? $spotlightCards : [];
$spotlightMobileCount = (int) ($spotlightMobileCount ?? 3);
$cmsAnchorSpotlight = trim((string) ($cmsAnchorSpotlight ?? 'DJ-ajanlo'));
$D = is_array($D ?? null) ? $D : [];
$spotlightMoreHref = trim((string) ($spotlightMoreHref ?? ''));
$spotlightMoreLabel = trim((string) ($spotlightMoreLabel ?? ''));
$spotlightTrackModule = trim((string) ($spotlightTrackModule ?? ''));
if ($spotlightCards === []) {
    return;
}
?>
<aside class="djs-public__spotlight" id="<?= h($cmsAnchorSpotlight) ?>" aria-label="<?= h((string) ($D['spotlight_aria'] ?? '')) ?>">
    <h2 class="djs-public__spotlight-title"><?= h((string) ($D['spotlight_heading'] ?? '')) ?></h2>
    <ul class="djs-public__spotlight-list" id="djs-spotlight-list" role="list">
        <?php foreach ($spotlightCards as $slotIndex => $card): ?>
            <?php
            $cardId = (int) ($card['id'] ?? 0);
            $cardName = (string) ($card['name'] ?? '');
            ?>
            <li class="djs-public__spotlight-item"<?= $slotIndex >= $spotlightMobileCount ? ' hidden' : '' ?>>
                <a
                    class="djs-public__spotlight-card"
                    href="<?= h((string) $card['href']) ?>"
                    aria-label="<?= h((string) $card['aria']) ?>"
                    <?php if ($spotlightTrackModule !== ''): ?>
                        data-lh-module-track="<?= h($spotlightTrackModule) ?>"
                        data-lh-item-key="<?= h($cardId > 0 ? 'dj:' . $cardId : '') ?>"
                        data-lh-item-label="<?= h($cardName) ?>"
                    <?php endif; ?>
                >
                    <span class="djs-public__spotlight-avatar<?= !empty($card['isLogo']) ? ' djs-public__spotlight-avatar--logo' : '' ?>" aria-hidden="true">
                        <?php if ((string) ($card['photo'] ?? '') !== ''): ?>
                            <img class="djs-public__spotlight-photo" src="<?= h((string) $card['photo']) ?>" alt="" loading="lazy" decoding="async"<?= (string) ($card['photoStyle'] ?? '') !== '' ? ' style="' . h((string) $card['photoStyle']) . '"' : '' ?>>
                        <?php else: ?>
                            <span class="djs-public__spotlight-initials"><?= h((string) ($card['initials'] ?? 'DJ')) ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="djs-public__spotlight-body">
                        <span class="djs-public__spotlight-heading">
                            <span class="djs-public__spotlight-name"><?= h((string) ($card['name'] ?? '')) ?></span>
                            <span class="djs-public__spotlight-logo"<?= (string) ($card['logo'] ?? '') === '' ? ' hidden' : '' ?> aria-hidden="true">
                                <?php if ((string) ($card['logo'] ?? '') !== ''): ?>
                                    <img class="djs-public__spotlight-logo-img" src="<?= h((string) $card['logo']) ?>" alt="" loading="lazy" decoding="async"<?= (string) ($card['logoStyle'] ?? '') !== '' ? ' style="' . h((string) $card['logoStyle']) . '"' : '' ?>>
                                <?php endif; ?>
                            </span>
                        </span>
                        <span class="djs-public__spotlight-meta"<?= (string) ($card['meta'] ?? '') === '' ? ' hidden' : '' ?>><?= h((string) ($card['meta'] ?? '')) ?></span>
                    </span>
                    <span class="djs-public__spotlight-go" aria-hidden="true">→</span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php if ($spotlightMoreHref !== '' && $spotlightMoreLabel !== ''): ?>
        <a
            class="latinfo-home__day-more djs-public__spotlight-more"
            href="<?= h($spotlightMoreHref) ?>"
            <?php if ($spotlightTrackModule !== ''): ?>
                data-lh-module-track="<?= h($spotlightTrackModule) ?>"
                data-lh-item-key="dj:more"
                data-lh-item-label="<?= h($spotlightMoreLabel) ?>"
            <?php endif; ?>
        >
            <span><?= h($spotlightMoreLabel) ?></span>
            <span class="latinfo-home__day-more-arrow" aria-hidden="true">→</span>
        </a>
    <?php endif; ?>
    <script type="application/json" id="djs-spotlight-data">
        <?= json_encode($spotlightCards, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    </script>
</aside>
