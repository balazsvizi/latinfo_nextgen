<?php
declare(strict_types=1);

/**
 * Partner – egy tánctanár hozzárendelési sor.
 *
 * @var int $partnerAssignRowIndex
 * @var array{tag_id?: int, role_types?: list<string>, role_type?: string, role_note?: string, name?: string} $partnerAssignRow
 * @var list<array{id:int,name:string}> $partnerAssignAllTeachers
 * @var array<string, string> $partnerTeacherRoleLabels
 * @var string $partnerTeacherChipLinkPattern
 */
$partnerAssignRowIndex = (int) ($partnerAssignRowIndex ?? 0);
$partnerAssignRow = $partnerAssignRow ?? [];
$partnerAssignAllTeachers = $partnerAssignAllTeachers ?? [];
$partnerTeacherRoleLabels = $partnerTeacherRoleLabels ?? nextgen_partner_teacher_role_labels();
$selectedTagId = (int) ($partnerAssignRow['tag_id'] ?? 0);
$selectedRoles = $partnerAssignRow['role_types'] ?? [];
if (!is_array($selectedRoles)) {
    $selectedRoles = [];
}
if ($selectedRoles === [] && isset($partnerAssignRow['role_type'])) {
    $selectedRoles = [(string) $partnerAssignRow['role_type']];
}
if ($selectedRoles === []) {
    $selectedRoles = ['teacher'];
}
$roleNote = (string) ($partnerAssignRow['role_note'] ?? '');
$wpTokenId = 'partner-teacher-token-' . $partnerAssignRowIndex;
$wpTokenLabel = '';
$wpTokenFieldName = 'teacher_rows[' . $partnerAssignRowIndex . '][tag_id]';
$wpTokenPlaceholder = 'Tánctanár keresése…';
$wpTokenHelp = '';
$wpTokenManageUrl = null;
$wpTokenManageLabel = '';
$wpTokenManageNewTab = true;
$wpTokenAll = $partnerAssignAllTeachers;
$wpTokenSelected = $selectedTagId > 0 ? [$selectedTagId] : [];
$wpTokenAllowCreate = false;
$wpTokenEntityType = '';
$wpTokenSingle = true;
$wpTokenShowPopular = false;
$wpTokenChipLinkPattern = $partnerTeacherChipLinkPattern ?? '';
$wpTokenChipLinkNewTab = true;
$showOtherNote = in_array('other', $selectedRoles, true);
?>
<div class="partner-assign-row" data-partner-assign-row="teacher">
    <div class="partner-assign-row__main">
        <div class="partner-assign-row__picker">
            <?php require dirname(__DIR__, 3) . '/events/partials/wp_token_field.php'; ?>
        </div>
        <fieldset class="partner-assign-row__roles" data-partner-role-checkboxes>
            <legend class="partner-assign-row__roles-label">Partner jelleg</legend>
            <?php foreach ($partnerTeacherRoleLabels as $roleValue => $roleLabel): ?>
                <label class="partner-assign-row__role-check">
                    <input
                        type="checkbox"
                        name="teacher_rows[<?= $partnerAssignRowIndex ?>][role_types][]"
                        value="<?= h($roleValue) ?>"
                        data-partner-role-value="<?= h($roleValue) ?>"
                        <?= in_array($roleValue, $selectedRoles, true) ? ' checked' : '' ?>
                    >
                    <span><?= h($roleLabel) ?></span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <div class="partner-assign-row__note" data-partner-role-note-wrap<?= $showOtherNote ? '' : ' hidden' ?>>
            <label class="visually-hidden" for="partner-teacher-note-<?= $partnerAssignRowIndex ?>">Megjegyzés</label>
            <input
                type="text"
                id="partner-teacher-note-<?= $partnerAssignRowIndex ?>"
                name="teacher_rows[<?= $partnerAssignRowIndex ?>][role_note]"
                class="partner-assign-row__note-input"
                value="<?= h($roleNote) ?>"
                placeholder="Megjegyzés (Egyéb)…"
                maxlength="500"
            >
        </div>
    </div>
    <button type="button" class="partner-assign-row__remove" data-partner-assign-remove aria-label="Sor törlése">&times;</button>
</div>
