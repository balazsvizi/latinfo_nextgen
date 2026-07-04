<?php
declare(strict_types=1);
/** @var string $listViewUrl */
/** @var string $calendarViewUrl */
/** @var 'list'|'month' $activeView */
/** @var string $listLimitValue */
/** @var int $listDisplayedCount */
/** @var int|null $listLimitDefault */
$listLimitDefault = $listLimitDefault ?? EVENTS_ADMIN_LIST_DEFAULT_LIMIT;
$listLimitValue = $listLimitValue ?? (string) $listLimitDefault;
$listDisplayedCount = $listDisplayedCount ?? 0;
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
        <?php
        $listLimitInForm = true;
        require __DIR__ . '/admin_list_display_limit.php';
        ?>
    <?php endif; ?>
</div>
