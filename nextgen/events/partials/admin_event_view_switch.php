<?php
declare(strict_types=1);
/** @var string $listViewUrl */
/** @var string $calendarViewUrl */
/** @var 'list'|'month' $activeView */
/** @var bool $showAll */
$showAll = $showAll ?? false;
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
        <label class="events-admin-list-limit events-toggle" for="ev-show-all" title="Lista méret: legújabb 100 vagy összes esemény">
            <span class="events-admin-list-limit__opt<?= !$showAll ? ' is-active' : '' ?>" aria-hidden="true">100</span>
            <input
                type="checkbox"
                name="show_all"
                value="1"
                id="ev-show-all"
                class="events-toggle__input"
                <?= $showAll ? 'checked' : '' ?>
                aria-label="Összes esemény megjelenítése"
            >
            <span class="events-toggle__ui" aria-hidden="true"></span>
            <span class="events-admin-list-limit__opt<?= $showAll ? ' is-active' : '' ?>" aria-hidden="true">Összes</span>
        </label>
    <?php endif; ?>
</div>
