<?php
declare(strict_types=1);

/**
 * Egy helyszínhez tartozó kínálat / óratípus sor.
 *
 * @var int $locIndex
 * @var int $offIndex
 * @var array<string, mixed> $offering
 * @var array<int, string> $styleOptions
 * @var array<string, string> $ageLabels
 * @var array<string, string> $levelLabels
 * @var array<string, string> $classTypeLabels
 */
$locIndex = (int) ($locIndex ?? 0);
$offIndex = (int) ($offIndex ?? 0);
$offering = is_array($offering ?? null) ? $offering : [];
$styleOptions = $styleOptions ?? [];
$ageLabels = $ageLabels ?? dance_school_age_group_labels();
$levelLabels = $levelLabels ?? dance_school_level_labels();
$classTypeLabels = $classTypeLabels ?? dance_school_class_type_labels();
$prefix = 'venues[' . $locIndex . '][offerings][' . $offIndex . ']';
$styleId = (int) ($offering['style_id'] ?? 0);
$age = (string) ($offering['age_group'] ?? 'adult');
$level = (string) ($offering['level'] ?? 'all');
$classType = (string) ($offering['class_type'] ?? 'group');
?>
<div class="dance-school-offering-row" data-dance-offering-row>
    <input type="hidden" name="<?= h($prefix) ?>[id]" value="<?= (int) ($offering['id'] ?? 0) ?>">
    <div class="events-tag-dj-profile__grid">
        <div class="form-group">
            <label>Stílus</label>
            <select name="<?= h($prefix) ?>[style_id]">
                <option value="0">— nincs / egyedi —</option>
                <?php foreach ($styleOptions as $sid => $sname): ?>
                    <option value="<?= (int) $sid ?>"<?= $styleId === (int) $sid ? ' selected' : '' ?>><?= h($sname) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Stílus felirat</label>
            <input type="text" name="<?= h($prefix) ?>[style_label]" value="<?= h((string) ($offering['style_label'] ?? '')) ?>" maxlength="128" placeholder="ha nincs a listában…">
        </div>
        <div class="form-group">
            <label>Korosztály</label>
            <select name="<?= h($prefix) ?>[age_group]">
                <?php foreach ($ageLabels as $val => $lab): ?>
                    <option value="<?= h($val) ?>"<?= $age === $val ? ' selected' : '' ?>><?= h($lab) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Szint</label>
            <select name="<?= h($prefix) ?>[level]">
                <?php foreach ($levelLabels as $val => $lab): ?>
                    <option value="<?= h($val) ?>"<?= $level === $val ? ' selected' : '' ?>><?= h($lab) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Óratípus</label>
            <select name="<?= h($prefix) ?>[class_type]">
                <?php foreach ($classTypeLabels as $val => $lab): ?>
                    <option value="<?= h($val) ?>"<?= $classType === $val ? ' selected' : '' ?>><?= h($lab) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Órarend megjegyzés</label>
            <input type="text" name="<?= h($prefix) ?>[schedule_note]" value="<?= h((string) ($offering['schedule_note'] ?? '')) ?>" maxlength="500" placeholder="pl. Hétfő 18:00">
        </div>
    </div>
    <button type="button" class="btn btn-secondary btn-sm" data-dance-offering-remove>Kínálat törlése</button>
</div>
