<?php
declare(strict_types=1);

/**
 * Partner – finance szervező hozzárendelés, számlázás jelöléssel.
 *
 * @var int $partnerAssignRowIndex
 * @var array{finance_organizer_id?: int, szamlazas?: bool, name?: string} $partnerAssignRow
 * @var list<array{id:int,name:string}> $partnerAssignAllFinanceOrganizers
 * @var string $partnerFinanceChipLinkPattern
 * @var string|null $partnerFinanceManageUrl
 */
$partnerAssignRowIndex = (int) ($partnerAssignRowIndex ?? 0);
$partnerAssignRow = $partnerAssignRow ?? [];
$partnerAssignAllFinanceOrganizers = $partnerAssignAllFinanceOrganizers ?? [];
$selectedOrganizerId = (int) ($partnerAssignRow['finance_organizer_id'] ?? 0);
$wpTokenId = 'partner-fin-token-' . $partnerAssignRowIndex;
$wpTokenLabel = '';
$wpTokenFieldName = 'finance_rows[' . $partnerAssignRowIndex . '][finance_organizer_id]';
$wpTokenPlaceholder = 'Finance szervező keresése…';
$wpTokenHelp = '';
$wpTokenManageUrl = $partnerFinanceManageUrl ?? null;
$wpTokenManageLabel = 'Új szervező felvétele';
$wpTokenManageNewTab = true;
$wpTokenAll = $partnerAssignAllFinanceOrganizers;
$wpTokenSelected = $selectedOrganizerId > 0 ? [$selectedOrganizerId] : [];
$wpTokenAllowCreate = false;
$wpTokenEntityType = '';
$wpTokenSingle = true;
$wpTokenShowPopular = false;
$wpTokenChipLinkPattern = $partnerFinanceChipLinkPattern ?? '';
$wpTokenChipLinkNewTab = true;
?>
<div class="partner-assign-row" data-partner-assign-row="finance">
    <div class="partner-assign-row__main">
        <div class="partner-assign-row__picker">
            <?php require dirname(__DIR__, 3) . '/events/partials/wp_token_field.php'; ?>
        </div>
    </div>
    <button type="button" class="partner-assign-row__remove" data-partner-assign-remove aria-label="Sor törlése">&times;</button>
</div>
