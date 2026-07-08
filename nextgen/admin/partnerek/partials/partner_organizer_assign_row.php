<?php
declare(strict_types=1);

/**
 * Partner – egy esemény szervező hozzárendelési sor.
 *
 * @var int $partnerAssignRowIndex
 * @var array{organizer_id?: int, role_type?: string, role_note?: string, name?: string} $partnerAssignRow
 * @var list<array{id:int,name:string}> $partnerAssignAllOrganizers
 * @var array<string, string> $partnerOrganizerRoleLabels
 * @var string $partnerOrganizerChipLinkPattern
 * @var string|null $partnerOrganizerManageUrl
 */
$partnerAssignRowIndex = (int) ($partnerAssignRowIndex ?? 0);
$partnerAssignRow = $partnerAssignRow ?? [];
$partnerAssignAllOrganizers = $partnerAssignAllOrganizers ?? [];
$partnerOrganizerRoleLabels = $partnerOrganizerRoleLabels ?? nextgen_partner_organizer_role_labels();
$selectedOrganizerId = (int) ($partnerAssignRow['organizer_id'] ?? 0);
$selectedRole = (string) ($partnerAssignRow['role_type'] ?? 'event');
if (!isset($partnerOrganizerRoleLabels[$selectedRole])) {
    $selectedRole = 'event';
}
$roleNote = (string) ($partnerAssignRow['role_note'] ?? '');
$wpTokenId = 'partner-org-token-' . $partnerAssignRowIndex;
$wpTokenLabel = '';
$wpTokenFieldName = 'organizer_rows[' . $partnerAssignRowIndex . '][organizer_id]';
$wpTokenPlaceholder = 'Szervező keresése…';
$wpTokenHelp = '';
$wpTokenManageUrl = $partnerOrganizerManageUrl ?? null;
$wpTokenManageLabel = 'Új szervező felvétele';
$wpTokenManageNewTab = true;
$wpTokenAll = $partnerAssignAllOrganizers;
$wpTokenSelected = $selectedOrganizerId > 0 ? [$selectedOrganizerId] : [];
$wpTokenAllowCreate = false;
$wpTokenEntityType = '';
$wpTokenSingle = true;
$wpTokenShowPopular = false;
$wpTokenChipLinkPattern = $partnerOrganizerChipLinkPattern ?? '';
$wpTokenChipLinkNewTab = true;
?>
<div class="partner-assign-row" data-partner-assign-row="organizer">
    <div class="partner-assign-row__main">
        <div class="partner-assign-row__picker">
            <?php require dirname(__DIR__, 3) . '/events/partials/wp_token_field.php'; ?>
        </div>
        <div class="partner-assign-row__role">
            <label class="visually-hidden" for="partner-org-role-<?= $partnerAssignRowIndex ?>">Partner jelleg</label>
            <select
                id="partner-org-role-<?= $partnerAssignRowIndex ?>"
                name="organizer_rows[<?= $partnerAssignRowIndex ?>][role_type]"
                class="partner-assign-row__role-select"
                data-partner-role-select
            >
                <?php foreach ($partnerOrganizerRoleLabels as $roleValue => $roleLabel): ?>
                    <option value="<?= h($roleValue) ?>"<?= $selectedRole === $roleValue ? ' selected' : '' ?>><?= h($roleLabel) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="partner-assign-row__note" data-partner-role-note-wrap<?= $selectedRole === 'other' ? '' : ' hidden' ?>>
            <label class="visually-hidden" for="partner-org-note-<?= $partnerAssignRowIndex ?>">Megjegyzés</label>
            <input
                type="text"
                id="partner-org-note-<?= $partnerAssignRowIndex ?>"
                name="organizer_rows[<?= $partnerAssignRowIndex ?>][role_note]"
                class="partner-assign-row__note-input"
                value="<?= h($roleNote) ?>"
                placeholder="Megjegyzés (Other)…"
                maxlength="500"
            >
        </div>
    </div>
    <button type="button" class="partner-assign-row__remove" data-partner-assign-remove aria-label="Sor törlése">&times;</button>
</div>
