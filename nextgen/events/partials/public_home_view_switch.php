<?php
declare(strict_types=1);

/**
 * Naptár / lista / térkép váltó a publikus főoldalon.
 *
 * @var string $homeActiveView cal|list|map
 * @var string $homeCalViewUrl
 * @var string $homeListViewUrl
 * @var string $homeMapViewUrl
 * @var array<string, string> $D
 */
$homeStandalone = $homeStandalone ?? false;
$switchClass = 'events-cal-view-switch' . ($homeStandalone ? ' events-cal-view-switch--standalone' : '');
$calLabel = (string) ($D['view_cal'] ?? 'Naptár');
$listLabel = (string) ($D['view_list'] ?? 'Lista');
$mapLabel = (string) ($D['view_map'] ?? 'Térkép');
$aria = (string) ($D['view_switch_aria'] ?? 'Nézet választó');
?>
<nav class="<?= h($switchClass) ?>" aria-label="<?= h($aria) ?>">
    <?php if ($homeActiveView === 'cal'): ?>
        <span class="events-cal-view-switch__item is-active" aria-current="page"><?= h($calLabel) ?></span>
    <?php else: ?>
        <a class="events-cal-view-switch__item" href="<?= h($homeCalViewUrl) ?>"><?= h($calLabel) ?></a>
    <?php endif; ?>
    <?php if ($homeActiveView === 'list'): ?>
        <span class="events-cal-view-switch__item is-active" aria-current="page"><?= h($listLabel) ?></span>
    <?php else: ?>
        <a class="events-cal-view-switch__item" href="<?= h($homeListViewUrl) ?>"><?= h($listLabel) ?></a>
    <?php endif; ?>
    <?php if ($homeActiveView === 'map'): ?>
        <span class="events-cal-view-switch__item is-active" aria-current="page"><?= h($mapLabel) ?></span>
    <?php else: ?>
        <a class="events-cal-view-switch__item" href="<?= h($homeMapViewUrl) ?>"><?= h($mapLabel) ?></a>
    <?php endif; ?>
</nav>
