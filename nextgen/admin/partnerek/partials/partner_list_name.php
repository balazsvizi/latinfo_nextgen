<?php
declare(strict_types=1);

/**
 * Partner név + kiegészítő infó megjelenítése listákban.
 *
 * @var array<string, mixed>|null $partner
 * @var string $partnerListNev
 * @var string $partnerListKieg
 */
$partnerListNev = trim((string) ($partnerListNev ?? nextgen_partner_nev_from_row($partner ?? [])));
$partnerListKieg = trim((string) ($partnerListKieg ?? nextgen_partner_kieg_info_from_row($partner ?? [])));
?>
<?= h($partnerListNev) ?><?php if ($partnerListKieg !== ''): ?> <span class="partner-kieg-info"><?= h($partnerListKieg) ?></span><?php endif; ?>
