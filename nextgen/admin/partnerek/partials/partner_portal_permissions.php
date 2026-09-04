<?php
declare(strict_types=1);

/**
 * Partner portál jogosultság checkboxok (admin űrlap).
 *
 * @var list<string> $selectedPortalJogok
 */
$selectedPortalJogok = nextgen_partner_normalize_portal_permissions($selectedPortalJogok ?? []);
$catalog = nextgen_partner_portal_permission_catalog();
?>
<fieldset class="partner-portal-perms">
    <legend>Portál jogosultságok</legend>
    <p class="help">Több jog is adható egyszerre. A partner csak a bepipált modulokat látja a partnerportálon.</p>
    <div class="partner-portal-perms__grid">
        <?php foreach ($catalog as $permKey => $permMeta): ?>
            <label class="partner-portal-perms__item">
                <input
                    type="checkbox"
                    name="portal_jogok[]"
                    value="<?= h($permKey) ?>"
                    <?= in_array($permKey, $selectedPortalJogok, true) ? ' checked' : '' ?>
                >
                <span>
                    <strong><?= h((string) ($permMeta['label'] ?? $permKey)) ?></strong>
                    <span class="partner-portal-perms__help"><?= h((string) ($permMeta['help'] ?? '')) ?></span>
                </span>
            </label>
        <?php endforeach; ?>
    </div>
</fieldset>
