<?php
declare(strict_types=1);
/** @var list<string> $tagTypeSelected */
$tagTypeSelected = $tagTypeSelected ?? [];
if (!events_tag_types_tables_available($db ?? getDb())) {
    return;
}
$typeMeta = events_tag_type_display_meta();
?>
<fieldset class="form-group events-tag-type-fieldset">
    <legend class="events-tag-type-fieldset__legend">Típus</legend>
    <p class="events-tag-type-fieldset__hint">Több is választható. Üresen hagyva általános címke marad.</p>
    <div class="events-tag-type-picker" role="group" aria-label="Címke típusok">
        <?php foreach (events_tag_type_codes() as $code): ?>
            <?php
            $meta = $typeMeta[$code] ?? ['icon' => '🏷️', 'tone' => 'default'];
            $tone = (string) ($meta['tone'] ?? 'default');
            $icon = (string) ($meta['icon'] ?? '🏷️');
            $isChecked = in_array($code, $tagTypeSelected, true);
            ?>
            <label class="events-tag-type-option events-tag-type-option--<?= h($tone) ?>">
                <input
                    type="checkbox"
                    class="events-tag-type-option__input visually-hidden"
                    name="tag_type_codes[]"
                    value="<?= h($code) ?>"
                    <?= $isChecked ? 'checked' : '' ?>
                >
                <span class="events-tag-type-option__pill" aria-hidden="true">
                    <span class="events-tag-type-option__icon"><?= $icon ?></span>
                    <span class="events-tag-type-option__label"><?= h(events_tag_type_label($code)) ?></span>
                </span>
                <span class="visually-hidden"><?= h(events_tag_type_label($code)) ?></span>
            </label>
        <?php endforeach; ?>
    </div>
</fieldset>
