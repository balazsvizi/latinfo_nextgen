<?php
declare(strict_types=1);
/** @var string $listViewUrl */
/** @var string $calendarViewUrl */
/** @var 'list'|'month' $activeView */
/** @var string $listLimitValue */
/** @var int $listDisplayedCount */
/** @var int $listPoolCount */
$listLimitValue = $listLimitValue ?? (string) EVENTS_ADMIN_LIST_DEFAULT_LIMIT;
$listDisplayedCount = $listDisplayedCount ?? 0;
$listPoolCount = $listPoolCount ?? 0;

$formatCount = static fn (int $n): string => number_format($n, 0, '', ' ');
if ($listDisplayedCount < $listPoolCount) {
    $listCountLabel = $formatCount($listDisplayedCount) . ' / ' . $formatCount($listPoolCount) . ' megjelenítve';
} else {
    $listCountLabel = $formatCount($listDisplayedCount) . ' megjelenítve';
}
?>
<div class="events-cal-view-switch-row">
    <nav class="events-cal-view-switch events-cal-view-switch--standalone" aria-label="Nézet választó">
        <?php if ($activeView === 'list'): ?>
            <span class="events-cal-view-switch__item is-active" aria-current="page">Lista</span>
            <a class="events-cal-view-switch__item" href="<?= h($calendarViewUrl) ?>">Hónap</a>
        <?php else: ?>
            <a class="events-cal-view-switch__item" href="<?= h($listViewUrl) ?>">Lista</a>
            <span class="events-cal-view-switch__item is-active" aria-current="page">Hónap</span>
        <?php endif; ?>
    </nav>
    <?php if ($activeView === 'list'): ?>
        <span class="events-cal-view-switch-row__sep" aria-hidden="true">|</span>
        <div class="events-admin-list-display">
            <label class="events-admin-list-display__label" for="ev-list-limit">Megjelenítve:</label>
            <div class="events-filter-select-wrap events-admin-list-display__select">
                <select class="events-filter-select" name="list_limit" id="ev-list-limit" title="Lista méret">
                    <?php foreach (events_admin_list_limit_options() as $limitOption): ?>
                        <option value="<?= (int) $limitOption ?>" <?= $listLimitValue === (string) $limitOption ? 'selected' : '' ?>><?= (int) $limitOption ?></option>
                    <?php endforeach; ?>
                    <option value="all" <?= $listLimitValue === 'all' ? 'selected' : '' ?>>Mind</option>
                </select>
            </div>
            <span class="events-admin-list-display__count" aria-live="polite"><?= h($listCountLabel) ?></span>
        </div>
    <?php endif; ?>
</div>
