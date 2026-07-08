<?php
declare(strict_types=1);

/**
 * Partner – egy DJ hozzárendelési sor.
 *
 * @var int $partnerAssignRowIndex
 * @var array{tag_id?: int, role_type?: string, role_note?: string, name?: string} $partnerAssignRow
 * @var list<array{id:int,name:string}> $partnerAssignAllDjs
 * @var array<string, string> $partnerDjRoleLabels
 * @var string $partnerDjChipLinkPattern
 */
$partnerAssignRowIndex = (int) ($partnerAssignRowIndex ?? 0);
$partnerAssignRow = $partnerAssignRow ?? [];
$partnerAssignAllDjs = $partnerAssignAllDjs ?? [];
$partnerDjRoleLabels = $partnerDjRoleLabels ?? nextgen_partner_dj_role_labels();
$selectedTagId = (int) ($partnerAssignRow['tag_id'] ?? 0);
$selectedRole = (string) ($partnerAssignRow['role_type'] ?? 'dj');
if (!isset($partnerDjRoleLabels[$selectedRole])) {
    $selectedRole = 'dj';
}
$roleNote = (string) ($partnerAssignRow['role_note'] ?? '');
$wpTokenId = 'partner-dj-token-' . $partnerAssignRowIndex;
$wpTokenLabel = '';
$wpTokenFieldName = 'dj_rows[' . $partnerAssignRowIndex . '][tag_id]';
$wpTokenPlaceholder = 'DJ keresése…';
$wpTokenHelp = '';
$wpTokenManageUrl = null;
$wpTokenManageLabel = '';
$wpTokenManageNewTab = true;
$wpTokenAll = $partnerAssignAllDjs;
$wpTokenSelected = $selectedTagId > 0 ? [$selectedTagId] : [];
$wpTokenAllowCreate = false;
$wpTokenEntityType = '';
$wpTokenSingle = true;
$wpTokenShowPopular = false;
$wpTokenChipLinkPattern = $partnerDjChipLinkPattern ?? '';
$wpTokenChipLinkNewTab = true;
?>
<div class="partner-assign-row" data-partner-assign-row="dj">
    <div class="partner-assign-row__main">
        <div class="partner-assign-row__picker">
            <?php require dirname(__DIR__, 3) . '/events/partials/wp_token_field.php'; ?>
        </div>
        <div class="partner-assign-row__role">
            <label class="visually-hidden" for="partner-dj-role-<?= $partnerAssignRowIndex ?>">Partner jelleg</label>
            <select
                id="partner-dj-role-<?= $partnerAssignRowIndex ?>"
                name="dj_rows[<?= $partnerAssignRowIndex ?>][role_type]"
                class="partner-assign-row__role-select"
                data-partner-role-select
            >
                <?php foreach ($partnerDjRoleLabels as $roleValue => $roleLabel): ?>
                    <option value="<?= h($roleValue) ?>"<?= $selectedRole === $roleValue ? ' selected' : '' ?>><?= h($roleLabel) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="partner-assign-row__note" data-partner-role-note-wrap<?= $selectedRole === 'other' ? '' : ' hidden' ?>>
            <label class="visually-hidden" for="partner-dj-note-<?= $partnerAssignRowIndex ?>">Megjegyzés</label>
            <input
                type="text"
                id="partner-dj-note-<?= $partnerAssignRowIndex ?>"
                name="dj_rows[<?= $partnerAssignRowIndex ?>][role_note]"
                class="partner-assign-row__note-input"
                value="<?= h($roleNote) ?>"
                placeholder="Megjegyzés (Egyéb)…"
                maxlength="500"
            >
        </div>
    </div>
    <button type="button" class="partner-assign-row__remove" data-partner-assign-remove aria-label="Sor törlése">&times;</button>
</div>
