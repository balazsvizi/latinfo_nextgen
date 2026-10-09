<?php
declare(strict_types=1);

/**
 * Tánciskola–tanár hozzárendelés sor.
 *
 * @var int $tchIndex
 * @var array<string, mixed> $teacherRow
 * @var list<array{id:int,name:string}> $teacherOptions
 * @var array<string, string> $roleLabels
 */
$tchIndex = (int) ($tchIndex ?? 0);
$teacherRow = is_array($teacherRow ?? null) ? $teacherRow : [];
$teacherOptions = $teacherOptions ?? [];
$roleLabels = $roleLabels ?? dance_school_teacher_role_labels();
$prefix = 'teachers[' . $tchIndex . ']';
$tagId = (int) ($teacherRow['tag_id'] ?? 0);
$role = (string) ($teacherRow['role_type'] ?? 'teacher');
?>
<div class="events-edit-panel dance-school-teacher-row" data-dance-teacher-row>
    <div class="events-list-head" style="margin-bottom:0.75rem;">
        <h4 class="events-edit-panel__title" style="margin:0;">Tanár #<?= (int) $tchIndex + 1 ?></h4>
        <button type="button" class="btn btn-secondary btn-sm" data-dance-teacher-remove>Törlés</button>
    </div>
    <div class="events-tag-dj-profile__grid">
        <div class="form-group">
            <label>Tánctanár</label>
            <select name="<?= h($prefix) ?>[tag_id]">
                <option value="0">— válassz —</option>
                <?php foreach ($teacherOptions as $opt): ?>
                    <option value="<?= (int) $opt['id'] ?>"<?= $tagId === (int) $opt['id'] ? ' selected' : '' ?>><?= h($opt['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="help" style="margin:0.35rem 0 0;">
                A tánctanárok a DJ-khez hasonló címkék.
                <?php if ($tagId > 0): ?>
                    <a href="<?= h(events_url('tanar_szerkeszt.php?id=') . $tagId) ?>" target="_blank" rel="noopener">Szerkesztés</a> ·
                <?php endif; ?>
                <a href="<?= h(events_url('tanar_letrehoz.php')) ?>" target="_blank" rel="noopener">Új tánctanár</a>
            </p>
        </div>
        <div class="form-group">
            <label>Szerep</label>
            <select name="<?= h($prefix) ?>[role_type]">
                <?php foreach ($roleLabels as $val => $lab): ?>
                    <option value="<?= h($val) ?>"<?= $role === $val ? ' selected' : '' ?>><?= h($lab) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Megjegyzés</label>
            <input type="text" name="<?= h($prefix) ?>[role_note]" value="<?= h((string) ($teacherRow['role_note'] ?? '')) ?>" maxlength="500">
        </div>
    </div>
</div>
