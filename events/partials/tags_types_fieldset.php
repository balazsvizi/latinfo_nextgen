<?php
declare(strict_types=1);
/** @var list<string> $tagTypeSelected */
$tagTypeSelected = $tagTypeSelected ?? [];
if (!events_tag_types_tables_available($db ?? getDb())) {
    return;
}
?>
<fieldset class="form-group events-tags-type-fieldset">
    <legend class="events-tags-type-legend">Típus(ok)</legend>
    <div class="events-tags-type-checkboxes">
        <?php foreach (events_tag_type_codes() as $code): ?>
            <label class="events-tags-type-check-label">
                <input type="checkbox" name="tag_type_codes[]" value="<?= h($code) ?>" <?= in_array($code, $tagTypeSelected, true) ? 'checked' : '' ?>>
                <span class="events-tags-type-check-text"><?= h(events_tag_type_label($code)) ?></span>
            </label>
        <?php endforeach; ?>
    </div>
    <p class="help">Több típus is választható (pl. DJ + Zenekar). Üresen hagyva általános címke.</p>
</fieldset>
