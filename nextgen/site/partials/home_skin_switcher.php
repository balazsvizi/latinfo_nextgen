<?php
declare(strict_types=1);

/**
 * Kezdőoldal-változat váltó (Üveg / Ritmus / Magazin) az admin előnézethez.
 *
 * @var array<string, string> $H
 * @var int $homeSkin
 * @var array<int, string> $skinUrls
 */
$homeSkin = (int) ($homeSkin ?? 1);
$skinUrls = is_array($skinUrls ?? null) ? $skinUrls : [];
$H = is_array($H ?? null) ? $H : [];

$skinLinks = [];
foreach ([1, 2, 3] as $skin) {
    $href = trim((string) ($skinUrls[$skin] ?? ''));
    if ($href === '') {
        continue;
    }
    $label = trim((string) ($H['skin_' . $skin] ?? ''));
    $skinLinks[$skin] = [
        'href' => $href,
        'label' => $label !== '' ? $label : (string) $skin,
        'aria' => trim((string) ($H['skin_' . $skin . '_aria'] ?? '')) ?: $label,
    ];
}

if ($skinLinks === []) {
    return;
}
?>
<nav class="latinfo-skins" aria-label="<?= h((string) ($H['skin_nav'] ?? 'Kezdőoldal-változatok')) ?>">
    <?php foreach ($skinLinks as $skin => $link): ?>
        <?php $isActive = $skin === $homeSkin; ?>
        <a
            class="latinfo-skins__btn<?= $isActive ? ' is-active' : '' ?>"
            href="<?= h($link['href']) ?>"
            title="<?= h($link['aria']) ?>"
            aria-label="<?= h($link['aria']) ?>"
            <?= $isActive ? 'aria-current="page"' : '' ?>
        >
            <span class="latinfo-skins__num" aria-hidden="true"><?= (int) $skin ?></span>
            <span class="latinfo-skins__text"><?= h($link['label']) ?></span>
        </a>
    <?php endforeach; ?>
</nav>
