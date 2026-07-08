<?php
declare(strict_types=1);

/**
 * Partner név + kiegészítő infó megjelenítése listákban.
 *
 * @var array<string, mixed>|null $partner
 * @var string $partnerListNev
 * @var string $partnerListKieg
 * @var string|null $partnerListEditUrl Ha megadva, a név erre a szerkesztő oldalra linkel.
 */
$partnerListNev = trim((string) ($partnerListNev ?? nextgen_partner_nev_from_row($partner ?? [])));
$partnerListKieg = trim((string) ($partnerListKieg ?? nextgen_partner_kieg_info_from_row($partner ?? [])));
$partnerListEditUrl = isset($partnerListEditUrl) ? trim((string) $partnerListEditUrl) : '';
?>
<span class="partner-list-name">
    <?php if ($partnerListEditUrl !== ''): ?>
        <a href="<?= h($partnerListEditUrl) ?>" class="partner-list-name__link"><?= h($partnerListNev) ?></a>
    <?php else: ?>
        <span class="partner-list-name__text"><?= h($partnerListNev) ?></span>
    <?php endif; ?>
    <?php if ($partnerListKieg !== ''): ?>
        <span class="partner-list-name__kieg"><?= h($partnerListKieg) ?></span>
    <?php endif; ?>
</span>
