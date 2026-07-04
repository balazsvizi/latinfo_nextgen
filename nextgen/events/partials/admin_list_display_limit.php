<?php
declare(strict_types=1);
/** @var string $listLimitValue */
/** @var int $listTotalInDb */
/** @var bool $listLimitInForm select name= a szülő űrlapban */
/** @var string $listLimitSelectId */
/** @var int|null $listLimitDefault */
/** @var string|null $listLimitLabel */
/** @var string|null $listLimitAllLabel */
/** @var string|null $listCountSuffix */
$listLimitDefault = $listLimitDefault ?? EVENTS_ADMIN_LIST_DEFAULT_LIMIT;
$listLimitValue = $listLimitValue ?? (string) $listLimitDefault;
$listTotalInDb = $listTotalInDb ?? 0;
$listLimitInForm = !empty($listLimitInForm);
$listLimitSelectId = $listLimitSelectId ?? 'ev-list-limit';
$listLimitLabel = $listLimitLabel ?? 'Megjelenítve:';
$listLimitAllLabel = $listLimitAllLabel ?? 'Mind';
$listCountSuffix = $listCountSuffix ?? ' megjelenítve';
$standaloneClass = empty($listLimitStandalone) ? '' : ' events-admin-list-display--standalone';
$displayCount = events_admin_list_display_limit_count($listLimitValue, $listTotalInDb);
$formatCount = static fn (int $n): string => number_format($n, 0, '', ' ');
$countAriaLabel = $formatCount($displayCount) . ' / ' . $formatCount($listTotalInDb) . $listCountSuffix;
$labelText = rtrim(trim((string) $listLimitLabel), ':');
?>
<div class="events-admin-list-display<?= h($standaloneClass) ?>">
    <div class="events-admin-list-display__control">
        <label class="events-admin-list-display__label" for="<?= h($listLimitSelectId) ?>">
            <span class="events-admin-list-display__label-text"><?= h($labelText) ?></span>
        </label>
        <div class="events-filter-select-wrap events-admin-list-display__select">
            <select
                class="events-filter-select events-admin-list-display__select-input<?= $listLimitInForm ? '' : ' events-list-limit-select' ?>"
                <?= $listLimitInForm ? 'name="list_limit"' : '' ?>
                id="<?= h($listLimitSelectId) ?>"
                title="Lista méret"
                <?= $listLimitInForm ? '' : ' data-list-limit-select' ?>
            >
                <?php foreach (events_admin_list_limit_options() as $limitOption): ?>
                    <option value="<?= (int) $limitOption ?>" <?= $listLimitValue === (string) $limitOption ? 'selected' : '' ?>><?= (int) $limitOption ?></option>
                <?php endforeach; ?>
                <option value="all" <?= $listLimitValue === 'all' ? 'selected' : '' ?>><?= h($listLimitAllLabel) ?></option>
            </select>
        </div>
    </div>
    <p class="events-admin-list-display__count" aria-live="polite" aria-label="<?= h($countAriaLabel) ?>">
        <span class="events-admin-list-display__count-pill">
            <span class="events-admin-list-display__count-num"><?= h($formatCount($displayCount)) ?></span>
            <span class="events-admin-list-display__count-sep" aria-hidden="true">/</span>
            <span class="events-admin-list-display__count-total"><?= h($formatCount($listTotalInDb)) ?></span>
        </span>
        <span class="events-admin-list-display__count-suffix"><?= h(ltrim($listCountSuffix)) ?></span>
    </p>
</div>
