<?php
declare(strict_types=1);
/** @var array<string, mixed> $filters events_public_filters_from_request() */
/** @var string $filterFormAction */
/** @var array<string, string> $filterFormHidden */
/** @var array<string, string> $D nyelvi sztringek */
/** @var bool $hideMapDateFiltersInPanel térkép nézetben a dátum a külön sávban van */
$hideMapDateFiltersInPanel = !empty($hideMapDateFiltersInPanel);
?>
<section class="events-filters-shell home-public__filters" aria-label="<?= h((string) ($D['filters_aria'] ?? 'Szűrők')) ?>"
    data-axis-min="<?= h($filters['axisMinStr']) ?>"
    data-axis-days="<?= (int) $filters['daysSpan'] ?>"
    data-idx-from="<?= (int) $filters['idxFrom'] ?>"
    data-idx-to="<?= (int) $filters['idxTo'] ?>">
    <div class="events-filters-grid">
        <div class="events-filter-field">
            <label class="<?= h(events_public_filter_label_attr_classes($filters, 'organizer')) ?>" for="ev-f-organizer"><?= h((string) ($D['filter_organizer'] ?? 'Szervező')) ?></label>
            <input class="events-filter-input" type="text" name="f_organizer" id="ev-f-organizer" value="<?= h($filters['f_organizer']) ?>" placeholder="<?= h((string) ($D['filter_organizer_ph'] ?? '')) ?>" autocomplete="off">
        </div>
        <div class="events-filter-field events-filter-field--status events-filter-field--multiselect">
            <?php
            $selectedCategoryIds = [];
            if (isset($filters['f_category_ids']) && is_array($filters['f_category_ids'])) {
                foreach ($filters['f_category_ids'] as $selectedCatId) {
                    $selectedCategoryIds[] = (int) $selectedCatId;
                }
            } elseif ((int) ($filters['f_category_id'] ?? 0) > 0) {
                $selectedCategoryIds[] = (int) $filters['f_category_id'];
            }
            $selectedCategoryCount = count($selectedCategoryIds);
            $allCategoriesLabel = (string) ($D['filter_all_categories'] ?? 'Összes kategória');
            $categoryCountTpl = (string) ($D['filter_category_selected_count'] ?? '%d kategória');
            if ($selectedCategoryCount === 0) {
                $categorySummary = $allCategoriesLabel;
            } elseif ($selectedCategoryCount === 1) {
                $onlyCatId = $selectedCategoryIds[0];
                $categorySummary = (string) ($filters['categoryOptions'][$onlyCatId] ?? $allCategoriesLabel);
            } else {
                $categorySummary = sprintf($categoryCountTpl, $selectedCategoryCount);
            }
            ?>
            <label class="<?= h(events_public_filter_label_attr_classes($filters, 'category')) ?>" for="ev-f-category"><?= h((string) ($D['filter_category'] ?? 'Kategória')) ?></label>
            <div
                class="events-filter-multiselect"
                data-filter-multiselect="category"
                data-all-label="<?= h($allCategoriesLabel) ?>"
                data-count-template="<?= h($categoryCountTpl) ?>"
            >
                <div class="events-filter-select-wrap">
                    <button
                        type="button"
                        class="events-filter-select events-filter-multiselect__toggle"
                        id="ev-f-category"
                        aria-haspopup="true"
                        aria-expanded="false"
                        aria-controls="ev-f-category-panel"
                        title="<?= h((string) ($D['filter_category'] ?? 'Kategória')) ?>"
                    >
                        <span class="events-filter-multiselect__summary"><?= h($categorySummary) ?></span>
                    </button>
                </div>
                <div class="events-filter-multiselect__panel" id="ev-f-category-panel" hidden role="group" aria-label="<?= h((string) ($D['filter_category'] ?? 'Kategória')) ?>">
                    <?php
                    $categoryParentById = is_array($filters['categoryParentById'] ?? null)
                        ? $filters['categoryParentById']
                        : [];
                    ?>
                    <?php foreach ($filters['categoryOptions'] as $cid => $cname): ?>
                        <?php
                        $cidInt = (int) $cid;
                        $optId = 'ev-f-category-opt-' . $cidInt;
                        $isChecked = in_array($cidInt, $selectedCategoryIds, true);
                        $parentId = (int) ($categoryParentById[$cidInt] ?? 0);
                        ?>
                        <label class="events-filter-multiselect__option" for="<?= h($optId) ?>">
                            <input
                                type="checkbox"
                                class="events-filter-multiselect__checkbox"
                                name="f_category[]"
                                id="<?= h($optId) ?>"
                                value="<?= $cidInt ?>"
                                data-parent-id="<?= $parentId ?>"
                                <?= $isChecked ? 'checked' : '' ?>
                            >
                            <span class="events-filter-multiselect__option-text"><?= h($cname) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php if ($filters['tagsAvailable']): ?>
        <div class="events-filter-field events-filter-field--status">
            <label class="<?= h(events_public_filter_label_attr_classes($filters, 'tag')) ?>" for="ev-f-tag"><?= h((string) ($D['filter_tag'] ?? 'Címke')) ?></label>
            <div class="events-filter-select-wrap">
                <select class="events-filter-select" name="f_tag" id="ev-f-tag" title="<?= h((string) ($D['filter_tag'] ?? 'Címke')) ?>">
                    <option value=""><?= h((string) ($D['filter_all_tags'] ?? 'Összes címke')) ?></option>
                    <?php foreach ($filters['tagOptions'] as $tid => $tname): ?>
                        <option value="<?= (int) $tid ?>" <?= $filters['f_tag_id'] === (int) $tid ? 'selected' : '' ?>><?= h($tname) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($filters['djsAvailable']): ?>
        <div class="events-filter-field events-filter-field--status">
            <label class="<?= h(events_public_filter_label_attr_classes($filters, 'dj')) ?>" for="ev-f-dj"><?= h((string) ($D['filter_dj'] ?? 'DJ')) ?></label>
            <div class="events-filter-select-wrap">
                <select class="events-filter-select" name="f_dj" id="ev-f-dj" title="<?= h((string) ($D['filter_dj'] ?? 'DJ')) ?>">
                    <option value=""><?= h((string) ($D['filter_all_djs'] ?? 'Összes DJ')) ?></option>
                    <?php foreach ($filters['djOptions'] as $did => $dname): ?>
                        <option value="<?= (int) $did ?>" <?= $filters['f_dj_id'] === (int) $did ? 'selected' : '' ?>><?= h($dname) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($filters['stylesAvailable']): ?>
        <div class="events-filter-field events-filter-field--status">
            <label class="<?= h(events_public_filter_label_attr_classes($filters, 'main_style')) ?>" for="ev-f-main-style"><?= h((string) ($D['filter_main_style'] ?? 'Fő stílus')) ?></label>
            <div class="events-filter-select-wrap">
                <select class="events-filter-select" name="f_main_style" id="ev-f-main-style" title="<?= h((string) ($D['filter_main_style'] ?? 'Fő stílus')) ?>">
                    <option value=""><?= h((string) ($D['filter_all_main_styles'] ?? 'Összes fő stílus')) ?></option>
                    <?php foreach ($filters['styleOptions'] as $sid => $sname): ?>
                        <option value="<?= (int) $sid ?>" <?= $filters['f_main_style_id'] === (int) $sid ? 'selected' : '' ?>><?= h($sname) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="events-filter-field events-filter-field--status">
            <label class="<?= h(events_public_filter_label_attr_classes($filters, 'supplementary_style')) ?>" for="ev-f-supplementary-style"><?= h((string) ($D['filter_supp_style'] ?? 'Kiegészítő stílus')) ?></label>
            <div class="events-filter-select-wrap">
                <select class="events-filter-select" name="f_supplementary_style" id="ev-f-supplementary-style" title="<?= h((string) ($D['filter_supp_style'] ?? 'Kiegészítő stílus')) ?>">
                    <option value=""><?= h((string) ($D['filter_all_supp_styles'] ?? 'Összes kiegészítő stílus')) ?></option>
                    <?php foreach ($filters['styleOptions'] as $sid => $sname): ?>
                        <option value="<?= (int) $sid ?>" <?= $filters['f_supplementary_style_id'] === (int) $sid ? 'selected' : '' ?>><?= h($sname) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php endif; ?>
        <div class="events-filter-field">
            <label class="<?= h(events_public_filter_label_attr_classes($filters, 'venue')) ?>" for="ev-f-venue"><?= h((string) ($D['filter_venue'] ?? 'Helyszín')) ?></label>
            <input class="events-filter-input" type="text" name="f_venue" id="ev-f-venue" value="<?= h($filters['f_venue']) ?>" placeholder="<?= h((string) ($D['filter_venue_ph'] ?? '')) ?>" autocomplete="off">
        </div>
        <div class="events-filter-field">
            <label class="<?= h(events_public_filter_label_attr_classes($filters, 'city')) ?>" for="ev-f-city"><?= h((string) ($D['filter_city'] ?? 'Város')) ?></label>
            <input class="events-filter-input" type="text" name="f_city" id="ev-f-city" value="<?= h($filters['f_city']) ?>" placeholder="<?= h((string) ($D['filter_city_ph'] ?? '')) ?>" autocomplete="off">
        </div>
        <div class="events-filter-field">
            <label class="<?= h(events_public_filter_label_attr_classes($filters, 'name')) ?>" for="ev-f-name"><?= h((string) ($D['filter_name'] ?? 'Esemény neve')) ?></label>
            <input class="events-filter-input" type="text" name="f_name" id="ev-f-name" value="<?= h($filters['f_name']) ?>" placeholder="<?= h((string) ($D['filter_name_ph'] ?? '')) ?>" autocomplete="off">
        </div>

        <?php if (!$hideMapDateFiltersInPanel): ?>
        <div class="events-filter-field events-filter-field--full">
            <div class="events-date-slider-row">
                <div class="events-date-range-visual">
                    <div class="events-date-range-track-bg" aria-hidden="true"></div>
                    <div class="events-date-range-fill" id="ev-date-range-fill" aria-hidden="true"></div>
                    <input type="range" class="events-range events-range-from" id="ev-range-from" min="0" max="<?= (int) $filters['daysSpan'] ?>" value="<?= (int) $filters['idxFrom'] ?>" step="1" aria-valuemin="0" aria-valuemax="<?= (int) $filters['daysSpan'] ?>" aria-label="<?= h((string) ($D['filter_date_from'] ?? 'Ettől')) ?>">
                    <input type="range" class="events-range events-range-to" id="ev-range-to" min="0" max="<?= (int) $filters['daysSpan'] ?>" value="<?= (int) $filters['idxTo'] ?>" step="1" aria-label="<?= h((string) ($D['filter_date_to'] ?? 'Eddig')) ?>">
                </div>
            </div>
            <div class="events-date-range-readouts">
                <div class="events-date-readout">
                    <span class="<?= h(events_public_filter_label_attr_classes($filters, 'start_from')) ?>" id="ev-lbl-from"><?= h((string) ($D['filter_date_from'] ?? 'Ettől')) ?></span>
                    <input class="events-filter-input events-filter-input--date" type="date" name="f_start_from" id="ev-f-start-from" value="<?= h($filters['f_start_from']) ?>">
                </div>
                <div class="events-date-readout">
                    <span class="<?= h(events_public_filter_label_attr_classes($filters, 'start_to')) ?>" id="ev-lbl-to"><?= h((string) ($D['filter_date_to'] ?? 'Eddig')) ?></span>
                    <input class="events-filter-input events-filter-input--date" name="f_start_to" id="ev-f-start-to" type="date" value="<?= h($filters['f_start_to']) ?>">
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php foreach ($filterFormHidden as $hiddenName => $hiddenValue): ?>
        <input type="hidden" name="<?= h((string) $hiddenName) ?>" value="<?= h((string) $hiddenValue) ?>">
    <?php endforeach; ?>
</section>
