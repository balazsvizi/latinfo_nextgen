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
        <div class="events-filter-field events-filter-field--status">
            <label class="<?= h(events_public_filter_label_attr_classes($filters, 'category')) ?>" for="ev-f-category"><?= h((string) ($D['filter_category'] ?? 'Kategória')) ?></label>
            <div class="events-filter-select-wrap">
                <select class="events-filter-select" name="f_category" id="ev-f-category" title="<?= h((string) ($D['filter_category'] ?? 'Kategória')) ?>">
                    <option value=""><?= h((string) ($D['filter_all_categories'] ?? 'Összes kategória')) ?></option>
                    <?php foreach ($filters['categoryOptions'] as $cid => $cname): ?>
                        <option value="<?= (int) $cid ?>" <?= $filters['f_category_id'] === (int) $cid ? 'selected' : '' ?>><?= h($cname) ?></option>
                    <?php endforeach; ?>
                </select>
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
