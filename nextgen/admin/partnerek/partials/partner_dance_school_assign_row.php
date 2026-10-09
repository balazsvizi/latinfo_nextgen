<?php
declare(strict_types=1);

/**
 * Partner – egy tánciskola hozzárendelési sor.
 *
 * @var int $partnerAssignRowIndex
 * @var array{school_id?: int, role_types?: list<string>, role_type?: string, role_note?: string, name?: string} $partnerAssignRow
 * @var list<array{id:int,name:string}> $partnerAssignAllSchools
 * @var array<string, string> $partnerDanceSchoolRoleLabels
 * @var string $partnerDanceSchoolChipLinkPattern
 */
$partnerAssignRowIndex = (int) ($partnerAssignRowIndex ?? 0);
$partnerAssignRow = $partnerAssignRow ?? [];
$partnerAssignAllSchools = $partnerAssignAllSchools ?? [];
$partnerDanceSchoolRoleLabels = $partnerDanceSchoolRoleLabels ?? nextgen_partner_dance_school_role_labels();
$selectedSchoolId = (int) ($partnerAssignRow['school_id'] ?? 0);
$selectedRoles = $partnerAssignRow['role_types'] ?? [];
if (!is_array($selectedRoles)) {
    $selectedRoles = [];
}
if ($selectedRoles === [] && isset($partnerAssignRow['role_type'])) {
    $selectedRoles = [(string) $partnerAssignRow['role_type']];
}
if ($selectedRoles === []) {
    $selectedRoles = ['school'];
}
$roleNote = (string) ($partnerAssignRow['role_note'] ?? '');
$wpTokenId = 'partner-school-token-' . $partnerAssignRowIndex;
$wpTokenLabel = '';
$wpTokenFieldName = 'school_rows[' . $partnerAssignRowIndex . '][school_id]';
$wpTokenPlaceholder = 'Tánciskola keresése…';
$wpTokenHelp = '';
$wpTokenManageUrl = null;
$wpTokenManageLabel = '';
$wpTokenManageNewTab = true;
$wpTokenAll = $partnerAssignAllSchools;
$wpTokenSelected = $selectedSchoolId > 0 ? [$selectedSchoolId] : [];
$wpTokenAllowCreate = false;
$wpTokenEntityType = '';
$wpTokenSingle = true;
$wpTokenShowPopular = false;
$wpTokenChipLinkPattern = $partnerDanceSchoolChipLinkPattern ?? '';
$wpTokenChipLinkNewTab = true;
$showOtherNote = in_array('other', $selectedRoles, true);
?>
<div class="partner-assign-row" data-partner-assign-row="dance-school">
    <div class="partner-assign-row__main">
        <div class="partner-assign-row__picker">
            <?php require dirname(__DIR__, 3) . '/events/partials/wp_token_field.php'; ?>
        </div>
        <fieldset class="partner-assign-row__roles" data-partner-role-checkboxes>
            <legend class="partner-assign-row__roles-label">Partner jelleg</legend>
            <?php foreach ($partnerDanceSchoolRoleLabels as $roleValue => $roleLabel): ?>
                <label class="partner-assign-row__role-check">
                    <input
                        type="checkbox"
                        name="school_rows[<?= $partnerAssignRowIndex ?>][role_types][]"
                        value="<?= h($roleValue) ?>"
                        data-partner-role-value="<?= h($roleValue) ?>"
                        <?= in_array($roleValue, $selectedRoles, true) ? ' checked' : '' ?>
                    >
                    <span><?= h($roleLabel) ?></span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <div class="partner-assign-row__note" data-partner-role-note-wrap<?= $showOtherNote ? '' : ' hidden' ?>>
            <label class="visually-hidden" for="partner-school-note-<?= $partnerAssignRowIndex ?>">Megjegyzés</label>
            <input
                type="text"
                id="partner-school-note-<?= $partnerAssignRowIndex ?>"
                name="school_rows[<?= $partnerAssignRowIndex ?>][role_note]"
                class="partner-assign-row__note-input"
                value="<?= h($roleNote) ?>"
                placeholder="Megjegyzés (Egyéb)…"
                maxlength="500"
            >
        </div>
    </div>
    <button type="button" class="partner-assign-row__remove" data-partner-assign-remove aria-label="Sor törlése">&times;</button>
</div>
