<?php
declare(strict_types=1);

/**
 * Partner név + kiegészítő infó megjelenítése listákban.
 *
 * @var array<string, mixed>|null $partner
 * @var string|null $partnerListNev
 * @var string|null $partnerListKieg
 * @var string|null $partnerListEditUrl Ha megadva, a név erre a szerkesztő oldalra linkel.
 */
$row = $partner ?? [];
$displayNev = isset($partnerListNev)
    ? trim((string) $partnerListNev)
    : nextgen_partner_nev_from_row($row);
$displayKieg = isset($partnerListKieg)
    ? trim((string) $partnerListKieg)
    : nextgen_partner_kieg_info_from_row($row);
$displayEditUrl = isset($partnerListEditUrl) ? trim((string) $partnerListEditUrl) : '';
?>
<span class="partner-list-name">
    <?php if ($displayEditUrl !== ''): ?>
        <a href="<?= h($displayEditUrl) ?>" class="partner-list-name__link"><?= h($displayNev) ?></a>
    <?php else: ?>
        <span class="partner-list-name__text"><?= h($displayNev) ?></span>
    <?php endif; ?>
    <?php if ($displayKieg !== ''): ?>
        <span class="partner-list-name__kieg"><?= h($displayKieg) ?></span>
    <?php endif; ?>
</span>
