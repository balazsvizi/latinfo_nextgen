<?php
declare(strict_types=1);
/** @var string $listLimitValue */
/** @var int $listDisplayedCount */
/** @var int $listPoolCount */
/** @var bool $listLimitInForm select name= a szülő űrlapban */
/** @var string $listLimitSelectId */
$listLimitValue = $listLimitValue ?? (string) EVENTS_ADMIN_LIST_DEFAULT_LIMIT;
$listDisplayedCount = $listDisplayedCount ?? 0;
$listPoolCount = $listPoolCount ?? 0;
$listLimitInForm = !empty($listLimitInForm);
$listLimitSelectId = $listLimitSelectId ?? 'ev-list-limit';
$listCountLabel = events_admin_list_count_label($listDisplayedCount, $listPoolCount);
$standaloneClass = empty($listLimitStandalone) ? '' : ' events-admin-list-display--standalone';
?>
<div class="events-admin-list-display<?= h($standaloneClass) ?>">
    <label class="events-admin-list-display__label" for="<?= h($listLimitSelectId) ?>">Megjelenítve:</label>
    <div class="events-filter-select-wrap events-admin-list-display__select">
        <select
            class="events-filter-select<?= $listLimitInForm ? '' : ' events-list-limit-select' ?>"
            <?= $listLimitInForm ? 'name="list_limit"' : '' ?>
            id="<?= h($listLimitSelectId) ?>"
            title="Lista méret"
            <?= $listLimitInForm ? '' : ' data-list-limit-select' ?>
        >
            <?php foreach (events_admin_list_limit_options() as $limitOption): ?>
                <option value="<?= (int) $limitOption ?>" <?= $listLimitValue === (string) $limitOption ? 'selected' : '' ?>><?= (int) $limitOption ?></option>
            <?php endforeach; ?>
            <option value="all" <?= $listLimitValue === 'all' ? 'selected' : '' ?>>Mind</option>
        </select>
    </div>
    <span class="events-admin-list-display__count" aria-live="polite"><?= h($listCountLabel) ?></span>
</div>
