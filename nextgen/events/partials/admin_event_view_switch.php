<?php
declare(strict_types=1);
/** @var string $listViewUrl */
/** @var string $calendarViewUrl */
/** @var 'list'|'month' $activeView */
?>
<nav class="events-cal-view-switch events-cal-view-switch--standalone" aria-label="Nézet választó">
    <?php if ($activeView === 'list'): ?>
        <span class="events-cal-view-switch__item is-active" aria-current="page">Lista</span>
        <a class="events-cal-view-switch__item" href="<?= h($calendarViewUrl) ?>">Hónap</a>
    <?php else: ?>
        <a class="events-cal-view-switch__item" href="<?= h($listViewUrl) ?>">Lista</a>
        <span class="events-cal-view-switch__item is-active" aria-current="page">Hónap</span>
    <?php endif; ?>
</nav>
