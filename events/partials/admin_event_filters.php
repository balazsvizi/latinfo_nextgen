<?php
declare(strict_types=1);
/** @var array $filters events_admin_filters_from_request() */
/** @var string $filterFormAction */
/** @var array<string, string> $filterFormHidden */
?>
<section class="events-filters-shell" aria-label="Szűrők"
    data-axis-min="<?= h($filters['axisMinStr']) ?>"
    data-axis-days="<?= (int) $filters['daysSpan'] ?>"
    data-idx-from="<?= (int) $filters['idxFrom'] ?>"
    data-idx-to="<?= (int) $filters['idxTo'] ?>">
    <div class="events-filters-grid">
        <div class="events-filter-field">
            <label class="events-filter-label" for="ev-f-organizer">Szervező</label>
            <input class="events-filter-input" type="text" name="f_organizer" id="ev-f-organizer" value="<?= h($filters['f_organizer']) ?>" placeholder="Részlet a névből…" autocomplete="off">
        </div>
        <div class="events-filter-field events-filter-field--status">
            <label class="events-filter-label" for="ev-f-category">Kategória</label>
            <div class="events-filter-select-wrap">
                <select class="events-filter-select" name="f_category" id="ev-f-category" title="Kategória szűrő">
                    <option value="">Összes kategória</option>
                    <?php foreach ($filters['categoryOptions'] as $cid => $cname): ?>
                        <option value="<?= (int) $cid ?>" <?= $filters['f_category_id'] === (int) $cid ? 'selected' : '' ?>><?= h($cname) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php if ($filters['tagsAvailable']): ?>
        <div class="events-filter-field events-filter-field--status">
            <label class="events-filter-label" for="ev-f-tag">Címke</label>
            <div class="events-filter-select-wrap">
                <select class="events-filter-select" name="f_tag" id="ev-f-tag" title="Címke szűrő">
                    <option value="">Összes címke</option>
                    <?php foreach ($filters['tagOptions'] as $tid => $tname): ?>
                        <option value="<?= (int) $tid ?>" <?= $filters['f_tag_id'] === (int) $tid ? 'selected' : '' ?>><?= h($tname) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($filters['djsAvailable']): ?>
        <div class="events-filter-field events-filter-field--status">
            <label class="events-filter-label" for="ev-f-dj">DJ</label>
            <div class="events-filter-select-wrap">
                <select class="events-filter-select" name="f_dj" id="ev-f-dj" title="DJ szűrő">
                    <option value="">Összes DJ</option>
                    <?php foreach ($filters['djOptions'] as $did => $dname): ?>
                        <option value="<?= (int) $did ?>" <?= $filters['f_dj_id'] === (int) $did ? 'selected' : '' ?>><?= h($dname) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($filters['stylesAvailable']): ?>
        <div class="events-filter-field events-filter-field--status">
            <label class="events-filter-label" for="ev-f-main-style">Fő stílus</label>
            <div class="events-filter-select-wrap">
                <select class="events-filter-select" name="f_main_style" id="ev-f-main-style" title="Fő stílus szűrő">
                    <option value="">Összes fő stílus</option>
                    <?php foreach ($filters['styleOptions'] as $sid => $sname): ?>
                        <option value="<?= (int) $sid ?>" <?= $filters['f_main_style_id'] === (int) $sid ? 'selected' : '' ?>><?= h($sname) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="events-filter-field events-filter-field--status">
            <label class="events-filter-label" for="ev-f-supplementary-style">Kiegészítő stílus</label>
            <div class="events-filter-select-wrap">
                <select class="events-filter-select" name="f_supplementary_style" id="ev-f-supplementary-style" title="Kiegészítő stílus szűrő">
                    <option value="">Összes kiegészítő stílus</option>
                    <?php foreach ($filters['styleOptions'] as $sid => $sname): ?>
                        <option value="<?= (int) $sid ?>" <?= $filters['f_supplementary_style_id'] === (int) $sid ? 'selected' : '' ?>><?= h($sname) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php endif; ?>
        <div class="events-filter-field">
            <label class="events-filter-label" for="ev-f-name">Esemény neve</label>
            <input class="events-filter-input" type="text" name="f_name" id="ev-f-name" value="<?= h($filters['f_name']) ?>" placeholder="Keresés a címben…" autocomplete="off">
        </div>
        <div class="events-filter-field">
            <label class="events-filter-label" for="ev-f-id">ID</label>
            <input class="events-filter-input" type="text" name="f_id" id="ev-f-id" value="<?= h($filters['f_id']) ?>" placeholder="Pl. 100001" inputmode="numeric" autocomplete="off">
        </div>
        <div class="events-filter-field">
            <label class="events-filter-label" for="ev-f-views">Min. megtekintés</label>
            <input class="events-filter-input" type="number" name="f_views_min" id="ev-f-views" value="<?= h($filters['f_views_min']) ?>" placeholder="0" min="0" step="1">
        </div>
        <div class="events-filter-field events-filter-field--status">
            <label class="events-filter-label" for="ev-f-status">Státusz</label>
            <div class="events-filter-select-wrap">
                <select class="events-filter-select events-filter-status" name="status" id="ev-f-status" title="Státusz szűrő">
                    <option value="">Összes státusz</option>
                    <?php foreach (events_allowed_post_statuses() as $st): ?>
                        <option value="<?= h($st) ?>" <?= $filters['status'] === $st ? 'selected' : '' ?>><?= h(events_post_status_label($st)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="events-filter-field events-filter-field--full">
            <div class="events-date-slider-row">
                <div class="events-date-range-visual">
                    <div class="events-date-range-track-bg" aria-hidden="true"></div>
                    <div class="events-date-range-fill" id="ev-date-range-fill" aria-hidden="true"></div>
                    <input type="range" class="events-range events-range-from" id="ev-range-from" min="0" max="<?= (int) $filters['daysSpan'] ?>" value="<?= (int) $filters['idxFrom'] ?>" step="1" aria-valuemin="0" aria-valuemax="<?= (int) $filters['daysSpan'] ?>" aria-label="Kezdő nap a tengelyen">
                    <input type="range" class="events-range events-range-to" id="ev-range-to" min="0" max="<?= (int) $filters['daysSpan'] ?>" value="<?= (int) $filters['idxTo'] ?>" step="1" aria-label="Záró nap a tengelyen">
                </div>
                <button type="submit" class="btn btn-primary events-filter-submit-inline">Szűrés alkalmazása</button>
            </div>
            <div class="events-date-range-readouts">
                <div class="events-date-readout">
                    <span class="events-date-readout-label" id="ev-lbl-from">Ettől</span>
                    <input class="events-filter-input events-filter-input--date" type="date" name="f_start_from" id="ev-f-start-from" value="<?= h($filters['f_start_from']) ?>">
                </div>
                <div class="events-date-readout">
                    <span class="events-date-readout-label" id="ev-lbl-to">Eddig</span>
                    <input class="events-filter-input events-filter-input--date" name="f_start_to" id="ev-f-start-to" type="date" value="<?= h($filters['f_start_to']) ?>">
                </div>
            </div>
        </div>
    </div>
    <?php foreach ($filterFormHidden as $hiddenName => $hiddenValue): ?>
        <input type="hidden" name="<?= h((string) $hiddenName) ?>" value="<?= h((string) $hiddenValue) ?>">
    <?php endforeach; ?>
</section>
