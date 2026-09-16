<?php
declare(strict_types=1);

/**
 * Véletlenszerűen váltakozó DJ ajánló (nyilvános DJ oldal és Latinfo kezdőoldal).
 *
 * @var list<array<string, mixed>> $spotlightCards
 * @var int $spotlightMobileCount
 * @var string $cmsAnchorSpotlight
 * @var array<string, string> $D
 */
$spotlightCards = is_array($spotlightCards ?? null) ? $spotlightCards : [];
$spotlightMobileCount = (int) ($spotlightMobileCount ?? 3);
$cmsAnchorSpotlight = trim((string) ($cmsAnchorSpotlight ?? 'DJ-ajanlo'));
$D = is_array($D ?? null) ? $D : [];
if ($spotlightCards === []) {
    return;
}
?>
<aside class="djs-public__spotlight" id="<?= h($cmsAnchorSpotlight) ?>" aria-label="<?= h((string) ($D['spotlight_aria'] ?? '')) ?>">
    <h2 class="djs-public__spotlight-title"><?= h((string) ($D['spotlight_heading'] ?? '')) ?></h2>
    <ul class="djs-public__spotlight-list" id="djs-spotlight-list" role="list">
        <?php foreach ($spotlightCards as $slotIndex => $card): ?>
            <li class="djs-public__spotlight-item"<?= $slotIndex >= $spotlightMobileCount ? ' hidden' : '' ?>>
                <a class="djs-public__spotlight-card" href="<?= h((string) $card['href']) ?>" aria-label="<?= h((string) $card['aria']) ?>">
                    <span class="djs-public__spotlight-avatar<?= !empty($card['isLogo']) ? ' djs-public__spotlight-avatar--logo' : '' ?>" aria-hidden="true">
                        <?php if ((string) ($card['photo'] ?? '') !== ''): ?>
                            <img class="djs-public__spotlight-photo" src="<?= h((string) $card['photo']) ?>" alt="" loading="lazy" decoding="async"<?= (string) ($card['photoStyle'] ?? '') !== '' ? ' style="' . h((string) $card['photoStyle']) . '"' : '' ?>>
                        <?php else: ?>
                            <span class="djs-public__spotlight-initials"><?= h((string) ($card['initials'] ?? 'DJ')) ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="djs-public__spotlight-body">
                        <span class="djs-public__spotlight-name"><?= h((string) ($card['name'] ?? '')) ?></span>
                        <span class="djs-public__spotlight-meta"<?= (string) ($card['meta'] ?? '') === '' ? ' hidden' : '' ?>><?= h((string) ($card['meta'] ?? '')) ?></span>
                    </span>
                    <span class="djs-public__spotlight-go" aria-hidden="true">→</span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <script type="application/json" id="djs-spotlight-data">
        <?= json_encode($spotlightCards, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    </script>
</aside>
